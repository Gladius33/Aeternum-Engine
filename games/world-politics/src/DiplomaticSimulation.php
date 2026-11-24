<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/GeopoliticalActorRegistry.php';

/** Adapter MUST use server-verified actor grants; never trust JSON for identity. */
interface DiplomaticPrincipal
{
    public function actorId():string;
    public function allows(string $world,string $actorId,string $ability):bool;
}

/**
 * Deterministic, headless international-relations aggregate.
 * - Publicly sourced entities vs fictional scenarios are isolated.
 * - Human players cannot issue commands for fixed AI-only actors.
 * - Diplomatic proposals are NOT signed treaties or operational sanctions.
 * - No inferences about contemporary leaders' private intentions.
 */
final class DiplomaticSimulation
{
    private const VERSION='world-diplomacy-wp06-0.1';
    /** @var array<string,mixed> */
    private array $state;

    public function __construct(
        string $world,
        string $startDay,
        private readonly GeopoliticalActorRegistry $actors
    ) {
        self::identifier($world);
        self::day($startDay);
        $this->state=[
            'world_id'=>$world,
            'day'=>$startDay,
            'rules_version'=>self::VERSION,
            'actors_sha'=>$actors->hash(),
            'proposals'=>[],
            'declarations'=>[],
            'events'=>[],
            'commands'=>[],
        ];
    }

    /** @return array<string,mixed> */
    public function propose(
        DiplomaticPrincipal $principal,
        string $from,
        string $to,
        string $action,
        string $commandId
    ):array {
        self::identifier($commandId);
        $sender=$this->actors->get($from);
        $target=$this->actors->get($to);
        if ($from===$to) throw new \InvalidArgumentException('Cannot address the same actor');
        $this->authorize($principal,$from,'diplomacy.propose');
        $supported=[
            'dialogue','humanitarian_assistance','trade_cooperation',
            'treaty_proposal','express_support','express_opposition','sanctions_proposal',
        ];
        if (!in_array($action,$supported,true)) {
            throw new \DomainException('Unknown diplomatic action');
        }
        if ($action==='treaty_proposal'
            && (!$this->actors->canProposeInterstateTreaty($from)
                || !$this->actors->canProposeInterstateTreaty($to))) {
            throw new \DomainException('A governing authority is not automatically an interstate treaty party');
        }
        if ($action==='sanctions_proposal' && $sender['kind']!=='state') {
            throw new \DomainException('Sanctions need a competent state authority');
        }
        $fingerprint=hash('sha256',implode('|',[$from,$to,$action]));
        $previous=$this->state['commands'][$commandId]??null;
        if ($previous!==null) {
            if (!hash_equals($previous['fingerprint'],$fingerprint)) {
                throw new \DomainException('Idempotency conflict');
            }
            return $previous['result'];
        }
        $entry=[
            'id'=>$commandId,'from'=>$from,'to'=>$to,'action'=>$action,
            'created_on'=>$this->state['day'],
            'status'=>match ($action) {
                'express_support','express_opposition'=>'declared_in_simulation',
                'sanctions_proposal'=>'pending_domestic_approval',
                default=>'pending_response',
            },
            'response_deadline'=>in_array($action,['express_support','express_opposition','sanctions_proposal'],true)
                ?null:self::plus($this->state['day'],14),
        ];
        if ($entry['status']==='declared_in_simulation') {
            $this->state['declarations'][$commandId]=$entry;
        } else {
            $this->state['proposals'][$commandId]=$entry;
        }
        $this->state['events'][]=[
            'on'=>$this->state['day'],'id'=>$commandId,'kind'=>'diplomatic_action_submitted',
            'result'=>$entry,
        ];
        $result=['id'=>$commandId,'status'=>$entry['status'],'action'=>$action];
        $this->state['commands'][$commandId]=['fingerprint'=>$fingerprint,'result'=>$result];
        return $result;
    }

    /** Accepted in principle, not treaty ratification nor military cooperation. */
    public function respond(
        DiplomaticPrincipal $principal,
        string $responder,
        string $proposalId,
        bool $accept,
        string $commandId
    ):array {
        self::identifier($proposalId);
        self::identifier($commandId);
        $this->actors->get($responder);
        $this->authorize($principal,$responder,'diplomacy.respond');
        $proposal=$this->state['proposals'][$proposalId]??null;
        if ($proposal===null || $proposal['to']!==$responder) {
            throw new \DomainException('No incoming diplomatic offer for this actor');
        }
        $fingerprint=hash('sha256',implode('|',[$responder,$proposalId,$accept?'yes':'no']));
        $previous=$this->state['commands'][$commandId]??null;
        if ($previous!==null) {
            if (!hash_equals($previous['fingerprint'],$fingerprint)) {
                throw new \DomainException('Idempotency conflict');
            }
            return $previous['result'];
        }
        if ($proposal['status']!=='pending_response') {
            throw new \DomainException('Proposal is not awaiting an answer');
        }
        $status=$accept
            ?($proposal['action']==='treaty_proposal'
                ?'accepted_for_treaty_negotiations':'accepted_in_principle')
            :'declined';
        $this->state['proposals'][$proposalId]['status']=$status;
        $this->state['proposals'][$proposalId]['responded_on']=$this->state['day'];
        $result=['proposal_id'=>$proposalId,'status'=>$status,'binding_effect'=>false];
        $this->state['commands'][$commandId]=['fingerprint'=>$fingerprint,'result'=>$result];
        $this->state['events'][]=[
            'on'=>$this->state['day'],'id'=>$commandId,'kind'=>'diplomatic_response',
            'result'=>$result,
        ];
        return $result;
    }

    /** Advance a virtual date; unanswered diplomatic offers expire. */
    public function advanceTo(string $targetDay):array
    {
        self::day($targetDay);
        if ($targetDay<$this->state['day']) {
            throw new \InvalidArgumentException('Diplomatic simulation cannot move back in time');
        }
        $expired=[];
        foreach ($this->state['proposals'] as $id=>&$proposal) {
            if ($proposal['status']==='pending_response'
                && $proposal['response_deadline']!==null
                && $proposal['response_deadline']<$targetDay) {
                $proposal['status']='expired';
                $expired[]=$id;
                $this->state['events'][]=[
                    'on'=>$proposal['response_deadline'],'id'=>$id.'-expired',
                    'kind'=>'offer_expired','result'=>['proposal_id'=>$id],
                ];
            }
        }
        unset($proposal);
        sort($expired,SORT_STRING);
        $this->state['day']=$targetDay;
        return ['day'=>$targetDay,'expired'=>$expired];
    }

    /** @return list<array<string,mixed>> */
    public function pendingFor(string $actorId):array
    {
        $this->actors->get($actorId);
        $result=[];
        foreach ($this->state['proposals'] as $proposal) {
            if ($proposal['to']===$actorId && $proposal['status']==='pending_response') {
                $result[]=$proposal;
            }
        }
        usort($result,static fn($a,$b)=>strcmp($a['id'],$b['id']));
        return $result;
    }

    /** @return array<string,mixed> */
    public function snapshot():array
    {
        $s=$this->state;
        foreach (['proposals','declarations','commands'] as $section) {
            ksort($s[$section],SORT_STRING);
        }
        return $s;
    }

    public function hash():string
    {
        return hash('sha256',json_encode(
            $this->snapshot(),
            JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
        ));
    }

    private function authorize(DiplomaticPrincipal $principal,string $actorId,string $ability):void
    {
        $actor=$this->actors->get($actorId);
        // Human clients must not impersonate an AI country's executive.
        // Grant for ai_only is intended to be issued only to a trusted bot.
        if (!$principal->allows($this->state['world_id'],$actorId,$ability)) {
            throw new \DomainException('Actor has no verified authority over this diplomatic participant');
        }
        if ($actor['controller']==='ai_only'
            && !$principal->allows($this->state['world_id'],$actorId,'diplomacy.ai_controller')) {
            throw new \DomainException('Human account cannot pilot an AI-only actor');
        }
    }

    private static function identifier(mixed $value):void
    {
        if (!is_string($value) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$value)) {
            throw new \InvalidArgumentException('Invalid world or command identifier');
        }
    }

    private static function day(string $value):void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)
            || ($date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value))===false
            || $date->format('Y-m-d')!==$value) {
            throw new \InvalidArgumentException('Invalid simulation date');
        }
    }

    private static function plus(string $day,int $days):string
    {
        return (new \DateTimeImmutable($day,new \DateTimeZone('UTC')))
            ->modify('+'.$days.' days')->format('Y-m-d');
    }
}

/**
 * First deterministic non-player diplomatic planner.
 * The policies below are explicitly fictional SCENARIO PARAMETERS and not
 * assessments of what actual Israeli, Iranian or Palestinian officials want.
 */
final class AutonomousDiplomacyPlanner
{
    /**
     * Produce proposed commands without mutating state or bypassing admission.
     * @return list<array{proposal_id:string,accept:bool,command_id:string}>
     */
    public function plan(DiplomaticSimulation $sim,string $aiActor,string $policy):array
    {
        if (!in_array($policy,['cautious','open_dialogue','humanitarian_open'],true)) {
            throw new \InvalidArgumentException('Unknown AI diplomacy strategy');
        }
        $result=[];
        foreach ($sim->pendingFor($aiActor) as $offer) {
            $accept=match ($policy) {
                'cautious'=>false,
                'open_dialogue'=>$offer['action']==='dialogue',
                'humanitarian_open'=>in_array(
                    $offer['action'],['dialogue','humanitarian_assistance'],true),
            };
            // A treaty or alliance is never automatically ratified.
            $result[]=[
                'proposal_id'=>$offer['id'],
                'accept'=>$accept,
                'command_id'=>'ai-'.substr(hash('sha256',
                    $sim->hash()."\n".$aiActor."\n".$offer['id']."\n".$policy
                ),0,32),
            ];
        }
        return $result;
    }

    /** Execute through the SAME authorization boundary as human actors. */
    public function execute(
        DiplomaticSimulation $sim,
        DiplomaticPrincipal $trustedBot,
        string $aiActor,
        string $policy
    ):array {
        $results=[];
        foreach ($this->plan($sim,$aiActor,$policy) as $decision) {
            $results[]=$sim->respond(
                $trustedBot,$aiActor,$decision['proposal_id'],
                $decision['accept'],$decision['command_id']
            );
        }
        return $results;
    }
}
