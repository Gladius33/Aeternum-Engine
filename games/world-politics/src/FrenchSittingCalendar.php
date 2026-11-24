<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/FrenchConstitutionalCrisis.php';

/**
 * WP26 — reviewed French Assembly sitting days and motion voting slots.
 *
 * Article 49 Constitution: at least 48 elapsed hours before a censure vote.
 * Article 154 Assembly rules: debate no later than the third sitting day
 * FOLLOWING expiration of those 48 hours. The calendar is a supplied,
 * auditable scenario input, NOT inferred from weekdays or holidays.
 *
 * Source of LAW: https://www.assemblee-nationale.fr/dyn/17/divers/texte_reference/02_reglement_assemblee_nationale.html
 * The actual sitting day schedule must be sourced separately.
 */
final class FrenchSittingCalendar
{
    public const VERSION='wp26-fr-sittings-1';
    private array $state;

    /**
     * @param list<string> $sittingDays reviewed dates Y-m-d in Europe/Paris
     */
    public function __construct(
        string $world,array $sittingDays,string $provenance,
        string $sourceUrl
    ) {
        self::id($world);
        if (!in_array($provenance,['fictional_scenario','verified_official'],true)
            || !filter_var($sourceUrl,FILTER_VALIDATE_URL)
            || !str_starts_with($sourceUrl,'https://')) {
            throw new \InvalidArgumentException('Sitting dates need explicit provenance');
        }
        if (!array_is_list($sittingDays)
            || count($sittingDays)<3 || count($sittingDays)>1000) {
            throw new \InvalidArgumentException('Sitting schedule must cover between 3 and 1000 dates');
        }
        foreach ($sittingDays as $day) self::day($day);
        $unique=array_unique($sittingDays);
        if (count($unique)!==count($sittingDays)) {
            throw new \InvalidArgumentException('Duplicate parliamentary sitting date');
        }
        sort($sittingDays,SORT_STRING);
        $this->state=[
            'world_id'=>$world,'version'=>self::VERSION,
            'timezone'=>'Europe/Paris','provenance'=>$provenance,
            'source_url'=>$sourceUrl,'sitting_days'=>$sittingDays,
            'motions'=>[]
        ];
    }

    /**
     * Register a date/time certified by the conference of group presidents.
     * The exact approved vote instant is offset-aware. For a simplified
     * simulation we model debate and roll call in that same sitting slot.
     */
    public function schedule(
        string $motionId,int $filedAt,int $earliestVoteAt,string $voteAt
    ):array {
        self::id($motionId);
        if ($filedAt<0 || $earliestVoteAt!==$filedAt+172800) {
            throw new \InvalidArgumentException('Unverified 48-hour censure interval');
        }
        $voteEpoch=FrenchConstitutionalCrisis::instant($voteAt);
        $voteDay=self::parisDay($voteEpoch);
        $earliestDay=self::parisDay($earliestVoteAt);
        if ($voteEpoch<$earliestVoteAt
            || !in_array($voteDay,$this->state['sitting_days'],true)) {
            throw new \DomainException('Motion vote must be on a reviewed sitting day after 48 hours');
        }
        // Article 154: strictly AFTER the expiration date, not inclusive.
        $following=array_values(array_filter(
            $this->state['sitting_days'],
            static fn(string $day):bool=>$day>$earliestDay
        ));
        if (count($following)<3) {
            throw new \DomainException('Calendar lacks three reviewed days after the 48-hour period');
        }
        $deadline=$following[2];
        if ($voteDay>$deadline) {
            throw new \DomainException('Motion vote later than third following sitting day');
        }
        $record=[
            'motion_id'=>$motionId,'filed_at'=>$filedAt,
            'earliest_vote_at'=>$earliestVoteAt,
            'vote_at'=>$voteEpoch,'vote_day'=>$voteDay,
            'third_following_sitting_day'=>$deadline,
            'rule'=>'assembly_rules_article_154'
        ];
        $prior=$this->state['motions'][$motionId]??null;
        if ($prior!==null) {
            if ($prior!==$record) {
                throw new \DomainException('Parliamentary vote already scheduled differently');
            }
            return $prior;
        }
        $this->state['motions'][$motionId]=$record;
        ksort($this->state['motions'],SORT_STRING);
        return $record;
    }

    /**
     * Fail-closed guard against skipping any due motion with the global tick.
     * No fictional "automatic vote" or "automatic government collapse".
     *
     * @param array<string,mixed> $constitution canonical 49.1/49.2/49.3 state
     */
    public function assertClockMayAdvance(array $constitution,int $targetEpoch):void
    {
        if (($constitution['world_id']??null)!==$this->state['world_id']) {
            throw new \DomainException('Constitutional and sitting worlds differ');
        }
        if ($targetEpoch<($constitution['now']??0)) {
            throw new \DomainException('Cannot backdate parliamentary time');
        }
        foreach ($this->pendingMotions($constitution) as $id=>$motion) {
            $slot=$this->state['motions'][$id]??null;
            if ($slot===null) {
                // No motion may silently vanish because the player advanced
                // time while the Conférence des présidents has not met.
                if ($targetEpoch>=$motion['earliest_vote_at']) {
                    throw new \DomainException('Undated parliamentary motion blocks time');
                }
                continue;
            }
            if ($slot['filed_at']!==$motion['filed_at']
                || $slot['earliest_vote_at']!==$motion['earliest_vote_at']) {
                throw new \UnexpectedValueException('Sitting agenda mismatches filed motion');
            }
            if ($targetEpoch>=$slot['vote_at']) {
                throw new \DomainException('Unvoted scheduled motion blocks passage of time');
            }
        }
    }

    /** Require exact, certified parliamentary slot for 49(2) or 49(3). */
    public function assertVoteAllowed(
        array $constitution,string $motionId,int $when
    ):void {
        self::id($motionId);
        if (($constitution['world_id']??null)!==$this->state['world_id']) {
            throw new \DomainException('Foreign constitutional record');
        }
        $pending=$this->pendingMotions($constitution);
        if (!isset($pending[$motionId])) {
            throw new \DomainException('Censure motion is not awaiting vote');
        }
        $slot=$this->state['motions'][$motionId]??null;
        if ($slot===null || $slot['vote_at']!==$when
            || $slot['filed_at']!==$pending[$motionId]['filed_at']
            || $slot['earliest_vote_at']!==$pending[$motionId]['earliest_vote_at']) {
            throw new \DomainException('Actual vote differs from approved parliamentary agenda');
        }
    }

    /** @return array<string,array<string,mixed>> */
    private function pendingMotions(array $constitution):array
    {
        $pending=[];
        foreach (['motions','spontaneous_motions'] as $collection) {
            if (!is_array($constitution[$collection]??null)) {
                throw new \DomainException('Incomplete constitutional motion registry');
            }
            foreach ($constitution[$collection] as $id=>$motion) {
                if (($motion['status']??null)==='filed') {
                    if (isset($pending[$id])) {
                        throw new \UnexpectedValueException('Censure ID appears in two registries');
                    }
                    $pending[$id]=$motion;
                }
            }
        }
        return $pending;
    }

    public function snapshot():array
    {
        return $this->state;
    }

    public function hash():string
    {
        return hash('sha256',json_encode(
            $this->state,
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR
        ));
    }

    public static function restore(array $snapshot):self
    {
        $expected=['world_id','version','timezone','provenance','source_url','sitting_days','motions'];
        self::fields($snapshot,$expected);
        if ($snapshot['version']!==self::VERSION || $snapshot['timezone']!=='Europe/Paris'
            || !is_array($snapshot['motions'])) {
            throw new \DomainException('Unsupported French parliamentary calendar snapshot');
        }
        $model=new self(
            $snapshot['world_id'],$snapshot['sitting_days'],
            $snapshot['provenance'],$snapshot['source_url']
        );
        foreach ($snapshot['motions'] as $id=>$value) {
            self::id($id);
            if (!is_array($value)) {
                throw new \DomainException('Invalid stored vote slot');
            }
            self::fields($value,[
                'motion_id','filed_at','earliest_vote_at','vote_at','vote_day',
                'third_following_sitting_day','rule'
            ]);
            if (!is_int($value['filed_at']) || !is_int($value['earliest_vote_at'])
                || !is_int($value['vote_at']) || $value['motion_id']!==$id
                || $value['rule']!=='assembly_rules_article_154') {
                throw new \DomainException('Malformed stored parliamentary timetable');
            }
            $when=(new \DateTimeImmutable('@'.$value['vote_at']))
                ->setTimezone(new \DateTimeZone('Europe/Paris'))
                ->format('Y-m-d\TH:i:sP');
            if ($model->schedule($id,$value['filed_at'],$value['earliest_vote_at'],$when)!==$value) {
                throw new \DomainException('Corrupted parliamentary vote schedule');
            }
        }
        return $model;
    }

    public static function parisDay(int $epoch):string
    {
        return (new \DateTimeImmutable('@'.$epoch))
            ->setTimezone(new \DateTimeZone('Europe/Paris'))
            ->format('Y-m-d');
    }

    /** @param list<string> $fields */
    private static function fields(array $value,array $fields):void
    {
        $keys=array_keys($value);
        sort($keys,SORT_STRING);sort($fields,SORT_STRING);
        if ($keys!==$fields) throw new \DomainException('Unexpected parliamentary calendar fields');
    }
    private static function id(string $id):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)) {
            throw new \InvalidArgumentException('Invalid motion or world identifier');
        }
    }
    private static function day(string $day):void
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day);
        if (!is_string($day) || $date===false || $date->format('Y-m-d')!==$day) {
            throw new \InvalidArgumentException('Invalid Assembly sitting date');
        }
    }
}
