#!/usr/bin/env bash
set -euo pipefail
# Only tracked game source, tests and documentation from an exact commit.
# This is a source handoff bundle, not an installable production release.
if [[ $# -ne 2 || ! "$1" =~ ^[a-f0-9]{40}$ ]]; then
  echo "Usage: package-alpha.sh COMMIT_SHA OUTPUT.tar.gz" >&2
  exit 2
fi
sha=$1
out=$(realpath -m "$2")
[[ ! -e "$out" ]] || { echo "Output already exists" >&2; exit 2; }
root=$(git rev-parse --show-toplevel)
cd "$root"
[[ $(git rev-parse "$sha^{commit}") == "$sha" ]]
mapfile -t selected < <(git ls-tree -r --name-only "$sha" -- games/world-politics docs tests LICENSE.md NOTICE WORLD_POLITICS_BRANCH_ONLY.md | grep -vE '^games/world-politics/data/wp115-(collection-v0\\.4(/|\\.zip$)|fiscal-staging-v1\\.json$)' | awk '/^games\/world-politics\// || /^docs\/WORLD_POLITICS.*\.md$/ || /^tests\/wp.*\.(php|py)$/ || /^(LICENSE\.md|NOTICE|WORLD_POLITICS_BRANCH_ONLY\.md)$/')
(("${#selected[@]}" > 10))
tmp=$(mktemp "${out}.XXXXXX")
raw=""
trap 'rm -f -- "$tmp" ${raw:+"$raw"}' EXIT
raw=$(mktemp "${out}.raw.XXXXXX")
git archive --format=tar --prefix=world-politics/ "$sha" -- "${selected[@]}" > "$raw"
[[ $(git get-tar-commit-id < "$raw") == "$sha" ]]
gzip -n -c -- "$raw" > "$tmp"
# Refuse to replace an existing artifact, even if created concurrently.
ln -- "$tmp" "$out"
sha256sum -- "$out"
