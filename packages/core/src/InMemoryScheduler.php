<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Single-process reference implementation, not a production transaction boundary. */
final class InMemoryScheduler
{
    /** @var array<string,array{event:ScheduledEvent,sequence:int,done:bool}> */
    private array $events = [];
    private int $sequence = 0;
    private int $watermark = -1;
    private bool $processing = false;
    private array $state = [];

    public function __construct(private readonly string $worldId, private readonly Clock $clock) {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $worldId)) {
            throw new \InvalidArgumentException('Invalid world identifier');
        }
    }

    public function schedule(ScheduledEvent $event): bool {
        if ($this->processing) throw new \LogicException('Reentrant mutation prohibited');
        if ($event->worldId !== $this->worldId) throw new \InvalidArgumentException('Wrong world');
        $key = 'event:' . $event->id;
        if (isset($this->events[$key])) {
            if (!$this->events[$key]['event']->equals($event)) throw new \DomainException('Conflicting event ID');
            return false;
        }
        if ($event->dueAt < $this->watermark) throw new \DomainException('Event precedes committed logical time');
        $this->events[$key] = ['event' => $event, 'sequence' => ++$this->sequence, 'done' => false];
        return true;
    }

    /** @return list<string> IDs committed in chronological order */
    public function processDue(EventHandler $handler, int $limit = 100): array {
        if ($limit < 1) throw new \InvalidArgumentException('Limit must be positive');
        if ($this->processing) throw new \LogicException('Reentrant mutation prohibited');
        $cutoff = $this->clock->now();
        if ($cutoff < $this->watermark) throw new \DomainException('Clock precedes committed logical time');
        $due = array_filter($this->events, static fn(array $row): bool => !$row['done'] && $row['event']->dueAt <= $cutoff);
        uasort($due, static fn(array $a, array $b): int => [$a['event']->dueAt, $a['sequence']] <=> [$b['event']->dueAt, $b['sequence']]);
        $committed = [];
        $this->processing = true;
        try {
            foreach (array_slice($due, 0, $limit, true) as $key => $row) {
                $event = $row['event'];
                $next = $handler->apply($event, $this->state);
                $validated = [];
                foreach ($next as $name => $value) {
                    if (!is_string($name) || !(is_int($value) || is_string($value) || is_bool($value) || $value === null)) {
                        throw new \UnexpectedValueException('Invalid world state');
                    }
                    $validated[$name] = $value;
                }
                $this->state = $validated;
                $this->events[$key]['done'] = true;
                $this->watermark = $event->dueAt;
                $committed[] = $event->id;
            }
        } finally {
            $this->processing = false;
        }
        return $committed;
    }
    public function state(): array { return $this->state; }
    public function pendingCount(): int {
        return count(array_filter($this->events, static fn(array $row): bool => !$row['done']));
    }
}
