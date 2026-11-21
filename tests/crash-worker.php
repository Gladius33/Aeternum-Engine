<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,FrozenClock,ScheduledEvent,EventHandler};
$q = new PdoScheduler(db(),'crash',new FrozenClock(500));
$q->processDue(new class implements EventHandler {
    public function apply(ScheduledEvent $event,array $state): array {
        // Test-only handshake while the world SQL transaction and lock are held.
        echo "transaction-open\n";
        fflush(STDOUT);
        sleep(30);
        return ['n'=>1];
    }
});
