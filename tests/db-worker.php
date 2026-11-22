<?php
declare(strict_types=1);
require __DIR__ . '/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,FrozenClock,ScheduledEvent,EventHandler};
$q = new PdoScheduler(db(), $argv[1], new FrozenClock(500));
if ($argv[2] === 'schedule') {
    $q->schedule(new ScheduledEvent($argv[1], 'shared', 'inc', 200));
} elseif ($argv[2] === 'process') {
    $q->processDue(new class implements EventHandler {
        public function apply(ScheduledEvent $e, array $s): array {
            usleep(10000);
            $s['n'] = ($s['n'] ?? 0) + 1;
            return $s;
        }
    }, 100);
} elseif ($argv[2] === 'debit') {
    try {
        $q->execute(new \Aeternum\Core\Command($argv[1],'debit-'.$argv[3],'debit'), new class implements \Aeternum\Core\CommandHandler {
            public function apply(\Aeternum\Core\Command $command,array $state,int $at): \Aeternum\Core\CommandResult {
                if (($state['stock'] ?? 0) < 60) throw new DomainException('Insufficient stock');
                usleep(10000);
                $state['stock'] -= 60;
                return new \Aeternum\Core\CommandResult($state);
            }
        });
    } catch (DomainException $e) {
        if ($e->getMessage() !== 'Insufficient stock') throw $e;
    }
} elseif ($argv[2] === 'economy-transfer') {
    $econ = new \Aeternum\Core\EconomyCommandHandler([new \Aeternum\Core\ResourceDefinition('ore',100,1000,0,1)]);
    $events = new \Aeternum\Core\EconomyEventHandler($econ);
    $rules = new \Aeternum\Core\RuleSet('transfer-race','1',hash('sha256','transfer-race-test'),$econ,$events);
    $q = new PdoScheduler(db(),$argv[1],new FrozenClock(500),$rules);
    try {
        $q->execute(new \Aeternum\Core\Command($argv[1],'transfer-'.$argv[3],'economy.transfer',['from'=>'a','to'=>'b','resource'=>'ore','units'=>60]),$econ);
    } catch (DomainException $e) {
        if ($e->getMessage() !== 'Invalid or insufficient debit') throw $e;
    }
} elseif ($argv[2] === 'market-accept') {
    $econ = new \Aeternum\Core\EconomyCommandHandler([new \Aeternum\Core\ResourceDefinition('ore',100,120,0,1),new \Aeternum\Core\ResourceDefinition('coin',100,120,0,1)]);
    $rules = new \Aeternum\Core\RuleSet('market-test','1',hash('sha256','market-test'),$econ,new \Aeternum\Core\EconomyEventHandler($econ));
    $q = new PdoScheduler(db(),$argv[1],new FrozenClock(500),$rules);
    try {
        $q->executeForActor('buyer',new \Aeternum\Core\Command($argv[1],'race-'.$argv[3],'market.accept',['offer'=>'race','seller'=>'s','buyer'=>'b']),$econ);
    } catch (DomainException $e) {
        if ($e->getMessage() !== 'Market offer unavailable') throw $e;
    }
} else {
    throw new InvalidArgumentException('Unknown worker action');
}
