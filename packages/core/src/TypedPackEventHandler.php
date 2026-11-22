<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Manifest-declared event envelopes are checked before trusted engine
 * dispatch, both during live processing and deterministic replay.
 */
final readonly class TypedPackEventHandler implements EventEffectsHandler
{
    /** @param array<string,PackFields> $hooks */
    public function __construct(private EventEffectsHandler $inner,private array $hooks) {}

    public function resolve(ScheduledEvent $event,array $state): EventResult
    {
        $schema=$this->hooks[$event->type]??throw new \DomainException('Event hook not declared by pack');
        $schema->assertPayload($event->payload);
        return $this->inner->resolve($event,$state);
    }

    public function apply(ScheduledEvent $event,array $state): array
    {
        return $this->resolve($event,$state)->state;
    }
}
