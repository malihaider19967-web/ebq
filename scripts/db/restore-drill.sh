#!/usr/bin/env bash
# EBQ — restore drill: prove a backup actually restores. Run periodically (a
# backup you've never restored is not a backup). Restores the latest local dump
# into a THROWAWAY database (name contains 'test' so the TestCase guard is happy
# and you can never confuse it with prod), checks the table count, then drops it.
set -euo pipefail

ENV_FILE="${EBQ_BACKUP_ENV:-/etc/ebq-backup.env}"
[ -f "$ENV_FILE" ] && . "$ENV_FILE"
: "${DB_USER:?set DB_USER}" "${DB_PASS:?set DB_PASS}" "${LOCAL_DIR:=/var/backups/ebq}"

LATEST="$(ls -1t "$LOCAL_DIR"/ebq-*.sql.gz 2>/dev/null | head -1 || true)"
[ -n "$LATEST" ] || { echo "no backup found in $LOCAL_DIR"; exit 1; }
DRILL_DB="ebq_restore_drill_test"

echo "[$(date -Is)] restoring $LATEST -> $DRILL_DB"
mysql -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS \`$DRILL_DB\`; CREATE DATABASE \`$DRILL_DB\`;"
gunzip -c "$LATEST" | mysql -u "$DB_USER" -p"$DB_PASS" "$DRILL_DB"

TABLES="$(mysql -N -u "$DB_USER" -p"$DB_PASS" -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DRILL_DB';")"
echo "[$(date -Is)] restored OK — $TABLES tables in $DRILL_DB"

mysql -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE \`$DRILL_DB\`;"
echo "[$(date -Is)] drill cleaned up. Restore path verified."
