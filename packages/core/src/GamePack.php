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
        public array $buildings=[],
        public ?SettlementRules $settlement=null,
        public ?IndustryRules $industry=null,
    ) {}

    public function sdk(): PackSdk
    {
        return new PackSdk($this);
    }

    /**
     * Operator-approved extension objects, not executable names from JSON.
     * Any opt-in creates a different rules key. No opt-in preserves the
     * exact old-world key and its deterministic replay implementation.
     *
     * @param list<ApprovedPackCommand> $trustedCommands
     */
    public function rules(array $trustedCommands=[]): RuleSet
    {
        $extensions=$trustedCommands===[]?null:new TrustedPackCommands($trustedCommands,$this->actions);
        $rulesDigest=$extensions===null?$this->digest:hash('sha256',
            "aeternum-trusted-pack-commands-v1\0".$this->digest."\0".$extensions->fingerprint());
        $economy=new EconomyCommandHandler($this->resources,$this->upgrades,$this->buildings,$this->settlement,$this->industry,$extensions);
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
        return new RuleSet($this->id,$this->version,$rulesDigest,$economy,
            $hooks===[]?$events:new TypedPackEventHandler($events,$hooks));
    }
}
