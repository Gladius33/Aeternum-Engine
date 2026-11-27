#!/usr/bin/env python3
"""Reconcile the additional WDI edition with exact source responses."""
import hashlib
import json
import math
from pathlib import Path
import importlib.util

BASE=Path(__file__).resolve().parents[1]/"games/world-politics"
spec=importlib.util.spec_from_file_location("wp115collector",BASE/"bin/collect-wp115-wdi-economic-social.py")
m=importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
ROOT=BASE/"data/wp115-wdi-economic-social-2026-11-27-v2"
def check(ok,msg):
    if not ok: raise ValueError(msg)
def load(p): return json.loads(p.read_bytes())
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
manifest=load(ROOT/"manifest.json")
ev=load(ROOT/"evidence.json")
check(set(ev["series"])==set(m.SERIES) and not ev["transport_or_validation_errors"],"source series")
check(ev["country_iso2"]==sorted(m.ISO) and ev["years"]==list(m.YEARS) and ev["runtime_admission"] is False,"coverage")
files={"evidence.json"}
count=0
for code,s in ev["series"].items():
    check(s["indicator"]==code and s["source_url"]=="https://data.worldbank.org/indicator/"+code,"source")
    pages=[]; metadata=False
    for f in s["raw_files"]:
        name=f["file"]
        check(name.startswith("raw/"+code+"-") and name not in files and "/" not in name[4:],"file")
        files.add(name)
        p=ROOT/name
        check(sha(p)==f["sha256"] and f["url"].startswith("https://api.worldbank.org/"),"source digest")
        doc=load(p)
        if name.endswith("-metadata.json"):
            check(doc[1][0]["id"]==code,"metadata")
            metadata=True
        else: pages.append(doc)
    check(metadata and pages,"source metadata and pages")
    vintage,rows=m.normalize(code,pages)
    check(vintage==s["source_vintage"] and rows==s["rows"],"raw reconciliation "+code)
    count+=sum(r["value"] is not None for r in rows)
check(set(manifest["files"])==files and {p.relative_to(ROOT).as_posix() for p in ROOT.rglob("*") if p.is_file()}==files|{"manifest.json"},"manifest membership")
for name,digest in manifest["files"].items(): check(sha(ROOT/name)==digest,"manifest digest")
check(count==manifest["non_null"]==2112 and manifest["numeric_slots"]==3528 and manifest["series"]==28,"counts")
print("PASS WP115: 3528 additional source slots reconciled, 2112 non-null, 1416 null")
