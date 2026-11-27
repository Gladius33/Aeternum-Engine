#!/usr/bin/env python3
"""Verify the exact WP115 WDI collection and freeze it as a new data edition."""
import hashlib
import json
import math
import re
import sys
from pathlib import Path
import zipfile

EXPECTED = "d270193afb6891c842de9696d0a949f79960b31f46374e54f6389405a2b8eb76"
COUNTRIES = dict(x.split(":") for x in "AT:AUT AU:AUS BE:BEL BG:BGR BR:BRA CA:CAN CH:CHE CN:CHN CY:CYP CZ:CZE DE:DEU DK:DNK EE:EST ES:ESP FI:FIN GB:GBR GR:GRC HR:HRV HU:HUN ID:IDN IE:IRL IN:IND IT:ITA JP:JPN KR:KOR LT:LTU LU:LUX LV:LVA MT:MLT MX:MEX NL:NLD PL:POL PT:PRT RO:ROU RU:RUS SA:SAU SE:SWE SI:SVN SK:SVK TR:TUR US:USA ZA:ZAF".split())
CODES = set("EG.IMP.CONS.ZS FI.RES.TOTL.CD MS.MIL.XPND.GD.ZS NE.CON.PRVT.ZS NE.EXP.GNFS.ZS NE.IMP.GNFS.ZS NV.AGR.TOTL.ZS NV.IND.MANF.ZS NV.IND.TOTL.ZS NV.SRV.TOTL.ZS PA.NUS.FCRF SE.XPD.TOTL.GD.ZS SH.XPD.CHEX.GD.ZS SH.XPD.GHED.GD.ZS SL.AGR.EMPL.ZS SL.IND.EMPL.ZS SL.SRV.EMPL.ZS SL.TLF.ACTI.ZS SM.POP.NETM SP.DYN.TFRT.IN SP.POP.0014.TO.ZS SP.POP.1564.TO.ZS SP.POP.65UP.TO.ZS SP.URB.TOTL.IN.ZS".split())
REV = {v:k for k,v in COUNTRIES.items()}
YEARS = [2023,2024,2025]

def check(ok,msg):
    if not ok:
        raise ValueError(msg)

def sha(b):
    return hashlib.sha256(b).hexdigest()

def read_json(b):
    return json.loads(b)

def validate(evidence, read):
    check(evidence["schema"]=="wp115-wdi-evidence-v1", "schema")
    check(evidence["country_iso2"]==sorted(COUNTRIES) and evidence["years"]==YEARS,"coverage")
    check(evidence["runtime_admission"] is False and not evidence["transport_or_validation_errors"], "classification")
    check(set(evidence["series"])==CODES,"indicator set")
    count=0
    files=set()
    for code,s in evidence["series"].items():
        check(s["indicator"]==code and s["source_url"]=="https://data.worldbank.org/indicator/"+code, "source")
        rawvals={}
        for f in s["raw_files"]:
            name=f["file"]
            check(re.fullmatch(r"raw/[A-Z0-9.]+-(?:[1-8]|metadata)\.json",name) and name not in files, "raw path")
            files.add(name)
            b=read(name)
            check(sha(b)==f["sha256"], "raw checksum "+name)
            check(f["url"].startswith("https://api.worldbank.org/"),"raw URL")
            doc=read_json(b)
            if name.endswith("-metadata.json"):
                check(doc[1][0]["id"]==code, "metadata indicator")
                continue
            check(doc[0]["lastupdated"]==s["source_vintage"],"source vintage")
            for row in doc[1]:
                check(row["indicator"]["id"]==code,"mixed data")
                iso=REV.get(row.get("countryiso3code"))
                if iso is None: continue
                k=(iso,int(row["date"]))
                check(k not in rawvals,"duplicate raw observation")
                rawvals[k]=row["value"]
        expected={(iso,y) for iso in COUNTRIES for y in YEARS}
        check(set(rawvals)==expected,"raw coverage")
        seen=set()
        for r in s["rows"]:
            k=(r["iso2"],r["year"])
            check(k in expected and k not in seen and r["indicator"]==code,"row key")
            seen.add(k)
            v=r["value"]
            check(v==rawvals[k],"numeric raw mismatch")
            check(v is None or (type(v) in (int,float) and math.isfinite(v)),"numeric type")
            check(r["kind"]==("missing" if v is None else "published_historical_or_estimated"),"classification")
            count+=v is not None
        check(seen==expected,"row coverage")
    check(len(files)==48 and count==2632,"pinned counts")
    return sorted(files),count

def freeze(archive,target):
    check(sha(archive.read_bytes())==EXPECTED,"artifact checksum")
    with zipfile.ZipFile(archive) as z:
        check(z.testzip() is None,"archive integrity")
        evidence=read_json(z.read("evidence.json"))
        files,count=validate(evidence,z.read)
        check(sorted(z.namelist())==sorted(files+["evidence.json"]),"archive members")
        target.mkdir(parents=True,exist_ok=False)
        (target/"raw").mkdir()
        for name in files:
            (target/name).write_bytes(z.read(name))
        for s in evidence["series"].values():
            s.pop("license_status",None)
        (target/"evidence.json").write_text(json.dumps(evidence,ensure_ascii=False,sort_keys=True,indent=2)+"\n",encoding="utf-8")
    manifest={"schema":"wp115-wdi-frozen-v1","original_artifact_sha256":EXPECTED,
              "source_run":45976171412,"source_artifact":15186464411,"countries":42,
              "series":24,"numeric_slots":3024,"non_null":count,"null":3024-count,
              "files":{name:sha((target/name).read_bytes()) for name in files+["evidence.json"]}}
    (target/"manifest.json").write_text(json.dumps(manifest,sort_keys=True,indent=2)+"\n",encoding="utf-8")
    print("WP115 frozen WDI: 42 countries, 24 series, 2632 values, 392 nulls")

def audit(target):
    manifest=read_json((target/"manifest.json").read_bytes())
    check(manifest["original_artifact_sha256"]==EXPECTED and manifest["source_run"]==45976171412,"pinned artifact")
    files,count=validate(read_json((target/"evidence.json").read_bytes()),lambda n:(target/n).read_bytes())
    check(set(manifest["files"])==set(files+["evidence.json"]),"manifest file list")
    check({p.relative_to(target).as_posix() for p in target.rglob("*") if p.is_file()}==set(files+["evidence.json","manifest.json"]),"directory file list")
    for name,digest in manifest["files"].items():
        check(sha((target/name).read_bytes())==digest,"snapshot digest")
    check(manifest["non_null"]==count and manifest["numeric_slots"]==3024 and manifest["null"]==392,"manifest counts")
    print("PASS WP115 frozen WDI: 3024 slots independently reconciled with raw source responses")

if __name__=="__main__":
    if len(sys.argv)==3 and sys.argv[1]=="--audit":
        audit(Path(sys.argv[2]))
    elif len(sys.argv)==3:
        freeze(Path(sys.argv[1]),Path(sys.argv[2]))
        audit(Path(sys.argv[2]))
    else:
        raise SystemExit("usage: freeze-wp115-wdi.py ARCHIVE TARGET | --audit TARGET")
