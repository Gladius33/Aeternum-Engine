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
        // Generic market expiration is a trusted engine event shared by all
        // packs. Typed manifests may add other hooks but cannot redefine the
        // built-in envelope. Old API-1 manifests retain their exact SHA/rules.
        $hooks=$this->eventHooks;
        if ($hooks!==[]) {
            $builtin=PackFields::load((object)['offer'=>'string','seller'=>'string']);
            if (isset($hooks['market.expire']) && $hooks['market.expire']->types!==$builtin->types) {
                throw new \InvalidArgumentException('Pack cannot redefine a built-in engine event');
            }
            $hooks['market.expire']=$builtin;
        }
        return new RuleSet($this->id,$this->version,$this->digest,$economy,
            $hooks===[]?$events:new TypedPackEventHandler($events,$hooks));
    }
}
