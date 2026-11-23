<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Deterministic, server-authoritative power comparison; no float ratios. */
final readonly class EngagementGuard
{
    public function __construct(
        private ScoreRules $scoreRules,
        private EngagementRules $rules,
    ) {}

    public function assertEligible(array $state,string $source,string $target): void
    {
        PdoMemberships::validateId($source);
        PdoMemberships::validateId($target);
        if ($source===$target) throw new \DomainException('Engagement denied');
        $projector=new ScoreProjector($this->scoreRules);
        $attacker=$projector->forAccount($state,$source)[$this->rules->powerMetric];
        $defender=$projector->forAccount($state,$target)[$this->rules->powerMetric];
        if ($defender<$this->rules->noviceShieldBelow
            || $attacker*100>$defender*$this->rules->maxAttackerRatioPercent) {
            // Deliberately do not report an opponent's shield or score.
            throw new \DomainException('Engagement denied');
        }
    }
}
