<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/InstitutionalRulebook.php';
require_once __DIR__.'/ComparativeGovernment.php';
require_once __DIR__.'/ElectoralCycle.php';
require_once __DIR__.'/PoliticalNegotiator.php';
require_once __DIR__.'/ConstitutionalRevision.php';
require_once __DIR__.'/BicameralLegislation.php';
require_once __DIR__.'/FrenchRecurringElections.php';
require_once __DIR__.'/RevisionAutonomy.php';

/**
 * WP05: small deterministic institution/event aggregate. Illustrates exactly
 * why FR/DE/US actions cannot share a generic presidential permission.
 *
 * Actor role is an explicit trusted server-side argument, NEVER read from the
 * action JSON. No tactical coercion, electoral manipulation or AI implemented.
 */
final class InstitutionalSimulation
{
    /** @var array<string,mixed> */
    private array $state;

    public function __construct(string $world, string $country, string $startingDay)
    {
        self::id($world);
        self::day($startingDay);
        $rules=InstitutionalRulebook::forCountry($country);
        $this->state=[
            'world_id'=>$world,
            'country_code'=>$country,
            'ruleset'=>$rules['version'],
            'day'=>$startingDay,
            'legislature'=>'sitting',
            'election'=>null,
            'last_dissolution'=>null,
            'last_election'=>null,
            'consultations'=>[],
            'bills'=>[],
            'agenda'=>[],
            'events'=>[],
            'constitutional_breaches'=>0,
        ];
    }

    /** Recreate state for a trusted database snapshot with validation. */
    public static function restore(array $state):self
    {
        $expected=['world_id','country_code','ruleset','day','legislature','election',
          'last_dissolution','last_election','consultations','bills','agenda','events','constitutional_breaches'];
        if (array_key_exists('accountability',$state)) $expected[]='accountability';
        if (array_key_exists('electoral',$state)) $expected[]='electoral';
        if (array_key_exists('political_ai',$state)) $expected[]='political_ai';
        if (array_key_exists('revision',$state)) $expected[]='revision';
        if (array_key_exists('legislation',$state)) $expected[]='legislation';
        if(array_key_exists('recurring_elections',$state))$expected[]='recurring_elections';
        if(array_key_exists('revision_requests',$state))$expected[]='revision_requests';
        $keys=array_keys($state);sort($keys);sort($expected);
        if ($keys!==$expected) throw new \InvalidArgumentException('Invalid institutional snapshot fields');
        $self=new self($state['world_id'],$state['country_code'],$state['day']);
        if ($state['ruleset']!==$self->state['ruleset']) {
            throw new \DomainException('Ruleset mismatch: never silently migrate worlds');
        }
        if (!in_array($state['legislature'],['sitting','dissolved'],true)
            || !is_array($state['consultations']) || !is_array($state['bills']) || !is_array($state['agenda']) || !is_array($state['events'])
            || !is_int($state['constitutional_breaches']) || $state['constitutional_breaches']<0) {
            throw new \InvalidArgumentException('Invalid institutional snapshot');
        }
        if ($state['election']!==null && (
            !is_array($state['election'])
            || !isset($state['election']['earliest'],$state['election']['deadline'])
        )) throw new \InvalidArgumentException('Invalid election window');
        if ($state['last_election']!==null) self::day($state['last_election']);
        foreach ($state['consultations'] as $office=>$consultedOn) {
            if (!in_array($office,['prime_minister','assembly_speaker','senate_speaker'],true)
                || !is_string($consultedOn)) {
                throw new \InvalidArgumentException('Invalid recorded consultation');
            }
            self::day($consultedOn);
        }
        foreach ($state['agenda'] as $event) {
            if (!is_array($event) || !isset($event['id'],$event['on'],$event['kind'])) {
                throw new \InvalidArgumentException('Invalid pending institutional event');
            }
            self::id($event['id']); self::day($event['on']);
            if (!in_array($event['kind'],['bill_debate_open','election_window_open','election_deadline'],true)) {
                throw new \InvalidArgumentException('Unknown scheduled event');
            }
        }
        if (isset($state['accountability'])) {
            $g=ComparativeGovernment::restore($state['accountability'])->snapshot();
            if ($g['world_id']!==$state['world_id']||$g['country']!==$state['country_code']||$g['day']!==$state['day'])
                throw new \DomainException('Accountability state scope or day mismatch');
        } elseif (array_key_exists('accountability',$state)) throw new \DomainException('Null accountability state');
        if (isset($state['electoral'])) {
            $el=ElectoralCycle::restore($state['electoral'])->snapshot();
            if($el['world_id']!==$state['world_id']||$el['country']!==$state['country_code']||$el['day']!==$state['day'])
                throw new \DomainException('Electoral state scope mismatch');
        } elseif(array_key_exists('electoral',$state))throw new \DomainException('Null electoral state');
        if(array_key_exists('political_ai',$state)){
            if(!isset($state['electoral'])||$state['political_ai']!==PoliticalNegotiator::evaluate($state['electoral']))
                throw new \DomainException('Political AI projection mismatch');
        }
        if(array_key_exists('revision',$state)){
            if(!is_array($state['revision'])||$state['country_code']!=='FR')throw new \DomainException('Invalid revision scope');
            $r=ConstitutionalRevision::restore($state['revision'])->snapshot();
            if($r['world_id']!==$state['world_id']||$r['day']!==$state['day'])throw new \DomainException('Revision scope or day mismatch');
        }
        if(array_key_exists('legislation',$state)){
            if(!is_array($state['legislation'])||$state['country_code']!=='FR')throw new \DomainException('Invalid legislation scope');
            $l=BicameralLegislation::restore($state['legislation'])->snapshot();
            if($l['world_id']!==$state['world_id']||$l['day']!==$state['day'])throw new \DomainException('Legislation scope or day mismatch');
            if(isset($l['responsibility']['lifecycle'])){
                $digest=$l['responsibility']['lifecycle']['election_digest'];
                $installed=$state['electoral']??null;
                if(isset($state['recurring_elections'])){
                    $rec=$state['recurring_elections'];
                    if($state['country_code']!=='FR'||$rec['version']!==FrenchRecurringElections::VERSION||!isset($l['calendar_replay'])||!is_int($rec['completed'])||$rec['completed']<0)throw new \DomainException('Invalid recurring election state');
                    $history=array_values(array_filter($l['history'],fn($h)=>$h['kind']==='election_install'));
                    if(count($history)!==$rec['completed'])throw new \DomainException('Recurring election archive mismatch');
                    $installed=$history===[]?null:$history[array_key_last($history)]['election'];
                    if($installed!==null&&$state['last_election']!==($installed['config']['runoff_day']??$installed['config']['poll_day']))throw new \DomainException('Last election mismatch');
                }
                if(!isset($state['electoral'])||$state['electoral']['seats_total']!==577||
                    ($digest!==null&&($installed===null||$installed['status']!=='results'||$digest!==ParliamentaryLifecycle::digest($installed))))
                    throw new \DomainException('Assembly election source mismatch');
            }
        }
        if(isset($state['revision_requests']))RevisionAutonomy::validate($state);
        $self->state=$state;
        return $self;
    }

    /**
     * Country-specific institutional action, with game-time side effects.
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function decide(string $role, string $action, array $input, string $commandId):array
    {
        self::id($commandId);
        $rules=InstitutionalRulebook::forCountry($this->state['country_code']);
        $day=$this->state['day'];
        if (!is_string($role) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$role)) {
            throw new \InvalidArgumentException('Unknown actor role');
        }
        if ($action==='record_consultation') {
            self::keys($input,[]);
            if ($this->state['country_code']!=='FR'
                || !in_array($role,['prime_minister','assembly_speaker','senate_speaker'],true)) {
                throw new \DomainException('Role cannot record this consultation');
            }
            $this->state['consultations'][$role]=$day;
            $result=['consulted_institution'=>$role,'consulted_on'=>$day];
        } elseif ($action==='submit_bill') {
            self::keys($input,['title']);
            if (!in_array($role,$rules['bill_proposers'],true)) {
                throw new \DomainException('Role cannot introduce legislation in this country');
            }
            $title=$input['title'];
            if (!is_string($title) || trim($title)==='' || strlen($title)>200
                || preg_match('/[\x00-\x1f\x7f]/',$title)) {
                throw new \InvalidArgumentException('Invalid bill title');
            }
            if (isset($this->state['bills'][$commandId])) throw new \DomainException('Bill already filed');
            $this->state['bills'][$commandId]=[
                'id'=>$commandId,'title'=>$title,'initiated_by_role'=>$role,
                'status'=>'filed','filed_on'=>$day,'source_of_result'=>'simulation',
            ];
            // SEVEN DAYS IS A CONFIGURABLE GAMEPLAY DEFAULT, NOT A LEGAL DEADLINE.
            $this->enqueue($commandId.'-debate','bill_debate_open',self::plus($day,7));
            $result=['bill_id'=>$commandId,'status'=>'filed','earliest_debate'=>self::plus($day,7)];
        } elseif ($action==='dissolve_lower_house') {
            self::keys($input,[]);
            if ($rules['dissolution']['mode']!=='consultations_required'
                || $role!==($rules['dissolution']['role']??null)) {
                throw new \DomainException('No ordinary dissolution power for that institution');
            }
            foreach (['prime_minister','assembly_speaker','senate_speaker'] as $office) {
                $consultedOn=$this->state['consultations'][$office]??null;
                if ($consultedOn===null || $consultedOn>$day
                    || self::difference($consultedOn,$day)>30) {
                    throw new \DomainException('Constitutional consultation is missing or stale');
                }
            }
            if ($this->state['legislature']!=='sitting') {
                throw new \DomainException('Legislature already dissolved');
            }
            // French Article 12: the one-year bar runs from the following
            // elections, not from the earlier date of dissolution.
            $lastElection=$this->state['last_election'];
            if ($lastElection!==null && $day<self::plusYears($lastElection,1)) {
                throw new \DomainException('Dissolution too soon after legislative elections');
            }
            if(isset($this->state['recurring_elections'])){
                if(($this->state['electoral']['status']??null)!=='scheduled'||!ParliamentaryLifecycle::quiet($this->state['legislation']['responsibility'])
                    ||($this->state['revision']['context']['presidential_vacancy']??false)||($this->state['revision']['context']['definitive_incapacity']??false))
                    throw new \DomainException('Dissolution unavailable during unresolved proceedings');
                $this->state['electoral']=(new ElectoralCycle($this->state['world_id'],'FR',$day,FrenchRecurringElections::early($day)))->snapshot();
                $this->state['political_ai']=PoliticalNegotiator::evaluate($this->state['electoral']);
            }
            $earliest=self::plus($day,$rules['dissolution']['election_min_days']);
            $deadline=self::plus($day,$rules['dissolution']['election_max_days']);
            $this->state['legislature']='dissolved';
            $this->state['last_dissolution']=$day;
            $this->state['consultations']=[];
            $this->state['election']=[
                'earliest'=>$earliest,'deadline'=>$deadline,'status'=>'scheduled',
            ];
            $this->enqueue($commandId.'-opening','election_window_open',$earliest);
            $this->enqueue($commandId.'-deadline','election_deadline',$deadline);
            $result=['status'=>'dissolved','election_earliest'=>$earliest,
                'election_deadline'=>$deadline,'legality'=>'within_modeled_constitution'];
        } elseif ($action==='hold_election') {
            self::keys($input,[]);
            if(isset($this->state['recurring_elections']))throw new \DomainException('Recurring ballot authority owns election completion');
            if ($role!=='election_authority') {
                throw new \DomainException('Election is administered by its authorized institution');
            }
            $election=$this->state['election'];
            if (!is_array($election) || $this->state['legislature']!=='dissolved'
                || $day<$election['earliest'] || $day>$election['deadline']) {
                throw new \DomainException('No valid election window');
            }
            // Results/seats cannot be fabricated before electoral simulation.
            $this->state['election']['status']='voting_recorded_results_pending';
            $this->state['legislature']='sitting';
            $this->state['last_election']=$day;
            $result=['status'=>'voting_recorded_results_pending','on'=>$day];
        } else {
            // Refuse bypasses: "cancel elections" cannot masquerade as a legal
            // presidential action. Abstract unconstitutional events are a
            // separate future game module with crisis consequences.
            throw new \DomainException('Unknown or unmodeled constitutional power');
        }
        $this->state['events'][]=[
            'id'=>$commandId,'on'=>$day,'kind'=>$action,'actor_role'=>$role,
            'result'=>$result,
        ];
        return $result;
    }

    /** Trusted scenario bootstrap only; never an HTTP action. */
    public function enableAccountability(string $incumbent,array $seats):void
    {
        if (isset($this->state['accountability'])||$this->state['events']!==[])throw new \DomainException('Accountability must be pinned at bootstrap');
        $g=new ComparativeGovernment($this->state['world_id'],$this->state['country_code'],$this->state['day'],$incumbent,$seats);
        $this->state['accountability']=$g->snapshot();
    }

    /** Evidence is obtained from the server-side provider by the durable store. */
    public function accountability(string $role,string $action,array $evidence,string $commandId):array
    {
        self::id($commandId);
        if (!isset($this->state['accountability'])) throw new \DomainException('Comparative government not enabled');
        $g=ComparativeGovernment::restore($this->state['accountability']);
        $result=$g->apply($action,$role,$evidence);
        $this->state['accountability']=$g->snapshot();
        $this->state['events'][]=['id'=>$commandId,'on'=>$this->state['day'],'kind'=>$action,'actor_role'=>$role,'result'=>$result];
        return $result;
    }

    public function enableElectoral(array $config):void
    {
        if(isset($this->state['electoral'])||$this->state['events']!==[])throw new \DomainException('Electoral rules must be pinned at bootstrap');
        $this->state['electoral']=(new ElectoralCycle($this->state['world_id'],$this->state['country_code'],$this->state['day'],$config))->snapshot();
    }
    public function enablePoliticalAI():void
    {
        if(isset($this->state['political_ai'])||!isset($this->state['electoral'])||$this->state['events']!==[])
            throw new \DomainException('Political AI must be pinned at bootstrap');
        $this->state['political_ai']=PoliticalNegotiator::evaluate($this->state['electoral']);
    }
    public function electoral(string $role,string $action,array $payload,string $commandId):array
    {
        self::id($commandId);
        if(!isset($this->state['electoral']))throw new \DomainException('No electoral pilot');
        $el=ElectoralCycle::restore($this->state['electoral']);
        if($action==='electoral_coalition'&&$role==='scenario_negotiator'){
            self::keys($payload,['parties','programme','candidate']);
            if(!is_array($payload['parties'])||!is_array($payload['programme'])||!is_string($payload['candidate']))throw new \InvalidArgumentException('Invalid coalition payload');
            $result=$el->coalition($commandId,$payload['parties'],$payload['programme'],$payload['candidate']);
        }elseif($action==='electoral_certify'&&$role==='constitutional_clerk')$result=$el->certify($commandId,$payload);
        else throw new \DomainException('Electoral command forbidden');
        // Only a verified formation certificate may change the comparative
        // incumbent. Never infer legal appointment from an AI recommendation.
        if($action==='electoral_certify'&&isset($this->state['political_ai'],$this->state['accountability'])){
            $g=ComparativeGovernment::restore($this->state['accountability']);
            $g->installCertifiedFormation($payload,$result);
            $this->state['accountability']=$g->snapshot();
        }
        $this->state['electoral']=$el->snapshot();
        if(isset($this->state['political_ai']))$this->state['political_ai']=PoliticalNegotiator::evaluate($this->state['electoral']);
        $this->state['events'][]=['id'=>$commandId,'on'=>$this->state['day'],'kind'=>$action,'actor_role'=>$role,'result'=>$result];
        return $result;
    }

    public function enableContinuityLot():void
    {
        if($this->state['country_code']!=='FR'||!isset($this->state['revision'],$this->state['electoral'],$this->state['legislation'],$this->state['political_ai'])||$this->state['events']!==[]||isset($this->state['recurring_elections']))throw new \DomainException('Continuity lot requires bootstrap');
        $l=BicameralLegislation::restore($this->state['legislation']);$l->enableCalendarReplay();$this->state['legislation']=$l->snapshot();
        $this->state['recurring_elections']=['version'=>FrenchRecurringElections::VERSION,'completed'=>0];
        $this->state['last_election']='2024-07-07';
        $this->state['revision_requests']=[];
    }
    public function enableRevision():void
    {
        if($this->state['country_code']!=='FR'||isset($this->state['revision'])||$this->state['events']!==[])
            throw new \DomainException('Revision must be pinned at bootstrap');
        $this->state['revision']=(new ConstitutionalRevision($this->state['world_id'],$this->state['day']))->snapshot();
    }
    public function revision(string $role,string $action,array $payload,string $commandId):array
    {
        self::id($commandId);
        if(!isset($this->state['revision']))throw new \DomainException('No revision pilot');
        $r=ConstitutionalRevision::restore($this->state['revision']);
        if($action==='revision_draft'&&$role==='scenario_revision_author'){
            $auto=array_key_exists('ratification_route',$payload);
            self::keys($payload,['template',...($auto?['ratification_route']:[])]);
            if($auto&&(!isset($this->state['revision_requests'])||!in_array($payload['ratification_route'],['congress','referendum'],true)||$this->state['legislature']!=='sitting'))throw new \DomainException('Autonomous revision unavailable');
            if(!is_string($payload['template']))throw new \InvalidArgumentException('Invalid template');
            $result=['procedure'=>$r->draft($commandId,$payload['template']),'legal_effect'=>false];
            if($auto){$this->state['revision_requests'][$commandId]=RevisionAutonomy::request($this->state,$commandId,$payload['ratification_route']);$result['request']=$this->state['revision_requests'][$commandId];}
        }elseif($action==='revision_evidence'&&$role==='revision_clerk'){
            if(isset($this->state['revision_requests'][$payload['revision_id']??'']))throw new \DomainException('Autonomous revision owns this procedure');
            $result=$r->certify($payload);
        }else throw new \DomainException('Revision action forbidden');
        $this->state['revision']=$r->snapshot();
        $this->state['events'][]=['id'=>$commandId,'on'=>$this->state['day'],'kind'=>$action,'actor_role'=>$role,'result'=>$result];
        return $result;
    }

    public function enableLegislation(bool $responsibility=false,bool $assembly=false,bool $lifecycle=false,bool $cabinet=false,bool $executive=false,bool $finance=false,bool $minorUnits=false):void
    {
        if($this->state['country_code']!=='FR'||isset($this->state['legislation'])||$this->state['events']!==[])throw new \DomainException('Legislation must be pinned at bootstrap');
        $l=new BicameralLegislation($this->state['world_id'],$this->state['day']);
        if($minorUnits)$l->enablePreciseUnits();
        if($responsibility)$l->enableResponsibility();
        if($assembly)$l->enableAssembly();
        if($lifecycle)$l->enableLifecycle();
        if($cabinet)$l->enableCabinet();
        if($executive)$l->enableExecutive();
        if($finance)$l->enableFinance();
        $this->state['legislation']=$l->snapshot();
    }
    public function legislation(string $role,string $action,array $payload,string $commandId):array
    {
        self::id($commandId);
        if(!isset($this->state['legislation']))throw new \DomainException('No legislation pilot');
        if(isset($this->state['recurring_elections'])&&$this->state['legislature']==='dissolved')throw new \DomainException('Assembly dissolved: legislative decisions suspended in this pilot');
        $l=BicameralLegislation::restore($this->state['legislation']);
        if($action==='legislation_draft'&&$role==='scenario_bill_author'){
            self::keys($payload,['policy_id']);
            if(!is_string($payload['policy_id']))throw new \InvalidArgumentException('Invalid policy id');
            $result=['bill'=>$l->draft($commandId,$payload['policy_id']),'policy_authorized'=>false];
        }elseif($action==='legislation_evidence'&&$role==='legislation_clerk')$result=$l->certify($payload);
        else throw new \DomainException('Legislation action forbidden');
        $this->state['legislation']=$l->snapshot();
        $this->state['events'][]=['id'=>$commandId,'on'=>$this->state['day'],'kind'=>$action,'actor_role'=>$role,'result'=>$result];
        return $result;
    }

    /** Advance deterministic game clock, executing only approved due events. */
    public function advanceTo(string $targetDay,?array $opinion=null):array
    {
        self::day($targetDay);
        if ($targetDay<$this->state['day']) throw new \InvalidArgumentException('Game clock cannot regress');
        if(isset($this->state['recurring_elections'])&&$targetDay!==self::plus($this->state['day'],1))throw new \DomainException('Recurring institution clock requires daily ticks');
        $due=[];$remain=[];
        foreach ($this->state['agenda'] as $event) {
            if ($event['on']<=$targetDay) $due[]=$event;
            else $remain[]=$event;
        }
        usort($due,static fn($a,$b)=>[$a['on'],$a['id']]<=>[$b['on'],$b['id']]);
        $applied=[];
        foreach ($due as $event) {
            $this->state['day']=$event['on'];
            $kind=$event['kind'];
            if ($kind==='bill_debate_open') {
                $billId=substr($event['id'],0,-7);
                if (isset($this->state['bills'][$billId])) {
                    $this->state['bills'][$billId]['status']='debate_ready';
                }
            } elseif ($kind==='election_window_open') {
                if (($this->state['election']['status']??null)==='scheduled') {
                    $this->state['election']['status']='voting_window_open';
                }
            } elseif ($kind==='election_deadline') {
                if ($this->state['legislature']==='dissolved') {
                    $this->state['election']['status']='constitutional_deadline_missed';
                    $this->state['constitutional_breaches']++;
                }
            }
            $this->state['events'][]=[
                'id'=>$event['id'],'on'=>$event['on'],
                'kind'=>$kind,'actor_role'=>'simulation_clock',
                'result'=>['processed'=>true],
            ];
            $applied[]=$event['id'];
        }
        if (isset($this->state['accountability'])) {
            $g=ComparativeGovernment::restore($this->state['accountability']);$g->advance($targetDay);
            $this->state['accountability']=$g->snapshot();
        }
        if(isset($this->state['electoral'])){
            $el=ElectoralCycle::restore($this->state['electoral']);
            if(isset($this->state['recurring_elections'])){
                $e=$el->snapshot();$lc=$this->state['legislation']['responsibility']['lifecycle'];
                if($e['status']==='results'&&$lc['election_digest']===ParliamentaryLifecycle::digest($e)){
                    $el=new ElectoralCycle($e['world_id'],'FR',$targetDay,FrenchRecurringElections::ordinary($targetDay,(int)substr($this->state['last_election'],0,4)));
                }
            }
            // Pin automatic negotiation to the actual result day, including
            // when an administrative clock jumps across multiple dates.
            if(isset($this->state['political_ai'])&&$this->state['country_code']!=='FR'){
                $before=$el->snapshot();
                if($before['status']!=='results'){
                    $finalDay=$before['config']['runoff_day']??$before['config']['poll_day'];
                    if($finalDay<=$targetDay&&$finalDay>=$before['day']){
                        $el->advance($finalDay);
                        $suggestion=PoliticalNegotiator::evaluate($el->snapshot())['recommendation'];
                        if($suggestion!==null&&$el->snapshot()['coalition']===null){
                            $id='ai-coalition-wp34';
                            $result=$el->coalition($id,$suggestion['parties'],$suggestion['programme'],$suggestion['candidate']);
                            $this->state['events'][]=['id'=>$id,'on'=>$finalDay,'kind'=>'electoral_coalition','actor_role'=>'simulation_political_ai','result'=>$result];
                        }
                    }
                }
            }
            $el->advance($targetDay);$this->state['electoral']=$el->snapshot();
            if(isset($this->state['political_ai']))$this->state['political_ai']=PoliticalNegotiator::evaluate($this->state['electoral']);
        }
        if(isset($this->state['revision'])){
            $r=ConstitutionalRevision::restore($this->state['revision']);$r->advance($targetDay);$this->state['revision']=$r->snapshot();
        }
        if(isset($this->state['legislation'])){
            $l=BicameralLegislation::restore($this->state['legislation']);$l->advance($targetDay);
            $r=$l->snapshot()['responsibility']??null;$e=$this->state['electoral']??null;
            if(isset($r['lifecycle'])&&($e['status']??null)==='results'&&ParliamentaryLifecycle::quiet($r)&&($r['lifecycle']['election_digest']===null||(isset($this->state['recurring_elections'])&&$r['lifecycle']['election_digest']!==ParliamentaryLifecycle::digest($e)))){
                $l->installElection($e);
                if(isset($this->state['recurring_elections'])){
                    $this->state['recurring_elections']['completed']++;
                    $this->state['last_election']=$e['config']['runoff_day']??$e['config']['poll_day'];
                    $this->state['legislature']='sitting';
                    if($this->state['election']!==null)$this->state['election']['status']='voting_recorded_results_pending';
                }
            }
            $this->state['legislation']=$l->snapshot();
        }
        $this->state['day']=$targetDay;
        if(isset($this->state['revision_requests'])&&$this->state['revision_requests']!==[])$this->state=RevisionAutonomy::advance($this->state,$opinion);
        $this->state['agenda']=$remain;
        return ['day'=>$targetDay,'processed'=>$applied,
            'constitutional_breaches'=>$this->state['constitutional_breaches']];
    }

    /** @return array<string,mixed> */
    public function snapshot():array
    {
        $s=$this->state;
        ksort($s['bills'],SORT_STRING);
        usort($s['agenda'],static fn($a,$b)=>[$a['on'],$a['id']]<=>[$b['on'],$b['id']]);
        return $s;
    }

    public function hash():string
    {
        return hash('sha256',json_encode($this->snapshot(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }

    private function enqueue(string $id,string $kind,string $on):void
    {
        self::id($id);
        $this->state['agenda'][]=['id'=>$id,'kind'=>$kind,'on'=>$on];
    }
    private static function keys(array $data,array $expected):void
    {
        $a=array_keys($data);sort($a);sort($expected);
        if ($a!==$expected) throw new \InvalidArgumentException('Unexpected institutional action fields');
    }
    private static function id(string $id):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)) {
            throw new \InvalidArgumentException('Invalid identifier');
        }
    }
    private static function day(string $day):void
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day);
        if ($date===false || $date->format('Y-m-d')!==$day) {
            throw new \InvalidArgumentException('Invalid simulation day');
        }
    }
    private static function plus(string $day,int $days):string
    {
        return (new \DateTimeImmutable($day,new \DateTimeZone('UTC')))
            ->modify('+'.$days.' days')->format('Y-m-d');
    }
    private static function plusYears(string $day,int $years):string
    {
        return (new \DateTimeImmutable($day,new \DateTimeZone('UTC')))
            ->modify('+'.$years.' year')->format('Y-m-d');
    }
    private static function difference(string $a,string $b):int
    {
        return (int)(new \DateTimeImmutable($a))->diff(new \DateTimeImmutable($b))->format('%r%a');
    }
}
