#!/bin/sh
set -eu
cd /opt/aeternum
test "${AETERNUM_LAB_ONLY:-}" = 1
test "$AETERNUM_DSN" = 'mysql:host=db;port=3306;dbname=aeternum_lab;charset=utf8mb4'
php bin/migrate.php
for pack in medieval industrial guild; do
    for player in alice bob; do
        printf '%s\n' "$AETERNUM_LAB_PLAYER_PASSWORD" | php bin/provision.php "reference-$pack" "lab-$pack" "$pack-$player" >/dev/null
    done
done
php lab/bots.php init
echo 'Six human players and thirty bots ready.'
