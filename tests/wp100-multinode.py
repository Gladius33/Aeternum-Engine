#!/usr/bin/env python3
"""Bounded two-process regression against a disposable shared MariaDB only."""
import concurrent.futures
import json
import os
import threading
import urllib.request
import urllib.error
import time

assert os.environ.get("WP_TEST_ISOLATED") == "YES"
assert "dbname=wp_test_multinode;" in os.environ["WP_DB_DSN"]
ports = (18731, 18732)
def req(node, method, path, body=None, token=None, extra=None):
    headers = {"Content-Type": "application/json", **(extra or {})}
    if token: headers["Authorization"] = "Bearer " + token
    data = None if body is None else json.dumps(body).encode()
    request = urllib.request.Request(f"http://127.0.0.1:{ports[node]}/api/v1{path}", data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(request, timeout=30) as r:
            return r.status, json.load(r)
    except urllib.error.HTTPError as r:
        return r.code, json.load(r)

def simultaneous(fn):
    gate = threading.Barrier(2)
    def one(node):
        gate.wait(timeout=10)
        return fn(node)
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        return list(pool.map(one, [0, 1]))

password = "test-two-node-password"
account = {"username":"multi", "password":password, "invite":os.environ["WP_ALPHA_INVITE"]}
assert req(0,"POST","/accounts",account)[0] == 201
status, auth = req(0,"POST","/sessions",{"username":"multi","password":password})
assert status == 200
token = auth["access_token"]
assert req(1,"GET","/me",token=token)[0] == 200
# Concurrent retry of one create command must produce one world and one receipt.
body = {"label":"Two-node fixture","scenario":"fr-sandbox-v1"}
extra = {"Idempotency-Key":"wp100-two-node-create"}
created = simultaneous(lambda n: req(n,"POST","/worlds",body,token,extra))
assert all(r[0] == 201 for r in created), [r[0] for r in created]
assert created[0][1] == created[1][1]
status, worlds = req(1,"GET","/worlds",token=token)
assert status == 200 and len(worlds["worlds"]) == 1
world = created[0][1]["world_id"]
# Limited burst, not a capacity benchmark or production SLA.
start = time.monotonic()
with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
    results = list(pool.map(lambda n: req(n%2,"GET",f"/worlds/{world}/clock",token=token),range(48)))
assert all(s == 200 for s,_ in results)
assert all(r[1] == results[0][1] for r in results)
print(f"48 consistent clock reads in {time.monotonic()-start:.3f}s (CI only)")
# The shared database must serialize recovery, regardless of serving node.
status, rec = req(0,"POST","/me/recovery-code",{"current_password":password},token)
assert status == 201
payload = {"username":"multi","recovery_code":rec["recovery_code"],"new_password":"test-recovered-password"}
raced = simultaneous(lambda n: req(n,"POST","/account-recovery",payload))
assert sorted(r[0] for r in raced) == [200,401], [r[0] for r in raced]
assert all(req(n,"GET","/me",token=token)[0] == 401 for n in (0,1))
assert req(1,"POST","/sessions",{"username":"multi","password":payload["new_password"]})[0] == 200
print("PASS WP100 shared database: idempotent creation, read burst, atomic one-time recovery")
