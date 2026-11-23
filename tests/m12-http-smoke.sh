#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/.."
tmp="$(mktemp -d)"
port=18462
server_pid=""
cleanup() {
  if [[ -n "$server_pid" ]]; then kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; fi
  rm -rf "$tmp"
}
trap cleanup EXIT
php -S "127.0.0.1:$port" -t public public/index.php >"$tmp/server.log" 2>&1 &
server_pid=$!
ready=0
for i in $(seq 1 40); do
  if curl -sS --max-time 1 "http://127.0.0.1:$port/v1/auth/login" -o /dev/null 2>/dev/null; then ready=1; break; fi
  if ! kill -0 "$server_pid" 2>/dev/null; then cat "$tmp/server.log" >&2; exit 1; fi
  sleep 0.25
done
test "$ready" -eq 1
for route in /v1/auth/login /v1/worlds/example/economy/commands /v1/public/worlds; do
  curl -sS --max-time 5 -D "$tmp/headers" -o "$tmp/body" -X POST "http://127.0.0.1:$port$route"
  status="$(head -n 1 "$tmp/headers")"
  [[ "$status" == *" 403 "* ]] || { echo "Expected HTTP 403: $status" >&2; exit 1; }
  grep -Fxq '{"error":"https_required"}' "$tmp/body"
  grep -Eiq '^cache-control: no-store, private' "$tmp/headers"
  grep -Eiq '^x-content-type-options: nosniff' "$tmp/headers"
  grep -Eiq '^content-security-policy: default-src .none.' "$tmp/headers"
  grep -Eiq '^cross-origin-resource-policy: same-origin' "$tmp/headers"
  if grep -Eiq '^strict-transport-security:' "$tmp/headers"; then
    echo "Cleartext response must not advertise HSTS" >&2; exit 1
  fi
done
echo 'PASS M12 live plaintext-denial and security header smoke'
