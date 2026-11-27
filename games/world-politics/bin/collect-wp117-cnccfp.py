#!/usr/bin/env python3
"""One-time source freeze: published CNCCFP annual party account identifiers, no financial or personal details."""
import csv, hashlib, io, json, pathlib, re, unicodedata, urllib.request
URL="https://www.data.gouv.fr/api/1/datasets/r/8c53e69e-607a-49d9-98d7-7dabad5ec4a9"
ROOT=pathlib.Path("games/world-politics/data/wp117-cnccfp-2024-v1")
def normalized(s):
    return re.sub(r"[^a-z0-9]+"," ",unicodedata.normalize("NFKD",s).encode("ascii","ignore").decode().lower()).strip()
def main():
    req=urllib.request.Request(URL,headers={"User-Agent":"WorldPolitics-WP117-reference/1.0","Accept":"text/csv,*/*"})
    with urllib.request.urlopen(req,timeout=45) as res:
        raw=res.read(5_000_001)
        assert len(raw)<5_000_001 and res.geturl().startswith("https://"),"invalid transport"
        source_url=res.geturl()
    try:text=raw.decode("utf-8-sig")
    except UnicodeDecodeError:text=raw.decode("cp1252")
    # Header-only delimiter discovery avoids misclassification from long
    # numeric rows, quoted text and embedded line breaks.
    candidates=[]
    for sep in (";",",","\t"):
        first=next(csv.reader(io.StringIO(text,newline=""),delimiter=sep),[])
        candidates.append((len(first),sep,first))
    n,sep,first=max(candidates,key=lambda x:x[0])
    print("Header preview:",json.dumps(first,ensure_ascii=False))
    assert n>=3,"source does not have a tabular header"
    reader=csv.DictReader(io.StringIO(text,newline=""),delimiter=sep)
    fields=reader.fieldnames or []
    norm={f:normalized(f) for f in fields}
    print("CSV headers:",json.dumps(fields,ensure_ascii=False))
    id_candidates=[f for f,n in norm.items() if n in ("id","id parti","identifiant","identifiant parti","identifiant du parti","numero","numero parti","numero du parti","n parti","n du parti","num parti","num du parti","code parti","numero cnccfp","numero identification","numero d identification","numero d identification du parti","n identification","n identification du parti","n d identification","n d identification du parti","n o parti")]
    if not id_candidates:id_candidates=[f for f,n in norm.items() if ("parti" in n or "cnccfp" in n) and any(x in n.split() for x in ("numero","num","identifiant","code"))]
    name_candidates=[f for f,n in norm.items() if n in ("nom","parti","nom parti","nom du parti","denomination","denomination parti","denomination du parti","formation politique","nom de la formation politique","libelle","libelle du parti","libelle parti","nom du parti politique","denomination du parti politique","parti politique")]
    if not name_candidates:name_candidates=[f for f,n in norm.items() if ("parti" in n or "formation" in n) and any(x in n.split() for x in ("nom","denomination","libelle"))]
    assert len(id_candidates)==1 and len(name_candidates)==1,(id_candidates,name_candidates)
    id_field,name_field=id_candidates[0],name_candidates[0]
    records={}
    total=0
    for row in reader:
        if not any((v or "").strip() for v in row.values() if isinstance(v,str)):continue
        total+=1
        ident=(row.get(id_field) or "").strip()
        name=(row.get(name_field) or "").strip()
        assert ident and name and len(ident)<=32 and len(name)<=500,(ident,name)
        assert re.fullmatch(r"[A-Za-z0-9._/-]+",ident),f"invalid identifier {ident!r}"
        records.setdefault(ident,set()).add(name)
    assert 300<=len(records)<=1000,f"unexpected unique formations count {len(records)}"
    rows=[{"source_id":ident,"names":sorted(names)} for ident,names in sorted(records.items())]
    out={"schema":"wp117-cnccfp-2024-v1","exercise_year":2024,"source_resource_url":URL,"resolved_source_url":source_url,"source_bytes_sha256":hashlib.sha256(raw).hexdigest(),"license":"Licence Ouverte / Open Licence","id_column":id_field,"name_column":name_field,"source_rows":total,"runtime_admission":False,"rows":rows}
    ROOT.mkdir(parents=True,exist_ok=True)
    p=ROOT/"parties.json"
    p.write_text(json.dumps(out,ensure_ascii=False,sort_keys=True,separators=(",",":"))+"\n",encoding="utf-8")
    print(json.dumps({"unique_formations":len(rows),"source_rows":total,"source_sha256":out["source_bytes_sha256"],"sha256":hashlib.sha256(p.read_bytes()).hexdigest(),"id_column":id_field,"name_column":name_field},ensure_ascii=False))
if __name__=="__main__":main()
