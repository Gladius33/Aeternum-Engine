"""Bounded real HTTP concurrency regression in the isolated CI database only."""
import concurrent.futures
import json
import os
import subprocess
import urllib.error
import urllib.request
import uuid

if os.environ.get("WP_RESEARCH_TEST") != "YES" or "dbname=wp_research_test;" not in os.environ.get("WP_RESEARCH_DB_DSN", ""):
    raise SystemExit("Isolated Research test required")

OP = ["php", "games/world-politics/research/operator.php"]


def operator(*args):
    return json.loads(subprocess.check_output(OP + list(map(str, args)), timeout=20))


def request(port, token, path, data=None, key=None):
    headers = {"Authorization": "Bearer " + token}
    if data is not None:
        headers["Content-Type"] = "application/json"
    if key:
        headers["Idempotency-Key"] = key
    req = urllib.request.Request(
        f"http://127.0.0.1:{port}/research/v1{path}",
        headers=headers,
        data=None if data is None else json.dumps(data).encode(),
    )
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read())


tenant = operator("create-tenant", "standard", 30, "private", "payg")
operator("topup", tenant["tenant_id"], "ci-" + uuid.uuid4().hex, 100)
token = tenant["token"]
ports = (18731, 18732)
operator("set-capacity", "standard", 86400, 4, 30000)
try:
    with concurrent.futures.ThreadPoolExecutor(max_workers=10) as pool:
        statuses = list(pool.map(lambda i: request(ports[i % 2], token, "/models")[0], range(10)))
    assert statuses.count(200) == 4 and statuses.count(429) == 6, statuses
finally:
    operator("set-capacity", "standard", 86400, 5000, 30000)
status, usage = request(18731, token, "/usage")
assert status == 200 and usage["prepaid_remaining"] == 96, usage
assert usage["lifetime_prepaid_used"] == 4, usage

# Same idempotency key on both nodes: two transport units, one compute unit.
payload = {"type": "countries", "parameters": {}}
key = "ci-job-" + uuid.uuid4().hex
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
    result = list(pool.map(lambda port: request(port, token, "/experiments", payload, key), ports))
assert result[0] == result[1] and result[0][0] == 202, result
status, usage = request(18732, token, "/usage")
assert status == 200 and usage["prepaid_remaining"] == 84, usage
assert usage["lifetime_prepaid_used"] == 16, usage
print("PASS WP123 two-node shared Research ceilings, atomic prepaid debits and idempotent compute")
