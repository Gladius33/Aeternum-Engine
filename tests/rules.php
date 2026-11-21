<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,FrozenClock,ScheduledEvent,EventHandler,Command,CommandHandler,CommandResult,RuleSet,RuleRegistry};
$rClock = new FrozenClock(100);
$rc = new class implements CommandHandler {
    public function apply(Command $command,array $state,int $at): CommandResult {
        return new CommandResult(['stock'=>10],[new ScheduledEvent($command->worldId,'complete','complete',$at+1)]);
    }
};
$re = new class implements EventHandler {
    public function apply(ScheduledEvent $event,array $state): array {
        $state['stock'] += 1; return $state;
    }
};
$rulesA = new RuleSet('game','1.0.0',hash('sha256','test-artifact-v1'),$rc,$re);
$rulesB = new RuleSet('game','2.0.0',hash('sha256','test-artifact-v2'),$rc,$re);
$rulesC = new RuleSet('game','1.0.0',hash('sha256','other-artifact'),$rc,$re);
$registry = new RuleRegistry();
$registry->register($rulesA);
$registry->register($rulesB);
check($registry->get($rulesA->key) === $rulesA,'registered rules resolved by exact identity');
rejects(fn()=> $registry->register($rulesA),DomainException::class,'duplicate rule registration rejected');
rejects(fn()=> $registry->get($rulesC->key),DomainException::class,'unknown rule artifact rejected');
$bound = new PdoScheduler(db(),'pinned',$rClock,$rulesA);
$bound->initialize();
$bound->execute(new Command('pinned','start','start'),$rc);
$rClock->advanceTo(101);
$wrongVersion = new PdoScheduler(db(),'pinned',$rClock,$rulesB);
$wrongArtifact = new PdoScheduler(db(),'pinned',$rClock,$rulesC);
$legacy = new PdoScheduler(db(),'pinned',$rClock);
rejects(fn()=> $wrongVersion->initialize(),DomainException::class,'cannot reprovision pinned world with new version');
rejects(fn()=> $wrongArtifact->processDue($re),DomainException::class,'changed artifact denied');
rejects(fn()=> $legacy->processDue($re),DomainException::class,'legacy adapter cannot mutate pinned world');
rejects(fn()=> $wrongVersion->state(),DomainException::class,'mismatched rules cannot read through scheduler');
$wrongHandler = new class implements EventHandler {
    public function apply(ScheduledEvent $event,array $state): array { return ['stock'=>999]; }
};
rejects(fn()=> $bound->processDue($wrongHandler),DomainException::class,'unbound event handler denied');
check($bound->pendingCount() === 1,'rejected handlers did not complete event');
$bound = new PdoScheduler(db(),'pinned',$rClock,$registry->get($rulesA->key));
check($bound->processDue($re) === ['complete'] && $bound->state() === ['stock'=>11],'same pinned version survives reconnect');
check($bound->verifyReplay($rc,$re)->entries === 2,'pinned replay works');
rejects(fn()=> $bound->verifyReplay($rc,$wrongHandler),DomainException::class,'pinned replay rejects alternate handler');
