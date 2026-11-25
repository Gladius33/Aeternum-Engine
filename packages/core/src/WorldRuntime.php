<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Single reviewed composition for all entrypoints. The registry supplies
 * data-only packs; only application code may supply executable handlers.
 */
final readonly class WorldRuntime
{
    /** @var array<string,GamePack> */
    public array $packs;
    /** @var array<string,RuleSet> */
    public array $rules;

    public function __construct(array $packs,array $commands=[],array $events=[])
    {
        $this->rules=TrustedWorldRules::bind($packs,$commands,$events);
        $this->packs=$packs;
    }

    public static function load(): self
    {
        // Explicit application composition point. Never resolve a class name,
        // executable path or callback supplied by a manifest or HTTP request.
        return new self(ConfiguredWorlds::load());
    }

    public function pack(string $world): GamePack
    {
        return $this->packs[$world]??throw new \InvalidArgumentException('Unapproved world');
    }

    public function rule(string $world): RuleSet
    {
        return $this->rules[$world]??throw new \InvalidArgumentException('Unapproved world');
    }

    /** Validate optional injected rules before starting a persistent operation. */
    public static function assertBindings(array $packs,array $rules): void
    {
        foreach ($rules as $world=>$rule) {
            $pack=$packs[$world]??null;
            if (!$pack instanceof GamePack || !$rule instanceof RuleSet
                || $rule->id!==$pack->id || $rule->version!==$pack->version
                || !hash_equals($pack->digest,$rule->packDigest??$rule->artifactDigest)) {
                throw new \InvalidArgumentException('Invalid approved world rule binding');
            }
        }
    }
}
