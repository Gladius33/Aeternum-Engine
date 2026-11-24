<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/WorldEconomicSimulation.php';

/**
 * WP15: typed, conservative mapping from an in-game diplomatic intention
 * into an *unapproved* economic-policy proposal.
 *
 * This is ONLY an in-process domain bridge. For durable cross-store
 * orchestration, use a transactional outbox and verify origin/destination
 * aggregate versions with a trusted service; NEVER accept player-posted
 * diplomatic snapshots as evidence of authorization.
 */
final class DiplomacyEconomicBridge
{
    /**
     * @return array<string,mixed>
     */
    public function proposeFromDiplomaticSanction(
        DiplomaticSimulation $diplomacy,
        WorldEconomicSimulation $economy,
        EconomicPolicyPrincipal $proposer,
        string $proposalId,
        int $impactBps,int $lowBps,int $highBps,string $effectiveDay
    ):array {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$proposalId)) {
            throw new \InvalidArgumentException('Invalid diplomatic proposal id');
        }
        $original=$diplomacy->snapshot();
        $destination=$economy->snapshot();
        if ($original['world_id']!==$destination['world_id']) {
            throw new \DomainException('Economic and diplomatic scenario worlds must match');
        }
        $offer=$original['proposals'][$proposalId]??null;
        if (!is_array($offer)
            || ($offer['id']??null)!==$proposalId
            || ($offer['action']??null)!=='sanctions_proposal'
            || ($offer['status']??null)!=='pending_domestic_approval') {
            throw new \DomainException('Only a verified pending sanction can open a policy proposal');
        }
        if ($offer['created_on']>$destination['day']) {
            throw new \DomainException('Cannot import a diplomatic intention from the future');
        }
        $id='bridge-'.substr(hash('sha256',
            $original['world_id']."\n".$proposalId."\ntrade_restriction"
        ),0,32);
        // Authorization is still enforced by the country-specific economy
        // domain. The bridge never approves the policy or sets GDP values.
        $result=$economy->proposeTradeRestriction(
            $proposer,$offer['from'],$offer['to'],
            $impactBps,$lowBps,$highBps,$effectiveDay,$id
        );
        return [
            'diplomatic_proposal_id'=>$proposalId,
            'diplomatic_world'=>$original['world_id'],
            'policy_id'=>$result['policy_id'],
            'policy_status'=>$result['status'],
            'needs_domestic_authorization'=>true,
            'economic_effect_applied'=>false,
            'provenance'=>'fictional_scenario_policy_bridge'
        ];
    }
}
