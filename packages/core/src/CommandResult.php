<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class CommandResult
{
    /** @param list<ScheduledEvent> $events */
    public function __construct(public array $state, public array $events = [], public array $movements = [])
    {
        ScalarMap::validate($state);
        if (!array_is_list($movements) || count($movements)>1000) throw new \InvalidArgumentException('Invalid ledger list');
        foreach ($movements as $movement) {
            if (!$movement instanceof ResourceMovement) throw new \InvalidArgumentException('Invalid ledger movement');
        }
        if (!array_is_list($events)) throw new \InvalidArgumentException('Events must be a list');
        foreach ($events as $event) {
            if (!$event instanceof ScheduledEvent) throw new \InvalidArgumentException('Invalid planned event');
        }
    }
}
