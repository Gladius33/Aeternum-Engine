<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,FrozenClock,ScheduledEvent,EventHandler,Command,CommandHandler,CommandResult};

$cClock = new FrozenClock(200);
$c = new PdoScheduler(db(),'commands',$cClock);
$c->initialize();
$action = new class implements CommandHandler {
    public function apply(Command $command, array $state, int $at): CommandResult {
        if ($command->type === 'fund') return new CommandResult(['stock'=>100]);
        if (($state['stock'] ?? 0) < 60) throw new DomainException('Insufficient stock');
        $state['stock'] -= 60;
        $events = [new ScheduledEvent($command->worldId, 'finish-'.$command->id, 'finish', $at+10)];
        if ($command->type === 'bad') $events[] = $events[0];
        return new CommandResult($state,$events);
    }
};
$finish = new class implements EventHandler {
    public function apply(ScheduledEvent $event,array $state): array {
        $state['built'] = ($state['built'] ?? 0)+1;
        return $state;
    }
};
$c->execute(new Command('commands','fund','fund'),$action);
$buy = new Command('commands','buy','build',['kind'=>'generic']);
$receipt = $c->execute($buy,$action);
check($receipt->state === ['stock'=>40] && $receipt->scheduledIds === ['finish-buy'] && $receipt->effectiveAt === 200,'command debit and event committed together');
$c = new PdoScheduler(db(),'commands',$cClock);
check($c->execute($buy,$action) == $receipt && $c->pendingCount() === 1,'durable command receipt on duplicate');
rejects(fn()=> $c->execute(new Command('commands','buy','build',['kind'=>'other']),$action),DomainException::class,'conflicting command rejected');
rejects(fn()=> $c->execute(new Command('commands','buy2','build'),$action),DomainException::class,'second debit cannot overspend');
check($c->state() === ['stock'=>40] && $c->pendingCount() === 1,'overspend unchanged');
$cClock->advanceTo(210);
rejects(fn()=> $c->execute(new Command('commands','fund2','fund'),$action),DomainException::class,'new command cannot overtake due event');
check($c->execute($buy,$action) == $receipt,'old receipt readable despite event backlog');
check($c->processDue($finish) === ['finish-buy'],'scheduled action completed');
$c->execute(new Command('commands','fund2','fund'),$action);
rejects(fn()=> $c->execute(new Command('commands','bad','bad'),$action),DomainException::class,'conflicting planned event aborts command');
check($c->state() === ['stock'=>100] && $c->pendingCount() === 0,'partial event and debit rolled back');
rejects(fn()=> $c->execute(new Command('other','x','fund'),$action),InvalidArgumentException::class,'command world scope');

$raceCmd = new PdoScheduler(db(),'race-cmd',new FrozenClock(500));
$raceCmd->initialize();
$raceCmd->execute(new Command('race-cmd','fund','fund'),$action);
parallel('race-cmd','debit');
check($raceCmd->state() === ['stock'=>40],'four concurrent commands cannot double-spend');
check($raceCmd->pendingCount() === 0,'competing debit commands left no unexpected events');
