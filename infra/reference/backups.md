# Backups & point-in-time recovery (Phase 0)

> **Tooling ships in `scripts/db/`; applying it is an operator step** (a one-time
> MariaDB restart + a Hetzner Storage Box). This closes the "no backups, binlog
> off — data loss is permanent" hole called out in root `CLAUDE.md`, and is the
> prerequisite for the sharded-tenant move backups and the production ULID cutover.

## What it gives you
1. **Binary logging** → point-in-time recovery + the foundation for a read replica.
2. **Nightly offsite logical backups** of `ebq` to a Hetzner Storage Box (~€3.80/mo).
3. **A restore drill** so a backup is never untested.

## Operator setup (one-time, ~15 min)
1. **Enable binlog** (one restart; brief blip — box is shared with Postal/Jitsi, do it off-peak):
   ```
   sudo mkdir -p /var/log/mysql && sudo chown mysql:mysql /var/log/mysql   # REQUIRED — else startup aborts
   sudo cp scripts/db/binlog.cnf /etc/mysql/mariadb.conf.d/99-ebq-binlog.cnf
   sudo systemctl restart mariadb
   sudo mysql -uroot -e "SHOW VARIABLES LIKE 'log_bin';"   # ON
   ```
   (Done on the live box 2026-06-17: binlog active as `ebq-bin.000001`.)
   Then soften the `CLAUDE.md` "no backups, binlog off" warning to point here.
2. **Storage Box**: order a Hetzner **BX11**, accept its host key once, then write `/etc/ebq-backup.env`:
   ```
   DB_NAME=ebq  DB_USER=ebquser  DB_PASS=<pass>
   SB_HOST=uXXXXXX.your-storagebox.de  SB_USER=uXXXXXX  SB_PORT=23  SB_DIR=ebq-backups
   ```
3. **Schedule** (root cron): `30 3 * * * /var/www/ebq/scripts/db/backup.sh >> /var/log/ebq-backup.log 2>&1`
4. **Verify**: run `scripts/db/backup.sh` once, then `scripts/db/restore-drill.sh` (restores into a
   throwaway `*_test` DB, checks table count, drops it).

## Restore (real)
```
gunzip -c /var/backups/ebq/ebq-<stamp>.sql.gz | mysql -u ebquser -p ebq
```
For point-in-time after the last dump, replay binlog from the dump position with `mysqlbinlog`.

## Scaling note
Logical `mysqldump` is the correct starting point while the DB is small. Once data grows (or a
dedicated DB box lands), switch to streaming **physical** backups (`mariabackup`) + ZFS snapshots — see
the storage-scaling order in the `scaling-roadmap` memory.

## Per-node (sharding)
Each shard node needs the same: binlog drop-in (distinct `server_id`) + a `backup.sh` cron pointed at
that node's DB. The fleet bootstrap (`DbFleetService::bootstrap`) is where this belongs once a node is
provisioned. Tenant-level backups (one customer's data) are the `ebq:shard` export path — see
[../sharding/README.md](../sharding/README.md).
