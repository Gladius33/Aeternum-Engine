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

    public function assertEligible(array $state,string $source,string $target,int $at=0): void
    {
        PdoMemberships::validateId($source);
        PdoMemberships::validateId($target);
        if ($source===$target || $at<0) throw new \DomainException('Engagement denied');
        if ($this->rules->cooldownSeconds>0) {
            $previous=$state['engagement:last:'.$source]??null;
            if ($previous!==null) {
                if (!is_int($previous) || $previous<0 || $previous>$at) {
                    throw new \DomainException('Invalid engagement clock');
                }
                if ($at-$previous<$this->rules->cooldownSeconds) {
                    throw new \DomainException('Engagement denied');
                }
            }
        }
        if ($this->rules->diplomacyEnabled) {
            $pair=[$source,$target];
            sort($pair,SORT_STRING);
            $until=$state['diplomacy:truce:'.$pair[0].':'.$pair[1].':until']??null;
            if ($until!==null) {
                if (!is_int($until) || $until<0) throw new \DomainException('Invalid truce state');
                if ($until>$at) throw new \DomainException('Engagement denied');
            }
        }
        $projector=new ScoreProjector($this->scoreRules);
        $attacker=$projector->forAccount($state,$source)[$this->rules->powerMetric];
        $defender=$projector->forAccount($state,$target)[$this->rules->powerMetric];
        if ($defender<$this->rules->noviceShieldBelow
            || $attacker*100>$defender*$this->rules->maxAttackerRatioPercent) {
            // Deliberately do not report an opponent's shield or score.
            throw new \DomainException('Engagement denied');
        }
    }

    /**
     * Called after a successful guarded action, in the same journaled
     * transition. It is never a separate SQL update or client score.
     */
    public function record(array $state,string $source,int $at): array
    {
        PdoMemberships::validateId($source);
        if ($at<0) throw new \DomainException('Invalid engagement time');
        if ($this->rules->cooldownSeconds>0) {
            $state['engagement:last:'.$source]=$at;
        }
        return $state;
    }
}
