<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/InternationalCommitments.php';

/** Non-bookable exposure, not an assessed penalty or sovereign rating model. */
final class CommitmentRisk
{
    public const VERSION='wp68-69-commitment-risk-v1';
    public static function report(array $s):array
    {
        if($s['version']!==InternationalCommitments::VERSION||$s['currency']!=='EUR'||$s['treasury_booked']!==false)
            throw new \DomainException('Unsupported commitment exposure');
        $partners=[];$interest=0;
        foreach($s['records'] as $r){
            $id=$r['partner'];
            $p=$partners[$id]??['original_minor'=>0,'arrears_minor'=>0,'oldest_arrears_days'=>0,'illustrative_open_interest_minor'=>0];
            $p['original_minor']+=$r['totals']['original_minor'];
            foreach($r['installments'] as $i){
                if($i['status']!=='arrears')continue;
                $days=(int)(new \DateTimeImmutable($i['due']))->diff(new \DateTimeImmutable($s['day']))->format('%r%a');
                if($days<0)throw new \DomainException('Arrears cannot precede maturity');
                $p['arrears_minor']+=$i['amount_minor'];$p['oldest_arrears_days']=max($p['oldest_arrears_days'],$days);
                // 3% per annum simple interest stress, on CURRENT unpaid
                // installments only. Not a contractual rate or booked expense.
                $estimate=intdiv(intdiv($i['amount_minor']*300,10000)*min(36500,$days),365);
                $p['illustrative_open_interest_minor']+=$estimate;$interest+=$estimate;
            }
            $partners[$id]=$p;
        }
        ksort($partners,SORT_STRING);$signals=[];
        foreach($partners as $id=>&$p){
            $p['stress_bps']=$p['original_minor']===0?0:intdiv($p['arrears_minor']*10000,$p['original_minor']);
            $p['status']=$p['arrears_minor']>0?'arrears_exposure':'no_open_arrears';
            $p['diplomatic_signal']=$p['arrears_minor']===0?0:-min(20,1+intdiv($p['stress_bps'],1000)+intdiv($p['oldest_arrears_days'],30));
            if(str_starts_with($id,'state_'))$signals[$id]=$p['diplomatic_signal'];
        }unset($p);
        return ['version'=>self::VERSION,'classification'=>'fictional_nonbooked_stress_not_prediction',
            'world_id'=>$s['world_id'],'day'=>$s['day'],'currency'=>'EUR','partners'=>$partners,'actor_signals'=>$signals,
            'open_arrears_minor'=>$s['totals']['arrears_minor'],'illustrative_interest_rate_bps'=>300,
            'illustrative_open_interest_minor'=>$interest,'actual_penalties_minor'=>null,'budget_delta_minor'=>null,
            'automatic_sanctions_or_treaties'=>false,'eu_signal_not_allocated_to_member_states'=>true];
    }
}
