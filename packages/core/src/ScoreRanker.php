<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Private, aggregate-only competition rank. No opponent account IDs, names,
 * or private score breakdowns are returned. Ties share the same rank.
 */
final readonly class ScoreRanker
{
    public const MAX_PARTICIPANTS=128;
    public function __construct(private ScoreRules $policy) {}

    /** @param list<string> $activeAccounts
     *  @return array{position:int,participants:int}
     */
    public function rank(array $state,array $activeAccounts,string $account): array
    {
        PdoMemberships::validateId($account);
        if (!array_is_list($activeAccounts) || $activeAccounts===[]
            || count($activeAccounts)>self::MAX_PARTICIPANTS) {
            throw new \DomainException('Ranking snapshot exceeds bounded participants');
        }
        $seen=[];
        foreach ($activeAccounts as $candidate) {
            if (!is_string($candidate)) {
                throw new \InvalidArgumentException('Invalid ranking account');
            }
            PdoMemberships::validateId($candidate);
            if (isset($seen[$candidate])) {
                throw new \InvalidArgumentException('Duplicate ranking actor');
            }
            $seen[$candidate]=true;
        }
        if (!isset($seen[$account])) {
            throw new \DomainException('Ranked account not active');
        }
        $projector=new ScoreProjector($this->policy);
        $mine=$projector->forAccount($state,$account)['global'];
        $ahead=0;
        foreach ($activeAccounts as $other) {
            if ($other===$account) continue;
            if ($projector->forAccount($state,$other)['global']>$mine) $ahead++;
        }
        return ['position'=>$ahead+1,'participants'=>count($activeAccounts)];
    }
}
