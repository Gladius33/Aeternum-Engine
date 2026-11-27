#!/usr/bin/env python3
"""Collect factual historical parliamentary result tables from IPU's public site.
Do not infer votes, allocation rules, current party affiliation or future results."""
import argparse
import concurrent.futures
import datetime as dt
import hashlib
import json
import re
import time
import urllib.request
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urljoin, urlparse

CODES=set("AT AU BE BG BR CA CH CN CY CZ DE DK EE ES FI GB GR HR HU ID IE IN IT JP KR LT LU LV MT MX NL PL PT RO RU SA SE SI SK TR US ZA".split())
AS_OF="2026-11-27"
INDEX="https://data.ipu.org/elections/?order=desc&sort=last_election"
PATH_RE=re.compile(r"/parliament/([A-Z]{2})/([A-Z]{2}-[A-Z0-9]+)/(?:election|elections)/([^/]+)/?")
def clean(s): return " ".join(s.split())
def sha(b): return hashlib.sha256(b).hexdigest()
def require(v,msg):
    if not v: raise ValueError(msg)
def get(url):
    require(urlparse(url).netloc=="data.ipu.org" and urlparse(url).scheme=="https","source host")
    for attempt in range(2):
        try:
            with urllib.request.urlopen(urllib.request.Request(url,headers={"User-Agent":"WorldPolitics research collector/1.0","Accept":"text/html"}),timeout=20) as r:
                require(urlparse(r.geturl()).netloc=="data.ipu.org","redirect host")
                b=r.read(3_000_001);require(len(b)<3_000_001,"page size")
                return b,r.geturl()
        except Exception:
            if attempt: raise
            time.sleep(1)

class Tables(HTMLParser):
    def __init__(self,raw):
        super().__init__(convert_charrefs=True)
        self.tables=[];self.table=None;self.row=None;self.cell=None;self.depth=0;self.links=[]
        self.feed(raw.decode("utf-8",errors="replace"))
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=="table":
            self.depth+=1
            if self.depth==1: self.table=[]
        elif tag=="tr" and self.depth==1:self.row=[]
        elif tag in ("td","th") and self.row is not None and self.depth==1:self.cell={"parts":[],"links":[]}
        elif tag=="a" and a.get("href"):
            self.links.append(a["href"])
            if self.cell is not None:self.cell["links"].append(a["href"])
        elif tag=="br" and self.cell is not None:self.cell["parts"].append(" ")
    def handle_data(self,data):
        if self.cell is not None:self.cell["parts"].append(data)
    def handle_endtag(self,tag):
        if tag in ("td","th") and self.cell is not None and self.depth==1:
            self.row.append({"text":clean("".join(self.cell["parts"])),"links":self.cell["links"]});self.cell=None
        elif tag=="tr" and self.row is not None and self.depth==1:
            self.table.append(self.row);self.row=None
        elif tag=="table":
            if self.depth==1 and self.table is not None:self.tables.append(self.table);self.table=None
            self.depth=max(0,self.depth-1)

def discover(raw):
    by={}
    for table in Tables(raw).tables:
        for row in table:
            if len(row)<7:continue
            txt=[x["text"] for x in row]
            if txt[3]!="Lower chamber" and txt[2]!="Unicameral":continue
            for link in row[1]["links"]:
                url=urljoin(INDEX,link)
                m=PATH_RE.fullmatch(urlparse(url).path)
                if not m or m[1] not in CODES:continue
                code=m[1]
                require(code not in by,"duplicate lower chamber "+code)
                by[code]={"iso2":code,"country_label":txt[0],"chamber":txt[1],"structure":txt[2],
                          "designation":txt[4],"last_renewal_reference":txt[5],"first_session_reference":txt[6],
                          "expected_next_renewal_reference":txt[7] if len(txt)>7 else None,"source_url":url}
    return by

def result_rows(raw):
    candidates=[]
    for table in Tables(raw).tables:
        for i,row in enumerate(table):
            cells=[c["text"] for c in row]
            if len(cells)>=2 and cells[0].lower()=="political group" and "Total" in cells[1:]:
                n=cells.index("Total");data=[]
                for r in table[i+1:]:
                    if len(r)<=n:continue
                    name=r[0]["text"];value=r[n]["text"].replace(",","").replace(" ","")
                    if name and re.fullmatch(r"[0-9]+",value) and name.lower() not in ("total","total seats"):
                        data.append({"party_or_coalition":name,"seats":int(value)})
                if data:candidates.append(data)
    require(len(candidates)<=1,"ambiguous party result tables")
    return candidates[0] if candidates else []

def collect(ref):
    b,url=get(ref["source_url"]);m=PATH_RE.fullmatch(urlparse(url).path)
    require(m is not None and m[1]==ref["iso2"],"wrong chamber")
    # Deduplicate by election ID, not links with language/query/fragment differences.
    current=m[3]
    ids={}
    for link in Tables(b).links+[url]:
        u=urljoin(url,link);p=PATH_RE.fullmatch(urlparse(u).path)
        if p and p[1]==m[1] and p[2]==m[2] and re.fullmatch(r"[A-Z]{2}-[A-Z0-9]+-E[0-9]{8}",p[3]):
            d=p[3][-8:]
            if d<=AS_OF.replace("-",""):
                ids[p[3]]="https://data.ipu.org"+urlparse(u).path.rstrip("/")+"/"
    order=sorted(ids,key=lambda i:i[-8:],reverse=True)
    if current in ids:
        order=[current]+[i for i in order if i!=current]
    requested=[(i,ids[i]) for i in order[:3]] or [(current,url)]
    records=[]
    # Latest and up to two previous published result pages.
    for expected,u in requested:
        try:
            raw,final=(b,url) if expected==current else get(u)
            p=PATH_RE.fullmatch(urlparse(final).path)
            require(p and p[1]==ref["iso2"] and p[2]==m[2] and p[3]==expected,"wrong historical chamber or election redirect")
            date=None
            if re.fullmatch(r"[A-Z]{2}-[A-Z0-9]+-E[0-9]{8}",p[3]):
                date=dt.datetime.strptime(p[3][-8:],"%Y%m%d").date().isoformat()
                require(date<=AS_OF,"future result")
            rows=result_rows(raw)
            require(len({r["party_or_coalition"] for r in rows})==len(rows),"duplicate party label")
            # Numeric facts only; no copy of descriptive election narratives or HTML.
            records.append({"election_code":p[3],"source_election_date_identifier":date,
                "source_url":final,"source_page_sha256":sha(raw),"parties":rows,
                "seats_reported_sum":sum(r["seats"] for r in rows) if rows else None,
                "status":"published_party_seat_reference" if rows else "no_party_table_available",
                "certifies_current_legislature":False})
        except Exception as e:
            records.append({"election_code":expected,"source_url":u,"status":"unavailable","error_type":type(e).__name__,"parties":[],"certifies_current_legislature":False})
    return dict(ref,results=records,runtime_admission=False)

def main():
    p=argparse.ArgumentParser();p.add_argument("--output",type=Path,required=True);a=p.parse_args()
    b,url=get(INDEX);refs=discover(b);print("IPU index countries:",len(refs),flush=True)
    records={}
    with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
        futures={pool.submit(collect,v):k for k,v in refs.items()}
        for f in concurrent.futures.as_completed(futures):
            k=futures[f]
            try:records[k]=f.result()
            except Exception as e:records[k]={"iso2":k,"source_url":refs[k]["source_url"],"status":"unavailable","error_type":type(e).__name__,"results":[],"runtime_admission":False}
            print(k,len(records[k]["results"]),flush=True)
    for k in CODES-set(records):records[k]={"iso2":k,"status":"not_in_source_index","results":[],"runtime_admission":False}
    result={"schema":"wp115-ipu-party-seat-reference-v2","as_of":AS_OF,"retrieved_at":dt.datetime.now(dt.timezone.utc).isoformat(),
        "source_index_url":url,"source_index_sha256":sha(b),"countries":[records[k] for k in sorted(CODES)],
        "runtime_admission":False}
    n=sum(len(r["parties"]) for c in records.values() for r in c["results"])
    require(n>0,"no verified results extracted")
    a.output.write_text(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+"\n",encoding="utf-8")
    print("IPU party seat entries:",n,"countries with results:",sum(any(r["parties"] for r in c["results"]) for c in records.values()))
if __name__=="__main__":main()
