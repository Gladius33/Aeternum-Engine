#!/usr/bin/env python3
"""Bounded real HTTP load against an explicitly disposable local MariaDB."""
import concurrent.futures as cf
import json
import math
import os
import pathlib
import platform
import signal
import socket
import subprocess
import tempfile
import threading
import time
import urllib.error
import urllib.request
from collections import defaultdict

ROOT = pathlib.Path(__file__).resolve().parents[1]
BUDGETS = {"p95_ms": 12000, "p99_ms": 20000, "profile_seconds": 180,
           "worker_lag_seconds": 10, "errors": 0, "request_timeout_seconds": 25}
PROFILES = (1, 4, 16, 32)
WORLD = "/v1/worlds/f02-load-world"
SERVER_WORKERS = 8
lock = threading.Lock()


def php(mode, directory):
    result = subprocess.run(["php", "tests/f02-load.php", mode, directory],
                            cwd=ROOT, capture_output=True, text=True, timeout=240)
    if result.returncode:
        raise RuntimeError("F02 PHP harness failed: " + mode + " (exit " + str(result.returncode) + ")")
    return json.loads(result.stdout)


def system_sample():
    fields = pathlib.Path("/proc/stat").read_text().splitlines()[0].split()[1:]
    cpu = list(map(int, fields))
    mem = {}
    for row in pathlib.Path("/proc/meminfo").read_text().splitlines():
        name, value = row.split(":", 1)
        if name in ("MemTotal", "MemAvailable"):
            mem[name] = int(value.split()[0])
    return {"cpu_ticks": sum(cpu), "idle_ticks": cpu[3] + cpu[4],
            "used_memory_kib": mem["MemTotal"] - mem["MemAvailable"]}


def percentiles(values):
    values = sorted(values)
    return {f"p{p}_ms": round(values[max(0, math.ceil(len(values)*p/100)-1)], 3)
            for p in (50, 95, 99)}


def main():
    if os.environ.get("AETERNUM_F02_DISPOSABLE") != "1":
        raise SystemExit("F02 requires explicit disposable database")
    report = {"scope": "isolated CI HTTP and MariaDB", "budgets": BUDGETS,
              "server_workers": SERVER_WORKERS, "runner": {
                  "os": platform.platform(), "logical_cpus": os.cpu_count(),
                  "source_sha": os.environ.get("GITHUB_SHA", "local")}, "profiles": []}
    with tempfile.TemporaryDirectory(prefix="aeternum-f02-") as d:
        report["dataset"] = php("setup", d)
        players = json.loads((pathlib.Path(d) / "players.json").read_text())
        env = dict(os.environ, AETERNUM_DSN=os.environ["AETERNUM_TEST_DSN"],
                   AETERNUM_DB_USER=os.environ["AETERNUM_TEST_USER"],
                   AETERNUM_DB_PASSWORD=os.environ["AETERNUM_TEST_PASSWORD"],
                   AETERNUM_WORLD_REGISTRY=str(pathlib.Path(d) / "worlds.json"),
                   AETERNUM_BROWSER_TEST="1", AETERNUM_TRUSTED_PROXY_IPS="127.0.0.1",
                   PHP_CLI_SERVER_WORKERS=str(SERVER_WORKERS))
        env.pop("AETERNUM_MODULE_REGISTRY", None)
        env.pop("AETERNUM_MEDIEVAL_WORLD", None)
        env.pop("AETERNUM_INDUSTRIAL_WORLD", None)
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        url = f"http://127.0.0.1:{port}"
        with open(pathlib.Path(d) / "server.log", "w") as log:
            server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "-t", "public",
                                       "tests/browser/router.php"], cwd=ROOT, env=env,
                                      stdout=log, stderr=log, start_new_session=True)
            try:
                for _ in range(100):
                    try:
                        with socket.create_connection(("127.0.0.1", port), timeout=.2):
                            break
                    except OSError:
                        if server.poll() is not None:
                            raise RuntimeError("HTTP server exited")
                        time.sleep(.1)
                else:
                    raise RuntimeError("HTTP server unavailable")
                last_tokens = {}
                for n in PROFILES:
                    samples = defaultdict(list)
                    retries = defaultdict(int)
                    status_counts = defaultdict(int)
                    start = time.monotonic()
                    sys0 = system_sample()
                    db0 = php("metrics", d)
                    peak_mem = [sys0["used_memory_kib"]]
                    stop_sample = threading.Event()
                    def monitor():
                        while not stop_sample.wait(.2):
                            peak_mem[0] = max(peak_mem[0], system_sample()["used_memory_kib"])
                    sampler = threading.Thread(target=monitor, daemon=True)
                    sampler.start()

                    def call(i, label, path, payload=None, token=None, retry_due=False):
                        began = time.monotonic()
                        # Synthetic distinct peers, only on the explicit loopback CI ingress.
                        headers = {"X-Forwarded-For": f"192.0.2.{i+1}"}
                        if token:
                            headers["Authorization"] = "Bearer " + token
                        if payload is not None:
                            headers["Content-Type"] = "application/json"
                        body = None if payload is None else json.dumps(payload).encode()
                        for attempt in range(10):
                            req = urllib.request.Request(url+path, data=body, headers=headers)
                            try:
                                with urllib.request.urlopen(req, timeout=BUDGETS["request_timeout_seconds"]) as r:
                                    code, data = r.status, json.load(r)
                            except urllib.error.HTTPError as e:
                                code, data = e.code, json.load(e)
                            with lock:
                                status_counts[str(code)] += 1
                            if code == 200:
                                with lock:
                                    samples[label].append((time.monotonic()-began)*1000)
                                return data["data"]
                            if retry_due and code == 409 and data.get("retryable") is True:
                                with lock:
                                    retries[label] += 1
                                time.sleep(.15*(attempt+1))
                                continue
                            raise RuntimeError(f"{label}: unexpected HTTP {code}")
                        raise RuntimeError(label+": bounded retries exhausted")

                    def command(i, token, kind, suffix, payload, retry=False):
                        return call(i, kind, WORLD+"/economy/commands",
                                    {"id": f"p{n}-{i}-{suffix}", "type": kind, "payload": payload},
                                    token, retry)
                    def login(i):
                        p = players[i]
                        return call(i, "login", "/v1/auth/login",
                                    {"login": p["login"], "password": p["password"]})["token"]
                    def mixed(i):
                        a, tok = players[i]["account"], tokens[i]
                        rank = call(i, "rank", WORLD+"/progression/"+a+"/rank", token=tok)
                        if rank["participants"] != 33:
                            raise RuntimeError("rank participant mismatch")
                        call(i, "balance", WORLD+"/economy/balances/"+a+"/silver", token=tok)
                        call(i, "market.read", WORLD+"/economy/market", token=tok)
                        call(i, "social", WORLD+"/social/alliances", token=tok)
                        command(i, tok, "market.create", "offer",
                                {"offer": f"p{n}-offer-{i}", "seller": a, "giveResource": "spice",
                                 "giveUnits": 1, "wantResource": "silver", "wantUnits": 1})
                        command(i, tok, "building.place", "place",
                                {"building": f"p{n}-tower-{i}", "account": a, "type": "watchtower",
                                 "territory": a, "entity": a})
                    def buy(i):
                        a, tok = players[i]["account"], tokens[i]
                        seller = 32 if n == 1 else (i+1) % n
                        offer = "solo-partner" if n == 1 else f"p{n}-offer-{seller}"
                        payload = {"offer": offer, "seller": players[seller]["account"], "buyer": a}
                        first = command(i, tok, "market.accept", "buy", payload)
                        retry = command(i, tok, "market.accept", "buy", payload)
                        if first != retry:
                            raise RuntimeError("receipt retry mismatch")
                        call(i, "score", WORLD+"/progression/"+a, token=tok)
                    def build(i):
                        command(i, tokens[i], "building.start", "build",
                                {"building": f"p{n}-tower-{i}", "account": players[i]["account"],
                                 "type": "watchtower"}, True)
                    worker = None
                    stop_path = pathlib.Path(d) / "stop-worker"
                    stop_path.unlink(missing_ok=True)
                    try:
                        with cf.ThreadPoolExecutor(max_workers=n) as pool:
                            tokens = list(pool.map(login, range(n)))
                            last_tokens = dict(enumerate(tokens))
                            list(pool.map(mixed, range(n)))
                            list(pool.map(buy, range(n)))
                            worker = subprocess.Popen(["php", "tests/f02-load.php", "worker", d],
                                                      cwd=ROOT, stdout=subprocess.PIPE,
                                                      stderr=subprocess.PIPE, text=True)
                            list(pool.map(build, range(n)))
                            time.sleep(2.5)
                        stop_path.touch()
                        out, err = worker.communicate(timeout=15)
                        if worker.returncode:
                            raise RuntimeError("due worker failed")
                        worker_metrics = json.loads(out)
                        if worker_metrics["processed"] != n:
                            raise RuntimeError("worker did not drain complete profile")
                    finally:
                        stop_sample.set()
                        sampler.join()
                        if worker is not None and worker.poll() is None:
                            stop_path.touch()
                            try:
                                worker.communicate(timeout=10)
                            except subprocess.TimeoutExpired:
                                worker.kill()
                                worker.communicate()
                    db1 = php("metrics", d)
                    sys1 = system_sample()
                    elapsed = time.monotonic()-start
                    all_samples = [x for vals in samples.values() for x in vals]
                    stats = percentiles(all_samples)
                    report["profiles"].append({"clients": n, "operations": len(all_samples),
                        "wall_seconds": round(elapsed, 3), "operations_per_second": round(len(all_samples)/elapsed, 3),
                        **stats, "by_operation": {k: {"count": len(v), **percentiles(v)} for k,v in sorted(samples.items())},
                        "http_statuses": dict(status_counts), "due_retries": dict(retries), "errors": 0,
                        "cpu_busy_percent": round(100*(1-(sys1["idle_ticks"]-sys0["idle_ticks"])/max(1,sys1["cpu_ticks"]-sys0["cpu_ticks"])), 2),
                        "runner_peak_used_memory_kib": peak_mem[0],
                        "sql_counter_deltas": {k: db1[k]-db0.get(k,0) for k in db1 if k.startswith("Innodb_")},
                        "sql_connections_after": db1.get("Threads_connected"),
                        "sql_max_used_connections": db1.get("Max_used_connections"), "worker": worker_metrics})
                    if (stats["p95_ms"] > BUDGETS["p95_ms"] or stats["p99_ms"] > BUDGETS["p99_ms"]
                        or elapsed > BUDGETS["profile_seconds"]
                        or worker_metrics["max_observed_due_lag_seconds"] > BUDGETS["worker_lag_seconds"]):
                        raise RuntimeError(f"profile {n} exceeds predeclared regression budget")
                # Deliberately saturate one actor's private rank quota. The
                # other actor remains available; the first recovers next window.
                def rank_status(i):
                    req = urllib.request.Request(url+WORLD+"/progression/"+players[i]["account"]+"/rank",
                                                 headers={"Authorization": "Bearer "+last_tokens[i]})
                    try:
                        with urllib.request.urlopen(req, timeout=25) as r:
                            return r.status
                    except urllib.error.HTTPError as e:
                        return e.code
                if int(time.time()) % 60 > 54:
                    time.sleep(61-int(time.time())%60)
                quota = [rank_status(0) for _ in range(14)]
                if 429 not in quota or any(x not in (200,429) for x in quota) or rank_status(1)!=200:
                    raise RuntimeError("rank overload isolation failed")
                time.sleep(61-int(time.time())%60)
                if rank_status(0)!=200:
                    raise RuntimeError("rank quota failed to recover")
                report["overload"] = {"observed_429": quota.count(429), "other_actor_available": True,
                                      "same_actor_recovered_next_window": True}
                report["verification"] = php("verify", d)
            finally:
                try:
                    os.killpg(server.pid, signal.SIGTERM)
                except ProcessLookupError:
                    pass
                try:
                    server.wait(timeout=10)
                except subprocess.TimeoutExpired:
                    os.killpg(server.pid, signal.SIGKILL)
                    server.wait()
    return report


if __name__ == "__main__":
    result = main()
    target = pathlib.Path(os.environ.get("F02_REPORT_PATH", "f02-capacity.json"))
    target.write_text(json.dumps(result, indent=2, sort_keys=True)+"\n")
    print("F02_CAPACITY_JSON "+json.dumps(result, separators=(",",":")))
