#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd -P)"
OUT="$(mktemp -d)"
trap 'rm -rf -- "$OUT"' EXIT
bash "$ROOT/tools/m11-package.sh" "$OUT" > "$OUT/build.json"
php -r '$x=json_decode(file_get_contents($argv[1]),true,8,JSON_THROW_ON_ERROR);
if (!preg_match("/^aeternum-[a-f0-9]{12}\.tar\.gz$/",$x["artifact"]) ||
    !preg_match("/^[a-f0-9]{64}$/",$x["sha256"]) ||
    !preg_match("/^[a-f0-9]{40}$/",$x["sourceSha"])) exit(1);' "$OUT/build.json"
archive="$(find "$OUT" -maxdepth 1 -type f -name '*.tar.gz' -print -quit)"
(cd "$OUT" && sha256sum -c "$(basename "$archive").sha256")
tar -tzf "$archive" > "$OUT/files.txt"
grep -qx 'aeternum/public/index.php' "$OUT/files.txt"
grep -qx 'aeternum/public/.htaccess' "$OUT/files.txt"
grep -qx 'aeternum/public/client-dist/aeternum-shell.js' "$OUT/files.txt"
grep -qx 'aeternum/bin/ops-cron.php' "$OUT/files.txt"
grep -qx 'aeternum/bin/ops-alerts.php' "$OUT/files.txt"
grep -qx 'aeternum/tools/m11-backup.sh' "$OUT/files.txt"
grep -qx 'aeternum/tools/m11-restore-lab.sh' "$OUT/files.txt"
grep -qx 'aeternum/database/migrations/031_admin_test_entitlements.sql' "$OUT/files.txt"
grep -qx 'aeternum/LICENSE.md' "$OUT/files.txt"
if grep -Eq '^aeternum/(games/world-politics/|docs/WORLD_POLITICS[^/]*\.md$)' "$OUT/files.txt"; then
  echo 'World Politics files belong in the separate game archive' >&2; exit 1
fi
if grep -E '(^|/)(tests|\.git|\.github|node_modules|client)/|(^|/)\.env|\.pem$|\.key$|\.sql\.gz$' "$OUT/files.txt"; then
  echo 'Forbidden path in package' >&2; exit 1
fi
mkdir "$OUT/unpacked"
tar -xzf "$archive" -C "$OUT/unpacked"
test ! -e "$OUT/unpacked/aeternum/.env"
test ! -e "$OUT/unpacked/aeternum/tests"
php -l "$OUT/unpacked/aeternum/public/index.php" >/dev/null
php -r 'require $argv[1]; if (!class_exists("Aeternum\\Core\\OperationsSnapshot")) exit(1);' \
  "$OUT/unpacked/aeternum/vendor/autoload.php"
first="$(sha256sum "$archive" | cut -d' ' -f1)"
bash "$ROOT/tools/m11-package.sh" "$OUT" > "$OUT/build2.json"
second="$(sha256sum "$archive" | cut -d' ' -f1)"
test "$first" = "$second"
echo 'PASS M11 deterministic installable PHP/client archive, checksum, and no dev/secret files'
