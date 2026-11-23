<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Optional non-executable policy for player-versus-player eligibility.
 * It grants no attack capability by itself.
 */
final readonly class EngagementRules
{
    private function __construct(
        public string $powerMetric,
        public int $noviceShieldBelow,
        public int $maxAttackerRatioPercent,
        public int $cooldownSeconds=0,
        public bool $diplomacyEnabled=false,
    ) {}

    public static function load(\stdClass $raw): self
    {
        $fields=get_object_vars($raw);
        $keys=array_keys($fields);sort($keys,SORT_STRING);
        if (!in_array($keys,[
            ['max_attacker_ratio_percent','novice_shield_below','power_metric'],
            ['cooldown_seconds','diplomacy_enabled','max_attacker_ratio_percent','novice_shield_below','power_metric']
        ],true)) {
            throw new \InvalidArgumentException('Invalid engagement policy fields');
        }
        $metric=$fields['power_metric'];
        $shield=$fields['novice_shield_below'];
        $ratio=$fields['max_attacker_ratio_percent'];
        if (!is_string($metric) || !in_array($metric,['global','military'],true)
            || !is_int($shield) || $shield<0 || $shield>1000000000
            || !is_int($ratio) || $ratio<100 || $ratio>1000) {
            throw new \InvalidArgumentException('Invalid engagement protection limits');
        }
        $cooldown=$fields['cooldown_seconds']??0;
        $diplomacy=$fields['diplomacy_enabled']??false;
        if (!is_int($cooldown) || $cooldown<0 || $cooldown>86400
            || !is_bool($diplomacy)) {
            throw new \InvalidArgumentException('Invalid diplomacy or cooldown policy');
        }
        return new self($metric,$shield,$ratio,$cooldown,$diplomacy);
    }
}
