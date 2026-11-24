#!/usr/bin/env bash
# Build an auditable PHP bundle without shipping tests, dotfiles or secrets.
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "$0")/.." && pwd -P)"
if [[ $# -ne 1 || "$1" != /* ]]; then
  echo 'Usage: tools/m11-package.sh ABSOLUTE_OUTPUT_DIRECTORY' >&2
  exit 2
fi
DEST="$1"
case "$DEST/" in "$ROOT/"*) echo 'Output must be outside source tree' >&2; exit 2;; esac
for cmd in git tar gzip sha256sum mktemp php; do
  command -v "$cmd" >/dev/null || { echo "Missing build dependency: $cmd" >&2; exit 1; }
done
[[ -f "$ROOT/vendor/autoload.php" && -s "$ROOT/public/client-dist/aeternum-shell.js" ]] ||
  { echo 'Build composer autoload and client bundle first' >&2; exit 1; }
SHA="$(git -C "$ROOT" rev-parse HEAD)"
[[ "$SHA" =~ ^[a-f0-9]{40}$ ]] || { echo 'Invalid revision' >&2; exit 1; }
mkdir -p -m 700 "$DEST"
DEST="$(cd "$DEST" && pwd -P)"
[[ "$DEST/" != "$ROOT/"* ]] || { echo 'Output inside source tree' >&2; exit 2; }
WORK="$(mktemp -d "$DEST/.m11-build.XXXXXXXX")"
trap 'rm -rf -- "$WORK"' EXIT
STAGE="$WORK/aeternum"
mkdir -p "$STAGE"
cd "$ROOT"
while IFS= read -r -d '' rel; do
  [[ -f "$rel" && ! -L "$rel" ]] || { echo 'Missing/symlink tracked source' >&2; exit 1; }
  mkdir -p "$STAGE/$(dirname "$rel")"
  cp -- "$rel" "$STAGE/$rel"
done < <(git ls-files -z -- LICENSE.md NOTICE README.md composer.json \
  bin/ packages/core/src/ database/migrations/ games/ schemas/ docs/ \
  tools/m11-backup.sh tools/m11-restore-lab.sh \
  public/.htaccess public/index.php public/play.html public/play.js public/play.css public/locale.js \
  public/world-scene.js public/world-scene.css public/game-ui.js public/game-ui.css public/game-ui-profiles.js public/game-art.js)
mkdir -p "$STAGE/vendor" "$STAGE/public/client-dist"
cp -- "$ROOT/vendor/autoload.php" "$STAGE/vendor/autoload.php"
cp -R -- "$ROOT/vendor/composer" "$STAGE/vendor/composer"
cp -- "$ROOT/public/client-dist/aeternum-shell.js" "$STAGE/public/client-dist/"
if find "$STAGE" -type l -print -quit | grep -q .; then
  echo 'Symlinks forbidden in deployment archive' >&2; exit 1
fi
if find "$STAGE" -type f \( -name '.env' -o -name '.env.*' -o -name '*.pem' \
  -o -name '*.key' -o -name '*.sql.gz' -o -name '*.dump' \) -print -quit | grep -q .; then
  echo 'Secret or backup artifact in release bundle' >&2; exit 1
fi
test ! -d "$STAGE/tests"
test ! -d "$STAGE/.github"
test ! -d "$STAGE/client"
php -l "$STAGE/public/index.php" >/dev/null
php -r 'require $argv[1]; if (!class_exists("Aeternum\\Core\\OperationsCron")) exit(1);' \
  "$STAGE/vendor/autoload.php"
STAMP="$(printenv SOURCE_DATE_EPOCH || echo 0)"
[[ "$STAMP" =~ ^[0-9]{1,12}$ ]] || { echo 'Invalid SOURCE_DATE_EPOCH' >&2; exit 2; }
ARCHIVE="aeternum-$(printf '%s' "$SHA" | cut -c1-12).tar.gz"
tar -C "$WORK" --sort=name --owner=0 --group=0 --numeric-owner \
  --mtime="@$STAMP" -cf - aeternum | gzip -n -9 > "$WORK/$ARCHIVE"
test -s "$WORK/$ARCHIVE"
mv -- "$WORK/$ARCHIVE" "$DEST/$ARCHIVE"
(cd "$DEST" && sha256sum "$ARCHIVE" > "$ARCHIVE.sha256")
printf '{"artifact":"%s","sha256":"%s","sourceSha":"%s","scope":"private-offline-package"}\n' \
  "$ARCHIVE" "$(sha256sum "$DEST/$ARCHIVE" | cut -d' ' -f1)" "$SHA"
