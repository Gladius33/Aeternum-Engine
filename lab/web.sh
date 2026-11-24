#!/bin/sh
set -eu
test "${AETERNUM_LAB_ONLY:-}" = 1
test "$AETERNUM_DSN" = 'mysql:host=db;port=3306;dbname=aeternum_lab;charset=utf8mb4'
# Private, per-container key generated at start, never stored in the image.
umask 077
if [ ! -s /etc/aeternum-lab/tls.key ] || [ ! -s /etc/aeternum-lab/tls.crt ]; then
    openssl req -x509 -newkey rsa:2048 -nodes -days 30 -subj /CN=localhost -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1' -keyout /etc/aeternum-lab/tls.key -out /etc/aeternum-lab/tls.crt
fi
exec apache2-foreground
