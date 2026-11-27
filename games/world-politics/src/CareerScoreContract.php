<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** WP116 executable specification only. NOT wired into authorization,
 * persistence, HTTP, v1 score publication or live world clocks.
 * Inputs are hypothetical certified facts; this class cannot certify them. */
final class CareerScoreContract
{
    public const VERSION='wp116-career-score-contract-v2';
    public const START='2027-05-14';
    public const HORIZON=7305;
    public const MIN_TENURE=365;

    public static function elapsed(string $from,string $to):int
    {
        $parse=static function(string $s):\DateTimeImmutable{
            $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$s,new \DateTimeZone('UTC'));
            if(!$d||$d->format('Y-m-d')!==$s)throw new \DomainException('Invalid civil day');
            return $d;
        };
        $days=(int)$parse($from)->diff($parse($to))->format('%r%a');
        if($days<0||$days>self::HORIZON)throw new \DomainException('Invalid elapsed days');
        return $days;
    }

    public static function timePoints(int $days):int
    {
        if(PHP_INT_SIZE<8||$days<0||$days>self::HORIZON)throw new \DomainException('Unsupported tenure');
        return intdiv($days*10000000,3652425);
    }

    /** W must already be certified and sealed at the first qualifying accession. */
    public static function points(int $tenure,int $creditDays,int $gainBps,int $horizon=self::HORIZON):array
    {
        if($horizon<self::MIN_TENURE||$horizon>self::HORIZON||$tenure<0||$creditDays<0
            ||$tenure>$horizon||$creditDays>$horizon-$tenure||$gainBps< -10000||$gainBps>1000000)
            throw new \DomainException('Score outside contract bounds');
        $a=self::timePoints($tenure);
        $total=self::timePoints($tenure+$creditDays);
        return ['tenure_days'=>$tenure,'credit_days'=>$creditDays,'tenure_points'=>$a,
            'career_credit_points'=>$total-$a,'purchasing_power_points'=>$gainBps,
            'score'=>max(0,$total+$gainBps)];
    }

    /** Cohorts are world/account independent. Every pin is a canonical SHA-256
     * of an independently versioned manifest, not an arbitrary client label. */
    public static function cohort(array $pins,string $country,string $start,int $horizon,string $mode):string
    {
        $keys=['scenario','rules','economy','data','difficulty','participation','autonomy','population_scope','mandate_authority'];
        if(count($pins)!==count($keys)||!preg_match('/^[A-Z]{2}$/D',$country)
            ||$start!==self::START||$horizon<self::MIN_TENURE||$horizon>self::HORIZON
            ||!in_array($mode,['opening','late_takeover','research'],true))
            throw new \DomainException('Invalid cohort');
        $canonical=[];
        foreach($keys as $k){
            $v=$pins[$k]??null;
            if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new \DomainException('Missing cohort pin');
            $canonical[$k]=$v;
        }
        return hash('sha256',json_encode(['version'=>self::VERSION,'country'=>$country,'start'=>$start,
            'horizon'=>$horizon,'min_tenure'=>self::MIN_TENURE,'mode'=>$mode,'pins'=>$canonical],JSON_THROW_ON_ERROR));
    }

    /** Reference projection for fixtures only. The future journal must derive
     * participation and legitimacy, never accept these fields from clients.
     * eligibleDays is the count of verified contiguous days before accession;
     * a gap disqualifies opening credit rather than resetting the clock. */
    public static function project(string $mode,string $joined,?string $accession,string $through,
        int $series,int $eligibleDays,bool $lawful,bool $participationVerified,?int $gainBps,
        bool $terminal=false,int $horizon=self::HORIZON):array
    {
        if(!in_array($mode,['opening','late_takeover','research'],true)||$series<1||$eligibleDays<0
            ||$horizon<self::MIN_TENURE||$horizon>self::HORIZON)throw new \DomainException('Invalid projection');
        $elapsed=self::elapsed(self::START,$through);
        self::elapsed(self::START,$joined);
        self::elapsed($joined,$through);
        if($elapsed>$horizon||($mode==='opening'&&$joined!==self::START)
            ||($mode==='late_takeover'&&$joined===self::START))throw new \DomainException('Invalid season or admission');
        $unranked=static fn(string $reason)=>['status'=>$reason,'score'=>null,'rankable'=>false,'official_eligible'=>false];
        if($accession===null){
            if($eligibleDays>self::elapsed($joined,$through)||$series!==1||$terminal||$gainBps!==null)
                throw new \DomainException('Invalid pre-mandate facts');
            return $unranked('pre_mandate');
        }
        $wait=self::elapsed($joined,$accession);
        $tenure=self::elapsed($accession,$through);
        if($eligibleDays>$wait||($series>1&&$eligibleDays!==0))throw new \DomainException('Invalid credit facts');
        if($mode==='research'||!$lawful)return $unranked('research_or_nonqualifying');
        if(!$participationVerified||($mode==='opening'&&$series===1&&$eligibleDays!==$wait))
            return $unranked('participation_unverified');
        if($gainBps===null)return $unranked('economic_evidence_missing');
        $w=$mode==='opening'&&$series===1?$eligibleDays:0;
        $p=self::points($tenure,$w,$gainBps,$horizon);
        // Until the minimum actual tenure is reached, neither direct nor
        // career starts enter the qualified experimental ranking.
        $qualified=$tenure>=self::MIN_TENURE;
        return ['status'=>$qualified?'qualified_experimental':'provisional',
            'rankable'=>$qualified,'official_eligible'=>false,'terminal'=>$terminal,...$p];
    }
}
