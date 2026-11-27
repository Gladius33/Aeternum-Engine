#!/usr/bin/env python3
"""Offline WP115 staging integrity gate. Never imports into a live world."""
import hashlib
import json
import math
from pathlib import Path
import re
import zipfile

BASE = Path(__file__).resolve().parents[1] / "games/world-politics/data"
FOLDER = BASE / "wp115-collection-v0.4"
COUNTRIES = set("AT AU BE BG BR CA CH CN CY CZ DE DK EE ES FI GB GR HR HU ID IE IN IT JP KR LT LU LV MT MX NL PL PT RO RU SA SE SI SK TR US ZA".split())

def require(condition, message):
    if not condition:
        raise ValueError(message)

def load(path):
    def pairs(items):
        result = {}
        for k, v in items:
            require(k not in result, "duplicate JSON key: " + k)
            result[k] = v
        return result
    return json.loads(path.read_text(encoding="utf-8"), object_pairs_hook=pairs,
                      parse_constant=lambda v: (_ for _ in ()).throw(ValueError(v)))

def audit(folder=FOLDER, archive=BASE / "wp115-collection-v0.4.zip"):
    manifest = load(folder / "manifest.json")
    require(set(manifest["country_iso2"]) == COUNTRIES and len(manifest["country_iso2"]) == 42, "country roster")
    require(manifest["status"] == "partial_collection_staged_not_runtime", "unexpected admission status")
    files = manifest["files"]
    actual = {p.relative_to(folder).as_posix() for p in folder.rglob("*") if p.is_file()}
    require(actual == set(files) | {"manifest.json"}, "unexpected or absent files")
    for name, digest in files.items():
        require(re.fullmatch("[a-f0-9]{64}", digest) is not None, "invalid digest")
        require(hashlib.sha256((folder / name).read_bytes()).hexdigest() == digest, "digest mismatch: " + name)
    require(hashlib.sha256(archive.read_bytes()).hexdigest() == "e54c7e9ced4b8fea7f24fcc6f92e9dc11e92407fe41ad8214f9bca841c6f8279", "archive digest")
    with zipfile.ZipFile(archive) as z:
        names = [n for n in z.namelist() if not n.endswith("/")]
        require(len(names) == len(actual) and set(names) == {"WP115/" + n for n in actual}, "archive membership")
        require(z.testzip() is None, "archive CRC")
        for name in actual:
            require(z.read("WP115/" + name) == (folder / name).read_bytes(), "archive mismatch: " + name)
    sources = load(folder / "documentation/sources.json")
    for source_id, source in sources.items():
        require(source.get("url", "").startswith("https://"), "source without HTTPS: " + source_id)
    report = load(folder / "documentation/quality_report.json")
    require(report["runtime_admission"] is False, "staging is not runtime-ready")

    def table(name):
        rows = load(folder / "data" / (name + ".json"))
        require(isinstance(rows, list), "invalid table: " + name)
        return rows

    for name in ("countries", "parliament", "renewals", "election_dates_reference", "vat_reference"):
        rows = table(name)
        require(len(rows) == 42 and {r["iso2"] for r in rows} == COUNTRIES, "coverage: " + name)
    for row in table("renewals"):
        require(row["certifies_result"] is False and re.fullmatch(r"\d{4}-(0[1-9]|1[0-2])", row["expected_renewal_month"]), "uncertified election")
    for country in COUNTRIES:
        record = load(folder / "countries" / (country + ".json"))
        require(record["identity"]["iso2"] == country, "country file identity")

    expected = {"population_total": "persons", "gdp_current_usd": "USD",
                "real_gdp_growth": "basis_points", "consumer_price_inflation_annual_average": "basis_points",
                "current_account_balance_share_gdp": "basis_points", "unemployment_rate": "basis_points"}
    all_rows = table("numeric_all")
    obs, pro = table("numeric_observations"), table("numeric_projections")
    key = lambda r: (r["iso2"], r["metric"], r["year"], r["source_id"])
    require(sorted(all_rows, key=key) == sorted(obs + pro, key=key), "observation/projection split")
    require(all(r["year"] <= 2025 and r["kind"] in ("observed", "historical_estimate") for r in obs), "historical classification")
    require(all(r["year"] >= 2026 and r["kind"] in ("projection", "missing") for r in pro), "future classification")
    require(len(all_rows) == 588, "core slot count")
    for r in all_rows:
        require(set(r) == {"iso2","metric","year","value","unit","kind","quality","source_id"}, "core schema")
        require(expected.get(r["metric"]) == r["unit"], "unit mismatch")
        require(r["source_id"] in ("REPO_WDI", "IMF_WEO"), "core source")
        require(r["value"] is None or type(r["value"]) is int, "noninteger core value")
        if r["metric"] in ("population_total", "gdp_current_usd"):
            require(r["kind"] == "observed" and r["year"] == 2024 and r["source_id"] == "REPO_WDI" and r["value"] > 0, "invalid WDI observation")

    fiscal = table("fiscal_monitor_2026_04")
    supplementary = table("supplementary_numeric")
    require(len(fiscal) == 378 and len(supplementary) == 710, "supplement slots")
    require(all(r["runtime_admission"] is False and r["vintage"] == "2026-04" and r["unit"] == "basis_points_of_gdp" and r["source_id"] == "IMF_FM_2026_04" and r["kind"] == ("historical_estimate" if r["year"] == 2025 else "projection") and type(r["value"]) is int for r in fiscal), "fiscal classification")
    for rows in (all_rows, supplementary, fiscal):
        seen = set()
        for r in rows:
            k = key(r)
            require(k not in seen, "duplicate numeric key: " + str(k))
            seen.add(k)
            require(r["iso2"] in COUNTRIES and r["source_id"] in sources, "unknown country/source")
            v = r["value"]
            require((v is None) == (r["kind"] == "missing"), "null classification")
            require(v is None or (type(v) in (int, float) and math.isfinite(v)), "nonfinite value")
    require({(r["iso2"], r["metric"], r["year"]) for r in fiscal} == {(c,m,y) for c in COUNTRIES for m in ("government_gross_debt","government_revenue","government_expenditure") for y in (2025,2026,2027)}, "fiscal coverage")
    gaps = table("gaps")
    require(len(gaps) == 924 and len({(r["iso2"],r["field"]) for r in gaps}) == 924, "gap coverage")
    require({r["iso2"] for r in gaps} == COUNTRIES and all(sum(r["iso2"] == c for r in gaps) == 22 for c in COUNTRIES), "national gap coverage")
    require(all(r["value"] is None and r["status"] in ("not_collected_or_not_validated","partially_collected_not_complete","partially_documented_not_validated") for r in gaps), "gap must remain explicit")
    missing = sum(r["status"] == "not_collected_or_not_validated" for r in gaps)
    count = sum(r["value"] is not None for rows in (all_rows,supplementary,fiscal) for r in rows)
    require(count == report["numeric_values_cumulative_including_distinct_vintages"] == 1669, "quality count")
    require(missing == report["gaps_not_collected_or_not_validated"] == 729, "gap report")
    return {"countries": 42, "files": len(actual), "numeric_slots": 1676, "non_null": count,
            "gap_slots": len(gaps), "unresolved": missing, "partially_documented": len(gaps)-missing,
            "runtime_admission": False, "commercial_clearance": False}

if __name__ == "__main__":
    print(json.dumps(audit(), sort_keys=True))
