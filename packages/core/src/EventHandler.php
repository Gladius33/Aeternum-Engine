<?php
declare(strict_types=1);
namespace Aeternum\Core;
interface EventHandler
{
    /** Pure deterministic handler: returns new scalar world state; no I/O or wall-clock access.
     * @param array<string,int|string|bool|null> $state
     * @return array<string,int|string|bool|null>
     */
    public function apply(ScheduledEvent $event, array $state): array;
}
