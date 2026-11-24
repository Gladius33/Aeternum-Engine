// G16 original graphics contract: no simulation or backend mutation.
'use strict';
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {runInNewContext}=require('node:vm');
const path=require('node:path');
const doc={createElementNS(ns,tag){return {tag,attrs:{},children:[],setAttribute(k,v){this.attrs[k]=String(v)},append(n){this.children.push(n)}}}};
const window={};
runInNewContext(readFileSync(path.join(__dirname,'../../public/living-art.js'),'utf8'),{window,document:doc});
const art=window.AeternumLivingArt;
const walk=n=>[n,...n.children.flatMap(walk)];
let count=0;
for(const theme of ['medieval','industrial','guild'])for(const phase of art.phases)for(const scene of ['district','atlas','inspection'])for(const [w,h] of [[360,490],[900,510]]){
    const root=doc.createElementNS('','svg');
    assert.ok(art.atmosphereProfile(theme,phase));
    assert.ok(art.atmosphere(root,theme,phase,w,h,scene));
    assert.ok(art.atmosphereDepth(root,theme,phase,w,h,scene));
    const all=walk(root),ids=all.flatMap(n=>n.attrs.id?[n.attrs.id]:[]);
    assert.equal(new Set(ids).size,ids.length);
    assert.equal(root.children.length,2);
    assert.ok(all.length<110);
    assert.ok(root.children.every(n=>n.attrs['pointer-events']==='none'&&n.attrs['aria-hidden']==='true'));
    assert.ok(!all.some(n=>Object.values(n.attrs).some(v=>/NaN|Infinity|undefined/.test(v))));
    for(const n of all)for(const value of Object.values(n.attrs))if(value.startsWith('url(#'))assert.ok(ids.includes(value.slice(5,-1)),value);
    count++;
}
const root=doc.createElementNS('','svg');
assert.equal(art.atmosphere(root,'unknown','day',10,10),false);
assert.equal(art.atmosphereDepth(root,'guild','day',0,10),false);
assert.equal(root.children.length,0);
assert.equal(count,54);
console.log('G16 atmosphere: '+count+' scene / theme / phase / viewport contracts passed.');
