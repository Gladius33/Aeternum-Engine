<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/GeopoliticalActorRegistry.php';
require_once __DIR__.'/UkraineAidDiplomacy.php';
require_once __DIR__.'/InternationalCommitments.php';
require_once __DIR__.'/CommitmentRisk.php';
require_once __DIR__.'/StrategicDefensePlanning.php';
require_once __DIR__.'/HouseholdIncomeReference.php';
require_once __DIR__.'/HistoricalCandidateReference.php';

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

    public const DAILY_AI_VERSION='wp54r-diplomatic-replies-v1';
    public function enableDailyReplies():void
    {
        if(isset($this->state['daily_ai'])||$this->state['events']!==[]||$this->state['commands']!==[])throw new \DomainException('Diplomatic AI must be pinned at bootstrap');
        $this->state['daily_ai']=self::DAILY_AI_VERSION;
    }
    public function enableAidBridge():void
    {
        if(!isset($this->state['daily_ai'])||isset($this->state['aid_bridge'])||$this->state['events']!==[]||$this->state['commands']!==[])throw new \DomainException('Aid bridge must be pinned at bootstrap');
        $this->state['aid_bridge']=['version'=>UkraineAidDiplomacy::VERSION,'latest'=>null];
    }
    public function enableCommitments():void
    {
        if(!isset($this->state['aid_bridge'])||isset($this->state['commitments'])||$this->state['events']!==[]||$this->state['commands']!==[])throw new \DomainException('Commitments must be pinned at bootstrap');
        $this->state['commitments']=(new InternationalCommitments($this->state['world_id'],$this->state['day']))->snapshot();
    }
    public function enableCandidateReference():void
    {
        if(!isset($this->state['household_reference'])||isset($this->state['candidate_reference'])||$this->state['events']!==[]||$this->state['commands']!==[])throw new \DomainException('Candidate reference must be pinned at bootstrap');
        $this->state['candidate_reference']=HistoricalCandidateReference::pin();
    }
    public function enableHouseholdReference():void
    {
        if(!isset($this->state['defense_reference'])||isset($this->state['household_reference'])||$this->state['events']!==[]||$this->state['commands']!==[])throw new \DomainException('Household reference must be pinned at bootstrap');
        $this->state['household_reference']=HouseholdIncomeReference::pin();
    }
    public function enableDefenseReference():void
    {
        if(!isset($this->state['commitments'])||isset($this->state['defense_reference'])||$this->state['events']!==[]||$this->state['commands']!==[])throw new \DomainException('Defense reference must be pinned at bootstrap');
        $this->state['defense_reference']=StrategicDefensePlanning::pin();
    }
    public function enableCommitmentRisk():void
    {
        if(!isset($this->state['commitments'])||isset($this->state['commitment_risk'])||$this->state['commands']!==[]||$this->state['events']!==[]||$this->state['commitments']['commands']!==[])throw new \DomainException('Commitment risk must be pinned at bootstrap');
        $this->state['commitment_risk']=CommitmentRisk::VERSION;
    }
    public function commitmentDecision(DiplomaticPrincipal $principal,string $id,string $action,string $command):array
    {
        $this->authorize($principal,'state_fr','diplomacy.commitment');
        if(!isset($this->state['commitments']))throw new \DomainException('Commitments not enabled');
        $ledger=InternationalCommitments::restore($this->state['commitments']);$r=$ledger->decide($id,$action,$command);
        $this->state['commitments']=$ledger->snapshot();return $r;
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
    public function advanceTo(string $targetDay,?DiplomaticPrincipal $clock=null,?array $aid=null,?array $universePolicy=null):array
    {
        self::day($targetDay);
        if ($targetDay<$this->state['day']) {
            throw new \InvalidArgumentException('Diplomatic simulation cannot move back in time');
        }
        if(isset($this->state['daily_ai'])){
            if($clock===null||!$clock->allows($this->state['world_id'],$clock->actorId(),'diplomacy.clock'))throw new \DomainException('Trusted diplomatic AI clock required');
            if($targetDay!==self::plus($this->state['day'],1))throw new \DomainException('Daily diplomacy requires one day per tick');
        }
        if(isset($this->state['aid_bridge'])!==($aid!==null))throw new \DomainException('Aid diplomacy observation must match pinned model');
        if($aid!==null)$this->state['aid_bridge']['latest']=UkraineAidDiplomacy::step($aid,$this->state['world_id'],$targetDay,$this->state['aid_bridge']['latest']);
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
        if(isset($this->state['commitments'])){
            $ledger=InternationalCommitments::restore($this->state['commitments']);$ledger->advance($targetDay);$this->state['commitments']=$ledger->snapshot();
        }
        $this->state['day']=$targetDay;
        $result=['day'=>$targetDay,'expired'=>$expired];
        if(isset($this->state['commitment_risk']))$result['commitment_risk']=CommitmentRisk::report($this->state['commitments']);
        if(isset($this->state['daily_ai']))$result['autonomous_replies']=$this->dailyReplies($universePolicy);
        if($universePolicy!==null){
            $result['universe_diplomacy_policy']=$universePolicy;
            if(isset($this->state['daily_ai'])&&in_array(true,array_column($universePolicy,'initiative_enabled'),true))
                $result['autonomous_initiative']=$this->dailyInitiative($universePolicy);
        }
        return $result;
    }

    /** Only the trusted clock calls this private routine. Each response is
     * non-binding and scoped to the particular incoming foreign offer. */
    private function dailyReplies(?array $universePolicy=null):array
    {
        $world=$this->state['world_id'];$day=$this->state['day'];$due=[];
        foreach($this->state['proposals'] as $offer){
            if($offer['status']!=='pending_response')continue;
            $policy=$universePolicy[$offer['to']]??null;
            if($policy!==null){
                if($policy['human_action_this_day']||($policy['succession_pending']??false))continue;
            }elseif($offer['to']==='state_fr'||$offer['from']!=='state_fr')continue;
            $delay=2+(hexdec(substr(hash('sha256',self::DAILY_AI_VERSION.'|delay|'.$offer['to']),0,4))%4);
            if(self::plus($offer['created_on'],$delay)<=$day)$due[]=$offer;
        }
        usort($due,fn($a,$b)=>strcmp($a['created_on'],$b['created_on'])?:strcmp($a['id'],$b['id']));
        $replies=[];$risk=isset($this->state['commitment_risk'])?CommitmentRisk::report($this->state['commitments'])['actor_signals']:[];
        foreach(array_slice($due,0,32) as $offer){
            $to=$offer['to'];
            $openness=hexdec(substr(hash('sha256',self::DAILY_AI_VERSION.'|profile|'.$to),0,4))%101;
            $history=0;
            foreach($this->state['proposals'] as $prior)
                if($prior['from']==='state_fr'&&$prior['to']===$to&&isset($prior['responded_on'])&&$prior['responded_on']<$day){
                    $history+=str_starts_with($prior['status'],'accepted')?4:-4;
                }
            $score=$openness+max(-20,min(20,$history))+($this->state['aid_bridge']['latest']['signals'][$to]??0)+($risk[$to]??0);
            $threshold=match($offer['action']){'dialogue'=>25,'humanitarian_assistance'=>35,'trade_cooperation'=>55,'treaty_proposal'=>75,default=>101};
            if(isset($universePolicy[$to]))$threshold+=match($universePolicy[$to]['programme']){
                'cooperative'=>-15,'balanced'=>0,'cautious'=>15,default=>throw new \DomainException('Invalid diplomatic programme')
            };
            $bot=new class($world,$to) implements DiplomaticPrincipal{
                public function __construct(private readonly string $world,private readonly string $target){}
                public function actorId():string{return 'fictional-diplomatic-reply';}
                public function allows(string $w,string $a,string $ability):bool{return $w===$this->world&&$a===$this->target&&in_array($ability,['diplomacy.respond','diplomacy.ai_controller'],true);}
            };
            $cmd='daily-ai-'.substr(hash('sha256',$world.'|'.$offer['id']),0,32);
            $replies[]=$this->respond($bot,$to,$offer['id'],$score>=$threshold,$cmd);
        }
        return $replies;
    }
    /** Bounded fictional diplomacy: one rotating, non-binding initiative per
     * week. Human commands in the preceding committed day take priority. */
    private function dailyInitiative(array $policy):?array
    {
        $day=$this->state['day'];$world=$this->state['world_id'];
        $elapsed=(int)(new \DateTimeImmutable('2027-05-14'))->diff(new \DateTimeImmutable($day))->format('%r%a');
        if($elapsed<7||$elapsed%7!==0)return null;
        $ids=array_keys($policy);sort($ids,SORT_STRING);
        if(count($ids)<2)return null;
        $i=(intdiv($elapsed,7)-1)%count($ids);$from=$ids[$i];$src=$policy[$from];
        if(!($src['initiative_enabled']??false)||$src['human_action_this_day']||($src['succession_pending']??false))return null;
        if($src['programme']==='cautious')return null;
        foreach($this->state['proposals'] as $prior)
            if($prior['from']===$from&&$prior['status']==='pending_response')return null;
        $to=null;
        for($j=1;$j<count($ids);$j++){
            $candidate=$ids[($i+$j)%count($ids)];
            if(!($policy[$candidate]['succession_pending']??false)){$to=$candidate;break;}
        }
        if($to===null)return null;
        $action=match($src['programme']){'cooperative'=>'trade_cooperation','balanced'=>'dialogue',default=>throw new \DomainException('Invalid initiative programme')};
        $bot=new class($world,$from) implements DiplomaticPrincipal{
            public function __construct(private readonly string $w,private readonly string $a){}
            public function actorId():string{return 'fictional-diplomatic-initiative';}
            public function allows(string $world,string $actor,string $ability):bool{return $world===$this->w&&$actor===$this->a&&$ability==='diplomacy.propose';}
        };
        $id='daily-initiative-'.substr(hash('sha256',$world.'|'.$day.'|'.$from),0,32);
        return $this->propose($bot,$from,$to,$action,$id);
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

    /**
     * Restore a trusted, world-scoped database aggregate.
     * The store also checks the authoritative hash and pinned actor registry.
     * @param array<string,mixed> $state
     */
    public static function fromSnapshot(array $state, GeopoliticalActorRegistry $actors):self
    {
        $keys=array_keys($state);
        $expected=['world_id','day','rules_version','actors_sha',
            'proposals','declarations','events','commands'];
        if(array_key_exists('daily_ai',$state)){
            if($state['daily_ai']!==self::DAILY_AI_VERSION)throw new \DomainException('Unknown diplomatic AI model');
            $expected[]='daily_ai';
        }
        if(array_key_exists('aid_bridge',$state)){
            if(!isset($state['daily_ai'])||($state['aid_bridge']['version']??null)!==UkraineAidDiplomacy::VERSION||!array_key_exists('latest',$state['aid_bridge']))throw new \DomainException('Invalid aid bridge');
            $a=$state['aid_bridge']['latest'];if($a!==null&&($a['source']['world_id']!==$state['world_id']||$a['source']['day']!==$state['day']||$a['version']!==UkraineAidDiplomacy::VERSION||min($a['signals'])<-20||max($a['signals'])>20))throw new \DomainException('Invalid aid bridge state');
            $expected[]='aid_bridge';
        }
        if(array_key_exists('commitments',$state)){
            $ledger=InternationalCommitments::restore($state['commitments']);$s=$ledger->snapshot();
            if(!isset($state['aid_bridge'])||$s['world_id']!==$state['world_id']||$s['day']!==$state['day'])throw new \DomainException('Commitment scope mismatch');
            $expected[]='commitments';
        }
        if(array_key_exists('commitment_risk',$state)){
            if($state['commitment_risk']!==CommitmentRisk::VERSION||!isset($state['commitments']))throw new \DomainException('Invalid commitment risk pin');
            CommitmentRisk::report($state['commitments']);$expected[]='commitment_risk';
        }
        if(array_key_exists('defense_reference',$state)){
            if(!isset($state['commitments']))throw new \DomainException('Defense reference without canonical commitments');
            StrategicDefensePlanning::report($state['defense_reference'],$state['world_id'],$state['day']);$expected[]='defense_reference';
        }
        if(array_key_exists('household_reference',$state)){
            if(!isset($state['defense_reference']))throw new \DomainException('Household reference without canonical pin');
            HouseholdIncomeReference::report($state['household_reference']);$expected[]='household_reference';
        }
        if(array_key_exists('candidate_reference',$state)){
            if(!isset($state['household_reference']))throw new \DomainException('Candidate reference without canonical pin');
            HistoricalCandidateReference::report($state['candidate_reference']);$expected[]='candidate_reference';
        }
        sort($keys);sort($expected);
        if ($keys!==$expected) throw new \InvalidArgumentException('Invalid diplomatic snapshot fields');
        if (!is_string($state['world_id']) || !is_string($state['day'])) {
            throw new \InvalidArgumentException('Invalid world snapshot identity');
        }
        $self=new self($state['world_id'],$state['day'],$actors);
        if ($state['rules_version']!==self::VERSION
            || !hash_equals($actors->hash(),$state['actors_sha'])) {
            throw new \DomainException('Actor dataset or diplomacy ruleset changed; pin migration instead');
        }
        foreach (['proposals','declarations','commands'] as $key) {
            if (!is_array($state[$key])
                || (array_is_list($state[$key]) && $state[$key]!==[])) {
                throw new \InvalidArgumentException('Invalid diplomatic keyed event collection');
            }
        }
        if (!is_array($state['events']) || !array_is_list($state['events'])) {
            throw new \InvalidArgumentException('Invalid diplomatic event journal');
        }
        foreach ($state['proposals'] as $id=>$proposal) {
            self::identifier($id);
            if (!is_array($proposal) || $proposal['id']!==$id
                || !isset($proposal['from'],$proposal['to'],$proposal['action'],$proposal['status'],$proposal['created_on'])
                || !in_array($proposal['status'],[
                    'pending_response','pending_domestic_approval','accepted_in_principle',
                    'accepted_for_treaty_negotiations','declined','expired'
                ],true)) {
                throw new \InvalidArgumentException('Invalid diplomatic proposal state');
            }
            $actors->get($proposal['from']);
            $actors->get($proposal['to']);
        }
        foreach ($state['declarations'] as $id=>$declaration) {
            self::identifier($id);
            if (!is_array($declaration) || ($declaration['id']??null)!==$id
                || ($declaration['status']??null)!=='declared_in_simulation') {
                throw new \InvalidArgumentException('Invalid declaration state');
            }
            $actors->get($declaration['from']);
            $actors->get($declaration['to']);
        }
        foreach ($state['commands'] as $id=>$receipt) {
            self::identifier($id);
            if (!is_array($receipt) || !isset($receipt['fingerprint'],$receipt['result'])
                || !is_string($receipt['fingerprint'])
                || !preg_match('/^[a-f0-9]{64}$/D',$receipt['fingerprint'])
                || !is_array($receipt['result'])) {
                throw new \InvalidArgumentException('Invalid diplomatic idempotency map');
            }
        }
        foreach ($state['events'] as $event) {
            if (!is_array($event) || !isset($event['id'],$event['on'],$event['kind'])
                || !is_string($event['id']) || !is_string($event['on'])) {
                throw new \InvalidArgumentException('Invalid diplomatic historical event');
            }
            self::day($event['on']);
        }
        $self->state=$state;
        return $self;
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
