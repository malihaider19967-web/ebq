#!/usr/bin/env bash
# Build a reusable Hetzner MariaDB snapshot for the DB-node fleet.
#
# DbFleetService::provision() creates DB boxes *from a snapshot* and
# bootstrap() assumes MariaDB is already installed (it only runs `mysql -e …`
# over SSH). This script builds that snapshot once: spin a temp Ubuntu box,
# install + configure MariaDB (remote-ready, binlog on, root via unix_socket),
# snapshot it, delete the temp box. Output: the snapshot image id (also written
# to /tmp/ebq_db_snapshot_id). Set HCLOUD_DB_IMAGE to it afterwards.
#
# Idempotent-ish: safe to re-run (creates a fresh temp box each time).
# Reads all infra ids + token from .env — never echoes the token.
set -euo pipefail
cd "$(dirname "$0")/../.."

env_val() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | tr -d '"'; }
TOK="$(env_val HCLOUD_TOKEN)"
NET="$(env_val HCLOUD_NETWORK_ID)"
SSHK="$(env_val HCLOUD_SSH_KEY_ID)"
FW="$(env_val HCLOUD_FIREWALL_ID)"
LOC="$(env_val HCLOUD_LOCATION)"
BASE_IMAGE="161547269"          # ubuntu-24.04
SERVER_TYPE="cx23"
KEY="/root/.ssh/id_ed25519_worker"
SSH="ssh -i $KEY -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=10"
NAME="ebq-db-snapshot-builder"
API="https://api.hetzner.cloud/v1"
auth=(-H "Authorization: Bearer $TOK" -H "Content-Type: application/json")

jq_py() { python3 -c "import sys,json;d=json.load(sys.stdin);print($1)"; }

echo "==> Creating temp box $NAME ($SERVER_TYPE, ubuntu-24.04) on network $NET ..."
CREATE=$(curl -s "${auth[@]}" -X POST "$API/servers" -d @- <<JSON
{ "name": "$NAME", "server_type": "$SERVER_TYPE", "image": $BASE_IMAGE,
  "location": "$LOC", "start_after_create": true,
  "ssh_keys": [$SSHK], "firewalls": [{"firewall": $FW}],
  "networks": [$NET], "labels": {"role": "ebq-db-snapshot-builder"} }
JSON
)
SID=$(echo "$CREATE" | jq_py 'd.get("server",{}).get("id","")')
if [ -z "$SID" ]; then echo "CREATE FAILED:"; echo "$CREATE"; exit 1; fi
echo "    server id=$SID"

cleanup() { echo "==> Deleting temp box $SID"; curl -s "${auth[@]}" -X DELETE "$API/servers/$SID" >/dev/null || true; }
trap cleanup EXIT

echo "==> Waiting for server to run + private IP ..."
PRIV=""
for i in $(seq 1 40); do
  S=$(curl -s "${auth[@]}" "$API/servers/$SID")
  ST=$(echo "$S" | jq_py 'd.get("server",{}).get("status","")')
  PRIV=$(echo "$S" | jq_py '(d.get("server",{}).get("private_net") or [{}])[0].get("ip","")')
  [ "$ST" = "running" ] && [ -n "$PRIV" ] && break
  sleep 5
done
echo "    status=$ST private_ip=$PRIV"
[ -n "$PRIV" ] || { echo "no private IP"; exit 1; }

echo "==> Waiting for SSH on $PRIV ..."
for i in $(seq 1 40); do $SSH "root@$PRIV" true 2>/dev/null && break; sleep 5; done
$SSH "root@$PRIV" true || { echo "SSH never came up"; exit 1; }

echo "==> Installing + configuring MariaDB ..."
$SSH "root@$PRIV" 'bash -s' <<'REMOTE'
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq mariadb-server >/dev/null
# binlog needs /var/log/mysql owned by mysql (a missing dir blocks startup).
mkdir -p /var/log/mysql && chown mysql:mysql /var/log/mysql
cat >/etc/mysql/mariadb.conf.d/99-ebq.cnf <<'CNF'
[mysqld]
bind-address              = 0.0.0.0
server_id                 = 1
log_bin                   = /var/log/mysql/mysql-bin
log_bin_index             = /var/log/mysql/mysql-bin.index
binlog_format             = ROW
expire_logs_days          = 7
innodb_buffer_pool_size   = 512M
max_connections           = 200
CNF
systemctl enable mariadb >/dev/null 2>&1 || true
systemctl restart mariadb
sleep 3
echo "--- verify ---"
mysql -e "SELECT @@bind_address AS bind, @@log_bin AS binlog, @@server_id AS sid;"
systemctl is-active mariadb
REMOTE

echo "==> Powering off (clean snapshot) ..."
curl -s "${auth[@]}" -X POST "$API/servers/$SID/actions/poweroff" >/dev/null
for i in $(seq 1 30); do
  ST=$(curl -s "${auth[@]}" "$API/servers/$SID" | jq_py 'd.get("server",{}).get("status","")')
  [ "$ST" = "off" ] && break; sleep 4
done
echo "    status=$ST"

echo "==> Creating snapshot image ..."
IMG=$(curl -s "${auth[@]}" -X POST "$API/servers/$SID/actions/create_image" \
  -d '{"type":"snapshot","description":"ebq-db-mariadb","labels":{"role":"ebq-db-node"}}')
IMGID=$(echo "$IMG" | jq_py 'd.get("image",{}).get("id","")')
[ -n "$IMGID" ] || { echo "IMAGE CREATE FAILED:"; echo "$IMG"; exit 1; }
echo "    image id=$IMGID — waiting until available ..."
for i in $(seq 1 60); do
  IST=$(curl -s "${auth[@]}" "$API/images/$IMGID" | jq_py 'd.get("image",{}).get("status","")')
  [ "$IST" = "available" ] && break; sleep 10
done
echo "    image status=$IST"

echo "$IMGID" > /tmp/ebq_db_snapshot_id
echo "==> DONE. Snapshot image id = $IMGID  (also in /tmp/ebq_db_snapshot_id)"
echo "    Set HCLOUD_DB_IMAGE=$IMGID in .env + .env.worker"
