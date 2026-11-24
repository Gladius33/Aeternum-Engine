<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

/**
 * WP28 — auditable, deterministic French National Assembly simulation.
 *
 * Fictional rosters and political objectives are game assumptions, NOT
 * historical election data or claims about actual parliamentarians.
 * Verified real-world sources must be imported as separately dated records.
 *
 * Votes: Constitution article 49, Assembly rule article 65 et seq.
 * Confidence: for > against among expressed votes.
 * Censure (49.2 / 49.3): only positive votes count, threshold is
 * floor(number of occupied seats / 2)+1; abstentions/absence cannot pass it.
 *
 * All 577 possible seats are explicit. A scenario may have vacant seats;
 * occupied_seats is provided separately to the constitutional domain.
 * Each deputy has an INDIVIDUAL ballot. Group/coalition policies influence
 * but never rigidly predetermine their vote.
 */
final class FrenchParliament
{
    public const VERSION='wp28-fr-assembly-1';
    public const TOPICS=['budget','social_policy','general_policy','security','foreign_policy'];
    private array $state;

    /**
     * @param list<array<string,mixed>> $groups
     * @param list<array<string,mixed>> $deputies
     */
    public function __construct(
        string $world,string $legislature,string $asOf,
        string $provenance,string $sourceUrl,
        array $groups,array $deputies
    ) {
        self::id($world);self::id($legislature);
        self::day($asOf);
        if (!in_array($provenance,['fictional_scenario','verified_official'],true)
            || !str_starts_with($sourceUrl,'https://')
            || filter_var($sourceUrl,FILTER_VALIDATE_URL)===false) {
            throw new \InvalidArgumentException('Parliamentary roster needs explicit dated provenance');
        }
        if (!array_is_list($groups) || count($groups)<1
            || count($groups)>50 || !array_is_list($deputies)
            || count($deputies)<1 || count($deputies)>577) {
            throw new \InvalidArgumentException('Invalid Assembly group or deputy count');
        }
        $indexedGroups=[];
        foreach ($groups as $group) {
            self::fields($group,['id','name','alignment','positions','whip_bps']);
            self::id($group['id']);
            if (isset($indexedGroups[$group['id']])
                || !is_string($group['name'])
                || trim($group['name'])==='' || strlen($group['name'])>100
                || !in_array($group['alignment'],['government','opposition','nonaligned'],true)
                || !is_array($group['positions'])
                || array_keys($group['positions'])!==self::TOPICS) {
                throw new \InvalidArgumentException('Invalid named parliamentary group');
            }
            self::bps($group['whip_bps']);
            foreach ($group['positions'] as $score) self::signed($score,-1000,1000);
            $indexedGroups[$group['id']]=$group;
        }
        ksort($indexedGroups,SORT_STRING);
        $indexedDeputies=[];$seats=[];
        foreach ($deputies as $deputy) {
            self::fields($deputy,[
                'id','seat','group','control','loyalty_bps',
                'attendance_bps','independence'
            ]);
            self::id($deputy['id']);
            self::id($deputy['group']);
            if (isset($indexedDeputies[$deputy['id']])
                || !isset($indexedGroups[$deputy['group']])
                || !is_int($deputy['seat'])
                || $deputy['seat']<1 || $deputy['seat']>577
                || isset($seats[$deputy['seat']])
                || !in_array($deputy['control'],['ai','human'],true)) {
                throw new \InvalidArgumentException('Duplicate, unoccupied or unverified deputy seat');
            }
            self::bps($deputy['loyalty_bps']);
            self::bps($deputy['attendance_bps']);
            self::signed($deputy['independence'],-1000,1000);
            $seats[$deputy['seat']]=true;
            $indexedDeputies[$deputy['id']]=$deputy;
        }
        ksort($indexedDeputies,SORT_STRING);
        $this->state=[
            'world_id'=>$world,'version'=>self::VERSION,
            'legislature'=>$legislature,'as_of'=>$asOf,
            'provenance'=>$provenance,'source_url'=>$sourceUrl,
            'total_seats'=>577,'occupied_seats'=>count($indexedDeputies),
            'groups'=>$indexedGroups,'deputies'=>$indexedDeputies,
            'coalitions'=>[],'matters'=>[],'signatures'=>[]
        ];
    }

    /**
     * Verified coalition protocol can change during the legislature.
     * Active coalition membership is mutually exclusive.
     *
     * @param list<string> $groupIds
     */
    public function coalition(string $id,array $groupIds,bool $active):array
    {
        self::id($id);
        if (!array_is_list($groupIds) || $groupIds===[] ||
            count($groupIds)>count($this->state['groups'])
            || count(array_unique($groupIds))!==count($groupIds)) {
            throw new \InvalidArgumentException('Coalition contains duplicate or empty group list');
        }
        foreach ($groupIds as $group) {
            self::id($group);
            if (!isset($this->state['groups'][$group])) {
                throw new \DomainException('Unknown coalition parliamentary group');
            }
        }
        sort($groupIds,SORT_STRING);
        if (isset($this->state['coalitions'][$id])) {
            $prior=$this->state['coalitions'][$id];
            if ($prior['groups']!==$groupIds) {
                throw new \DomainException('Existing coalition membership is immutable; create a new agreement');
            }
        }
        if ($active) {
            foreach ($this->state['coalitions'] as $oldId=>$old) {
                if ($oldId!==$id && $old['active']
                    && array_intersect($old['groups'],$groupIds)!==[]) {
                    throw new \DomainException('Group cannot join two active coalitions');
                }
            }
        }
        $this->state['coalitions'][$id]=[
            'id'=>$id,'groups'=>$groupIds,'active'=>$active
        ];
        ksort($this->state['coalitions'],SORT_STRING);
        return $this->state['coalitions'][$id];
    }

    /**
     * Only the verified clerk may record a real filing roll, and only
     * independently registered individual deputy IDs count.
     *
     * @param list<string> $deputyIds
     */
    public function signatures(string $motionId,array $deputyIds):array
    {
        self::id($motionId);
        if (!array_is_list($deputyIds)
            || count($deputyIds)<1
            || count($deputyIds)>$this->state['occupied_seats']
            || count(array_unique($deputyIds))!==count($deputyIds)) {
            throw new \InvalidArgumentException('Invalid motion signatory count or duplicate IDs');
        }
        foreach ($deputyIds as $id) {
            self::id($id);
            if (!isset($this->state['deputies'][$id])) {
                throw new \DomainException('A motion signer is not an elected member');
            }
        }
        sort($deputyIds,SORT_STRING);
        if (isset($this->state['signatures'][$motionId])
            && $this->state['signatures'][$motionId]!==$deputyIds) {
            throw new \DomainException('Parliamentary motion signature roll is sealed');
        }
        $this->state['signatures'][$motionId]=$deputyIds;
        ksort($this->state['signatures'],SORT_STRING);
        return $deputyIds;
    }

    /**
     * The caller MUST compare sourceConstitutionSha with the actual verified
     * constitutional state before opening this agenda.
     * @param array<string,int> $concessions by parliamentary group
     */
    public function openMatter(
        string $id,string $kind,string $topic,
        int $publicPressure,array $concessions,
        string $sourceConstitutionSha
    ):array {
        self::id($id);
        if (!in_array($kind,['confidence','censure_49_2','censure_49_3'],true)
            || !in_array($topic,self::TOPICS,true)) {
            throw new \InvalidArgumentException('Unknown parliamentary matter or policy topic');
        }
        self::signed($publicPressure,-1000,1000);
        self::sha($sourceConstitutionSha);
        if (isset($this->state['matters'][$id])
            || count($this->state['matters'])>=2000) {
            throw new \DomainException('Parliamentary item already opened or world limit reached');
        }
        foreach ($concessions as $group=>$value) {
            self::id($group);
            if (!isset($this->state['groups'][$group])) {
                throw new \DomainException('Concession cannot target an unknown group');
            }
            self::signed($value,-1000,1000);
        }
        ksort($concessions,SORT_STRING);
        $activeCoalitionGroups=$this->activeCoalitionGroups();
        $this->state['matters'][$id]=[
            'id'=>$id,'kind'=>$kind,'topic'=>$topic,
            'coalition_groups'=>$activeCoalitionGroups,
            'public_pressure'=>$publicPressure,'concessions'=>$concessions,
            'source_constitution_sha'=>$sourceConstitutionSha,
            'status'=>'awaiting_ballots','human_votes'=>[],
            'roll_call'=>null,'tally'=>null
        ];
        ksort($this->state['matters'],SORT_STRING);
        return $this->state['matters'][$id];
    }

    /** Member-specific expression; an authenticated adapter checks identity. */
    public function humanBallot(string $matterId,string $deputyId,string $choice):array
    {
        self::id($matterId);self::id($deputyId);
        if (!in_array($choice,['for','against','abstain','absent'],true)) {
            throw new \InvalidArgumentException('Unknown roll-call choice');
        }
        $m=$this->requireOpen($matterId);
        $deputy=$this->state['deputies'][$deputyId]??null;
        if ($deputy===null || $deputy['control']!=='human') {
            throw new \DomainException('Only a controlled sitting deputy may submit this ballot');
        }
        if (isset($m['human_votes'][$deputyId])
            && $m['human_votes'][$deputyId]!==$choice) {
            throw new \DomainException('Parliamentary ballot already cast; no silent replacement');
        }
        $this->state['matters'][$matterId]['human_votes'][$deputyId]=$choice;
        ksort($this->state['matters'][$matterId]['human_votes'],SORT_STRING);
        return ['matter_id'=>$matterId,'deputy_id'=>$deputyId,'choice'=>$choice];
    }

    /**
     * Every member has a distinct ballot; an AI is neither a perfect bloc
     * nor a uniform random dice throw. Scores and turnout depend on
     * party programme, legislative issue, coalition, member independence,
     * loyalty, public pressure, and group-specific concessions. A stable
     * SHA-256 draw introduces a documented scenario-specific propensity,
     * reproducible across replay and PHP processes.
     */
    public function resolve(string $id):array
    {
        $matter=$this->requireOpen($id);
        foreach ($this->state['deputies'] as $member) {
            if ($member['control']==='human'
                && !isset($matter['human_votes'][$member['id']])) {
                throw new \DomainException('All human parliamentary votes must be submitted or marked absent');
            }
        }
        $coalitionGroups=array_fill_keys($matter['coalition_groups'],true);
        $roll=[];$tally=['for'=>0,'against'=>0,'abstain'=>0,'absent'=>0];
        foreach ($this->state['deputies'] as $member) {
            $group=$this->state['groups'][$member['group']];
            $choice=$member['control']==='human'
                ?$matter['human_votes'][$member['id']]
                :$this->aiChoice($member,$group,$matter,isset($coalitionGroups[$member['group']]));
            $roll[$member['id']]=$choice;
            $tally[$choice]++;
        }
        if (array_sum($tally)!==$this->state['occupied_seats']) {
            throw new \LogicException('Individual roll call did not cover all occupied seats');
        }
        $passed=$matter['kind']==='confidence'
            ?$tally['for']>$tally['against']
            :$tally['for']>=intdiv($this->state['occupied_seats'],2)+1;
        $result=[
            'matter_id'=>$id,'kind'=>$matter['kind'],
            'source_constitution_sha'=>$matter['source_constitution_sha'],
            'tally'=>$tally,'roll_call'=>$roll,
            'occupied_seats'=>$this->state['occupied_seats'],
            'majority_required'=>$matter['kind']==='confidence'
                ?null:intdiv($this->state['occupied_seats'],2)+1,
            'adopted'=>$passed,'regime_changed'=>false,
            'classification'=>'fictional_political_scenario'
        ];
        $this->state['matters'][$id]['status']='resolved';
        $this->state['matters'][$id]['roll_call']=$roll;
        $this->state['matters'][$id]['tally']=$tally;
        $this->state['matters'][$id]['result']=$result;
        return $result;
    }

    /** @return array<string,mixed> */
    public function result(string $matterId):array
    {
        self::id($matterId);
        $matter=$this->state['matters'][$matterId]??null;
        if ($matter===null || $matter['status']!=='resolved') {
            throw new \DomainException('Parliament has not completed this roll call');
        }
        return $matter['result'];
    }

    /** @return list<string> */
    public function signatories(string $motionId):array
    {
        self::id($motionId);
        if (!isset($this->state['signatures'][$motionId])) {
            throw new \DomainException('No authenticated Assembly signature roll');
        }
        return $this->state['signatures'][$motionId];
    }

    public function snapshot():array { return $this->state; }
    public function hash():string
    {
        return hash('sha256',self::json($this->state));
    }

    /** Validate complete historical state by deterministic re-execution. */
    public static function restore(array $saved):self
    {
        self::fields($saved,[
            'world_id','version','legislature','as_of','provenance',
            'source_url','total_seats','occupied_seats','groups',
            'deputies','coalitions','matters','signatures'
        ]);
        if ($saved['version']!==self::VERSION
            || $saved['total_seats']!==577 || !is_array($saved['groups'])
            || !is_array($saved['deputies'])
            || !is_array($saved['coalitions'])
            || !is_array($saved['matters'])
            || !is_array($saved['signatures'])) {
            throw new \DomainException('Unsupported French Assembly archive');
        }
        $model=new self(
            $saved['world_id'],$saved['legislature'],$saved['as_of'],
            $saved['provenance'],$saved['source_url'],
            array_values($saved['groups']),array_values($saved['deputies'])
        );
        if ($model->state['groups']!==$saved['groups']
            || $model->state['deputies']!==$saved['deputies']
            || $model->state['occupied_seats']!==$saved['occupied_seats']) {
            throw new \DomainException('Roster was altered in Assembly archive');
        }
        foreach ($saved['coalitions'] as $id=>$coalition) {
            if (!is_array($coalition)) throw new \DomainException('Malformed coalition');
            self::fields($coalition,['id','groups','active']);
            if ($coalition['id']!==$id || !is_bool($coalition['active'])
                || $model->coalition($id,$coalition['groups'],$coalition['active'])!==$coalition) {
                throw new \DomainException('Invalid restored coalition');
            }
        }
        foreach ($saved['signatures'] as $id=>$signers) {
            if ($model->signatures($id,$signers)!==$signers) {
                throw new \DomainException('Invalid restored signatures');
            }
        }
        foreach ($saved['matters'] as $id=>$matter) {
            if (!is_array($matter)) throw new \DomainException('Malformed legislative item');
            $keys=array_keys($matter);sort($keys,SORT_STRING);
            $expected=[
                'coalition_groups','concessions','human_votes','id','kind','public_pressure',
                'roll_call','source_constitution_sha','status','tally','topic'
            ];
            if ($matter['status']==='resolved') $expected[]='result';
            sort($expected,SORT_STRING);
            if ($keys!==$expected || $matter['id']!==$id) {
                throw new \DomainException('Incomplete parliamentary archive');
            }
            $model->openMatter(
                $id,$matter['kind'],$matter['topic'],
                $matter['public_pressure'],$matter['concessions'],
                $matter['source_constitution_sha']
            );
            // Coalition membership is frozen when an agenda is created.
            // Historical votes must NOT be recomputed using the current,
            // potentially different coalition agreements.
            $historicalGroups=$matter['coalition_groups'];
            if (!is_array($historicalGroups) || !array_is_list($historicalGroups)
                || count($historicalGroups)!==count(array_unique($historicalGroups))) {
                throw new \DomainException('Unverifiable historical coalition membership');
            }
            $sortedGroups=$historicalGroups;
            sort($sortedGroups,SORT_STRING);
            if ($historicalGroups!==$sortedGroups) {
                throw new \DomainException('Noncanonical historical coalition snapshot');
            }
            foreach ($historicalGroups as $groupId) {
                self::id($groupId);
                if (!isset($model->state['groups'][$groupId])) {
                    throw new \DomainException('Historical coalition names unknown group');
                }
            }
            $model->state['matters'][$id]['coalition_groups']=$historicalGroups;
            if (!is_array($matter['human_votes'])) {
                throw new \DomainException('Malformed parliamentary ballots');
            }
            foreach ($matter['human_votes'] as $deputy=>$vote) {
                $model->humanBallot($id,$deputy,$vote);
            }
            if ($matter['status']==='resolved') {
                if ($model->resolve($id)!==$matter['result']) {
                    throw new \DomainException('Resolved parliamentary roll call failed deterministic verification');
                }
            }
            if ($model->state['matters'][$id]!==$matter) {
                throw new \DomainException('Parliamentary snapshot differs from validated replay');
            }
        }
        if ($model->snapshot()!==$saved) {
            throw new \DomainException('Noncanonical French Assembly snapshot');
        }
        return $model;
    }

    /** @return list<string> */
    private function activeCoalitionGroups():array
    {
        $groups=[];
        foreach ($this->state['coalitions'] as $coalition) {
            if ($coalition['active']) {
                foreach ($coalition['groups'] as $group) $groups[$group]=true;
            }
        }
        $names=array_keys($groups);
        sort($names,SORT_STRING);
        return $names;
    }

    private function aiChoice(array $member,array $group,array $matter,bool $coalition):string
    {
        $id=$member['id'];$issue=$matter['topic'];
        $turnout=self::draw($this->state['legislature']."\n".$matter['id']."\n".$id."\nturnout",10000);
        if ($turnout>=$member['attendance_bps']) return 'absent';
        $jitter=self::draw($this->state['legislature']."\n".$matter['id']."\n".$id."\nopinion",401)-200;
        $govBonus=$coalition?420:match($group['alignment']) {
            'government'=>220,'opposition'=>-260,'nonaligned'=>0
        };
        // "loyalty" modulates the impact of a party whip; independence
        // allows disagreement. No group receives guaranteed bloc support.
        $whipBias=intdiv(
            $govBonus*$group['whip_bps']*$member['loyalty_bps'],100000000
        );
        $score=$group['positions'][$issue]
            +$whipBias+$member['independence']+$jitter
            +$matter['public_pressure']
            +($matter['concessions'][$member['group']]??0);
        if ($matter['kind']==='confidence') {
            return $score>125?'for':($score< -125?'against':'abstain');
        }
        return $score< -125?'for':($score>125?'against':'abstain');
    }

    private static function draw(string $seed,int $modulus):int
    {
        // hexdec of 32-bit prefix is exact in PHP 64-bit.
        return hexdec(substr(hash('sha256',$seed),0,8))%$modulus;
    }

    private function requireOpen(string $id):array
    {
        $matter=$this->state['matters'][$id]??null;
        if ($matter===null || $matter['status']!=='awaiting_ballots') {
            throw new \DomainException('No pending authenticated parliamentary vote');
        }
        return $matter;
    }

    private static function json(mixed $v):string
    {
        return json_encode(
            $v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
        );
    }
    private static function fields(array $v,array $fields):void
    {
        $a=array_keys($v);sort($a,SORT_STRING);sort($fields,SORT_STRING);
        if ($a!==$fields) throw new \DomainException('Unexpected French Assembly record fields');
    }
    private static function id(mixed $id):void
    {
        if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)) {
            throw new \InvalidArgumentException('Invalid French Assembly identifier');
        }
    }
    private static function sha(mixed $value):void
    {
        if (!is_string($value)||!preg_match('/^[a-f0-9]{64}$/D',$value)) {
            throw new \InvalidArgumentException('Untrusted parliamentary constitutional source hash');
        }
    }
    private static function bps(mixed $v):void
    {
        if (!is_int($v)||$v<0||$v>10000) {
            throw new \InvalidArgumentException('Invalid parliamentary basis-point parameter');
        }
    }
    private static function signed(mixed $v,int $low,int $high):void
    {
        if (!is_int($v)||$v<$low||$v>$high) {
            throw new \InvalidArgumentException('Invalid reviewed political scenario parameter');
        }
    }
    private static function day(string $date):void
    {
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if ($d===false||$d->format('Y-m-d')!==$date) {
            throw new \InvalidArgumentException('Invalid parliamentary historical date');
        }
    }
}
