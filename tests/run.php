<?php
declare(strict_types=1);
use Aeternum\Core\{FrozenClock, ScheduledEvent, EventHandler, InMemoryScheduler};
spl_autoload_register(static function(string $class): void {
    $prefix = 'Aeternum\\Core\\';
    if (str_starts_with($class, $prefix)) require __DIR__ . '/../packages/core/src/' . substr($class, strlen($prefix)) . '.php';
});
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS $message\n";
}
function rejects(callable $call, string $class, string $message): void {
    try { $call(); } catch (Throwable $e) { check($e instanceof $class, $message); return; }
    throw new RuntimeException("Expected exception: $message");
}
$clock = new FrozenClock(100);
$queue = new InMemoryScheduler('world1', $clock);
$handler = new class implements EventHandler {
    public function apply(ScheduledEvent $event, array $state): array {
        if ($event->type === 'fail') throw new RuntimeException('Simulated failure');
        $state['trace'] = ($state['trace'] ?? '') . $event->id;
        return $state;
    }
};
$event = new ScheduledEvent('world1','a','append',90,['x'=>1,'y'=>true]);
check($queue->schedule($event), 'new event accepted');
check(!$queue->schedule(new ScheduledEvent('world1','a','append',90,['y'=>true,'x'=>1])), 'idempotent duplicate independent of map order');
rejects(fn()=> $queue->schedule(new ScheduledEvent('world1','a','append',91)), DomainException::class, 'conflicting duplicate rejected');
rejects(fn()=> $queue->schedule(new ScheduledEvent('world2','x','append',90)), InvalidArgumentException::class, 'cross-world event rejected');
$queue->schedule(new ScheduledEvent('world1','b','append',80));
$queue->schedule(new ScheduledEvent('world1','c','append',90));
$queue->schedule(new ScheduledEvent('world1','d','append',110));
check($queue->processDue($handler,2) === ['b','a'], 'chronological order, stable ties, bounded batch');
check($queue->processDue($handler) === ['c'], 'remaining due event processed');
check($queue->processDue($handler) === [], 'future and already processed events excluded');
check($queue->pendingCount() === 1 && $queue->state()['trace'] === 'bac', 'state and pending count');
rejects(fn()=> $queue->schedule(new ScheduledEvent('world1','old','append',89)), DomainException::class, 'no scheduling behind committed time');
$clock->advanceTo(110);
check($queue->processDue($handler) === ['d'], 'future event processed at cutoff');
check(!$queue->schedule($event), 'completed event remains idempotent');
$queue->schedule(new ScheduledEvent('world1','e','fail',110));
$queue->schedule(new ScheduledEvent('world1','f','append',110));
rejects(fn()=> $queue->processDue($handler), RuntimeException::class, 'handler failure propagates');
check($queue->pendingCount() === 2 && $queue->state()['trace'] === 'bacd', 'failed event retained without state change or overtaking');
rejects(fn()=> $clock->advanceTo(109), InvalidArgumentException::class, 'clock cannot regress');
rejects(fn()=> new ScheduledEvent('world1','x','append',0,['bad'=>[]]), InvalidArgumentException::class, 'non-scalar payload rejected');
rejects(fn()=> $queue->processDue($handler,0), InvalidArgumentException::class, 'invalid batch limit rejected');
require __DIR__ . "/peer-key.php";
require __DIR__ . "/proxy-peer.php";
require __DIR__ . "/m12-security-headers.php";
require __DIR__ . "/m12-strict-json.php";
require __DIR__ . "/production.php";
require __DIR__ . "/packs.php";
require __DIR__ . "/m02-registry.php";
require __DIR__ . "/m03-economy.php";
require __DIR__ . "/m05-medieval-pack.php";
require __DIR__ . "/m06-industrial-pack.php";
require __DIR__ . "/m03-typed-market.php";
require __DIR__ . "/market-projection.php";
require __DIR__ . "/m11-alerts.php";
echo "All regression checks passed.\n";
