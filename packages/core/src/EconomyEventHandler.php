<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class EconomyEventHandler implements EventEffectsHandler
{
    public function __construct(private EconomyCommandHandler $economy) {}

    public function resolve(ScheduledEvent $event,array $state): EventResult
    {
        return $this->economy->complete($event,$state);
    }

    public function apply(ScheduledEvent $event,array $state): array
    {
        return $this->resolve($event,$state)->state;
    }
}
