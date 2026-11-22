<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,FrozenClock,Command,EconomyCommandHandler,
    EconomyEventHandler,ResourceDefinition,RuleSet};
$i=filter_var($argv[1]??null,FILTER_VALIDATE_INT);
if($i===false || $i<0 || $i>3) throw new InvalidArgumentException('Invalid worker index');
$e=new EconomyCommandHandler([
    new ResourceDefinition('ore',20,20,0,1),
    new ResourceDefinition('coin',0,20,0,1)
]);
$rules=new RuleSet('m03-parallel-writes','1',hash('sha256','m03-parallel-writes'),
    $e,new EconomyEventHandler($e));
$world='m03-parallel-writes';
$store=new PdoScheduler(db(),$world,new FrozenClock(100),$rules);
$store->executeForActor('writer-'.$i,new Command($world,'create-'.$i,'market.create',[
    'offer'=>'write-'.$i,'seller'=>'writer-account-'.$i,
    'giveResource'=>'ore','giveUnits'=>1,'wantResource'=>'coin','wantUnits'=>1
]),$e);
echo 'ok';
