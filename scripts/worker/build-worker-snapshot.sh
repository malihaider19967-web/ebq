#!/usr/bin/env bash
# Build a fresh, COLD crawl-worker snapshot for the autoscaler/fleet.
#
# Why: WorkerFleetService::provision() boots ephemeral boxes from HCLOUD_WORKER_IMAGE.
# The old snapshot was stale (old code) AND auto-started its workers on boot
# (restart:always) → a box ran OLD code until bootstrap finished, and ran it forever
# if bootstrap failed. This builds a snapshot that:
#   1. has CURRENT code + vendor baked in (so bootstrap's rsync is a tiny diff), and
#   2. is COLD — `docker compose down` before snapshotting, so a fresh box auto-starts
#      NOTHING. Workers only start when bootstrap runs `up` with current code.
#
# It provisions a temp box FROM the current worker snapshot (so Docker + the
# ebq-worker image + the deploy key are already there), refreshes it, powers off,
# snapshots, and prints the new image id. Set HCLOUD_WORKER_IMAGE to it afterwards.
# Idempotent-ish: safe to re-run (fresh temp box each time, auto-deleted).
set -euo pipefail
cd "$(dirname "$0")/../.."

env_val() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | tr -d '"'; }
TOK="$(env_val HCLOUD_TOKEN)"
NET="$(env_val HCLOUD_NETWORK_ID)"
SSHK="$(env_val HCLOUD_SSH_KEY_ID)"
FW="$(env_val HCLOUD_FIREWALL_ID)"
LOC="$(env_val HCLOUD_LOCATION)"
BASE_IMAGE="$(env_val HCLOUD_WORKER_IMAGE)"   # current worker snapshot = base (has Docker + ebq-worker image)
SERVER_TYPE="cx23"
KEY="/root/.ssh/id_ed25519_worker"
SSH="ssh -i $KEY -o BatchMode=yes -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10"
NAME="ebq-worker-snapshot-builder"
API="https://api.hetzner.cloud/v1"
auth=(-H "Authorization: Bearer $TOK" -H "Content-Type: application/json")
EXCLUDES="--exclude=.env --exclude=.git/ --exclude=storage/ --exclude=node_modules/ --exclude=vendor/ --exclude=public/build/ --exclude=bootstrap/cache/ --exclude=ebq-wordpress-plugin/ --exclude=ebq-seo-wp/"

jq_py() { python3 -c "import sys,json;d=json.load(sys.stdin);print($1)"; }

echo "==> Creating temp box $NAME ($SERVER_TYPE) from current worker image $BASE_IMAGE ..."
CREATE=$(curl -s "${auth[@]}" -X POST "$API/servers" -d @- <<JSON
{ "name": "$NAME", "server_type": "$SERVER_TYPE", "image": $BASE_IMAGE,
  "location": "$LOC", "start_after_create": true,
  "ssh_keys": [$SSHK], "firewalls": [{"firewall": $FW}],
  "networks": [$NET], "labels": {"role": "ebq-worker-snapshot-builder"} }
JSON
)
SID=$(echo "$CREATE" | jq_py 'd.get("server",{}).get("id","")')
[ -n "$SID" ] || { echo "CREATE FAILED:"; echo "$CREATE"; exit 1; }
echo "    server id=$SID"
cleanup() { echo "==> Deleting temp box $SID"; curl -s "${auth[@]}" -X DELETE "$API/servers/$SID" >/dev/null || true; }
trap cleanup EXIT

echo "==> Waiting for running + private IP ..."
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
for i in $(seq 1 90); do $SSH "root@$PRIV" true 2>/dev/null && break; sleep 5; done
$SSH "root@$PRIV" true || { echo "SSH never came up"; exit 1; }

echo "==> Stopping whatever the base image auto-started (so we snapshot COLD) ..."
$SSH "root@$PRIV" 'docker compose -f /var/www/ebq/docker-compose.worker.yml down --remove-orphans 2>/dev/null || true'

echo "==> Pushing CURRENT code + vendor + worker env + crawl compose ..."
rsync -az $EXCLUDES -e "$SSH" /var/www/ebq/ "root@$PRIV:/var/www/ebq/"
rsync -az --delete -e "$SSH" /var/www/ebq/vendor/ "root@$PRIV:/var/www/ebq/vendor/"
rsync -az -e "$SSH" /var/www/ebq/.env.worker "root@$PRIV:/var/www/ebq/.env"
$SSH "root@$PRIV" 'cp /var/www/ebq/docker-compose.ephemeral.yml /var/www/ebq/docker-compose.worker.yml'

echo "==> Ensuring COLD + clean (no running containers, no cached config/logs) ..."
$SSH "root@$PRIV" 'cd /var/www/ebq && docker compose -f docker-compose.worker.yml down --remove-orphans 2>/dev/null || true; rm -f bootstrap/cache/*.php; : > storage/logs/laravel.log 2>/dev/null || true'
echo "    containers after down (expect none):"
$SSH "root@$PRIV" 'docker ps -a --format "{{.Names}} {{.Status}}" | head'

echo "==> Powering off (clean snapshot) ..."
curl -s "${auth[@]}" -X POST "$API/servers/$SID/actions/poweroff" >/dev/null
for i in $(seq 1 30); do
  ST=$(curl -s "${auth[@]}" "$API/servers/$SID" | jq_py 'd.get("server",{}).get("status","")')
  [ "$ST" = "off" ] && break; sleep 4
done
echo "    status=$ST"

echo "==> Creating snapshot image ..."
IMG=$(curl -s "${auth[@]}" -X POST "$API/servers/$SID/actions/create_image" \
  -d '{"type":"snapshot","description":"ebq-worker","labels":{"role":"ebq-crawl-worker"}}')
IMGID=$(echo "$IMG" | jq_py 'd.get("image",{}).get("id","")')
[ -n "$IMGID" ] || { echo "IMAGE CREATE FAILED:"; echo "$IMG"; exit 1; }
echo "    image id=$IMGID — waiting until available ..."
for i in $(seq 1 90); do
  IST=$(curl -s "${auth[@]}" "$API/images/$IMGID" | jq_py 'd.get("image",{}).get("status","")')
  [ "$IST" = "available" ] && break; sleep 10
done
echo "    image status=$IST"

echo "$IMGID" > /tmp/ebq_worker_snapshot_id
echo "==> DONE. New COLD worker snapshot id = $IMGID  (also in /tmp/ebq_worker_snapshot_id)"
echo "    Next: set HCLOUD_WORKER_IMAGE=$IMGID in .env + .env.worker, and clear the"
echo "    fleet:snapshots:* cache so the dropdown shows it."
