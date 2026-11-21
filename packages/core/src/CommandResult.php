<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class CommandResult
{
    /** @param list<ScheduledEvent> $events */
    public function __construct(public array $state, public array $events = [])
    {
        ScalarMap::validate($state);
        if (!array_is_list($events)) throw new \InvalidArgumentException('Events must be a list');
        foreach ($events as $event) {
            if (!$event instanceof ScheduledEvent) throw new \InvalidArgumentException('Invalid planned event');
        }
    }
}
