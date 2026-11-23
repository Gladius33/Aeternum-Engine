<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Trusted event dispatch runs INSIDE PdoScheduler's ordinary world lock.
 * The deterministic event must already be registered in the pinned pack,
 * with an independently reviewed handler approved by the application owner.
 */
final class TrustedPackEvents implements EventEffectsHandler
{
    /** @var array<string,ApprovedPackEvent> */
    private array $handlers=[];
    /** @var array<string,PackFields> */
    private array $schemas=[];
    private string $fingerprint;

    /**
     * @param list<ApprovedPackEvent> $handlers
     * @param array<string,PackFields> $declaredHooks
     */
    public function __construct(
        array $handlers,
        array $declaredHooks,
        private readonly EventEffectsHandler $builtin,
    ) {
        if (!array_is_list($handlers) || $handlers===[] || count($handlers)>16) {
            throw new \InvalidArgumentException('Invalid trusted event handler list');
        }
        $identities=[];
        foreach ($handlers as $handler) {
            if (!$handler instanceof ApprovedPackEvent) {
                throw new \InvalidArgumentException('Only approved event handlers are accepted');
            }
            $digest=$handler->codeDigest();
            if (!preg_match('/^[a-f0-9]{64}$/D',$digest)) {
                throw new \InvalidArgumentException('Invalid reviewed event code digest');
            }
            $types=$handler->supportedTypes();
            if (!is_array($types) || !array_is_list($types) || $types===[] || count($types)>16) {
                throw new \InvalidArgumentException('Invalid event type list');
            }
            foreach ($types as $type) {
                if (!is_string($type)
                    || !preg_match('/^pack\.[a-z][a-z0-9_-]{0,31}\.[a-z][a-z0-9_-]{0,31}$/D',$type)
                    || !isset($declaredHooks[$type]) || !$declaredHooks[$type] instanceof PackFields
                    || isset($this->handlers[$type]) || count($this->handlers)>=64) {
                    throw new \InvalidArgumentException('Unapproved, duplicate or reserved event type');
                }
                $this->handlers[$type]=$handler;
                $this->schemas[$type]=$declaredHooks[$type];
                $identities[$type]=$digest;
            }
        }
        ksort($identities,SORT_STRING);
        $this->fingerprint=hash('sha256',
            json_encode($identities,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }

    public function fingerprint(): string { return $this->fingerprint; }

    public function resolve(ScheduledEvent $event,array $state): EventResult
    {
        $handler=$this->handlers[$event->type]??null;
        if ($handler===null) return $this->builtin->resolve($event,$state);
        $this->schemas[$event->type]->assertPayload($event->payload);
        return $handler->resolve($event,$state);
    }

    public function apply(ScheduledEvent $event,array $state): array
    {
        return $this->resolve($event,$state)->state;
    }
}
