<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Optional persistent event handler with ledger effects; apply() must return the same state. */
interface EventEffectsHandler extends EventHandler
{
    public function resolve(ScheduledEvent $event,array $state): EventResult;
}
