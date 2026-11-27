#!/usr/bin/env python3
"""Acquire a dated WDI evidence pack; network errors are not observations.
No automatic repository commit, production import, or commercial clearance.
"""
import argparse
import concurrent.futures
import datetime as dt
import hashlib
import json
import math
from pathlib import Path
import time
import urllib.request

ISO = dict(x.split(":") for x in "AT:AUT AU:AUS BE:BEL BG:BGR BR:BRA CA:CAN CH:CHE CN:CHN CY:CYP CZ:CZE DE:DEU DK:DNK EE:EST ES:ESP FI:FIN GB:GBR GR:GRC HR:HRV HU:HUN ID:IDN IE:IRL IN:IND IT:ITA JP:JPN KR:KOR LT:LTU LU:LUX LV:LVA MT:MLT MX:MEX NL:NLD PL:POL PT:PRT RO:ROU RU:RUS SA:SAU SE:SWE SI:SVN SK:SVK TR:TUR US:USA ZA:ZAF".split())
SERIES = {
    "SP.POP.0014.TO.ZS": ("population_age_0_14_share", "percent_population"),
    "SP.POP.1564.TO.ZS": ("population_age_15_64_share", "percent_population"),
    "SP.POP.65UP.TO.ZS": ("population_age_65_plus_share", "percent_population"),
    "SP.DYN.TFRT.IN": ("fertility_rate", "births_per_woman"),
    "SM.POP.NETM": ("net_migration", "persons"),
    "SP.URB.TOTL.IN.ZS": ("urban_population_share", "percent_population"),
    "SL.TLF.ACTI.ZS": ("labour_force_participation_15_64_modelled", "percent_age_15_64"),
    "SL.AGR.EMPL.ZS": ("agricultural_employment_share_modelled", "percent_employment"),
    "SL.IND.EMPL.ZS": ("industry_employment_share_modelled", "percent_employment"),
    "SL.SRV.EMPL.ZS": ("service_employment_share_modelled", "percent_employment"),
    "NV.AGR.TOTL.ZS": ("agriculture_value_added_share", "percent_gdp"),
    "NV.IND.MANF.ZS": ("manufacturing_value_added_share", "percent_gdp"),
    "NV.IND.TOTL.ZS": ("industry_value_added_share", "percent_gdp"),
    "NV.SRV.TOTL.ZS": ("service_value_added_share", "percent_gdp"),
    "NE.EXP.GNFS.ZS": ("exports_goods_services_share", "percent_gdp"),
    "NE.IMP.GNFS.ZS": ("imports_goods_services_share", "percent_gdp"),
    "NE.CON.PRVT.ZS": ("households_npish_consumption_share", "percent_gdp"),
    "SH.XPD.CHEX.GD.ZS": ("current_health_expenditure_share", "percent_gdp"),
    "SH.XPD.GHED.GD.ZS": ("domestic_government_health_expenditure_share", "percent_gdp"),
    "SE.XPD.TOTL.GD.ZS": ("government_education_expenditure_share", "percent_gdp"),
    "FI.RES.TOTL.CD": ("total_reserves_including_gold", "USD_current"),
    "PA.NUS.FCRF": ("official_exchange_rate_period_average", "LCU_per_USD"),
    "EG.IMP.CONS.ZS": ("energy_imports_net_share", "percent_energy_use"),
    "MS.MIL.XPND.GD.ZS": ("military_expenditure_share", "percent_gdp"),
}
YEARS = (2023, 2024, 2025)
REVERSE = {v:k for k,v in ISO.items()}
TERMS = "https://www.worldbank.org/ext/en/legal/terms-conditions/datasets"

def fetch(url):
    for attempt in range(2):
        try:
            request = urllib.request.Request(url, headers={"Accept":"application/json","User-Agent":"WorldPolitics-WP115 research collector/1"})
            with urllib.request.urlopen(request, timeout=18) as response:
                if not response.geturl().startswith("https://api.worldbank.org/"):
                    raise ValueError("unexpected redirect")
                raw = response.read(5_000_001)
                if len(raw) > 5_000_000:
                    raise ValueError("response too large")
                return raw, json.loads(raw)
        except Exception:
            if attempt == 0:
                time.sleep(1)
            else:
                raise

def normalize(code, pages):
    seen = {}
    edition = set()
    for page in pages:
        if not isinstance(page,list) or len(page) != 2 or not isinstance(page[1],list):
            raise ValueError("API did not return a data page")
        header, rows = page
        edition.add(str(header.get("lastupdated", "")))
        for row in rows:
            if row["indicator"]["id"] != code:
                raise ValueError("mixed indicator response")
            country = REVERSE.get(row.get("countryiso3code"))
            if country is None:
                continue
            year = int(row["date"])
            if year not in YEARS:
                raise ValueError("unexpected year")
            key = (country, year)
            if key in seen:
                raise ValueError("duplicate national observation")
            value = row["value"]
            if value is not None and (type(value) not in (int,float) or not math.isfinite(value)):
                raise ValueError("invalid numeric value")
            seen[key] = value
    if set(seen) != {(c,y) for c in ISO for y in YEARS}:
        raise ValueError("incomplete country-year coverage; not interpreted as null")
    if len(edition) != 1 or not next(iter(edition)):
        raise ValueError("missing or inconsistent source vintage")
    metric, unit = SERIES[code]
    return next(iter(edition)), [
        {"iso2":c,"indicator":code,"metric":metric,"year":y,"value":seen[(c,y)],"unit":unit,
         "kind":"missing" if seen[(c,y)] is None else "published_historical_or_estimated"}
        for c in sorted(ISO) for y in YEARS]

def collect(code, out):
    base = "https://api.worldbank.org/v2/country/all/indicator/" + code + "?source=2&format=json&date=2023:2025&per_page=1000"
    raw, first = fetch(base + "&page=1")
    if not isinstance(first,list) or len(first)!=2 or not isinstance(first[0],dict):
        raise ValueError("invalid first page")
    n = int(first[0]["pages"])
    if n < 1 or n > 8:
        raise ValueError("page bound exceeded")
    pages, hashes = [first], []
    for page in range(1,n+1):
        if page != 1:
            raw, data = fetch(base + "&page=" + str(page))
            pages.append(data)
        header = pages[-1][0]
        if int(header["page"])!=page or int(header["pages"])!=n or int(header["total"])!=int(first[0]["total"]):
            raise ValueError("inconsistent pagination")
        name = code + "-" + str(page) + ".json"
        (out / "raw" / name).write_bytes(raw)
        hashes.append({"file":"raw/"+name,"sha256":hashlib.sha256(raw).hexdigest(),"url":base+"&page="+str(page)})
    if sum(len(p[1]) for p in pages) != int(first[0]["total"]):
        raise ValueError("truncated pagination")
    vintage, rows = normalize(code, pages)
    meta_url = "https://api.worldbank.org/v2/indicator/" + code + "?source=2&format=json"
    raw, metadata = fetch(meta_url)
    if not isinstance(metadata,list) or len(metadata)!=2 or not metadata[1] or metadata[1][0]["id"]!=code:
        raise ValueError("indicator metadata missing")
    (out / "raw" / (code+"-metadata.json")).write_bytes(raw)
    hashes.append({"file":"raw/"+code+"-metadata.json","sha256":hashlib.sha256(raw).hexdigest(),"url":meta_url})
    meta = metadata[1][0]
    return {"indicator":code,"source_url":"https://data.worldbank.org/indicator/"+code,
            "source_vintage":vintage,"publisher":meta.get("sourceOrganization"),
            "terms_url":TERMS,"license_status":"exact_indicator_rights_review_pending",
            "raw_files":hashes,"rows":rows}

def main():
    p = argparse.ArgumentParser()
    p.add_argument("--output",type=Path,required=True)
    a = p.parse_args()
    out = a.output
    out.mkdir(parents=True,exist_ok=False)
    (out/"raw").mkdir()
    successful, errors = {}, {}
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        futures = {pool.submit(collect,c,out):c for c in SERIES}
        for f in concurrent.futures.as_completed(futures):
            c = futures[f]
            try:
                successful[c] = f.result()
                print(c, "OK", sum(r["value"] is not None for r in successful[c]["rows"]), flush=True)
            except Exception as e:
                errors[c] = type(e).__name__ + ": " + str(e)
                print(c, "FAILED", errors[c], flush=True)
    result = {"schema":"wp115-wdi-evidence-v1","retrieved_at":dt.datetime.now(dt.timezone.utc).isoformat(),
              "country_iso2":sorted(ISO),"years":list(YEARS),"runtime_admission":False,
              "series":{k:successful[k] for k in sorted(successful)},"transport_or_validation_errors":errors}
    (out/"evidence.json").write_text(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+"\n",encoding="utf-8")
    print("WP115 WDI successful series:", len(successful), "/", len(SERIES))
    if errors:
        raise SystemExit(1)

if __name__ == "__main__":
    main()
