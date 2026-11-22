<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Immutable, data-only content; executable handlers remain trusted engine code. */
final readonly class GamePack
{
    /**
     * @param list<ResourceDefinition> $resources
     * @param list<ResourceUpgrade> $upgrades
     * @param array<string,PackFields> $components
     * @param array<string,PackFields> $actions
     * @param array<string,PackFields> $eventHooks
     */
    public function __construct(
        public string $id,
        public string $version,
        public string $name,
        public string $digest,
        public array $resources,
        public array $upgrades,
        public array $components=[],
        public array $actions=[],
        public array $eventHooks=[],
    ) {}

    public function sdk(): PackSdk
    {
        return new PackSdk($this);
    }

    public function rules(): RuleSet
    {
        $economy=new EconomyCommandHandler($this->resources,$this->upgrades);
        $events=new EconomyEventHandler($economy);
        // Existing API-1 manifests without hook metadata keep their exact
        // handler semantics, version and digest. New hooks validate envelopes.
        return new RuleSet($this->id,$this->version,$this->digest,$economy,
            $this->eventHooks===[]?$events:new TypedPackEventHandler($events,$this->eventHooks));
    }
}
