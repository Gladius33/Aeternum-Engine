#!/usr/bin/env bash
# A verified backup candidate, never a replacement for a test restoration.
# MariaDB client credentials belong in a separate chmod 600 option file.
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "$0")/.." && pwd -P)"
if [[ $# -ne 1 || "$1" != /* || "$(printenv M11_BACKUP_APPROVED || true)" != 'YES' ]]; then
  echo 'Requires absolute private destination and M11_BACKUP_APPROVED=YES' >&2; exit 2
fi
for tool in mariadb-dump gzip sha256sum mktemp stat; do
  command -v "$tool" >/dev/null || { echo "Missing backup command" >&2; exit 1; }
done
CREDS="$(printenv M11_CREDENTIALS_FILE || true)"
DB="$(printenv M11_SOURCE_DATABASE || true)"
[[ "$CREDS" == /* && -f "$CREDS" && ! -L "$CREDS" && "$DB" =~ ^[a-zA-Z][a-zA-Z0-9_]{0,63}$ ]] ||
  { echo 'Invalid private option file or database name' >&2; exit 2; }
mode="$(stat -c '%a' "$CREDS")"
(( (8#$mode & 077) == 0 )) || { echo 'Database option file too permissive' >&2; exit 2; }
mkdir -p -m 700 -- "$1"
DEST="$(cd "$1" && pwd -P)"
[[ "$DEST/" != "$ROOT/public/"* && "$DEST" != "$ROOT/public" ]] ||
  { echo 'Backup inside public document root forbidden' >&2; exit 2; }
WORK="$(mktemp -d "$DEST/.m11-dump.XXXXXXXX")"
trap 'rm -rf -- "$WORK"' EXIT
BASENAME="aeternum-$(date -u +%Y%m%dT%H%M%SZ)-$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n').sql.gz"
# Dump all InnoDB tables in one transaction. Operator must freeze writes and
# DDL before this command: the dump cannot provide a migration-safe snapshot
# during schema changes. No password is passed as a process argument.
mariadb-dump --defaults-extra-file="$CREDS" --single-transaction \
  --quick --skip-lock-tables --no-tablespaces --hex-blob \
  "$DB" | gzip -n -9 > "$WORK/$BASENAME"
test -s "$WORK/$BASENAME"
gzip -t "$WORK/$BASENAME"
mv -- "$WORK/$BASENAME" "$DEST/$BASENAME"
(cd "$DEST" && sha256sum "$BASENAME" > "$BASENAME.sha256")
printf '{"archive":"%s","sha256":"%s","validatedGzip":true,"restoreDrillRequired":true}\n' \
  "$BASENAME" "$(sha256sum "$DEST/$BASENAME" | cut -d' ' -f1)"
