#!/usr/bin/env bash
# Fail-closed restoration into a PRECREATED, EMPTY, explicitly disposable
# schema. Never drops a database and never targets a production DB by name.
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "$0")/.." && pwd -P)"
if [[ $# -ne 1 || "$1" != /* ||
  "$(printenv M11_RESTORE_DISPOSABLE || true)" != 'YES' ]]; then
  echo 'Explicit disposable restore acknowledgement and absolute archive required' >&2
  exit 2
fi
for tool in mariadb gzip sha256sum stat; do
  command -v "$tool" >/dev/null || { echo 'Missing restore command' >&2; exit 1; }
done
CREDS="$(printenv M11_CREDENTIALS_FILE || true)"
DB="$(printenv M11_RESTORE_DATABASE || true)"
[[ "$CREDS" == /* && -f "$CREDS" && ! -L "$CREDS" &&
  "$DB" =~ ^aeternum_restore(_[a-z0-9]{1,32})?$ ]] ||
  { echo 'Unsafe destination schema or credentials' >&2; exit 2; }
CREDS="$(realpath "$CREDS")"
[[ "$CREDS" != "$ROOT/public/"* ]] || { echo 'Public credential file forbidden' >&2; exit 2; }
mode="$(stat -c '%a' "$CREDS")"
(( (8#$mode & 077) == 0 )) || { echo 'Database option file too permissive' >&2; exit 2; }
ARCHIVE="$1"
[[ -f "$ARCHIVE" && ! -L "$ARCHIVE" && "$ARCHIVE" == *.sql.gz ]] ||
  { echo 'Invalid restore archive' >&2; exit 2; }
ARCHIVE="$(realpath "$ARCHIVE")"
[[ "$ARCHIVE" != "$ROOT/public/"* ]] || { echo 'Public backup forbidden' >&2; exit 2; }
# Bind the checksum sidecar to the EXACT archive requested. A checksum
# file naming another valid dump must not authorize importing this archive.
SIDECAR="$ARCHIVE.sha256"
[[ -f "$SIDECAR" && ! -L "$SIDECAR" ]] ||
  { echo 'Missing or unsafe checksum sidecar' >&2; exit 2; }
EXPECTED_NAME="$(basename "$ARCHIVE")"
EXPECTED_LINE="$(cat -- "$SIDECAR")"
EXPECTED_HASH="${EXPECTED_LINE:0:64}"
[[ "$EXPECTED_HASH" =~ ^[a-fA-F0-9]{64}$ &&
   "${EXPECTED_LINE:64}" == "  $EXPECTED_NAME" ]] ||
  { echo 'Checksum sidecar does not name the requested archive' >&2; exit 2; }
ACTUAL_HASH="$(sha256sum -- "$ARCHIVE")"
[[ "${ACTUAL_HASH:0:64}" == "$EXPECTED_HASH" ]] ||
  { echo 'Archive checksum mismatch' >&2; exit 2; }
gzip -t "$ARCHIVE"
# SHOW TABLES is an actual DB check: a nonempty target or missing target must
# fail rather than overwriting anything. The restricted DB name is never
# interpolated into SQL.
tables="$(mariadb --defaults-extra-file="$CREDS" --batch --skip-column-names \
  --database="$DB" --execute='SHOW TABLES')"
[[ -z "$tables" ]] || { echo 'Restore target is NOT empty' >&2; exit 3; }
gzip -cd -- "$ARCHIVE" | mariadb --defaults-extra-file="$CREDS" --database="$DB"
# The caller must separately verify source-vs-restored row fingerprints and
# journal replay. Partial import after a failure requires discarding the lab.
count="$(mariadb --defaults-extra-file="$CREDS" --batch --skip-column-names \
  --database="$DB" --execute='SELECT COUNT(*) FROM engine_schema_migrations WHERE completed=1')"
[[ "$count" =~ ^[1-9][0-9]*$ ]] || { echo 'Restored migration ledger missing' >&2; exit 1; }
printf '{"disposableRestore":true,"completedMigrations":%s,"sourceFingerprintStillRequired":true}\n' "$count"
