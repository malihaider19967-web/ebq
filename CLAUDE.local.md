# Local environment notes

## PHP-FPM opcache: code changes need a reload

The FPM SAPI runs with `opcache.enable = 1` and `opcache.validate_timestamps = 0`
(see /etc/php/8.3/fpm/conf.d). This means PHP-FPM **never re-checks file mtimes** —
edits to `.php` files are NOT picked up by the web/API layer until opcache is cleared.

IMPORTANT: a graceful `reload` (USR2) is NOT enough. opcache stores compiled bytecode
in shared memory (SHM) that survives worker respawns, so reloaded workers re-attach to
the SAME stale bytecode. You must do a full restart (rebuilds the SHM) or call
`opcache_reset()` through the FPM SAPI:

    sudo systemctl restart php8.3-fpm   # tears down + rebuilds opcache SHM (brief blip)

Tell-tale: after `restart` the FPM *master* PID changes; after `reload` it does not.

`opcache.enable_cli = 0`, so `php artisan tinker` / CLI always compiles fresh.
Symptom of forgetting the reload: tinker shows the new behaviour but the website /
WordPress plugin still serves the old code. Always reload FPM after editing PHP that
the web app or API serves.
