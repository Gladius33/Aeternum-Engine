<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,FrozenClock,ScheduledEvent,EventHandler,Command,CommandHandler,CommandResult};

$jClock = new FrozenClock(1000);
$j = new PdoScheduler(db(),'journal',$jClock);
$j->initialize();
$rules = new class implements CommandHandler {
    public function apply(Command $command,array $state,int $at): CommandResult {
        if ($command->type === 'grant') return new CommandResult(['stock'=>100]);
        $state['stock'] -= 25;
        return new CommandResult($state,[new ScheduledEvent($command->worldId,'done-'.$command->id,'complete',$at+10)]);
    }
};
$events = new class implements EventHandler {
    public function apply(ScheduledEvent $event,array $state): array {
        $state['done'] = ($state['done'] ?? 0)+1;
        return $state;
    }
};
$j->execute(new Command('journal','grant','grant'),$rules);
$j->execute(new Command('journal','build','build'),$rules);
$j->execute(new Command('journal','build','build'),$rules);
$jClock->advanceTo(1010);
$j->processDue($events);
$j = new PdoScheduler(db(),'journal',$jClock);
$report = $j->verifyReplay($rules,$events);
check($report->entries === 3 && $report->effectiveAt === 1010 && $report->checkpointAt === -1,'journal replay across reconnect in mixed command/event order');
check($report->stateDigest === hash('sha256',\Aeternum\Core\ScalarMap::encode($j->state())),'replay matches persistent state');
$wrong = new class implements EventHandler {
    public function apply(ScheduledEvent $event,array $state): array {
        $state['done'] = 999;
        return $state;
    }
};
rejects(fn()=> $j->verifyReplay($rules,$wrong),UnexpectedValueException::class,'replay detects changed rules');
$wrongFollowup = new class implements CommandHandler {
    public function apply(Command $command,array $state,int $at): CommandResult {
        if ($command->type === 'grant') return new CommandResult(['stock'=>100]);
        $state['stock'] -= 25;
        return new CommandResult($state,[new ScheduledEvent($command->worldId,'done-'.$command->id,'complete',$at+11)]);
    }
};
rejects(fn()=> $j->verifyReplay($wrongFollowup,$events),UnexpectedValueException::class,'replay detects changed follow-up schedule');
rejects(fn()=> $j->verifyReplay($rules,$events,2),LengthException::class,'replay has explicit size limit');
check($j->verifyReplay($rules,$events)->entries === 3,'failed verification left journal unchanged');
