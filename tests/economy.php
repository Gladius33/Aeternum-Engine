<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,FrozenClock,Command,EconomyCommandHandler,ResourceDefinition,EventHandler,ScheduledEvent,RuleSet};

$ec = new FrozenClock(100);
$econ = new EconomyCommandHandler([new ResourceDefinition('wood',100,1000,3,2),new ResourceDefinition('stone',0,200,1,4)]);
$ee = new class implements EventHandler {
    public function apply(ScheduledEvent $event,array $state): array { throw new DomainException('No events in reference economy'); }
};
$er = new RuleSet('economy','1.0',hash('sha256','integration-test-rules'),$econ,$ee);
$epdo = db();
$e = new PdoScheduler($epdo,'economy',$ec,$er);
$e->initialize();
foreach (['alice','bob'] as $account) {
    $e->execute(new Command('economy','open-'.$account,'economy.open',['account'=>$account,'resource'=>'wood']),$econ);
}
$ec->advanceTo(111);
$before = $econ->balanceAt($e->state(),'alice','wood',111);
check($before->stock === 116 && $before->remainder === 1,'lazy balance projected with fractional carry');
$transfer = new Command('economy','t1','economy.transfer',['from'=>'alice','to'=>'bob','resource'=>'wood','units'=>60]);
$receipt = $e->execute($transfer,$econ);
check($econ->balanceAt($e->state(),'alice','wood',111)->stock === 56 && $econ->balanceAt($e->state(),'bob','wood',111)->stock === 176,'persistent lazy settlement and transfer');
check($e->execute($transfer,$econ) == $receipt,'transfer retry idempotent');
$ledger = $epdo->query("SELECT account_id,delta_units,balance_after,reason FROM engine_resource_ledger WHERE world_id='economy' ORDER BY journal_no,line_no")->fetchAll(PDO::FETCH_NUM);
check(count($ledger) === 6 && (int)$ledger[4][1] === -60 && (int)$ledger[5][1] === 60,'balanced transfer recorded in persistent ledger');
check(array_sum(array_column($ledger,1)) === 232,'resource ledger conserves transfers and records production');
$ec->advanceTo(112);
rejects(fn()=> $e->execute(new Command('economy','bad','economy.transfer',['from'=>'alice','to'=>'bob','resource'=>'wood','units'=>900]),$econ),DomainException::class,'insufficient transfer rejected');
check($e->state() === $receipt->state && count($epdo->query("SELECT journal_no FROM engine_resource_ledger WHERE world_id='economy'")->fetchAll()) === 6,'failed transfer rolls back lazy settlement and ledger');
rejects(fn()=> $e->execute(new Command('economy','same','economy.transfer',['from'=>'alice','to'=>'alice','resource'=>'wood','units'=>1]),$econ),InvalidArgumentException::class,'self transfer rejected');
rejects(fn()=> $e->execute(new Command('economy','unknown','economy.transfer',['from'=>'alice','to'=>'bob','resource'=>'stone','units'=>1]),$econ),DomainException::class,'unopened resource account rejected');
$e->execute(new Command('economy','settle','economy.settle',['account'=>'alice','resource'=>'wood']),$econ);
check($econ->balanceAt($e->state(),'alice','wood',112)->stock === 58,'fractional carry survives persistent transfer');
check($e->verifyReplay($econ,$ee)->entries === 4,'economy replay verifies ledger and state');
// Test-only tampering; replay must reject divergent ledger content.
$epdo->exec("UPDATE engine_resource_ledger SET delta_units = 59 WHERE world_id='economy' AND journal_no=3 AND line_no=4");
rejects(fn()=> $e->verifyReplay($econ,$ee),UnexpectedValueException::class,'replay detects ledger divergence');

$cap = new EconomyCommandHandler([new ResourceDefinition('ore',90,100,0,1)]);
$capRules = new RuleSet('capacity','1',hash('sha256','capacity-test'),$cap,$ee);
$ce = new PdoScheduler(db(),'capacity',new FrozenClock(10),$capRules);
$ce->initialize();
foreach (['a','b'] as $account) $ce->execute(new Command('capacity','open-'.$account,'economy.open',['account'=>$account,'resource'=>'ore']),$cap);
$capState = $ce->state();
rejects(fn()=> $ce->execute(new Command('capacity','overflow','economy.transfer',['from'=>'a','to'=>'b','resource'=>'ore','units'=>11]),$cap),DomainException::class,'target capacity rejects transfer');
check($ce->state() === $capState && $ce->verifyReplay($cap,$ee)->entries === 2,'capacity rejection is atomic');
