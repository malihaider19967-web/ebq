#!/usr/bin/env bash
# Ubuntu VPS production installer for the ebq Laravel app.
#
# Installs and configures:
#   - Apache 2.4 (mpm_event) + PHP-FPM (8.3) + opcache
#   - MariaDB/MySQL (with non-interactive hardening)
#   - Redis (optional), Node.js 22 LTS, Composer
#   - Cloudflare DNS A-record upsert (optional)
#   - Let's Encrypt certificate via certbot (optional) + HTTP->HTTPS redirect
#   - Security headers (HSTS, XFO, XCTO, Referrer-Policy, Permissions-Policy)
#   - Laravel queue worker (systemd) + scheduler (cron)
#   - Logrotate, unattended security upgrades, UFW, fail2ban (optional), swap (optional)
#
# Usage:
#   sudo cp scripts/deploy.env.example scripts/deploy.env
#   sudo nano scripts/deploy.env
#   sudo bash scripts/ubuntu-vps-install.sh
#
# Re-runs are idempotent: packages are skipped if present, configs are rewritten,
# DB user password is synced, git pull updates the app dir, systemd units are
# restarted on change.

set -euo pipefail

#──────────────────────────────────────────────────────────────────────────────
# Bootstrap & env
#──────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_ENV_FILE="${SCRIPT_DIR}/deploy.env"
: "${DEPLOY_ENV_FILE:=${DEFAULT_ENV_FILE}}"

log()  { printf '\033[1;34m[install]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[warn]\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31m[error]\033[0m %s\n' "$*" >&2; exit 1; }

trap 'rc=$?; [[ $rc -ne 0 ]] && warn "installer failed at line ${LINENO} (exit ${rc})"' ERR

[[ "${EUID:-$(id -u)}" -eq 0 ]] || die "Run as root: sudo $0"

if [[ -f "${DEPLOY_ENV_FILE}" ]]; then
  # shellcheck source=/dev/null
  set -a && source "${DEPLOY_ENV_FILE}" && set +a
else
  die "Missing ${DEPLOY_ENV_FILE} — copy deploy.env.example to deploy.env and edit."
fi

: "${GIT_REPO:?Set GIT_REPO in deploy.env}"
: "${GIT_BRANCH:=main}"
: "${APP_DIR:=/var/www/ebq}"
: "${PRIMARY_DOMAIN:?Set PRIMARY_DOMAIN in deploy.env}"
: "${EXTRA_DOMAINS:=}"
: "${ENABLE_SSL:=0}"
: "${ADMIN_EMAIL:=}"
: "${ENABLE_UFW:=1}"
: "${ENABLE_FAIL2BAN:=0}"
: "${ENABLE_UNATTENDED_UPGRADES:=1}"
: "${ENABLE_REDIS:=0}"
: "${ENABLE_QUEUE_WORKER:=1}"
: "${ENABLE_SCHEDULER:=1}"
: "${ENABLE_SWAP:=0}"
: "${SWAP_SIZE_MB:=2048}"
: "${TIMEZONE:=}"
: "${PHP_VERSION:=8.3}"
: "${PHP_MEMORY_LIMIT:=512M}"
: "${UPLOAD_MAX_FILESIZE:=64M}"
: "${POST_MAX_SIZE:=64M}"
: "${PHP_MAX_EXECUTION_TIME:=120}"
: "${OPCACHE_MEMORY_MB:=192}"

DEPLOY_USER="${SUDO_USER:-root}"

#──────────────────────────────────────────────────────────────────────────────
# Helpers
#──────────────────────────────────────────────────────────────────────────────

require_cmd() { command -v "$1" >/dev/null 2>&1; }
have_pkg()    { dpkg -s "$1" >/dev/null 2>&1; }

apt_install() {
  DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$@"
}

apt_update_once() {
  if [[ "${_APT_UPDATED:-0}" != "1" ]]; then
    apt-get update -y
    _APT_UPDATED=1
  fi
}

ensure_service() {
  local svc="$1"
  systemctl enable "${svc}" >/dev/null 2>&1 || true
  systemctl start  "${svc}" >/dev/null 2>&1 || true
  systemctl is-active --quiet "${svc}" ||
    die "service ${svc} is not active; check: journalctl -u ${svc} -n 80"
}

mysql_escape_single() { printf '%s' "$1" | sed "s/'/''/g"; }
mysql_escape_ident()  { printf '%s' "$1" | sed 's/`/``/g'; }

server_all_names() {
  local names=("${PRIMARY_DOMAIN}")
  IFS=',' read -r -a extra <<< "${EXTRA_DOMAINS// /}"
  local x
  for x in "${extra[@]}"; do
    [[ -n "$x" ]] && names+=("$x")
  done
  printf '%s\n' "${names[@]}" | awk 'NF' | sort -u
}

extra_server_aliases() { server_all_names | grep -vxF "${PRIMARY_DOMAIN}" || true; }

public_ipv4() { curl -fsS https://api.ipify.org || curl -fsS https://ifconfig.me; }

#──────────────────────────────────────────────────────────────────────────────
# Phase 1 — OS prerequisites
#──────────────────────────────────────────────────────────────────────────────

configure_system_misc() {
  log "configuring system basics"

  if [[ -n "${TIMEZONE}" ]]; then
    timedatectl set-timezone "${TIMEZONE}" >/dev/null || warn "failed to set timezone ${TIMEZONE}"
  fi

  if [[ "${ENABLE_SWAP}" == "1" && ! -f /swapfile ]]; then
    log "creating ${SWAP_SIZE_MB}MB swapfile"
    fallocate -l "${SWAP_SIZE_MB}M" /swapfile || dd if=/dev/zero of=/swapfile bs=1M count="${SWAP_SIZE_MB}"
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
  fi

  if [[ "${ENABLE_UNATTENDED_UPGRADES}" == "1" ]]; then
    apt_update_once
    apt_install unattended-upgrades
    dpkg-reconfigure -f noninteractive unattended-upgrades >/dev/null 2>&1 || true
  fi
}

configure_firewall() {
  [[ "${ENABLE_UFW}" == "1" ]] || return 0
  log "configuring UFW"
  apt_update_once
  apt_install ufw
  ufw allow OpenSSH >/dev/null 2>&1 || ufw allow 22/tcp >/dev/null 2>&1 || true
  ufw allow 80/tcp  >/dev/null 2>&1 || true
  ufw allow 443/tcp >/dev/null 2>&1 || true
  ufw --force enable >/dev/null
}

configure_fail2ban() {
  [[ "${ENABLE_FAIL2BAN}" == "1" ]] || return 0
  log "configuring fail2ban"
  apt_update_once
  apt_install fail2ban
  cat >/etc/fail2ban/jail.d/ebq.conf <<'EOF'
[sshd]
enabled = true
maxretry = 5
findtime = 10m
bantime = 1h

[apache-auth]
enabled = true

[apache-badbots]
enabled = true

[apache-noscript]
enabled = true

[apache-overflows]
enabled = true
EOF
  systemctl restart fail2ban
}

#──────────────────────────────────────────────────────────────────────────────
# Phase 2 — Stack install
#──────────────────────────────────────────────────────────────────────────────

ensure_repo_php() {
  if apt-cache show "php${PHP_VERSION}-cli" >/dev/null 2>&1; then
    return 0
  fi
  log "adding ondrej/php PPA for PHP ${PHP_VERSION}"
  apt_install software-properties-common ca-certificates lsb-release gnupg
  add-apt-repository -y ppa:ondrej/php
  apt-get update -y
  _APT_UPDATED=1
}

install_base_stack() {
  log "installing base packages"
  apt_update_once
  apt_install git curl unzip jq openssl acl ca-certificates cron logrotate rsync

  ensure_repo_php

  local pkgs=(
    apache2
    "php${PHP_VERSION}-fpm"
    "php${PHP_VERSION}-cli"
    "php${PHP_VERSION}-common"
    "php${PHP_VERSION}-curl"
    "php${PHP_VERSION}-mbstring"
    "php${PHP_VERSION}-xml"
    "php${PHP_VERSION}-zip"
    "php${PHP_VERSION}-mysql"
    "php${PHP_VERSION}-bcmath"
    "php${PHP_VERSION}-intl"
    "php${PHP_VERSION}-readline"
    "php${PHP_VERSION}-sqlite3"
    "php${PHP_VERSION}-gd"
    "php${PHP_VERSION}-opcache"
  )
  [[ "${ENABLE_REDIS}" == "1" ]] && pkgs+=("php${PHP_VERSION}-redis")

  apt_install "${pkgs[@]}"

  # Switch Apache from prefork+mod_php to mpm_event+php-fpm (production pattern).
  a2dismod "php${PHP_VERSION}"      >/dev/null 2>&1 || true
  a2dismod mpm_prefork              >/dev/null 2>&1 || true
  a2enmod  mpm_event                >/dev/null 2>&1 || true
  a2enmod  proxy_fcgi setenvif      >/dev/null 2>&1
  a2enmod  rewrite headers ssl http2 >/dev/null 2>&1 || true
  a2enconf "php${PHP_VERSION}-fpm"  >/dev/null 2>&1 || true
  a2dissite 000-default.conf        >/dev/null 2>&1 || true

  if ! require_cmd composer || ! composer --version >/dev/null 2>&1; then
    log "installing composer"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  fi

  ensure_node_lts

  if ! require_cmd certbot; then
    apt_install certbot python3-certbot-apache
  fi
}

ensure_node_lts() {
  local need=0 major=0
  if require_cmd node; then
    major="$(node -p "Number(process.versions.node.split('.')[0])" 2>/dev/null || echo 0)"
    major="${major:-0}"
  else
    need=1
  fi
  [[ "${need}" -eq 0 && "${major}" -lt 20 ]] && need=1
  if [[ "${need}" -eq 1 ]]; then
    log "installing Node.js 22 LTS"
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt_install nodejs
  fi
}

mysql_like_server_installed() {
  have_pkg mariadb-server || have_pkg mysql-server || have_pkg mysql-server-8.0
}

ensure_database_server() {
  if mysql_like_server_installed; then
    return 0
  fi
  log "installing ${DB_SERVER_PACKAGE:-mariadb-server}"
  apt_update_once
  case "${DB_SERVER_PACKAGE:-mariadb-server}" in
    mysql-server) apt_install mysql-server ;;
    *)            apt_install mariadb-server mariadb-client ;;
  esac
}

harden_mysql() {
  # Idempotent, non-interactive equivalent of mysql_secure_installation.
  mysql --protocol=socket -uroot <<'SQL' >/dev/null 2>&1 || true
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost','127.0.0.1','::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
SQL
}

ensure_redis() {
  [[ "${ENABLE_REDIS}" == "1" ]] || return 0
  log "installing redis"
  apt_update_once
  apt_install redis-server
  # Bind to loopback only, enable supervised systemd
  sed -i -E 's/^#?\s*bind .*/bind 127.0.0.1 ::1/' /etc/redis/redis.conf
  sed -i -E 's/^#?\s*supervised .*/supervised systemd/' /etc/redis/redis.conf
  systemctl enable --now redis-server
}

#──────────────────────────────────────────────────────────────────────────────
# Phase 3 — PHP / Apache config
#──────────────────────────────────────────────────────────────────────────────

configure_php() {
  log "writing PHP / opcache config"
  local ini
  ini="$(cat <<INI
; Managed by ubuntu-vps-install.sh — Laravel production defaults
memory_limit = ${PHP_MEMORY_LIMIT}
upload_max_filesize = ${UPLOAD_MAX_FILESIZE}
post_max_size = ${POST_MAX_SIZE}
max_execution_time = ${PHP_MAX_EXECUTION_TIME}
max_input_time = 120
expose_php = Off
date.timezone = ${TIMEZONE:-UTC}
realpath_cache_size = 4096K
realpath_cache_ttl = 600
INI
)"
  printf '%s\n' "${ini}" >"/etc/php/${PHP_VERSION}/fpm/conf.d/99-ebq-laravel.ini"
  printf '%s\n' "${ini}" >"/etc/php/${PHP_VERSION}/cli/conf.d/99-ebq-laravel.ini"

  local opcache
  opcache="$(cat <<INI
; Managed by ubuntu-vps-install.sh
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = ${OPCACHE_MEMORY_MB}
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.jit = tracing
opcache.jit_buffer_size = 64M
INI
)"
  printf '%s\n' "${opcache}" >"/etc/php/${PHP_VERSION}/fpm/conf.d/10-opcache.ini"

  systemctl restart "php${PHP_VERSION}-fpm"
}

configure_apache_global() {
  log "writing Apache global config"
  cat >/etc/apache2/conf-available/ebq-servername.conf <<EOF
# Managed by ubuntu-vps-install.sh
ServerName ${PRIMARY_DOMAIN}
EOF
  a2enconf ebq-servername >/dev/null 2>&1 || true

  cat >/etc/apache2/conf-available/ebq-hardening.conf <<'EOF'
# Managed by ubuntu-vps-install.sh — hide server identity / sane defaults
ServerTokens Prod
ServerSignature Off
TraceEnable Off
FileETag None
Timeout 60
KeepAlive On
KeepAliveTimeout 5
MaxKeepAliveRequests 100
EOF
  a2enconf ebq-hardening >/dev/null 2>&1 || true
}

apache_vhost_path() { echo "/etc/apache2/sites-available/${PRIMARY_DOMAIN}.conf"; }

write_apache_vhost() {
  log "writing Apache vhost for ${PRIMARY_DOMAIN}"
  local conf alias_line=""
  conf="$(apache_vhost_path)"
  if extra_server_aliases | grep -q .; then
    alias_line="    ServerAlias $(extra_server_aliases | paste -sd' ' -)"
  fi

  cat >"${conf}" <<EOF
# Managed by ubuntu-vps-install.sh — HTTP vhost (certbot will extend w/ :443)
<VirtualHost *:80>
    ServerName ${PRIMARY_DOMAIN}
${alias_line}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Security headers (effective on both :80 and inherited by certbot's :443)
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"

    <FilesMatch "\.(env|git|sql|bak|log|ini|sh)$">
        Require all denied
    </FilesMatch>

    ErrorLog  \${APACHE_LOG_DIR}/${PRIMARY_DOMAIN}-error.log
    CustomLog \${APACHE_LOG_DIR}/${PRIMARY_DOMAIN}-access.log combined
</VirtualHost>
EOF

  a2ensite "$(basename "${conf}")" >/dev/null
  apache2ctl configtest >/dev/null
  systemctl reload apache2
}

apply_hsts_after_ssl() {
  # Only set HSTS once we actually have a cert; otherwise browsers get locked out.
  [[ "${ENABLE_SSL}" == "1" ]] || return 0
  local conf
  conf="$(apache_vhost_path)"
  local ssl_conf="${conf%.conf}-le-ssl.conf"
  [[ -f "${ssl_conf}" ]] || return 0
  if ! grep -q 'Strict-Transport-Security' "${ssl_conf}"; then
    sed -i '/<\/VirtualHost>/i \    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"' "${ssl_conf}"
  fi
  apache2ctl configtest >/dev/null
  systemctl reload apache2
}

#──────────────────────────────────────────────────────────────────────────────
# Phase 4 — Database
#──────────────────────────────────────────────────────────────────────────────

mysql_ensure_db_user() {
  : "${DB_DATABASE:=ebq}"
  : "${DB_USERNAME:=ebq}"
  : "${DB_PASSWORD:?Set DB_PASSWORD in deploy.env}"

  log "ensuring DB ${DB_DATABASE} / user ${DB_USERNAME}"

  local epw eu edb
  epw="$(mysql_escape_single "${DB_PASSWORD}")"
  eu="$(mysql_escape_single "${DB_USERNAME}")"
  edb="$(mysql_escape_ident "${DB_DATABASE}")"

  mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${edb}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${eu}'@'localhost' IDENTIFIED BY '${epw}';
ALTER USER '${eu}'@'localhost' IDENTIFIED BY '${epw}';
GRANT ALL PRIVILEGES ON \`${edb}\`.* TO '${eu}'@'localhost';
FLUSH PRIVILEGES;
SQL
}

#──────────────────────────────────────────────────────────────────────────────
# Phase 5 — Git deploy + app build
#──────────────────────────────────────────────────────────────────────────────

git_deploy() {
  log "deploying ${GIT_REPO}#${GIT_BRANCH} -> ${APP_DIR}"
  mkdir -p "$(dirname "${APP_DIR}")"

  if [[ -n "${GIT_SSH_IDENTITY_FILE:-}" ]]; then
    [[ -r "${GIT_SSH_IDENTITY_FILE}" ]] || die "GIT_SSH_IDENTITY_FILE not readable: ${GIT_SSH_IDENTITY_FILE}"
    local keyq
    keyq="$(printf '%q' "${GIT_SSH_IDENTITY_FILE}")"
    export GIT_SSH_COMMAND="ssh -i ${keyq} -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
  fi

  if [[ -d "${APP_DIR}/.git" ]]; then
    git -C "${APP_DIR}" fetch origin
    git -C "${APP_DIR}" checkout "${GIT_BRANCH}"
    git -C "${APP_DIR}" pull --ff-only origin "${GIT_BRANCH}"
  elif [[ -e "${APP_DIR}" ]]; then
    die "${APP_DIR} exists but is not a git clone; remove it or set APP_DIR to an empty path."
  else
    git clone --branch "${GIT_BRANCH}" --single-branch "${GIT_REPO}" "${APP_DIR}"
  fi

  # Deploy user owns files, www-data group can read + write to storage/cache.
  usermod -aG www-data "${DEPLOY_USER}" 2>/dev/null || true
  # Single-pass chown/chmod — the old `find -exec \;` loop spawned one process
  # per file and took forever on trees with vendor/ + node_modules/.
  chown -R "${DEPLOY_USER}:www-data" "${APP_DIR}"
  chmod -R u=rwX,g=rX,o=rX "${APP_DIR}"
  [[ -f "${APP_DIR}/artisan" ]] && chmod 755 "${APP_DIR}/artisan"
  chmod -R ug+rwX "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true
  # ACL so php-fpm (www-data) can create/rotate logs + caches regardless of umask.
  setfacl -R -m u:www-data:rwX -m d:u:www-data:rwX \
    "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true
}

configure_git_safe_directory() {
  _safe_add() {
    local u="$1"
    if [[ "${u}" == "root" ]]; then
      git config --global --get-all safe.directory 2>/dev/null | grep -qxF "${APP_DIR}" && return 0
      git config --global --add safe.directory "${APP_DIR}" || true
    else
      sudo -u "${u}" git config --global --get-all safe.directory 2>/dev/null | grep -qxF "${APP_DIR}" && return 0
      sudo -u "${u}" git config --global --add safe.directory "${APP_DIR}" || true
    fi
  }
  _safe_add root
  [[ "${DEPLOY_USER}" != "root" ]] && _safe_add "${DEPLOY_USER}"
}

laravel_env_and_build() {
  log "seeding .env + installing dependencies + building assets"
  local env_file="${APP_DIR}/.env"

  if [[ ! -f "${env_file}" ]]; then
    if [[ -f "${APP_DIR}/.env.example" ]]; then
      cp "${APP_DIR}/.env.example" "${env_file}"
    else
      die "No .env.example in ${APP_DIR}; create .env manually."
    fi
  fi

  # Merge/overwrite the keys we manage. Everything else in .env is left untouched.
  ENV_FILE="${env_file}" \
  APP_URL="${APP_URL:-https://${PRIMARY_DOMAIN}}" \
  DB_CONNECTION="${DB_CONNECTION:-mysql}" \
  DB_HOST="${DB_HOST:-127.0.0.1}" \
  DB_PORT="${DB_PORT:-3306}" \
  DB_DATABASE="${DB_DATABASE:-ebq}" \
  DB_USERNAME="${DB_USERNAME:-ebq}" \
  DB_PASSWORD="${DB_PASSWORD:-}" \
  ENABLE_REDIS="${ENABLE_REDIS}" \
  php -r '
    $path = getenv("ENV_FILE");
    $pairs = [
      "APP_ENV"       => "production",
      "APP_DEBUG"     => "false",
      "APP_URL"       => getenv("APP_URL")       ?: "",
      "DB_CONNECTION" => getenv("DB_CONNECTION") ?: "mysql",
      "DB_HOST"       => getenv("DB_HOST")       ?: "127.0.0.1",
      "DB_PORT"       => getenv("DB_PORT")       ?: "3306",
      "DB_DATABASE"   => getenv("DB_DATABASE")   ?: "",
      "DB_USERNAME"   => getenv("DB_USERNAME")   ?: "",
      "DB_PASSWORD"   => getenv("DB_PASSWORD")   ?: "",
    ];
    if (getenv("ENABLE_REDIS") === "1") {
      $pairs["REDIS_HOST"]   = "127.0.0.1";
      $pairs["REDIS_PORT"]   = "6379";
      $pairs["REDIS_CLIENT"] = "phpredis";
    }
    $lines = file_exists($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    $out = [];
    foreach ($lines as $line) {
      if (preg_match("/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/", $line, $m)
          && array_key_exists($m[1], $pairs)) continue;
      $out[] = $line;
    }
    foreach ($pairs as $k => $v) { if ($v !== "") $out[] = "$k=$v"; }
    file_put_contents($path, implode(PHP_EOL, $out) . PHP_EOL);
  '

  local run="sudo -u ${DEPLOY_USER} bash -lc"
  ${run} "cd '${APP_DIR}' && composer install --no-dev --optimize-autoloader --no-interaction"

  # Generate APP_KEY only if missing — Laravel's own tool handles quoting correctly.
  if ! grep -qE '^APP_KEY=base64:' "${env_file}"; then
    ${run} "cd '${APP_DIR}' && php artisan key:generate --force --no-interaction"
  fi

  ${run} "cd '${APP_DIR}' && npm ci && npm run build"
  ${run} "cd '${APP_DIR}' && php artisan storage:link || true"
  ${run} "cd '${APP_DIR}' && php artisan migrate --force --no-interaction"

  # Rebuild all caches from scratch so stale caches never survive a deploy.
  ${run} "cd '${APP_DIR}' && php artisan optimize:clear"
  ${run} "cd '${APP_DIR}' && php artisan config:cache"
  ${run} "cd '${APP_DIR}' && php artisan route:cache"
  ${run} "cd '${APP_DIR}' && php artisan view:cache"
  ${run} "cd '${APP_DIR}' && php artisan event:cache || true"

  # Re-apply ownership after composer/npm/artisan may have created files as DEPLOY_USER.
  chown -R "${DEPLOY_USER}:www-data" "${APP_DIR}"
  chmod -R ug+rwX "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
  setfacl -R -m u:www-data:rwX -m d:u:www-data:rwX \
    "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true
}

#──────────────────────────────────────────────────────────────────────────────
# Phase 6 — Queue worker, scheduler, logrotate
#──────────────────────────────────────────────────────────────────────────────

install_queue_worker() {
  [[ "${ENABLE_QUEUE_WORKER}" == "1" ]] || return 0
  log "installing ebq-queue.service"

  cat >/etc/systemd/system/ebq-queue.service <<EOF
[Unit]
Description=ebq Laravel queue worker
After=network.target mariadb.service mysql.service redis-server.service
PartOf=multi-user.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=90
Restart=always
RestartSec=5
KillSignal=SIGTERM
TimeoutStopSec=90

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable ebq-queue.service >/dev/null
  systemctl restart ebq-queue.service
}

install_scheduler_cron() {
  [[ "${ENABLE_SCHEDULER}" == "1" ]] || return 0
  log "installing Laravel scheduler cron"
  local cronfile="/etc/cron.d/ebq-scheduler"
  cat >"${cronfile}" <<EOF
# Managed by ubuntu-vps-install.sh — Laravel scheduler (runs every minute)
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www-data cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /var/log/ebq/scheduler.log 2>&1
EOF
  chmod 644 "${cronfile}"
  install -d -o www-data -g www-data -m 0755 /var/log/ebq
}

install_logrotate() {
  log "installing logrotate policies"
  cat >/etc/logrotate.d/ebq <<EOF
${APP_DIR}/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 ${DEPLOY_USER} www-data
    sharedscripts
}

/var/log/ebq/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 www-data www-data
}
EOF
}

#──────────────────────────────────────────────────────────────────────────────
# Phase 7 — DNS + SSL
#──────────────────────────────────────────────────────────────────────────────

cloudflare_upsert_a() {
  local token="${CLOUDFLARE_API_TOKEN:-}"
  local zone_name="${CLOUDFLARE_ZONE_NAME:-}"
  if [[ -z "$token" && -z "$zone_name" ]]; then
    return 0
  fi
  if [[ -z "$token" || -z "$zone_name" ]]; then
    die "Set both CLOUDFLARE_API_TOKEN and CLOUDFLARE_ZONE_NAME, or leave both unset."
  fi

  log "upserting Cloudflare A records in zone ${zone_name}"
  local zone_id
  zone_id="$(curl -fsS -H "Authorization: Bearer ${token}" \
    "https://api.cloudflare.com/client/v4/zones?name=${zone_name}" | jq -r '.result[0].id // empty')"
  [[ -n "${zone_id}" && "${zone_id}" != "null" ]] ||
    die "Cloudflare: could not resolve zone ${zone_name}"

  local ip
  ip="$(public_ipv4)"
  local name
  while IFS= read -r name; do
    [[ -z "$name" ]] && continue
    local existing rid payload
    existing="$(curl -fsS -H "Authorization: Bearer ${token}" \
      "https://api.cloudflare.com/client/v4/zones/${zone_id}/dns_records?type=A&name=${name}")"
    rid="$(echo "${existing}" | jq -r '.result[0].id // empty')"
    payload="$(jq -nc --arg name "$name" --arg ip "$ip" \
      '{type:"A",name:$name,content:$ip,ttl:300,proxied:false}')"
    if [[ -n "$rid" && "$rid" != "null" ]]; then
      curl -fsS -X PUT -H "Authorization: Bearer ${token}" -H "Content-Type: application/json" \
        "https://api.cloudflare.com/client/v4/zones/${zone_id}/dns_records/${rid}" -d "${payload}" >/dev/null
      log "Cloudflare: updated A ${name} -> ${ip}"
    else
      curl -fsS -X POST -H "Authorization: Bearer ${token}" -H "Content-Type: application/json" \
        "https://api.cloudflare.com/client/v4/zones/${zone_id}/dns_records" -d "${payload}" >/dev/null
      log "Cloudflare: created A ${name} -> ${ip}"
    fi
  done < <(server_all_names)
}

maybe_ssl() {
  [[ "${ENABLE_SSL}" == "1" ]] || return 0
  : "${ADMIN_EMAIL:?Set ADMIN_EMAIL when ENABLE_SSL=1}"
  log "requesting Let's Encrypt certificate(s) via certbot"

  local cert_args=() d
  while IFS= read -r d; do
    [[ -z "$d" ]] && continue
    cert_args+=(-d "$d")
  done < <(server_all_names)

  # --redirect adds the HTTP->HTTPS rewrite automatically.
  certbot --apache --non-interactive --agree-tos --redirect \
    -m "${ADMIN_EMAIL}" "${cert_args[@]}" || {
      warn "certbot failed — site will still serve HTTP. Check: certbot certificates"
      return 0
    }

  # certbot installs its own systemd timer on Ubuntu — ensure it's active.
  systemctl enable --now certbot.timer >/dev/null 2>&1 || true
  apply_hsts_after_ssl
}

#──────────────────────────────────────────────────────────────────────────────
# Main
#──────────────────────────────────────────────────────────────────────────────

main() {
  configure_system_misc
  install_base_stack
  ensure_database_server
  ensure_redis
  configure_firewall
  configure_fail2ban
  configure_php
  configure_apache_global

  ensure_service "php${PHP_VERSION}-fpm"
  ensure_service mariadb 2>/dev/null || ensure_service mysql
  harden_mysql
  mysql_ensure_db_user

  git_deploy
  configure_git_safe_directory
  laravel_env_and_build

  write_apache_vhost
  cloudflare_upsert_a
  ensure_service apache2
  systemctl restart apache2

  install_queue_worker
  install_scheduler_cron
  install_logrotate

  maybe_ssl
  systemctl reload apache2

  cat <<EOF

────────────────────────────────────────────────────────────────
 ebq VPS installer completed.
 App dir     : ${APP_DIR}
 Primary URL : $([[ "${ENABLE_SSL}" == "1" ]] && echo "https://${PRIMARY_DOMAIN}" || echo "http://${PRIMARY_DOMAIN}")
 Aliases     : $(extra_server_aliases | paste -sd' ' - || echo '(none)')
 PHP-FPM     : php${PHP_VERSION}-fpm
 Queue unit  : $([[ "${ENABLE_QUEUE_WORKER}" == "1" ]] && echo "ebq-queue.service (enabled)" || echo "disabled")
 Scheduler   : $([[ "${ENABLE_SCHEDULER}" == "1"    ]] && echo "/etc/cron.d/ebq-scheduler"    || echo "disabled")
 Redis       : $([[ "${ENABLE_REDIS}" == "1"        ]] && echo "127.0.0.1:6379"               || echo "not installed")
────────────────────────────────────────────────────────────────
EOF
}

main "$@"
