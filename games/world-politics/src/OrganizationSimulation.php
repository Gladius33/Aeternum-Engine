<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/DiplomaticSimulation.php';

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
        $this->state=[
            'world_id'=>$world,'day'=>$day,
            'actors_sha'=>$actors->hash(),'version'=>'organizations-wp09-0.1',
            'organizations'=>$organizations,
            'applications'=>[],'withdrawals'=>[],'events'=>[],'commands'=>[]
        ];
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
