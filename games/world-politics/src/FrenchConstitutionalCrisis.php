<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

/**
 * An institutionally verified principal. There is no "council_approved",
 * number of signatories or parliamentary ballot value in player JSON.
 *
 * Production implementations must verify the office, the actual council
 * record, deputy signatures and the authenticated Assembly ballot.
 */
interface FrenchConstitutionalPrincipal
{
    public function actorId():string;
    public function allows(string $world,string $ability):bool;
    public function councilDeliberated(string $world,string $billId):bool;
    /** Legislative legal classification verified from official bill metadata. */
    public function verifiedBillCategory(string $world,string $billId):string;
    public function verifiedSignatures(string $world,string $motionId):int;
    public function verifiedVotesFor(string $world,string $motionId):int;
    /** Authoritative simulation time, not a client-selected timestamp. */
    public function verifiedInstant(string $world,string $operation):string;
}

/**
 * WP19 — France, Articles 49(3), 49(2) procedure & 50.
 *
 * The actual constitutional law is separated from configurable political
 * feedback. Government resignation is never a regime collapse. Adopted
 * means adopted by the NATIONAL ASSEMBLY, NOT a promulgated statute.
 *
 * Inputs are offset-aware instants; the statute-based 24h and 48h limits
 * remain durations, never "next game-day" shortcuts.
 *
 * Sources:
 * https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000571356/
 * https://www.assemblee-nationale.fr/dyn/synthese/fonctionnement-assemblee-nationale/travail-legislatif/la-procedure-legislative
 */
/**
 * Additional authenticated parliamentary clerk sources for 49(1) and 49(2).
 * Only this server-side interface can certify roll calls and deputy identities.
 */
interface FrenchParliamentaryPrincipal extends FrenchConstitutionalPrincipal
{
    /** @return list<string> unique, verified actual Assembly deputy identifiers */
    public function verifiedDeputySignatories(string $world,string $motionId):array;
    /** @return array{for:int,against:int,abstain:int} */
    public function verifiedConfidenceBallot(string $world,string $statementId):array;
    /** Session type must come from approved parliamentary calendar. */
    public function verifiedSessionKind(string $world,string $session):string;
}

/**
 * Mandatory when the Assembly roster is pinned to a scenario.
 * The pre-vote constitutional SHA is checked under the state lock.
 */
interface FrenchBallotProofPrincipal extends FrenchParliamentaryPrincipal
{
    public function verifiedBallotSourceSha(
        string $world,string $matterId,string $kind
    ):string;
}

final class FrenchConstitutionalCrisis
{
    public const VERSION='wp22-fr-constitutional-responsibility-1';
    private array $state;

    public function __construct(
        string $world,string $session,int $startingInstant,int $assemblyMembers
    ) {
        self::id($world);self::id($session);
        if ($startingInstant<0 || $startingInstant>4102444800) {
            throw new \InvalidArgumentException('Invalid simulation epoch time');
        }
        if ($assemblyMembers<1 || $assemblyMembers>577) {
            throw new \InvalidArgumentException('Verified French Assembly members must be 1..577');
        }
        $this->state=[
            'world_id'=>$world,'version'=>self::VERSION,
            'session'=>$session,'session_kind'=>'ordinary','now'=>$startingInstant,
            'assembly_members'=>$assemblyMembers,
            'confidence'=>null,'confidence_history'=>[],
            'spontaneous_motions'=>[],'signatory_counters'=>[],
            'ordinary_49_3_used'=>false,'ordinary_49_3_bill'=>null,
            'government'=>'in_office',
            'current_bill'=>null,'motions'=>[],'history'=>[],
            'events'=>[],'commands'=>[]
        ];
    }

    public static function instant(string $stamp):int
    {
        // Require a precise timezone offset, so daylight-saving changes
        // never silently add/remove 60 minutes from a constitutional window.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D',$stamp)) {
            throw new \InvalidArgumentException('Use offset-aware RFC3339 seconds');
        }
        try {
            $date=new \DateTimeImmutable($stamp);
        } catch (\Exception $error) {
            throw new \InvalidArgumentException('Invalid constitutional instant',0,$error);
        }
        $dateInOriginalOffset=$date->format('Y-m-d\TH:i:sP');
        $normalized=str_ends_with($stamp,'Z')
            ? substr($stamp,0,-1).'+00:00':$stamp;
        if ($dateInOriginalOffset!==$normalized) {
            throw new \InvalidArgumentException('Invalid constitutional calendar timestamp');
        }
        return $date->getTimestamp();
    }

    /** 49.3 applies to a draft finance/social security finance bill, or one other bill/session. */
    public function engage(
        FrenchConstitutionalPrincipal $principal,string $billId,
        string $category,string $at,string $commandId
    ):array {
        self::id($billId);self::id($commandId);
        $when=$this->at($at);
        if (!$principal->allows($this->state['world_id'],'fr.49_3.engage')
            || !$principal->councilDeliberated($this->state['world_id'],$billId)) {
            throw new \DomainException('Prime minister authority and prior Council deliberation required');
        }
        if (!in_array($category,['finance','social_security_finance','ordinary'],true)) {
            throw new \InvalidArgumentException('Article 49(3) is not available for this category');
        }
        if ($principal->verifiedBillCategory($this->state['world_id'],$billId)!==$category) {
            throw new \DomainException('Verified bill category differs from the requested Article 49(3) route');
        }
        $payload=[$billId,$category,$when];
        if ($prior=$this->prior($commandId,'engage',$payload)) return $prior;
        if ($this->state['government']!=='in_office' || $this->state['current_bill']!==null) {
            throw new \DomainException('No active government or a previous 49(3) remains unresolved');
        }
        if ($category==='ordinary'
            && $this->state['ordinary_49_3_used']
            && $this->state['ordinary_49_3_bill']!==$billId) {
            throw new \DomainException('A different ordinary text already used Article 49(3) this session');
        }
        if ($category==='ordinary' && !$this->state['ordinary_49_3_used']) {
            $this->state['ordinary_49_3_used']=true;
            $this->state['ordinary_49_3_bill']=$billId;
        }
        $this->state['current_bill']=[
            'bill_id'=>$billId,'category'=>$category,'engaged_at'=>$when,
            'censure_filing_deadline'=>$when+86400,
            'status'=>'censure_window_open',
            'assembly_adoption'=>null
        ];
        $this->state['now']=$when;
        return $this->record($commandId,'engage',$payload,[
            'bill_id'=>$billId,'status'=>'censure_window_open',
            'filing_closes_at'=>$when+86400,
            'gov_resigned'=>false,'law_promulgated'=>false
        ]);
    }

    /** Verified parliamentary signatures: ceil(Assembly members / 10). */
    public function fileMotion(
        FrenchConstitutionalPrincipal $principal,string $motionId,
        string $at,string $commandId
    ):array {
        self::id($motionId);self::id($commandId);
        $when=$this->at($at);
        $this->require($principal,'fr.censure.file');
        $signatures=$principal->verifiedSignatures(
            $this->state['world_id'],$motionId
        );
        if ($signatures<0 || $signatures>$this->state['assembly_members']) {
            throw new \DomainException('Unverified motion signatures');
        }
        $payload=[$motionId,$when,$signatures];
        if ($prior=$this->prior($commandId,'file_motion',$payload)) return $prior;
        $bill=$this->state['current_bill'];
        if ($bill===null || $bill['status']!=='censure_window_open'
            || $when>$bill['censure_filing_deadline']) {
            throw new \DomainException('Article 49(3) 24-hour filing window is closed');
        }
        if ($signatures<intdiv($this->state['assembly_members']+9,10)) {
            throw new \DomainException('Fewer than one tenth of Assembly members signed');
        }
        if (isset($this->state['motions'][$motionId])) {
            throw new \DomainException('Motion already filed');
        }
        $this->state['motions'][$motionId]=[
            'id'=>$motionId,'bill_id'=>$bill['bill_id'],
            'filed_at'=>$when,'earliest_vote_at'=>$when+172800,
            'verified_signatures'=>$signatures,
            'status'=>'filed','verified_votes_for'=>null
        ];
        $this->state['now']=$when;
        return $this->record($commandId,'file_motion',$payload,[
            'motion_id'=>$motionId,'earliest_vote_at'=>$when+172800,
            'status'=>'filed','needed_votes'=>intdiv($this->state['assembly_members'],2)+1
        ]);
    }

    /** Only recorded positive votes count; abstentions do not pass the motion. */
    public function vote(
        FrenchConstitutionalPrincipal $principal,string $motionId,
        string $at,string $commandId
    ):array {
        self::id($motionId);self::id($commandId);
        $when=$this->at($at);
        $this->require($principal,'fr.censure.vote');
        $yes=$principal->verifiedVotesFor($this->state['world_id'],$motionId);
        if ($yes<0 || $yes>$this->state['assembly_members']) {
            throw new \DomainException('Ballot must be verified against actual Assembly members');
        }
        $payload=[$motionId,$when,$yes];
        if ($prior=$this->prior($commandId,'vote_motion',$payload)) return $prior;
        $motion=$this->state['motions'][$motionId]??null;
        if ($motion===null || $motion['status']!=='filed'
            || ($this->state['current_bill']['bill_id']??null)!==$motion['bill_id']
            || $when<$motion['earliest_vote_at']) {
            throw new \DomainException('Motion not pending or constitutional 48 hours not elapsed');
        }
        $passed=$yes>=intdiv($this->state['assembly_members'],2)+1;
        $this->state['motions'][$motionId]['status']=$passed?'adopted':'rejected';
        $this->state['motions'][$motionId]['verified_votes_for']=$yes;
        $this->state['now']=$when;
        if ($passed) {
            $this->state['government']='resignation_required';
            $this->closeBill('government_censured',false);
        } elseif ($when>$this->state['current_bill']['censure_filing_deadline']
            && $this->noPendingMotions()) {
            $this->closeBill('adopted_by_assembly',true);
        }
        return $this->record($commandId,'vote_motion',$payload,[
            'motion_id'=>$motionId,
            'motion_status'=>$passed?'adopted':'rejected',
            'government'=>$this->state['government'],
            'regime_changed'=>false,
            'bill_assembly_status'=>$passed?'not_adopted_due_to_censure':
                ($this->state['current_bill']['status']??'adopted_by_assembly'),
        ]);
    }

    /**
     * Article 49(1) — government confidence on its programme or general
     * policy, after Council of Ministers deliberation. The PM, not a web
     * player, must have verified permission.
     */
    public function engageConfidence(
        FrenchParliamentaryPrincipal $principal,string $statementId,
        string $kind,string $at,string $commandId
    ):array {
        self::id($statementId);self::id($commandId);
        $when=$this->at($at);
        $this->require($principal,'fr.49_1.engage');
        if (!$principal->councilDeliberated($this->state['world_id'],$statementId)) {
            throw new \DomainException('Article 49(1) requires prior cabinet deliberation');
        }
        if (!in_array($kind,['programme','general_policy'],true)) {
            throw new \InvalidArgumentException('Unsupported confidence declaration');
        }
        $payload=[$statementId,$kind,$when];
        if ($prior=$this->prior($commandId,'engage_confidence',$payload)) return $prior;
        if ($this->state['government']!=='in_office'
            || $this->state['confidence']!==null) {
            throw new \DomainException('Government cannot open overlapping confidence votes');
        }
        $this->state['confidence']=[
            'statement_id'=>$statementId,'kind'=>$kind,
            'engaged_at'=>$when,'status'=>'awaiting_vote'
        ];
        $this->state['now']=$when;
        return $this->record($commandId,'engage_confidence',$payload,[
            'statement_id'=>$statementId,'status'=>'awaiting_vote',
            'government'=>'in_office','regime_changed'=>false
        ]);
    }

    /**
     * A 49(1) vote differs from a censure motion:
     * votes FOR must strictly outnumber votes AGAINST among expressed votes.
     * Abstentions do not count as expressed votes. Rejection requires the
     * government's resignation under Article 50, NOT regime change.
     */
    public function voteConfidence(
        FrenchParliamentaryPrincipal $principal,string $statementId,
        string $at,string $commandId
    ):array {
        self::id($statementId);self::id($commandId);
        $when=$this->at($at);
        $this->require($principal,'fr.49_1.vote');
        $ballot=$principal->verifiedConfidenceBallot(
            $this->state['world_id'],$statementId
        );
        self::fields($ballot,['for','against','abstain']);
        foreach ($ballot as $number) {
            if (!is_int($number) || $number<0 || $number>$this->state['assembly_members']) {
                throw new \DomainException('Unverified parliamentary ballot count');
            }
        }
        if (array_sum($ballot)>$this->state['assembly_members']) {
            throw new \DomainException('More votes than Assembly members');
        }
        $payload=[$statementId,$when,$ballot];
        if ($prior=$this->prior($commandId,'vote_confidence',$payload)) return $prior;
        $current=$this->state['confidence'];
        if ($current===null || $current['statement_id']!==$statementId
            || $current['status']!=='awaiting_vote'
            || $this->state['government']!=='in_office') {
            throw new \DomainException('No outstanding 49(1) vote for this statement');
        }
        $passed=$ballot['for']>$ballot['against'];
        $current['status']=$passed?'approved':'rejected';
        $current['ballot']=$ballot;
        $current['voted_at']=$when;
        $this->state['confidence_history'][]=$current;
        $this->state['confidence']=null;
        if (!$passed) {
            $this->state['government']='resignation_required';
            if ($this->state['current_bill']!==null) {
                $this->closeBill('government_censured',false);
            }
        }
        $this->state['now']=$when;
        return $this->record($commandId,'vote_confidence',$payload,[
            'statement_id'=>$statementId,
            'confidence'=>$passed?'approved':'rejected',
            'votes_for'=>$ballot['for'],'votes_against'=>$ballot['against'],
            'abstentions'=>$ballot['abstain'],
            'government'=>$this->state['government'],
            'regime_changed'=>false
        ]);
    }

    /**
     * Article 49(2) motion filed without any 49(3) engagement.
     * Every actual signatory is checked against 3/1 quotas per session,
     * independently of unlimited 49(3) incidental motions.
     */
    public function fileSpontaneousMotion(
        FrenchParliamentaryPrincipal $principal,string $motionId,
        string $at,string $commandId
    ):array {
        self::id($motionId);self::id($commandId);
        $when=$this->at($at);
        $this->require($principal,'fr.49_2.file');
        $names=$principal->verifiedDeputySignatories(
            $this->state['world_id'],$motionId
        );
        if (!is_array($names) || !array_is_list($names)
            || count($names)>$this->state['assembly_members']) {
            throw new \DomainException('Invalid verified list of motion signatories');
        }
        foreach ($names as $name) {
            if (!is_string($name)) throw new \DomainException('Invalid deputy identity');
            self::id($name);
        }
        if (count(array_unique($names))!==count($names)) {
            throw new \DomainException('Duplicate deputy signatures on motion');
        }
        sort($names,SORT_STRING);
        if (count($names)<intdiv($this->state['assembly_members']+9,10)) {
            throw new \DomainException('At least one tenth of deputies must sign the motion');
        }
        $payload=[$motionId,$when,$names,$this->state['session']];
        if ($prior=$this->prior($commandId,'file_spontaneous',$payload)) return $prior;
        if ($this->state['government']!=='in_office'
            || isset($this->state['spontaneous_motions'][$motionId])
            || isset($this->state['motions'][$motionId])) {
            throw new \DomainException('The ministry is no longer in office or motion already exists');
        }
        $limit=$this->state['session_kind']==='extraordinary'?1:3;
        foreach ($names as $name) {
            if (($this->state['signatory_counters'][$name]??0)>=$limit) {
                throw new \DomainException('Constitutional per-deputy motion signature quota exceeded');
            }
        }
        foreach ($names as $name) {
            $this->state['signatory_counters'][$name]=
                ($this->state['signatory_counters'][$name]??0)+1;
        }
        $this->state['spontaneous_motions'][$motionId]=[
            'id'=>$motionId,'session'=>$this->state['session'],
            'filed_at'=>$when,'earliest_vote_at'=>$when+172800,
            'signatories'=>$names,'status'=>'filed','verified_votes_for'=>null
        ];
        $this->state['now']=$when;
        return $this->record($commandId,'file_spontaneous',$payload,[
            'motion_id'=>$motionId,'status'=>'filed',
            'earliest_vote_at'=>$when+172800,
            'needed_votes'=>intdiv($this->state['assembly_members'],2)+1,
            'signatories_count'=>count($names),
            'per_deputy_limit'=>$limit
        ]);
    }

    public function voteSpontaneousMotion(
        FrenchParliamentaryPrincipal $principal,string $motionId,
        string $at,string $commandId
    ):array {
        self::id($motionId);self::id($commandId);
        $when=$this->at($at);
        $this->require($principal,'fr.49_2.vote');
        $yes=$principal->verifiedVotesFor(
            $this->state['world_id'],$motionId
        );
        if ($yes<0 || $yes>$this->state['assembly_members']) {
            throw new \DomainException('Unverified 49(2) censure votes');
        }
        $payload=[$motionId,$when,$yes];
        if ($prior=$this->prior($commandId,'vote_spontaneous',$payload)) return $prior;
        $motion=$this->state['spontaneous_motions'][$motionId]??null;
        if ($motion===null || $motion['status']!=='filed'
            || $when<$motion['earliest_vote_at']
            || $this->state['government']!=='in_office') {
            throw new \DomainException('No votable Article 49(2) motion or 48-hour delay not elapsed');
        }
        $adopted=$yes>=intdiv($this->state['assembly_members'],2)+1;
        $this->state['spontaneous_motions'][$motionId]['status']=
            $adopted?'adopted':'rejected';
        $this->state['spontaneous_motions'][$motionId]['verified_votes_for']=$yes;
        if ($adopted) {
            $this->state['government']='resignation_required';
            if ($this->state['current_bill']!==null) {
                $this->closeBill('government_censured',false);
            }
            if ($this->state['confidence']!==null) {
                $this->state['confidence']['status']='cancelled_after_censure';
                $this->state['confidence_history'][]=$this->state['confidence'];
                $this->state['confidence']=null;
            }
        }
        $this->state['now']=$when;
        return $this->record($commandId,'vote_spontaneous',$payload,[
            'motion_id'=>$motionId,'motion_status'=>$adopted?'adopted':'rejected',
            'verified_votes_for'=>$yes,
            'government'=>$this->state['government'],
            'regime_changed'=>false
        ]);
    }

    /** Deterministic housekeeping: 24h window expires, pending votes remain pending. */
    public function advance(
        FrenchConstitutionalPrincipal $scheduler,string $target,
        string $commandId
    ):array {
        self::id($commandId);
        $when=$this->at($target);
        $this->require($scheduler,'fr.constitution.clock');
        if ($prior=$this->prior($commandId,'clock',[$when])) return $prior;
        $bill=$this->state['current_bill'];
        if ($bill!==null && $when>$bill['censure_filing_deadline']) {
            if ($this->noPendingMotions()) {
                $this->closeBill('adopted_by_assembly',true);
            } else {
                $this->state['current_bill']['status']='censure_vote_pending';
            }
        }
        $this->state['now']=$when;
        return $this->record($commandId,'clock',[$when],[
            'now'=>$when,
            'government'=>$this->state['government'],
            'pending_bill'=>$this->state['current_bill']['bill_id']??null,
            'regime_changed'=>false
        ]);
    }

    /**
     * Only a trusted constitutional calendar may start a new parliamentary
     * session. This does NOT appoint a new government after a censure.
     */
    public function openSession(
        FrenchConstitutionalPrincipal $scheduler,string $nextSession,
        string $at,string $commandId
    ):array {
        self::id($nextSession);self::id($commandId);
        $when=$this->at($at);
        $this->require($scheduler,'fr.constitution.session');
        if ($prior=$this->prior($commandId,'session',[$nextSession,$when])) return $prior;
        if ($nextSession===$this->state['session']
            || $this->state['current_bill']!==null
            || $this->state['confidence']!==null) {
            throw new \DomainException('A new session requires a distinct session and no unresolved bill');
        }
        foreach ($this->state['spontaneous_motions'] as $motion) {
            if ($motion['status']==='filed') {
                throw new \DomainException('Cannot silently end a session with undecided censure motions');
            }
        }
        $kind=$scheduler instanceof FrenchParliamentaryPrincipal
            ?$scheduler->verifiedSessionKind($this->state['world_id'],$nextSession)
            :'ordinary';
        if (!in_array($kind,['ordinary','extraordinary'],true)) {
            throw new \DomainException('Unverified parliamentary session type');
        }
        $this->state['session']=$nextSession;
        $this->state['session_kind']=$kind;
        $this->state['signatory_counters']=[];
        $this->state['ordinary_49_3_used']=false;
        $this->state['ordinary_49_3_bill']=null;
        $this->state['now']=$when;
        return $this->record($commandId,'session',[$nextSession,$when],[
            'session'=>$nextSession,'ordinary_49_3_available'=>true
        ]);
    }

    /** @return array<string,mixed> */
    public function snapshot():array
    {
        $s=$this->state;
        ksort($s['motions'],SORT_STRING);
        ksort($s['spontaneous_motions'],SORT_STRING);
        ksort($s['signatory_counters'],SORT_STRING);
        ksort($s['commands'],SORT_STRING);
        return $s;
    }

    public function hash():string
    {
        return hash('sha256',json_encode(
            $this->snapshot(),
            JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR
        ));
    }

    public static function restore(array $state):self
    {
        $fields=['world_id','version','session','now','assembly_members',
            'ordinary_49_3_used','ordinary_49_3_bill',
            'government','current_bill','motions','session_kind',
            'confidence','confidence_history','spontaneous_motions',
            'signatory_counters',
            'history','events','commands'];
        self::fields($state,$fields);
        if (($state['version']??null)!==self::VERSION
            || !is_string($state['world_id']) || !is_string($state['session'])
            || !is_int($state['now']) || !is_int($state['assembly_members'])) {
            throw new \DomainException('Unsupported constitutional data version or scope');
        }
        $self=new self(
            $state['world_id'],$state['session'],
            $state['now'],$state['assembly_members']
        );
        if (!in_array($state['session_kind'],['ordinary','extraordinary'],true)
            || !is_array($state['confidence_history'])
            || !array_is_list($state['confidence_history'])
            || ($state['confidence']!==null && !is_array($state['confidence']))
            || !is_array($state['spontaneous_motions'])
            || !is_array($state['signatory_counters'])) {
            throw new \InvalidArgumentException('Invalid 49(1)/49(2) constitutional state');
        }
        if (!is_bool($state['ordinary_49_3_used'])
            || ($state['ordinary_49_3_bill']!==null
                && !is_string($state['ordinary_49_3_bill']))
            || ($state['ordinary_49_3_used']!==($state['ordinary_49_3_bill']!==null))
            || !in_array($state['government'],['in_office','resignation_required'],true)
            || ($state['current_bill']!==null && !is_array($state['current_bill']))
            || !is_array($state['motions']) || !is_array($state['history'])
            || !is_array($state['events']) || !is_array($state['commands'])
            || !array_is_list($state['events']) || !array_is_list($state['history'])) {
            throw new \InvalidArgumentException('Invalid constitutional snapshot');
        }
        if (count($state['events'])>10000 || count($state['commands'])>10000
            || count($state['motions'])>300
            || count($state['spontaneous_motions'])>300
            || count($state['signatory_counters'])>577) {
            throw new \InvalidArgumentException('Oversized constitutional state');
        }
        if ($state['current_bill']!==null) {
            self::fields($state['current_bill'],[
                'bill_id','category','engaged_at','censure_filing_deadline',
                'status','assembly_adoption'
            ]);
            self::id($state['current_bill']['bill_id']);
            if (!is_int($state['current_bill']['engaged_at'])
                || $state['current_bill']['censure_filing_deadline']
                    !==$state['current_bill']['engaged_at']+86400
                || !in_array($state['current_bill']['category'],[
                    'finance','social_security_finance','ordinary'
                ],true)
                || !in_array($state['current_bill']['status'],[
                    'censure_window_open','censure_vote_pending'
                ],true)) throw new \InvalidArgumentException('Invalid ongoing 49.3');
        }
        foreach ($state['motions'] as $key=>$motion) {
            self::id($key);
            if (!is_array($motion)) throw new \InvalidArgumentException('Invalid motion');
            self::fields($motion,[
                'id','bill_id','filed_at','earliest_vote_at',
                'verified_signatures','status','verified_votes_for'
            ]);
            if ($key!==$motion['id']
                || !is_int($motion['filed_at'])
                || $motion['earliest_vote_at']!==$motion['filed_at']+172800
                || !in_array($motion['status'],['filed','rejected','adopted'],true)) {
                throw new \InvalidArgumentException('Invalid persisted motion');
            }
        }
        foreach ($state['signatory_counters'] as $name=>$n) {
            self::id($name);
            if (!is_int($n) || $n<0 || $n>3) {
                throw new \InvalidArgumentException('Invalid deputy constitutional signature counter');
            }
        }
        foreach ($state['spontaneous_motions'] as $id=>$motion) {
            self::id($id);
            if (!is_array($motion)) {
                throw new \InvalidArgumentException('Invalid spontaneous censure record');
            }
            self::fields($motion,[
                'id','session','filed_at','earliest_vote_at','signatories',
                'status','verified_votes_for'
            ]);
            if ($id!==$motion['id'] || !is_int($motion['filed_at'])
                || $motion['earliest_vote_at']!==$motion['filed_at']+172800
                || !in_array($motion['status'],['filed','adopted','rejected'],true)
                || !is_array($motion['signatories'])
                || count($motion['signatories'])>$state['assembly_members']) {
                throw new \InvalidArgumentException('Invalid 49(2) motion receipt');
            }
        }
        if ($state['confidence']!==null) {
            self::fields($state['confidence'],[
                'statement_id','kind','engaged_at','status'
            ]);
            if ($state['confidence']['status']!=='awaiting_vote') {
                throw new \InvalidArgumentException('Illegal pending confidence status');
            }
        }
        if ($state['ordinary_49_3_bill']!==null) {
            self::id($state['ordinary_49_3_bill']);
        }
        $self->state=$state;
        return $self;
    }

    private function closeBill(string $status,bool $adopted):void
    {
        $bill=$this->state['current_bill'];
        if ($bill===null) throw new \DomainException('No active 49(3) text');
        $bill['status']=$status;
        $bill['assembly_adoption']=$adopted;
        $this->state['history'][]=$bill;
        $this->state['current_bill']=null;
    }

    private function noPendingMotions():bool
    {
        $billId=$this->state['current_bill']['bill_id']??null;
        foreach ($this->state['motions'] as $motion) {
            if ($motion['bill_id']===$billId
                && $motion['status']==='filed') return false;
        }
        return true;
    }

    private function require(FrenchConstitutionalPrincipal $principal,string $ability):void
    {
        if ($principal->actorId()===''
            || !$principal->allows($this->state['world_id'],$ability)) {
            throw new \DomainException('Verified constitutional office required');
        }
    }

    private function at(string $stamp):int
    {
        $when=self::instant($stamp);
        if ($when<$this->state['now']) {
            throw new \DomainException('Cannot backdate a constitutional act');
        }
        return $when;
    }

    /** @param list<mixed> $payload */
    private function prior(string $id,string $operation,array $payload):?array
    {
        $entry=$this->state['commands'][$id]??null;
        if ($entry===null) return null;
        $fingerprint=hash('sha256',json_encode(
            [$operation,$payload],JSON_THROW_ON_ERROR
        ));
        if (!hash_equals($entry['fingerprint'],$fingerprint)) {
            throw new \DomainException('Constitutional command idempotency conflict');
        }
        return $entry['result'];
    }

    /** @param list<mixed> $payload @param array<string,mixed> $result */
    private function record(string $id,string $operation,array $payload,array $result):array
    {
        $this->state['commands'][$id]=[
            'fingerprint'=>hash('sha256',json_encode(
                [$operation,$payload],JSON_THROW_ON_ERROR
            )),
            'result'=>$result
        ];
        $this->state['events'][]=[
            'id'=>$id,'at'=>$this->state['now'],
            'kind'=>$operation,'result'=>$result
        ];
        return $result;
    }

    /** @param list<string> $required */
    private static function fields(array $obj,array $required):void
    {
        $keys=array_keys($obj);
        sort($keys,SORT_STRING);sort($required,SORT_STRING);
        if ($keys!==$required) throw new \InvalidArgumentException('Invalid constitutional state fields');
    }

    private static function id(string $value):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$value)) {
            throw new \InvalidArgumentException('Invalid constitutional identifier');
        }
    }
}
