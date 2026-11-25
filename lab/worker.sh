#!/bin/sh
set -eu
cd /opt/aeternum
test "${AETERNUM_LAB_ONLY:-}" = 1
test "$AETERNUM_DSN" = 'mysql:host=db;port=3306;dbname=aeternum_lab;charset=utf8mb4'
while :; do
    for pack in medieval industrial guild; do
        php bin/worker.php "reference-$pack" "lab-$pack-f04"
    done
    sleep 2
done
