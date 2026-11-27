#!/usr/bin/env python3
"""Isolated collection of published Party Facts cross-source identifiers (frozen 2023 release)."""
import csv, hashlib, io, json, pathlib, re, urllib.request, urllib.parse
from collections import Counter
API="https://dataverse.harvard.edu/api/"
DOI="doi:10.7910/DVN/TJINLQ"
ROOT=pathlib.Path("games/world-politics/data/wp117-partyfacts-2023-v1")
MAX=64_000_000
def get(url):
    req=urllib.request.Request(url,headers={"User-Agent":"WorldPolitics-WP117-reference/1.0","Accept":"*/*"})
    with urllib.request.urlopen(req,timeout=45) as response:
        raw=response.read(MAX+1)
        if len(raw)>MAX or not response.geturl().startswith("https://"): raise ValueError("invalid response")
        return raw
def digest(b): return hashlib.sha256(b).hexdigest()
def main():
    meta=get(API+"datasets/:persistentId/?"+urllib.parse.urlencode({"persistentId":DOI}))
    d=json.loads(meta)["data"]; v=d["latestVersion"]
    assert d["persistentUrl"].endswith("10.7910/DVN/TJINLQ") and (v["versionNumber"],v["versionMinorNumber"])==(1,1)
    assert "CC0" in json.dumps(v.get("license",{}))
    print("Release files:",[(f["label"],f["dataFile"]["id"]) for f in v["files"]])
    candidates=[f for f in v["files"] if re.search(r"(external|dataset)[-_ ]parties\.(csv|tab|tsv)$",f["label"],re.I)]
    assert len(candidates)==1, "external party table not unique"
    url=API+"access/datafile/"+str(int(candidates[0]["dataFile"]["id"]))+"?format=original"
    raw=get(url)
    txt=raw.decode("utf-8-sig")
    fields=["external_id","dataset_key","dataset_party_id","country","partyfacts_id","year_first","year_last"]
    readers=[csv.DictReader(io.StringIO(txt,newline=""),delimiter=sep) for sep in (",","\t",";")]
    readers=[r for r in readers if set(fields).issubset(r.fieldnames or [])]
    assert len(readers)==1,"unexpected source columns"
    rows=[]; seen=set(); counts=Counter(); links=0
    def value(row,key):
        val=(row.get(key) or "").strip()
        return val if val not in ("","NA","NULL") else None
    for row in readers[0]:
        vals=[value(row,k) for k in fields]
        eid,ds,pid,country,core,first,last=vals
        assert eid and eid.isdigit() and eid not in seen and ds and pid and country,vals[:5]
        assert len(ds)<=100 and len(pid)<=200 and len(country)<=16 and (core is None or re.fullmatch(r"-?\d+",core)),vals[:5]
        assert all(x is None or len(x)<=64 for x in (first,last))
        seen.add(eid);counts[ds]+=1;links+=int(core is not None);rows.append(vals)
    assert 10000<=len(rows)<=150000 and len(counts)>=20
    rows.sort(key=lambda r:int(r[0]))
    out={"schema":"wp117-partyfacts-crosswalk-2023-v1","doi":DOI,"dataset_version":"1.1","license":"CC0-1.0","source_url":url,"source_bytes_sha256":digest(raw),"metadata_sha256":digest(meta),"fields":fields,"source_rows":len(rows),"linked_rows":links,"dataset_counts":dict(sorted(counts.items())),"runtime_admission":False,"rows":rows}
    ROOT.mkdir(parents=True,exist_ok=True);path=ROOT/"external-crosswalks.json"
    path.write_text(json.dumps(out,ensure_ascii=False,sort_keys=True,separators=(",",":"))+"\n",encoding="utf-8")
    print(json.dumps({k:v for k,v in out.items() if k!="rows"},ensure_ascii=False))
    print("output bytes",path.stat().st_size,"output sha256",digest(path.read_bytes()))
if __name__=="__main__":main()
