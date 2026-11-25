#!/usr/bin/env bash
# Verify the complete extracted file set against the internal inventory.
# First authenticate the original archive hash through a trusted channel.
set -Eeuo pipefail
umask 077
[[ $# -eq 1 && "$1" == /* && -d "$1" && ! -L "$1" ]] || { echo 'Expected absolute extracted package directory' >&2; exit 2; }
ROOT="$(cd "$1" && pwd -P)"
[[ -f "$ROOT/PACKAGE.SHA256" && ! -L "$ROOT/PACKAGE.SHA256" && -f "$ROOT/PACKAGE.json" ]] || exit 2
[[ -z "$(find "$ROOT" ! -type f ! -type d -print -quit)" ]] || { echo 'Unexpected special file or symlink' >&2; exit 1; }
WORK="$(mktemp -d)"
trap 'rm -rf -- "$WORK"' EXIT
# Reject unexpected file names (including paths with newline), secrets and
# development trees before evaluating manifest bytes. Never follow symlinks.
cd "$ROOT"
while IFS= read -r -d '' path; do
    [[ "$path" =~ ^\./[a-zA-Z0-9_./+-]+$ ]] || { echo 'Unsafe package path' >&2; exit 1; }
    case "$path" in
      */tests/*|*/.git/*|*/.github/*|*/node_modules/*|./client/*|*/.env*|*.pem|*.key|*.sql.gz|*.dump)
        echo 'Forbidden file in package' >&2; exit 1;;
    esac
done < <(find . -type f -print0)
# Generate the expected full manifest from current files, including metadata.
# A malicious manifest cannot cause sha256sum to open paths outside this root.
find . -type f ! -path './PACKAGE.SHA256' -print0 | LC_ALL=C sort -z | xargs -0 sha256sum > "$WORK/actual"
cmp -s "$WORK/actual" PACKAGE.SHA256 || { echo 'Package contents or file inventory differ' >&2; exit 1; }
php -r '
  $p=json_decode(file_get_contents("PACKAGE.json"),true,8,JSON_THROW_ON_ERROR);
  if(($p["format"]??null)!==1||!preg_match("/^[a-f0-9]{40}$/D",$p["sourceSha"]??"")
     ||!preg_match("/^[a-f0-9]{64}$/D",$p["clientLockSha256"]??"")||($p["scope"]??null)!=="private-offline-package")exit(1);
'
test -s LICENSE.md;test -s NOTICE;test -s public/client-dist/aeternum-shell.js
# Known disposable lab credentials must not escape in docs, scripts or assets.
if grep -rIlE 'aeternum-local-playtest[-]only|local-lab-db[-]only|local-lab-root[-]only' . | grep -q .; then
    echo 'Disposable lab credential in package' >&2; exit 1
fi
printf '{"verified":true,"files":%s}\n' "$(wc -l < "$WORK/actual" | tr -d ' ')"
