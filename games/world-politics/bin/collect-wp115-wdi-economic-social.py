#!/usr/bin/env python3
"""Acquire a dated WDI evidence pack; network errors are not observations.
No automatic repository commit or production import.
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
    "NE.CON.PRVT.PC.KD": ("household_npish_consumption_per_capita_real", "USD_constant_2015"),
    "NY.GDP.PCAP.PP.KD": ("gdp_per_capita_ppp_real", "international_dollar_constant_2021"),
    "NY.GNP.PCAP.CD": ("gni_per_capita_atlas", "USD_current"),
    "SI.POV.GINI": ("gini_index", "index_0_100"),
    "SI.POV.DDAY": ("poverty_international_line", "percent_population"),
    "SL.EMP.VULN.ZS": ("vulnerable_employment_modelled", "percent_employment"),
    "SL.EMP.TOTL.SP.ZS": ("employment_population_ratio_age_15_plus_modelled", "percent_age_15_plus"),
    "SL.UEM.1524.ZS": ("youth_unemployment_modelled", "percent_labour_force_age_15_24"),
    "SL.TLF.TOTL.IN": ("labour_force_total", "persons"),
    "SL.TLF.CACT.FE.ZS": ("female_labour_force_participation_age_15_plus_modelled", "percent_age_15_plus_female"),
    "GC.TAX.TOTL.GD.ZS": ("tax_revenue_central_government", "percent_gdp"),
    "GC.REV.XGRT.GD.ZS": ("revenue_excluding_grants_central_government", "percent_gdp"),
    "GC.XPN.TOTL.GD.ZS": ("expense_central_government", "percent_gdp"),
    "GC.DOD.TOTL.GD.ZS": ("central_government_debt", "percent_gdp"),
    "GC.XPN.INTP.RV.ZS": ("interest_payments_central_government_share_revenue", "percent_revenue"),
    "DT.DOD.DPPG.CD": ("external_debt_public_publicly_guaranteed", "USD_current"),
    "DT.TDS.DPPG.CD": ("external_debt_service_public_publicly_guaranteed", "USD_current"),
    "BX.KLT.DINV.WD.GD.ZS": ("foreign_direct_investment_net_inflows", "percent_gdp"),
    "BN.CAB.XOKA.GD.ZS": ("current_account_balance", "percent_gdp"),
    "FS.AST.PRVT.GD.ZS": ("domestic_credit_private_sector", "percent_gdp"),
    "SH.MED.PHYS.ZS": ("physicians_density", "per_1000_people"),
    "SH.MED.BEDS.ZS": ("hospital_beds_density", "per_1000_people"),
    "SH.XPD.OOPC.CH.ZS": ("out_of_pocket_health_expenditure", "percent_current_health_expenditure"),
    "EG.ELC.ACCS.ZS": ("electricity_access", "percent_population"),
    "EG.USE.PCAP.KG.OE": ("energy_use_per_capita", "kg_oil_equivalent_per_person"),
    "AG.LND.ARBL.ZS": ("arable_land_share", "percent_land_area"),
    "NY.GDP.TOTL.RT.ZS": ("total_natural_resource_rents", "percent_gdp"),
    "GB.XPD.RSDV.GD.ZS": ("research_development_expenditure", "percent_gdp"),
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
            "terms_url":TERMS,
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
    result = {"schema":"wp115-wdi-economic-social-v2","retrieved_at":dt.datetime.now(dt.timezone.utc).isoformat(),
              "country_iso2":sorted(ISO),"years":list(YEARS),"runtime_admission":False,
              "series":{k:successful[k] for k in sorted(successful)},"transport_or_validation_errors":errors}
    (out/"evidence.json").write_text(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+"\n",encoding="utf-8")
    print("WP115 WDI successful series:", len(successful), "/", len(SERIES))
    if errors:
        raise SystemExit(1)

if __name__ == "__main__":
    main()
