<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,FrozenClock,Command,EconomyCommandHandler,
    EconomyEventHandler,ResourceDefinition,RuleSet};
if($argc!==2 || !preg_match('/^[0-3]$/D',$argv[1])) exit(2);
$econ=new EconomyCommandHandler([
    new ResourceDefinition('ore',100,100,100,10),
    new ResourceDefinition('coin',0,100,0,10)
]);
$rules=new RuleSet('m03-claim-race','1',hash('sha256','m03-claim-race'),$econ,new EconomyEventHandler($econ));
try {
    $s=new PdoScheduler(db(),'m03-claim-race',new FrozenClock(110),$rules);
    $s->executeForActor('claim-racer',new Command('m03-claim-race',
        'claim-competitor-'.$argv[1],'market.claim',
        ['account'=>'claim-account','resource'=>'ore']),$econ);
    echo "won\n";
} catch (DomainException) {
    echo "denied\n";
} catch (Throwable $e) {
    fwrite(STDERR,get_class($e).": ".$e->getMessage()."\n");
    exit(1);
}
