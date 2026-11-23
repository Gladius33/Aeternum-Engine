<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,PdoMemberships,FrozenClock,Command,EconomyCommandHandler,EconomyEventHandler,ResourceDefinition,RuleSet};

$marketEcon=new EconomyCommandHandler([new ResourceDefinition('ore',100,120,0,1),new ResourceDefinition('coin',100,120,0,1)]);
$marketRules=new RuleSet('market-test','1',hash('sha256','market-test'),$marketEcon,new EconomyEventHandler($marketEcon));
$marketClock=new FrozenClock(500);
$marketWorld='market-test';
$market=new PdoScheduler(db(),$marketWorld,$marketClock,$marketRules);
$market->initialize();
$marketMembers=new PdoMemberships(db(),$marketWorld);
foreach (['seller'=>'s','buyer'=>'b','other'=>'o'] as $actor=>$account) {
    $marketMembers->grant($actor); $marketMembers->assign($account,$actor);
    foreach (['ore','coin'] as $resource) {
        $market->executeForActor($actor,new Command($marketWorld,'open-'.$resource,'economy.open',['account'=>$account,'resource'=>$resource]),$marketEcon);
    }
}
$marketCmd=fn($id,$type,$p)=>new Command($marketWorld,$id,'market.'.$type,$p);
$terms=['offer'=>'first','seller'=>'s','giveResource'=>'ore','giveUnits'=>15,'wantResource'=>'coin','wantUnits'=>10];
$create=$marketCmd('create','create',$terms);
$accept=$marketCmd('accept','accept',['offer'=>'first','seller'=>'s','buyer'=>'b']);
$stock=fn($actor,$account,$resource)=>$market->balanceForActor($actor,$account,$resource,$marketEcon)->stock;
rejects(fn()=> $market->executeForActor('buyer',$create,$marketEcon),DomainException::class,'market cannot reserve foreign resources');
$bad=$terms; $bad['giveUnits']=101;
rejects(fn()=> $market->executeForActor('seller',$marketCmd('overspend','create',$bad),$marketEcon),DomainException::class,'market rejects insufficient escrow');
$bad=$terms; $bad['wantResource']='ore';
rejects(fn()=> $market->executeForActor('seller',$marketCmd('same','create',$bad),$marketEcon),InvalidArgumentException::class,'market rejects same-resource barter');
$receipt=$market->executeForActor('seller',$create,$marketEcon);
check($stock('seller','s','ore')===85,'market reserves real stock');
check($market->executeForActor('seller',$create,$marketEcon)==$receipt,'market create retry does not reserve twice');
rejects(fn()=> $market->executeForActor('seller',$marketCmd('new','create',$terms),$marketEcon),DomainException::class,'offer ID cannot be reused');
rejects(fn()=> $market->executeForActor('other',$accept,$marketEcon),DomainException::class,'foreign buyer ownership denied');
rejects(fn()=> $market->executeForActor('buyer',$marketCmd('forged','accept',['offer'=>'first','seller'=>'o','buyer'=>'b']),$marketEcon),DomainException::class,'forged seller denied');
$marketMembers->revoke('seller');
rejects(fn()=> $market->executeForActor('buyer',$accept,$marketEcon),DomainException::class,'revoked seller cannot receive payment');
$marketMembers->grant('seller');
$accepted=$market->executeForActor('buyer',$accept,$marketEcon);
check($stock('buyer','b','ore')===115 && $stock('buyer','b','coin')===90 && $stock('seller','s','coin')===110,'market atomically delivers and pays');
$reconnect=new PdoScheduler(db(),$marketWorld,$marketClock,$marketRules);
check($reconnect->executeForActor('buyer',$accept,$marketEcon)==$accepted,'market accept retry survives reconnect');
rejects(fn()=> $market->executeForActor('buyer',$marketCmd('second','accept',$accept->payload),$marketEcon),DomainException::class,'filled offer cannot sell twice');
rejects(fn()=> $market->executeForActor('seller',$marketCmd('cancel-filled','cancel',['offer'=>'first','seller'=>'s']),$marketEcon),DomainException::class,'filled offer cannot refund');
$terms['offer']='cancel'; $terms['giveUnits']=10; $terms['wantUnits']=5;
$market->executeForActor('seller',$marketCmd('create-cancel','create',$terms),$marketEcon);
$cancel=$marketCmd('cancel','cancel',['offer'=>'cancel','seller'=>'s']);
rejects(fn()=> $market->executeForActor('buyer',$cancel,$marketEcon),DomainException::class,'foreign cancellation denied');
$before=$market->state();
rejects(fn()=> $market->executeForActor('buyer',$marketCmd('overflow','accept',['offer'=>'cancel','seller'=>'s','buyer'=>'b']),$marketEcon),DomainException::class,'buyer capacity prevents exchange');
check($market->state()===$before,'failed market transaction preserves entire state');
$cancelReceipt=$market->executeForActor('seller',$cancel,$marketEcon);
check($market->executeForActor('seller',$cancel,$marketEcon)==$cancelReceipt && $stock('seller','s','ore')===85,'cancellation refunds exactly once');
$terms['offer']='race'; $terms['giveUnits']=5;
$market->executeForActor('seller',$marketCmd('create-race','create',$terms),$marketEcon);
parallel($marketWorld,'market-accept');
check($stock('buyer','b','ore')===120 && $stock('buyer','b','coin')===85 && $stock('seller','s','coin')===115,'four competing acceptances execute one exchange');
check((int)db()->query("SELECT COUNT(*) FROM engine_commands WHERE world_id='market-test' AND client_request_id LIKE 'race-%'")->fetchColumn()===1,'only one concurrent acceptance receipt');
check((int)db()->query("SELECT SUM(delta_units) FROM engine_resource_ledger WHERE world_id='market-test' AND resource_id='ore'")->fetchColumn()===300,'market ledger conserves ore after all offers close');
check((int)db()->query("SELECT SUM(delta_units) FROM engine_resource_ledger WHERE world_id='market-test' AND resource_id='coin'")->fetchColumn()===300,'market ledger conserves payment resource');
check($market->verifyReplay($marketEcon,$marketRules->events)->entries===12,'market replay verifies all committed transitions');

// A committed market view must be bounded, world scoped, and membership filtered.
check($market->marketForActor('buyer')===['offers'=>[],'nextAfter'=>null],'market view omits filled and cancelled offers');
foreach (['p2'=>'s','p1'=>'s','p3'=>'o'] as $offer=>$seller) {
    $market->executeForActor($seller==='s'?'seller':'other',$marketCmd('create-'.$offer,'create',['offer'=>$offer,'seller'=>$seller,'giveResource'=>'ore','giveUnits'=>1,'wantResource'=>'coin','wantUnits'=>1]),$marketEcon);
}
$beforeView=$market->state();
check(array_column($market->marketForActor('buyer',null,2,'ore','coin')['offers'],'id')===['p1','p2'],'market filtered pagination first page');
check(array_column($market->marketForActor('buyer','p2',2,'ore','coin')['offers'],'id')===['p3'],'market filtered pagination second page');
check($market->marketForActor('buyer',null,2,'coin','ore')['offers']===[],'market filter excludes reversed pair');
rejects(fn()=> $market->marketForActor('buyer',null,2,'invalid:resource'),InvalidArgumentException::class,'market filter rejects malformed resource');
$pageOne=$market->marketForActor('buyer',null,2);
check(array_column($pageOne['offers'],'id')===['p1','p2'] && $pageOne['nextAfter']==='p2','market view stable first page');
$pageTwo=$market->marketForActor('buyer',$pageOne['nextAfter'],2);
check(array_column($pageTwo['offers'],'id')===['p3'] && $pageTwo['nextAfter']===null,'market view stable final page');
check($market->state()===$beforeView,'market view does not mutate committed state');
$marketMembers->revoke('other');
check(array_column($market->marketForActor('buyer')['offers'],'id')===['p1','p2'],'market view excludes revoked sellers');
$marketMembers->revoke('buyer');
rejects(fn()=> $market->marketForActor('buyer'),DomainException::class,'market view denies revoked requester');
rejects(fn()=> $market->marketForActor('outsider'),DomainException::class,'market view denies nonmember');
$marketMembers->grant('buyer');
rejects(fn()=> $market->marketForActor('buyer','invalid:cursor'),InvalidArgumentException::class,'market view rejects malformed cursor');
rejects(fn()=> $market->marketForActor('buyer',null,101),InvalidArgumentException::class,'market view rejects excessive page size');
$foreignMarket=new PdoScheduler(db(),'market-other-world',$marketClock,$marketRules);
$foreignMarket->initialize();
(new PdoMemberships(db(),'market-other-world'))->grant('buyer');
check($foreignMarket->marketForActor('buyer')===['offers'=>[],'nextAfter'=>null],'market view isolates worlds');
