<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/OrganizationSimulation.php';

/**
 * Pure, inspectable NPC policy. These policies are entirely fictional scenario
 * parameters, NOT forecasts of real governments' international voting.
 * The planner NEVER changes state; an authorized service submits each vote via
 * the same world/country command boundary as human actors.
 */
final class AutonomousOrganizationPlanner
{
    /**
     * @return list<array{application_id:string,approve:bool,command_id:string,rationale:string}>
     */
    public function plan(
        OrganizationSimulation $sim,
        GeopoliticalActorRegistry $actors,
        string $voter,string $policy
    ):array {
        $record=$actors->get($voter);
        if ($record['controller']!=='ai_only') {
            throw new \DomainException('Automated plan only for non-playable state actors');
        }
        if (!in_array($policy,['cautious','cooperative','consensus_seeking'],true)) {
            throw new \InvalidArgumentException('Unknown fictional NPC decision profile');
        }
        $snapshot=$sim->snapshot();
        $plans=[];
        foreach ($snapshot['applications'] as $id=>$application) {
            if ($application['status']!=='voting'
                || !in_array($voter,$application['electors'],true)
                || isset($application['ballots'][$voter])) continue;
            $votes=$application['ballots'];
            $yes=count(array_filter($votes,static fn(bool $choice):bool=>$choice));
            $no=count($votes)-$yes;
            if ($policy==='consensus_seeking' && ($yes===0 || $no>0)) continue;
            $approve=$policy!=='cautious';
            $rationale=match ($policy) {
                'cautious'=>'fictional_policy_default_no',
                'cooperative'=>'fictional_policy_default_yes',
                'consensus_seeking'=>'fictional_policy_supports_existing_consensus'
            };
            $plans[]=[
                'application_id'=>$id,'approve'=>$approve,
                'command_id'=>'ai-'.substr(hash('sha256',
                    $snapshot['world_id']."\n".$voter."\n".$id."\n".$policy
                ),0,32),
                'rationale'=>$rationale
            ];
        }
        usort($plans,static fn($a,$b)=>strcmp($a['application_id'],$b['application_id']));
        return $plans;
    }

    /**
     * The trusted service still needs authorization for each country it
     * controls. No direct SQL writes, shortcuts, or ratification.
     *
     * @return list<array<string,mixed>>
     */
    public function execute(
        OrganizationSimulation $sim,
        GeopoliticalActorRegistry $actors,
        DiplomaticPrincipal $service,
        string $voter,string $policy
    ):array {
        $outcomes=[];
        foreach ($this->plan($sim,$actors,$voter,$policy) as $vote) {
            $result=$sim->vote(
                $service,$voter,$vote['application_id'],
                $vote['approve'],$vote['command_id']
            );
            $outcomes[]=['decision'=>$vote,'result'=>$result];
        }
        return $outcomes;
    }
}
