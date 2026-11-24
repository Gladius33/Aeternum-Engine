'use strict';
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {runInNewContext}=require('node:vm');
const path=require('node:path');
const doc={createElementNS(ns,tag){return {tag,attrs:{},children:[],setAttribute(k,v){this.attrs[k]=String(v)},append(n){this.children.push(n)}}}};
const window={};
runInNewContext(readFileSync(path.join(__dirname,'../../public/living-art.js'),'utf8'),{window,document:doc});
const art=window.AeternumLivingArt,walk=n=>[n,...n.children.flatMap(walk)];
for(const resource of ['wood','grain','food','spice','silver','gold','steel','iron','stone','coal','energy','electricity','oil','water','goods','future_resource']){
    const root=doc.createElementNS('','svg');
    assert.ok(art.cargo(root,resource));
    assert.equal(root.children[0].attrs['data-cargo-resource'],resource);
    assert.ok(walk(root).length<100);
    assert.ok(!walk(root).some(n=>Object.values(n.attrs).some(v=>/NaN|Infinity|undefined/.test(v))));
}
for(const theme of ['medieval','industrial','guild']){
    const root=doc.createElementNS('','svg');
    assert.ok(art.marketHall(root,theme));
    assert.equal(root.children[0].attrs['data-commerce-hall'],theme);
    assert.ok(walk(root).length<200);
}
assert.ok(art.exchangeTerms('wood',1,'grain',1000000000000));
for(const n of [0,-1,1.5,NaN,Infinity,1000000000001])assert.equal(art.exchangeTerms('wood',n,'grain',1),null);
assert.equal(art.exchangeTerms('wood',1,'wood',1),null);
assert.equal(art.exchangeTerms('',1,'grain',1),null);
console.log('G18: 16 resource glyphs, three halls and exact exchange validation passed.');
