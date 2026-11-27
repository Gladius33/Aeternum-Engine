#!/usr/bin/env python3
"""Collect frozen public Party Facts 2023 data; documentary staging, not runtime."""
import csv, hashlib, io, json, pathlib, urllib.request, urllib.parse
from collections import Counter

ROOT=pathlib.Path("games/world-politics/data/wp117-partyfacts-2023-v1")
API="https://dataverse.harvard.edu/api/"
DOI="doi:10.7910/DVN/TJINLQ"
MAX=8_000_000

def get(url):
    req=urllib.request.Request(url,headers={"User-Agent":"WorldPolitics-WP117-reference/1.0","Accept":"*/*"})
    with urllib.request.urlopen(req,timeout=45) as res:
        data=res.read(MAX+1)
        if len(data)>MAX: raise ValueError("response too large")
        if not res.geturl().startswith("https://"): raise ValueError("non-HTTPS redirect")
        return data

def sha(data): return hashlib.sha256(data).hexdigest()
def main():
    meta_url=API+"datasets/:persistentId/?"+urllib.parse.urlencode({"persistentId":DOI})
    meta=get(meta_url)
    d=json.loads(meta)["data"]
    v=d["latestVersion"]
    assert d["persistentUrl"].endswith("10.7910/DVN/TJINLQ"),"wrong dataset"
    assert (v["versionNumber"],v["versionMinorNumber"])==(1,1),"unexpected release"
    assert "CC0" in json.dumps(v.get("license",{})),"unexpected dataset terms"
    matches=[f for f in v["files"] if f.get("label") in ("core-parties.tab","core-parties.csv") or f["dataFile"].get("filename")=="core-parties.tab"]
    assert len(matches)==1,"missing unique core parties file"
    f=matches[0]["dataFile"]
    url=API+"access/datafile/"+str(int(f["id"]))+"?format=original"
    raw=get(url)
    stream=io.StringIO(raw.decode("utf-8-sig"),newline="")
    reader=csv.DictReader(stream)
    expected={"country","partyfacts_id","name_short","name","name_english","name_other","year_first","year_last"}
    if not expected.issubset(reader.fieldnames or []):
        stream.seek(0);reader=csv.DictReader(stream,delimiter="\t")
    assert expected.issubset(reader.fieldnames or []),"unexpected columns"
    seen=set();rows=[]
    def val(row,k):
        s=(row.get(k) or "").strip()
        return s if s not in ("","NA","NULL") else None
    for row in reader:
        i=val(row,"partyfacts_id")
        assert i and i.isdigit() and i not in seen,"invalid or duplicate source ID"
        seen.add(i)
        country=val(row,"country")
        assert country and 2<=len(country)<=8 and country.isalnum(),"unexpected country code"
        names={k:val(row,k) for k in ("name_short","name","name_english","name_other")}
        assert any(names.values()),"missing name"
        rows.append({"source_id":i,"source_country_code":country,**names,"year_first_source":val(row,"year_first"),"year_last_source":val(row,"year_last")})
    assert len(rows)==7199, f"unexpected row count {len(rows)}"
    rows.sort(key=lambda r:int(r["source_id"]))
    out={"schema":"wp117-partyfacts-2023-v1","doi":DOI,"dataset_version":"1.1","publication_date":"2023-12-15","license":"CC0-1.0","source_url":url,"source_bytes_sha256":sha(raw),"metadata_sha256":sha(meta),"country_counts":dict(sorted(Counter(r["source_country_code"] for r in rows).items())),"runtime_admission":False,"rows":rows}
    ROOT.mkdir(parents=True,exist_ok=True)
    p=ROOT/"core-parties.json"
    p.write_text(json.dumps(out,ensure_ascii=False,sort_keys=True,separators=(",",":"))+"\n",encoding="utf-8")
    print(json.dumps({"rows":len(rows),"source_countries":len(out["country_counts"]),"sha256":sha(p.read_bytes()),"source_sha256":sha(raw),"source_url":url}))
if __name__=="__main__":main()
