<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,PdoOutbox,FrozenClock,Command,CommandHandler,CommandResult,ScheduledEvent,EventHandler};

$oClock = new FrozenClock(100);
$o = new PdoScheduler(db(),'outbox',$oClock);
$o->initialize();
$wall = new FrozenClock(1000);
$queue = new PdoOutbox(db(),'outbox',$wall);
$queue2 = new PdoOutbox(db(),'outbox',$wall);
$commandHandler = new class implements CommandHandler {
    public function apply(Command $command,array $state,int $at): CommandResult {
        if ($command->type === 'fail') throw new DomainException('Rejected');
        return new CommandResult(['secret'=>'not for outbox'],[new ScheduledEvent($command->worldId,'finish','done',$at+10)]);
    }
};
$cmd = new Command('outbox','start','start',['private'=>'value']);
$o->execute($cmd,$commandHandler);
$o->execute($cmd,$commandHandler);
check($queue->pendingCount() === 1,'one outbox entry for idempotent command');
rejects(fn()=> $o->execute(new Command('outbox','bad','fail'),$commandHandler),DomainException::class,'failed command');
check($queue->pendingCount() === 1,'failed command emits no outbox entry');
$d = $queue->claim(5);
check($d !== null && $d->attempts === 1 && $d->envelope['kind'] === 'command' && $d->envelope['id'] === 'start','claim committed transition metadata');
check(!isset($d->envelope['private']) && !isset($d->envelope['secret']),'no command payload or state exposed');
check($queue2->claim(5) === null,'active lease excludes second worker');
$wall->advanceTo(1005);
$again = $queue2->claim(5);
check($again !== null && $again->idempotencyKey() === $d->idempotencyKey() && $again->leaseToken !== $d->leaseToken && $again->attempts === 2,'expired lease redelivered with stable key');
check(!$queue->acknowledge($d),'stale lease cannot acknowledge');
check($queue2->release($again,10),'current lease released with delay');
check($queue->claim() === null,'retry delay respected');
$wall->advanceTo(1015);
$last = $queue->claim();
check($last !== null && $last->attempts === 3,'retry after delay');
check($queue->acknowledge($last) && !$queue->acknowledge($last) && $queue->pendingCount() === 0,'one durable acknowledgment');
$oClock->advanceTo(110);
$o->processDue(new class implements EventHandler {
    public function apply(ScheduledEvent $event,array $state): array { $state['done'] = true; return $state; }
});
$eventDelivery = $queue2->claim();
check($eventDelivery !== null && $eventDelivery->envelope['kind'] === 'event' && $eventDelivery->journalNo === 2,'resolved event emitted after commit');
$outside = new PdoOutbox(db(),'world-B',$wall);
rejects(fn()=> $outside->acknowledge($eventDelivery),InvalidArgumentException::class,'delivery world isolation');
check($queue2->acknowledge($eventDelivery),'event acknowledged');

$crash = new PdoScheduler(db(),'crash',new FrozenClock(500));
$crash->initialize();
$crash->schedule(new ScheduledEvent('crash','crash-event','inc',200));
$p = proc_open([PHP_BINARY,__DIR__.'/crash-worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
if (!is_resource($p)) throw new RuntimeException('Cannot spawn crash worker');
fclose($pipes[0]);
try {
    stream_set_timeout($pipes[1],10);
    $signal = fgets($pipes[1]);
    if ($signal !== "transaction-open\n") throw new RuntimeException('Crash worker failed to reach transaction: '.($signal ?: 'no signal'));
    if (!proc_terminate($p,9)) throw new RuntimeException('Cannot terminate worker');
} finally {
    fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
}
$crashOutbox = new PdoOutbox(db(),'crash',new FrozenClock(1000));
check($crash->state() === [] && $crash->pendingCount() === 1 && $crashOutbox->pendingCount() === 0,'SIGKILL before commit leaves no committed state or outbox');
$crash->processDue(new class implements EventHandler {
    public function apply(ScheduledEvent $event,array $state): array { return ['n'=>1]; }
});
check($crash->state() === ['n'=>1] && $crash->pendingCount() === 0 && $crashOutbox->pendingCount() === 1,'abrupt termination recovered exactly once');
