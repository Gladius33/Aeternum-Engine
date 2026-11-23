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
    ) {}

    public static function load(\stdClass $raw): self
    {
        $fields=get_object_vars($raw);
        $keys=array_keys($fields);sort($keys,SORT_STRING);
        if ($keys!==['max_attacker_ratio_percent','novice_shield_below','power_metric']) {
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
        return new self($metric,$shield,$ratio);
    }
}
