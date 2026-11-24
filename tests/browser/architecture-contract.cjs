// G15 deterministic SVG contract. No mocked gameplay or server state.
'use strict';
const assert=require('node:assert/strict');
const {readFileSync}=require('node:fs');
const {runInNewContext}=require('node:vm');
const path=require('node:path');
const doc={createElementNS(ns,tag){return {tag,attrs:{},children:[],setAttribute(k,v){this.attrs[k]=String(v)},append(n){this.children.push(n)}}}};
const window={};
runInNewContext(readFileSync(path.join(__dirname,'../../public/game-art.js'),'utf8'),{window,document:doc});
const nodes=n=>[n,...n.children.flatMap(nodes)];
const themes={medieval:['lumberyard','farm','granary','keep'],industrial:['power_plant','steelworks','warehouse','freight_terminal'],guild:['market_hall','caravan_hub']};
const stages=[['unknown',null],['foundation',{level:0,pending:null}],['pending',{level:0,pending:{}}],['built',{level:1,pending:null}],['pending',{level:1,pending:{}}],['built',{level:2,pending:null}]];
let checked=0;
for(const [theme,types] of Object.entries(themes)){
    const art=window.AeternumArt.create({skin:()=>theme,palette:()=>['#222','#333','#555','#777','#888','#999']});
    for(const type of types)for(const [stage,detail] of stages){
        const root=doc.createElementNS('','svg');
        art.model(root,type,detail);
        const all=nodes(root),models=all.filter(n=>n.attrs['data-model']===type),architecture=all.filter(n=>n.attrs['data-architecture']===type);
        assert.equal(models.length,1,type);
        assert.equal(models[0].attrs['data-stage'],stage,type);
        assert.equal(architecture.length,detail?.level>0?1:0,type);
        assert.equal(all.filter(n=>n.attrs['data-architectural-scaffold']).length,detail?.pending?1:0,type);
        if(architecture.length)assert.equal(architecture[0].attrs['data-architecture-level'],String(detail.level));
        assert.ok(all.length<300,type+' bounded SVG size');
        assert.ok(!all.some(n=>Object.values(n.attrs).some(v=>/NaN|Infinity|undefined/.test(v))),type+' finite SVG values');
        const ids=all.flatMap(n=>n.attrs.id?[n.attrs.id]:[]);
        assert.equal(new Set(ids).size,ids.length,type+' unique gradients');
        checked++;
    }
}
assert.equal(checked,60);
console.log('G15 architecture: '+checked+' stage/model contracts passed.');
