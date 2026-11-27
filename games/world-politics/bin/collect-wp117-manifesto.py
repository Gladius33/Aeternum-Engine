#!/usr/bin/env python3
"""Freeze publicly accessible Manifesto Project catalogue metadata, not document text."""
import csv, hashlib, io, json, pathlib, re, urllib.request, urllib.parse
from html.parser import HTMLParser
HOSTS=("manifesto-project.wzb.eu","manifestoproject.wzb.eu","manifesto.wzb.eu","tools.manifesto.wzb.eu","gitlab.manifesto-project.wzb.eu")
PAGES=["https://"+host+"/datasets/MPDS2026a" for host in HOSTS]
MAX=16000000
def get(url):
    u=urllib.parse.urlparse(url)
    if u.scheme!="https" or u.hostname not in HOSTS: raise ValueError("unexpected source host")
    with urllib.request.urlopen(urllib.request.Request(url,headers={"User-Agent":"WorldPolitics-WP117/1.0"}),timeout=12) as r:
        raw=r.read(MAX+1); final=urllib.parse.urlparse(r.geturl())
        if len(raw)>MAX or final.scheme!="https" or final.hostname not in HOSTS: raise ValueError("invalid response")
        return raw,r.geturl()
class Links(HTMLParser):
    def __init__(self):
        super().__init__(); self.active=None; self.links=[]
    def handle_starttag(self,t,attrs):
        if t=="a": self.active=[dict(attrs).get("href"),""]
    def handle_data(self,s):
        if self.active is not None:self.active[1]+=s
    def handle_endtag(self,t):
        if t=="a" and self.active is not None:self.links.append(tuple(self.active));self.active=None
def sha(b):return hashlib.sha256(b).hexdigest()
def main():
    for page in PAGES:
        try:
            html,final=get(page); parser=Links();parser.feed(html.decode("utf-8","replace"))
            links=[(urllib.parse.urljoin(final,u),t.strip()) for u,t in parser.links if u and ("coded documents" in t.lower() or "list" in t.lower() and "short" in t.lower())]
            print("Found links:",links)
            docs=[u for u,t in links if "coded documents" in t.lower()]
            assert len(docs)==1,"missing document catalogue"
            raw,url=get(docs[0]); txt=raw.decode("utf-8-sig")
            dialect=csv.Sniffer().sniff(txt[:8192],delimiters=",;\t")
            reader=csv.DictReader(io.StringIO(txt,newline=""),dialect=dialect)
            fields=reader.fieldnames or []
            print("Catalogue fields",fields)
            assert len(fields)>=3 and any("party" in x.lower() for x in fields)
            rows=[]
            for row in reader:
                assert None not in row and all(v is not None and len(v)<=1500 for v in row.values())
                # Only published bibliographic metadata, not texts, annotations or contacts.
                keys=[k for k in fields if re.search(r"party|country|date|year|month|title|language|document|source|election|manifesto|version",k,re.I) and not re.search(r"body|content|text|contact|email|address|annotation",k,re.I)]
                rows.append({k:row[k] for k in keys})
            assert 1000<=len(rows)<=12000,"unexpected row count"
            out={"schema":"wp117-manifesto-catalogue-2026a-v1","edition":"MPDS2026a","doi":"10.25522/manifesto.mpds.2026a","source_url":url,"source_bytes_sha256":sha(raw),"index_page_sha256":sha(html),"row_count":len(rows),"runtime_admission":False,"rows":rows}
            p=pathlib.Path("games/world-politics/data/wp117-manifesto-2026a-v1/documents.json");p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(out,ensure_ascii=False,sort_keys=True,separators=(",",":"))+"\n",encoding="utf-8");print(json.dumps({k:v for k,v in out.items() if k!="rows"}));return
        except Exception as e:print("Source unavailable",page,type(e).__name__,str(e)[:300])
    raise SystemExit("No accessible public catalogue")
if __name__=="__main__":main()
