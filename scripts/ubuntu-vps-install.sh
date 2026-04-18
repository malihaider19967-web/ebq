#!/usr/bin/env bash
# Ubuntu VPS bootstrap: stack check/install, git deploy, Apache vhost, optional SSL + Cloudflare DNS.
# Usage:
#   sudo cp scripts/deploy.env.example scripts/deploy.env
#   sudo nano scripts/deploy.env
#   sudo bash scripts/ubuntu-vps-install.sh
#
# Re-runs are safe: apt/composer/node checks are idempotent; config snippets are rewritten;
# DB user password is synced; git pull updates the app dir.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_ENV_FILE="${SCRIPT_DIR}/deploy.env"
: "${DEPLOY_ENV_FILE:=${DEFAULT_ENV_FILE}}"

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "Run as root: sudo $0" >&2
  exit 1
fi

if [[ -f "${DEPLOY_ENV_FILE}" ]]; then
  # shellcheck source=/dev/null
  set -a && source "${DEPLOY_ENV_FILE}" && set +a
else
  echo "Missing ${DEPLOY_ENV_FILE}" >&2
  echo "Copy deploy.env.example to deploy.env and edit values." >&2
  exit 1
fi

: "${GIT_REPO:?Set GIT_REPO in deploy.env}"
: "${GIT_BRANCH:=main}"
: "${APP_DIR:=/var/www/ebq}"
: "${PRIMARY_DOMAIN:?Set PRIMARY_DOMAIN in deploy.env}"
: "${EXTRA_DOMAINS:=}"
: "${ENABLE_SSL:=0}"
: "${ADMIN_EMAIL:=}"
: "${ENABLE_UFW:=0}"
: "${TIMEZONE:=}"
: "${PHP_MEMORY_LIMIT:=512M}"
: "${UPLOAD_MAX_FILESIZE:=64M}"
: "${POST_MAX_SIZE:=64M}"

PHP_VERSION="${PHP_VERSION:-8.3}"

require_cmd() {
  command -v "$1" >/dev/null 2>&1
}

apt_install() {
  DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "$@"
}

ensure_repo_php() {
  if apt-cache show "php${PHP_VERSION}-cli" >/dev/null 2>&1; then
    return 0
  fi
  apt_install software-properties-common ca-certificates lsb-release gnupg
  add-apt-repository -y ppa:ondrej/php
  apt-get update -y
}

have_pkg() {
  dpkg -s "$1" >/dev/null 2>&1
}

mysql_like_server_installed() {
  have_pkg mariadb-server || have_pkg mysql-server || have_pkg mysql-server-8.0
}

# Installs a MySQL-protocol server if none is present (Laravel uses DB_CONNECTION=mysql either way).
ensure_database_server() {
  if mysql_like_server_installed; then
    return 0
  fi
  echo "No MySQL-compatible server found; installing ${DB_SERVER_PACKAGE:-mariadb-server}..." >&2
  apt-get update -y
  case "${DB_SERVER_PACKAGE:-mariadb-server}" in
    mysql-server)
      apt_install mysql-server
      ;;
    *)
      apt_install mariadb-server mariadb-client
      ;;
  esac
}

install_base_stack() {
  apt-get update -y
  apt_install git curl unzip jq openssl acl ca-certificates

  ensure_repo_php

  local pkgs=(
    "apache2"
    "libapache2-mod-php${PHP_VERSION}"
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
  )

  apt_install "${pkgs[@]}"

  a2enmod rewrite headers ssl >/dev/null 2>&1 || true
  a2dissite 000-default.conf >/dev/null 2>&1 || true

  if ! require_cmd composer || ! composer --version >/dev/null 2>&1; then
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
  local need=0
  local major=0
  if require_cmd node; then
    major="$(node -p "Number(process.versions.node.split('.')[0])" 2>/dev/null || echo 0)"
    major="${major:-0}"
  else
    need=1
  fi
  if [[ "${need}" -eq 0 && "${major}" -lt 20 ]]; then
    need=1
  fi
  if [[ "${need}" -eq 1 ]]; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt_install nodejs
  fi
}

configure_system_misc() {
  if [[ -n "${TIMEZONE}" ]]; then
    timedatectl set-timezone "${TIMEZONE}" >/dev/null
  fi

  if [[ "${ENABLE_UFW}" == "1" ]]; then
    apt_install ufw
    ufw allow OpenSSH >/dev/null 2>&1 || ufw allow 22/tcp >/dev/null 2>&1 || true
    ufw allow 80/tcp >/dev/null 2>&1 || true
    ufw allow 443/tcp >/dev/null 2>&1 || true
    ufw --force enable >/dev/null
  fi
}

configure_php_apache() {
  local body
  body="$(cat <<INI
; Managed by ubuntu-vps-install.sh — Laravel-friendly defaults
memory_limit = ${PHP_MEMORY_LIMIT}
upload_max_filesize = ${UPLOAD_MAX_FILESIZE}
post_max_size = ${POST_MAX_SIZE}
max_execution_time = 120
expose_php = Off
INI
)"
  printf '%s\n' "${body}" >"/etc/php/${PHP_VERSION}/apache2/conf.d/99-ebq-laravel.ini"
  printf '%s\n' "${body}" >"/etc/php/${PHP_VERSION}/cli/conf.d/99-ebq-laravel.ini"
}

configure_apache_global() {
  local sn="/etc/apache2/conf-available/ebq-servername.conf"
  cat >"${sn}" <<EOF
# Managed by ubuntu-vps-install.sh
ServerName ${PRIMARY_DOMAIN}
EOF
  a2enconf ebq-servername >/dev/null 2>&1 || true
}

ensure_service() {
  local svc="$1"
  systemctl enable "${svc}" >/dev/null 2>&1 || true
  systemctl start "${svc}" >/dev/null 2>&1 || true
  systemctl is-active --quiet "${svc}" || {
    echo "Service ${svc} is not active; check: journalctl -u ${svc} -n 50" >&2
    return 1
  }
}

server_all_names() {
  local names=("${PRIMARY_DOMAIN}")
  IFS=',' read -r -a extra <<< "${EXTRA_DOMAINS// /}"
  local x
  for x in "${extra[@]}"; do
    [[ -n "$x" ]] && names+=("$x")
  done
  printf '%s\n' "${names[@]}" | awk 'NF' | sort -u
}

apache_vhost_path() {
  echo "/etc/apache2/sites-available/${PRIMARY_DOMAIN}.conf"
}

extra_server_aliases() {
  server_all_names | grep -vxF "${PRIMARY_DOMAIN}" || true
}

write_apache_vhost() {
  local conf
  conf="$(apache_vhost_path)"
  local alias_line=""
  if extra_server_aliases | grep -q .; then
    alias_line="    ServerAlias $(extra_server_aliases | paste -sd' ' -)"
  fi

  cat >"${conf}" <<EOF
<VirtualHost *:80>
    ServerName ${PRIMARY_DOMAIN}
${alias_line}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${PRIMARY_DOMAIN}-error.log
    CustomLog \${APACHE_LOG_DIR}/${PRIMARY_DOMAIN}-access.log combined
</VirtualHost>
EOF

  a2ensite "$(basename "${conf}")" >/dev/null
  systemctl reload apache2
}

mysql_escape_single() {
  printf '%s' "$1" | sed "s/'/''/g"
}

mysql_escape_ident() {
  printf '%s' "$1" | sed 's/`/``/g'
}

mysql_ensure_db_user() {
  : "${DB_DATABASE:=ebq}"
  : "${DB_USERNAME:=ebq}"
  : "${DB_PASSWORD:?Set DB_PASSWORD in deploy.env}"

  local epw
  epw="$(mysql_escape_single "${DB_PASSWORD}")"
  local eu
  eu="$(mysql_escape_single "${DB_USERNAME}")"
  local edb
  edb="$(mysql_escape_ident "${DB_DATABASE}")"

  mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${edb}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${eu}'@'localhost' IDENTIFIED BY '${epw}';
ALTER USER '${eu}'@'localhost' IDENTIFIED BY '${epw}';
GRANT ALL PRIVILEGES ON \`${edb}\`.* TO '${eu}'@'localhost';
FLUSH PRIVILEGES;
SQL
}

git_deploy() {
  mkdir -p "$(dirname "${APP_DIR}")"
  local owner="${SUDO_USER:-root}"

  if [[ -n "${GIT_SSH_IDENTITY_FILE:-}" ]]; then
    if [[ ! -r "${GIT_SSH_IDENTITY_FILE}" ]]; then
      echo "GIT_SSH_IDENTITY_FILE is not readable: ${GIT_SSH_IDENTITY_FILE}" >&2
      exit 1
    fi
    local keyq
    keyq="$(printf '%q' "${GIT_SSH_IDENTITY_FILE}")"
    export GIT_SSH_COMMAND="ssh -i ${keyq} -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
  fi

  if [[ -d "${APP_DIR}/.git" ]]; then
    git -C "${APP_DIR}" fetch origin
    git -C "${APP_DIR}" checkout "${GIT_BRANCH}"
    git -C "${APP_DIR}" pull --ff-only origin "${GIT_BRANCH}"
  elif [[ -e "${APP_DIR}" ]]; then
    echo "${APP_DIR} exists but is not a git clone; remove it or set APP_DIR to an empty path." >&2
    exit 1
  else
    git clone --branch "${GIT_BRANCH}" --single-branch "${GIT_REPO}" "${APP_DIR}"
  fi

  chown -R "${owner}:www-data" "${APP_DIR}"
  find "${APP_DIR}" -type d -exec chmod 775 {} \;
  find "${APP_DIR}" -type f -exec chmod 664 {} \;
  [[ -f "${APP_DIR}/artisan" ]] && chmod 775 "${APP_DIR}/artisan"
  chmod -R g+w "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true
}

configure_git_safe_directory() {
  local owner="${SUDO_USER:-root}"
  git_safe_add() {
    local u="$1"
    if [[ "${u}" == "root" ]]; then
      git config --global --get-all safe.directory 2>/dev/null | grep -qxF "${APP_DIR}" && return 0
      git config --global --add safe.directory "${APP_DIR}" 2>/dev/null || true
    else
      sudo -u "${u}" git config --global --get-all safe.directory 2>/dev/null | grep -qxF "${APP_DIR}" && return 0
      sudo -u "${u}" git config --global --add safe.directory "${APP_DIR}" 2>/dev/null || true
    fi
  }
  git_safe_add root
  if [[ "${owner}" != "root" ]]; then
    git_safe_add "${owner}"
  fi
}

laravel_env_and_build() {
  local env_file="${APP_DIR}/.env"
  if [[ ! -f "${env_file}" ]]; then
    if [[ -f "${APP_DIR}/.env.example" ]]; then
      cp "${APP_DIR}/.env.example" "${env_file}"
    else
      echo "No .env.example in ${APP_DIR}; create .env manually." >&2
      exit 1
    fi
  fi

  local app_key_value=""
  local raw_key
  raw_key="$(grep -E '^APP_KEY=' "${env_file}" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' || true)"
  raw_key="${raw_key#"${raw_key%%[![:space:]]*}"}"
  raw_key="${raw_key%"${raw_key##*[![:space:]]}"}"
  if [[ -z "${raw_key}" ]]; then
    app_key_value="$(cd "${APP_DIR}" && php -r "echo 'base64:'.base64_encode(random_bytes(32));")"
  fi

  ENV_FILE="${env_file}" \
    APP_URL="${APP_URL:-https://${PRIMARY_DOMAIN}}" \
    APP_KEY_VALUE="${app_key_value}" \
    DB_CONNECTION="${DB_CONNECTION:-mysql}" \
    DB_HOST="${DB_HOST:-127.0.0.1}" \
    DB_PORT="${DB_PORT:-3306}" \
    DB_DATABASE="${DB_DATABASE:-ebq}" \
    DB_USERNAME="${DB_USERNAME:-ebq}" \
    DB_PASSWORD="${DB_PASSWORD:-}" \
    php -r '
    $path = getenv("ENV_FILE");
    $pairs = [
      "APP_ENV" => "production",
      "APP_DEBUG" => "false",
      "APP_URL" => getenv("APP_URL") ?: "",
      "DB_CONNECTION" => getenv("DB_CONNECTION") ?: "mysql",
      "DB_HOST" => getenv("DB_HOST") ?: "127.0.0.1",
      "DB_PORT" => getenv("DB_PORT") ?: "3306",
      "DB_DATABASE" => getenv("DB_DATABASE") ?: "",
      "DB_USERNAME" => getenv("DB_USERNAME") ?: "",
      "DB_PASSWORD" => getenv("DB_PASSWORD") ?: "",
    ];
    $k = getenv("APP_KEY_VALUE");
    if ($k !== false && $k !== "") {
      $pairs["APP_KEY"] = $k;
    }
    $lines = file_exists($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    $out = [];
    foreach ($lines as $line) {
      if (preg_match("/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=/", $line, $m)) {
        $key = $m[1];
        if (array_key_exists($key, $pairs)) {
          continue;
        }
      }
      $out[] = $line;
    }
    foreach ($pairs as $key => $v) {
      if ($v === "") {
        continue;
      }
      $out[] = $key . "=" . $v;
    }
    file_put_contents($path, implode(PHP_EOL, $out) . PHP_EOL);
  '

  local owner="${SUDO_USER:-root}"
  sudo -u "${owner}" bash -c "cd '${APP_DIR}' && composer install --no-dev --optimize-autoloader --no-interaction"
  sudo -u "${owner}" bash -c "cd '${APP_DIR}' && npm ci && npm run build"

  sudo -u "${owner}" bash -c "cd '${APP_DIR}' && php artisan migrate --force"
  sudo -u "${owner}" bash -c "cd '${APP_DIR}' && php artisan config:cache && php artisan route:cache && php artisan view:cache"
}

public_ipv4() {
  curl -fsS https://api.ipify.org || curl -fsS https://ifconfig.me
}

cloudflare_upsert_a() {
  local token="${CLOUDFLARE_API_TOKEN:-}"
  local zone_name="${CLOUDFLARE_ZONE_NAME:-}"
  if [[ -z "$token" && -z "$zone_name" ]]; then
    return 0
  fi
  if [[ -z "$token" || -z "$zone_name" ]]; then
    echo "Set both CLOUDFLARE_API_TOKEN and CLOUDFLARE_ZONE_NAME, or leave both unset." >&2
    return 1
  fi

  local zone_id
  zone_id="$(curl -fsS -H "Authorization: Bearer ${token}" \
    "https://api.cloudflare.com/client/v4/zones?name=${zone_name}" | jq -r '.result[0].id // empty')"
  if [[ -z "${zone_id}" || "${zone_id}" == "null" ]]; then
    echo "Cloudflare: could not resolve zone for ${zone_name}. Check CLOUDFLARE_ZONE_NAME and token." >&2
    return 1
  fi

  local ip
  ip="$(public_ipv4)"
  local name
  while IFS= read -r name; do
    [[ -z "$name" ]] && continue
    local existing
    existing="$(curl -fsS -H "Authorization: Bearer ${token}" \
      "https://api.cloudflare.com/client/v4/zones/${zone_id}/dns_records?type=A&name=${name}")"
    local rid
    rid="$(echo "${existing}" | jq -r '.result[0].id // empty')"

    local payload
    payload="$(jq -nc --arg name "$name" --arg ip "$ip" \
      '{type:"A",name:$name,content:$ip,ttl:300,proxied:false}')"

    if [[ -n "$rid" && "$rid" != "null" ]]; then
      curl -fsS -X PUT -H "Authorization: Bearer ${token}" -H "Content-Type: application/json" \
        "https://api.cloudflare.com/client/v4/zones/${zone_id}/dns_records/${rid}" \
        -d "${payload}" >/dev/null
      echo "Cloudflare: updated A ${name} -> ${ip}"
    else
      curl -fsS -X POST -H "Authorization: Bearer ${token}" -H "Content-Type: application/json" \
        "https://api.cloudflare.com/client/v4/zones/${zone_id}/dns_records" \
        -d "${payload}" >/dev/null
      echo "Cloudflare: created A ${name} -> ${ip}"
    fi
  done < <(server_all_names)
}

maybe_ssl() {
  if [[ "${ENABLE_SSL}" != "1" ]]; then
    return 0
  fi
  : "${ADMIN_EMAIL:?Set ADMIN_EMAIL when ENABLE_SSL=1}"
  local cert_args=()
  local d
  while IFS= read -r d; do
    [[ -z "$d" ]] && continue
    cert_args+=(-d "$d")
  done < <(server_all_names)
  certbot --apache --non-interactive --agree-tos -m "${ADMIN_EMAIL}" "${cert_args[@]}"
}

main() {
  install_base_stack
  ensure_database_server
  configure_system_misc
  configure_php_apache
  configure_apache_global
  ensure_service mariadb || ensure_service mysql
  mysql_ensure_db_user
  git_deploy
  configure_git_safe_directory
  laravel_env_and_build
  write_apache_vhost
  cloudflare_upsert_a
  ensure_service apache2
  systemctl restart apache2
  maybe_ssl
  systemctl reload apache2

  echo "Done. Site: http://${PRIMARY_DOMAIN} (or HTTPS if certbot succeeded)."
}

main "$@"
