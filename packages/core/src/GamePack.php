<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Immutable, data-only content. The runtime handler implementation remains trusted application code. */
final readonly class GamePack
{
    /** @param list<ResourceDefinition> $resources @param list<ResourceUpgrade> $upgrades */
    public function __construct(
        public string $id,
        public string $version,
        public string $name,
        public string $digest,
        public array $resources,
        public array $upgrades,
    ) {}

    public function rules(): RuleSet
    {
        $economy = new EconomyCommandHandler($this->resources,$this->upgrades);
        return new RuleSet($this->id,$this->version,$this->digest,$economy,new EconomyEventHandler($economy));
    }
}
