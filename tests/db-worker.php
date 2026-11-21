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
} else {
    throw new InvalidArgumentException('Unknown worker action');
}
