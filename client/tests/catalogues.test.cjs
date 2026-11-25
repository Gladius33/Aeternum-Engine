// Unit verification of the exact TypeScript translation and action-label modules.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const ts=require('typescript');
function loadTypeScript(relative) {
  const source=fs.readFileSync(path.join(__dirname,'..',relative),'utf8');
  const js=ts.transpileModule(source,{compilerOptions:{module:ts.ModuleKind.CommonJS,target:ts.ScriptTarget.ES2022}}).outputText;
  const exports={};
  vm.runInNewContext(js,{exports},{timeout:1000,filename:relative});
  return exports;
}
const locale=loadTypeScript('src/messages.ts');
const actions=loadTypeScript('src/command-labels.ts');
test('French and English share the exact same UI message keys',()=>{
  assert.deepEqual(Object.keys(locale.messages.fr).sort(),Object.keys(locale.messages.en).sort());
  for(const lang of ['fr','en']) {
    for(const key of Object.keys(locale.messages.en)) {
      assert.equal(typeof locale.translate(lang,key),'string');
      assert.ok(locale.translate(lang,key).trim().length>0,key);
    }
  }
});
test('unknown languages fail closed to English without changing the selected world',()=>{
  assert.equal(locale.languageOf('fr'),'fr');
  assert.equal(locale.languageOf('de'),'en');
  assert.equal(locale.languageOf('en'),'en');
});
test('server-acknowledged command types have human-readable bilingual names',()=>{
  assert.equal(actions.actionLabel('fr','market.accept'),'Échange de troc confirmé');
  assert.equal(actions.actionLabel('en','building.place'),'Foundation placed');
  assert.equal(actions.actionLabel('fr','industry.ship'),'Marchandises expédiées');
  assert.equal(actions.actionLabel('en','future.action'),'future action');
});

test('F05 campaign actions distinguish scheduled work from completion in both languages',()=>{
  assert.equal(actions.actionLabel('en','pack.campaign.research'),'Research scheduled');
  assert.equal(actions.actionLabel('fr','pack.campaign.expedition'),'Expédition planifiée');
  assert.equal(actions.actionLabel('fr','pack.diplomacy.accept'),'Trêve acceptée');
});
const events=loadTypeScript('src/player-events.ts');
test('F05 typed presentation contracts discard stale or malformed events',()=>{
  const scope=events.playerContext({world:'lab-guild-f04',account:'alice'});
  assert.equal(scope.world,'lab-guild-f04');
  assert.equal(events.playerContext({world:'bad/world',account:'alice'}),null);
  assert.equal(events.playerContext({world:'a'.repeat(129),account:'alice'}),null);
  const good={world:scope.world,account:'alice',type:'pack.campaign.research',id:'cmd-1'};
  assert.equal(events.playerReceipt(good,scope).id,'cmd-1');
  assert.equal(events.playerReceipt({...good,account:'bob'},scope),null);
  assert.equal(events.playerReceipt({...good,world:'other'},scope),null);
  assert.equal(events.playerReceipt({...good,type:'<script>'},scope),null);
  assert.equal(events.playerReceipt(good,null),null);
  assert.equal(events.playerFlag({pending:'true'},'pending'),false);
  assert.equal(events.playerFlag({pending:true},'pending'),true);
});
