<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/DiplomaticSimulation.php';
require_once __DIR__.'/EuropeanWithdrawal.php';

/**
 * WP09: charter-configured international organization memberships.
 *
 * No real charter rules are baked into this class. A real organization's
 * reviewed procedure, sources and effective dates must be imported by a
 * separate trusted reference-data pipeline. The bundled tests use a
 * completely FICTIONAL organization, not NATO/WTO/WHO/BRICS rules.
 */
final class OrganizationSimulation
{
    /** @var array<string,mixed> */
    private array $state;

    /**
     * @param list<array<string,mixed>> $definitions Trusted scenario fixtures
     */
    public function __construct(
        string $world,string $day,
        private readonly GeopoliticalActorRegistry $actors,
        array $definitions
    ) {
        self::identifier($world);
        self::day($day);
        $organizations=[];
        foreach ($definitions as $record) {
            self::keys($record,[
                'id','name','status','sources','approval_rule',
                'ratification_days','withdrawal_notice_days','founders'
            ]);
            self::identifier($record['id']);
            if (isset($organizations[$record['id']])) {
                throw new \DomainException('Duplicate organization');
            }
            if (!is_string($record['name']) || trim($record['name'])===''
                || strlen($record['name'])>180) {
                throw new \InvalidArgumentException('Invalid organization name');
            }
            if (!in_array($record['status'],['fictional_scenario','curated_real_charter'],true)) {
                throw new \InvalidArgumentException('Unknown treaty evidence status');
            }
            if (!is_array($record['sources']) || !array_is_list($record['sources'])
                || ($record['status']==='fictional_scenario' && $record['sources']!==[])
                || ($record['status']==='curated_real_charter' && $record['sources']===[])) {
                throw new \InvalidArgumentException('Charter provenance missing or misleading');
            }
            foreach ($record['sources'] as $source) {
                if (!is_string($source) || !str_starts_with($source,'https://')
                    || filter_var($source,FILTER_VALIDATE_URL)===false) {
                    throw new \InvalidArgumentException('Invalid official charter source');
                }
            }
            if (!in_array($record['approval_rule'],['unanimous','simple_majority'],true)
                || !is_int($record['ratification_days'])
                || !is_int($record['withdrawal_notice_days'])
                || $record['ratification_days']<0 || $record['ratification_days']>3650
                || $record['withdrawal_notice_days']<0 || $record['withdrawal_notice_days']>3650
                || !is_array($record['founders'])
                || !array_is_list($record['founders'])
                || count($record['founders'])<1 || count($record['founders'])>193) {
                throw new \InvalidArgumentException('Invalid charter constraints');
            }
            foreach ($record['founders'] as $id) {
                $this->requireState($id);
            }
            if (count($record['founders'])!==count(array_unique($record['founders']))) {
                throw new \InvalidArgumentException('Founding organization cannot have duplicate members');
            }
            $members=$record['founders'];
            sort($members,SORT_STRING);
            $record['members']=$members;
            unset($record['founders']);
            $organizations[$record['id']]=$record;
        }
        ksort($organizations,SORT_STRING);
        $charterSha=hash('sha256',self::encode($organizations));
        $this->state=[
            'world_id'=>$world,'day'=>$day,
            'actors_sha'=>$actors->hash(),'version'=>'organizations-wp10-0.1',
            'charters_sha'=>$charterSha,
            'organizations'=>$organizations,
            'applications'=>[],'withdrawals'=>[],'events'=>[],'commands'=>[]
        ];
    }

    /**
     * Reconstruct a saved campaign using the SAME trusted original charter
     * definitions. Memberships can change, but rules never silently change.
     *
     * @param array<string,mixed> $snapshot
     * @param list<array<string,mixed>> $charters
     */
    public static function fromSnapshot(
        array $snapshot, GeopoliticalActorRegistry $actors, array $charters
    ):self {
        self::keys($snapshot,[
            'world_id','day','actors_sha','version','charters_sha',
            'organizations','applications','withdrawals','events','commands',...(array_key_exists('eu_withdrawal',$snapshot)?['eu_withdrawal']:[])
        ]);
        if (!is_string($snapshot['world_id']) || !is_string($snapshot['day'])) {
            throw new \InvalidArgumentException('Invalid world identity in snapshot');
        }
        $self=new self($snapshot['world_id'],$snapshot['day'],$actors,$charters);
        if ($snapshot['version']!==$self->state['version']
            || !is_string($snapshot['actors_sha'])
            || !hash_equals($self->state['actors_sha'],$snapshot['actors_sha'])
            || !is_string($snapshot['charters_sha'])
            || !hash_equals($self->state['charters_sha'],$snapshot['charters_sha'])) {
            throw new \DomainException('Pinned charter version or geopolitical data differs');
        }
        foreach (['organizations','applications','withdrawals','commands'] as $key) {
            if (!is_array($snapshot[$key])
                || (array_is_list($snapshot[$key]) && $snapshot[$key]!==[])) {
                throw new \InvalidArgumentException('Invalid persisted keyed section '.$key);
            }
        }
        if (!is_array($snapshot['events']) || !array_is_list($snapshot['events'])) {
            throw new \InvalidArgumentException('Invalid persisted event journal');
        }
        $charterIds=array_keys($self->state['organizations']);
        $savedIds=array_keys($snapshot['organizations']);
        sort($charterIds,SORT_STRING);sort($savedIds,SORT_STRING);
        if ($charterIds!==$savedIds) {
            throw new \DomainException('Cannot change the charter roster on a running world');
        }
        foreach ($self->state['organizations'] as $orgId=>$template) {
            $record=$snapshot['organizations'][$orgId];
            if (!is_array($record)) throw new \InvalidArgumentException('Invalid saved charter');
            self::keys($record,array_keys($template));
            foreach ($template as $field=>$original) {
                if ($field!=='members' && $record[$field]!==$original) {
                    throw new \DomainException('Persisted charter rule was modified');
                }
            }
            $members=$record['members'];
            if (!is_array($members) || !array_is_list($members) || count($members)>193
                || count($members)!==count(array_unique($members))
                || $members!==array_values(array_unique($members))) {
                throw new \InvalidArgumentException('Invalid membership list');
            }
            foreach ($members as $id) {
                if (!is_string($id)) throw new \InvalidArgumentException('Invalid member');
                $self->requireState($id);
            }
        }
        foreach ($snapshot['applications'] as $id=>$application) {
            if (!is_string($id) || !is_array($application)) {
                throw new \InvalidArgumentException('Invalid application');
            }
            self::identifier($id);
            self::keys($application,[
                'id','organization','applicant','created_on','status',
                'electors','ballots','ready_on','approval_rule'
            ]);
            if ($application['id']!==$id
                || !isset($snapshot['organizations'][$application['organization']])
                || !in_array($application['status'],[
                    'voting','rejected','approved_pending_formalization','member'
                ],true)
                || !is_array($application['electors']) || !array_is_list($application['electors'])
                || !is_array($application['ballots'])
                || (array_is_list($application['ballots']) && $application['ballots']!==[])) {
                throw new \InvalidArgumentException('Invalid application status');
            }
            $self->requireState($application['applicant']);
            self::day($application['created_on']);
            if ($application['ready_on']!==null) self::day($application['ready_on']);
            $rule=$self->state['organizations'][$application['organization']]['approval_rule'];
            if ($application['approval_rule']!==$rule) {
                throw new \DomainException('Application violates pinned voting rule');
            }
            foreach ($application['electors'] as $elector) $self->requireState($elector);
            if (count($application['electors'])!==count(array_unique($application['electors']))) {
                throw new \InvalidArgumentException('Duplicate historical electors');
            }
            foreach ($application['ballots'] as $voter=>$ballot) {
                if (!in_array($voter,$application['electors'],true) || !is_bool($ballot)) {
                    throw new \InvalidArgumentException('Invalid historical ballot');
                }
            }
        }
        foreach ($snapshot['withdrawals'] as $id=>$withdrawal) {
            if (!is_string($id) || !is_array($withdrawal)) {
                throw new \InvalidArgumentException('Invalid withdrawal entry');
            }
            self::identifier($id);
            self::keys($withdrawal,['id','organization','actor','status','requested_on','effective_on']);
            if ($withdrawal['id']!==$id
                || !isset($snapshot['organizations'][$withdrawal['organization']])
                || !in_array($withdrawal['status'],['pending','effective'],true)) {
                throw new \InvalidArgumentException('Invalid withdrawal state');
            }
            $self->requireState($withdrawal['actor']);
            self::day($withdrawal['requested_on']);
            self::day($withdrawal['effective_on']);
        }
        foreach ($snapshot['commands'] as $id=>$receipt) {
            if (!is_string($id) || !is_array($receipt)) {
                throw new \InvalidArgumentException('Invalid command receipt');
            }
            self::identifier($id);
            self::keys($receipt,['sha','result']);
            if (!is_string($receipt['sha'])
                || !preg_match('/^[a-f0-9]{64}$/D',$receipt['sha'])
                || !is_array($receipt['result'])) {
                throw new \InvalidArgumentException('Invalid saved command receipt');
            }
        }
        foreach ($snapshot['events'] as $event) {
            if (!is_array($event) || !isset($event['id'],$event['day'],$event['kind'])
                || !is_string($event['id']) || !is_string($event['day'])) {
                throw new \InvalidArgumentException('Invalid saved organization event');
            }
            self::identifier($event['id']);
            self::day($event['day']);
        }
        if(isset($snapshot['eu_withdrawal'])){
            $eu=EuropeanWithdrawal::restore($snapshot['eu_withdrawal']);
            if($eu['day']!==$snapshot['day'])throw new \DomainException('EU clock mismatch');
        }elseif(array_key_exists('eu_withdrawal',$snapshot))throw new \DomainException('Invalid EU state');
        $self->state=$snapshot;
        return $self;
    }

    public function enableEuropeanWithdrawal(bool $tradeEnabled=false,bool $spilloversEnabled=false,bool $monetaryStressEnabled=false):void
    {
        if(isset($this->state['eu_withdrawal'])||$this->state['events']!==[]||$this->state['commands']!==[])throw new \DomainException('EU model must be pinned at bootstrap');
        $this->state['eu_withdrawal']=EuropeanWithdrawal::initial($this->state['day'],$tradeEnabled,$spilloversEnabled,$monetaryStressEnabled);
    }
    public function euIntent(DiplomaticPrincipal $principal,string $commandId):array
    {
        $this->authorize($principal,'state_fr','organizations.eu_intent');
        if($seen=$this->previous($commandId,'eu_intent','fr'))return $seen;
        if(!isset($this->state['eu_withdrawal']))throw new \DomainException('No EU model');
        $this->state['eu_withdrawal']=EuropeanWithdrawal::request($this->state['eu_withdrawal'],$this->state['day']);
        return $this->record($commandId,'eu_intent','fr',['eu_withdrawal'=>$this->state['eu_withdrawal'],'binding_effect'=>false]);
    }
    /** Trusted scenario evidence only; never exposed on player HTTP. */
    public function euEvidence(DiplomaticPrincipal $principal,string $kind,array $data,string $commandId):array
    {
        $this->authorizeService($principal,'organizations.eu_certify');
        $payload=self::encode([$kind,$data]);
        if($seen=$this->previous($commandId,'eu_evidence',$payload))return $seen;
        if(!isset($this->state['eu_withdrawal']))throw new \DomainException('No EU model');
        $this->state['eu_withdrawal']=EuropeanWithdrawal::certify($this->state['eu_withdrawal'],$this->state['day'],$kind,$data);
        return $this->record($commandId,'eu_evidence',$payload,['eu_withdrawal'=>$this->state['eu_withdrawal']]);
    }
    /** Request an application for membership; never grants membership now. */
    public function apply(
        DiplomaticPrincipal $principal,
        string $organization,string $applicant,string $commandId
    ):array {
        $this->authorize($principal,$applicant,'organizations.apply');
        $this->requireState($applicant);
        $org=$this->organization($organization);
        $payload=$organization."\n".$applicant;
        if ($seen=$this->previous($commandId,'apply',$payload)) return $seen;
        if (in_array($applicant,$org['members'],true)) {
            throw new \DomainException('Already a member');
        }
        foreach ($this->state['applications'] as $application) {
            if ($application['organization']===$organization && $application['applicant']===$applicant
                && $application['status']==='voting') {
                throw new \DomainException('An application is already pending');
            }
        }
        $application=[
            'id'=>$commandId,'organization'=>$organization,'applicant'=>$applicant,
            'created_on'=>$this->state['day'],'status'=>'voting',
            'electors'=>$org['members'],'ballots'=>[],
            'ready_on'=>null,'approval_rule'=>$org['approval_rule']
        ];
        $this->state['applications'][$commandId]=$application;
        return $this->record($commandId,'apply',$payload,[
            'application_id'=>$commandId,'status'=>'voting'
        ]);
    }

    /**
     * In-game member vote. This only permits subsequent formal ratification;
     * it does NOT equate to real-world accession.
     */
    public function vote(
        DiplomaticPrincipal $principal,
        string $voter,string $applicationId,bool $approve,string $commandId
    ):array {
        self::identifier($applicationId);
        $this->authorize($principal,$voter,'organizations.vote');
        $payload=$voter."\n".$applicationId."\n".($approve?'yes':'no');
        if ($seen=$this->previous($commandId,'vote',$payload)) return $seen;
        $application=$this->state['applications'][$applicationId]??null;
        if ($application===null || $application['status']!=='voting'
            || !in_array($voter,$application['electors'],true)) {
            throw new \DomainException('No voting entitlement');
        }
        if (isset($application['ballots'][$voter])) {
            throw new \DomainException('Vote already recorded');
        }
        $this->state['applications'][$applicationId]['ballots'][$voter]=$approve;
        $votes=$this->state['applications'][$applicationId]['ballots'];
        $electorCount=count($application['electors']);
        $yes=count(array_filter($votes,static fn(bool $v):bool=>$v));
        $no=count($votes)-$yes;
        $approved=$application['approval_rule']==='unanimous'
            ?$yes===$electorCount
            :$yes>intdiv($electorCount,2);
        $rejected=$application['approval_rule']==='unanimous'
            ?$no>0
            :$no>=intdiv($electorCount+1,2);
        if ($approved) {
            $this->state['applications'][$applicationId]['status']='approved_pending_formalization';
            $this->state['applications'][$applicationId]['ready_on']=self::plus(
                $this->state['day'],
                $this->organization($application['organization'])['ratification_days']
            );
        } elseif ($rejected) {
            $this->state['applications'][$applicationId]['status']='rejected';
        }
        $status=$this->state['applications'][$applicationId]['status'];
        return $this->record($commandId,'vote',$payload,[
            'application_id'=>$applicationId,'status'=>$status,
            'binding_effect'=>false
        ]);
    }

    /** Only the trusted charter-administration service may finalize accession. */
    public function formalize(
        DiplomaticPrincipal $principal,
        string $applicationId,string $commandId
    ):array {
        self::identifier($applicationId);
        $this->authorizeService($principal,'organizations.formalize');
        $payload=$applicationId;
        if ($seen=$this->previous($commandId,'formalize',$payload)) return $seen;
        $application=$this->state['applications'][$applicationId]??null;
        if ($application===null || $application['status']!=='approved_pending_formalization'
            || $application['ready_on']>$this->state['day']) {
            throw new \DomainException('Organization accession is not ready');
        }
        $orgId=$application['organization'];
        $member=$application['applicant'];
        if (in_array($member,$this->state['organizations'][$orgId]['members'],true)) {
            throw new \DomainException('Membership already effective');
        }
        $this->state['organizations'][$orgId]['members'][]=$member;
        sort($this->state['organizations'][$orgId]['members'],SORT_STRING);
        $this->state['applications'][$applicationId]['status']='member';
        return $this->record($commandId,'formalize',$payload,[
            'application_id'=>$applicationId,'status'=>'member',
            'member'=>$member
        ]);
    }

    /** Initiate withdrawal according to the scenario's source-pinned notice. */
    public function requestWithdrawal(
        DiplomaticPrincipal $principal,
        string $organization,string $actor,string $commandId
    ):array {
        $this->authorize($principal,$actor,'organizations.withdraw');
        $payload=$organization."\n".$actor;
        if ($seen=$this->previous($commandId,'withdraw',$payload)) return $seen;
        $org=$this->organization($organization);
        if (!in_array($actor,$org['members'],true)) {
            throw new \DomainException('No active membership to withdraw');
        }
        foreach ($this->state['withdrawals'] as $withdrawal) {
            if ($withdrawal['organization']===$organization && $withdrawal['actor']===$actor
                && $withdrawal['status']==='pending') {
                throw new \DomainException('Withdrawal already pending');
            }
        }
        $effective=self::plus($this->state['day'],$org['withdrawal_notice_days']);
        $this->state['withdrawals'][$commandId]=[
            'id'=>$commandId,'organization'=>$organization,
            'actor'=>$actor,'status'=>'pending',
            'requested_on'=>$this->state['day'],'effective_on'=>$effective
        ];
        return $this->record($commandId,'withdraw',$payload,[
            'withdrawal_id'=>$commandId,'status'=>'pending','effective_on'=>$effective
        ]);
    }

    /** Simulation clock, server worker only; due withdrawals become effective. */
    public function advanceTo(DiplomaticPrincipal $service,string $day,string $commandId):array
    {
        $this->authorizeService($service,'organizations.clock');
        self::day($day);
        if ($day<$this->state['day']) throw new \InvalidArgumentException('Time reversal');
        if ($seen=$this->previous($commandId,'clock',$day)) return $seen;
        $this->state['day']=$day;
        if(isset($this->state['eu_withdrawal']))$this->state['eu_withdrawal']=EuropeanWithdrawal::advance($this->state['eu_withdrawal'],$day);
        $processed=[];
        foreach ($this->state['withdrawals'] as $id=>&$withdrawal) {
            if ($withdrawal['status']==='pending' && $withdrawal['effective_on']<=$day) {
                $orgId=$withdrawal['organization'];
                $this->state['organizations'][$orgId]['members']=array_values(array_filter(
                    $this->state['organizations'][$orgId]['members'],
                    static fn(string $member):bool=>$member!==$withdrawal['actor']
                ));
                $withdrawal['status']='effective';
                $processed[]=$id;
            }
        }
        unset($withdrawal);
        sort($processed,SORT_STRING);
        return $this->record($commandId,'clock',$day,[
            'day'=>$day,'withdrawals_effective'=>$processed
        ]);
    }

    /** @return array<string,mixed> */
    public function snapshot():array
    {
        $state=$this->state;
        foreach(['applications','withdrawals','commands'] as $key) ksort($state[$key],SORT_STRING);
        foreach ($state['applications'] as &$app) ksort($app['ballots'],SORT_STRING);
        unset($app);
        return $state;
    }

    public function hash():string
    {
        return hash('sha256',json_encode(
            $this->snapshot(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
        ));
    }

    private static function encode(mixed $data):string
    {
        return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string,mixed> */
    private function organization(string $id):array
    {
        self::identifier($id);
        if (!isset($this->state['organizations'][$id])) {
            throw new \DomainException('Unknown organization');
        }
        return $this->state['organizations'][$id];
    }

    private function requireState(string $id):void
    {
        if ($this->actors->get($id)['kind']!=='state') {
            throw new \DomainException('Administrative authority is not automatically eligible for interstate membership');
        }
    }

    private function authorize(DiplomaticPrincipal $principal,string $actor,string $ability):void
    {
        $record=$this->actors->get($actor);
        if (!$principal->allows($this->state['world_id'],$actor,$ability)
            || ($record['controller']==='ai_only'
                && !$principal->allows($this->state['world_id'],$actor,'organizations.ai_controller'))) {
            throw new \DomainException('Actor not authorized for this organization action');
        }
    }

    private function authorizeService(DiplomaticPrincipal $principal,string $ability):void
    {
        if (!$principal->allows($this->state['world_id'],$principal->actorId(),$ability)) {
            throw new \DomainException('Trusted treaty service required');
        }
    }

    /** @return array<string,mixed>|null */
    private function previous(string $commandId,string $kind,string $payload):?array
    {
        self::identifier($commandId);
        $receipt=$this->state['commands'][$commandId]??null;
        if ($receipt===null) return null;
        $fingerprint=hash('sha256',$kind."\n".$payload);
        if (!hash_equals($receipt['sha'],$fingerprint)) {
            throw new \DomainException('Idempotency conflict');
        }
        return $receipt['result'];
    }

    /** @param array<string,mixed> $result @return array<string,mixed> */
    private function record(string $commandId,string $kind,string $payload,array $result):array
    {
        $this->state['commands'][$commandId]=[
            'sha'=>hash('sha256',$kind."\n".$payload),'result'=>$result
        ];
        $this->state['events'][]=[
            'id'=>$commandId,'day'=>$this->state['day'],'kind'=>$kind,'result'=>$result
        ];
        return $result;
    }

    /** @param array<string,mixed> $record @param list<string> $expected */
    private static function keys(array $record,array $expected):void
    {
        $keys=array_keys($record);sort($keys);sort($expected);
        if ($keys!==$expected) throw new \InvalidArgumentException('Invalid organization schema');
    }

    private static function identifier(string $id):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)) {
            throw new \InvalidArgumentException('Invalid organization or command id');
        }
    }

    private static function day(string $day):void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day)
            || ($date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day))===false
            || $date->format('Y-m-d')!==$day) {
            throw new \InvalidArgumentException('Invalid game day');
        }
    }

    private static function plus(string $day,int $days):string
    {
        return (new \DateTimeImmutable($day,new \DateTimeZone('UTC')))
            ->modify('+'.$days.' days')->format('Y-m-d');
    }
}
