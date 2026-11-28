<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CareerInstitutionLedger.php';

/** Fictional game reputation, not polling or real-world persuasion.
 * Every seven simulated days at most one career activity. Server-side
 * deterministic effects, diminishing repetition, 180-day linear decay.
 * The political score and economic household model are not changed. */
final class CareerCampaignActivity{
    public const VERSION='wp128-career-reputation-v1';
    public const COOLDOWN=7;
    public const WINDOW=180;
    private const TYPES=['programme_review'=>140,'public_meeting'=>200,'debate'=>260,'constituency_work'=>180];
    public static function enabled(array $s):bool{return $s['country']==='FR'&&$s['mode']==='career'&&($s['cabinet_autonomy']??null)===CareerInstitutionLedger::CABINET_V6;}
    public static function digest(array $e):string{return CareerInstitutionLedger::digest(['kind'=>self::VERSION,'world'=>$e['world'],'day'=>$e['day'],'actor'=>$e['actor'],'operation'=>$e['operation'],'payload'=>$e['payload']]);}
    private static function age(string $from,string $to):int{return (int)(new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->format('%r%a');}
    private static function nextDay(array $s,string $id):?string{
        $last=array_key_last($s['campaign_activities'][$id]??[]);
        return $last===null?null:(new \DateTimeImmutable($last))->modify('+'.self::COOLDOWN.' days')->format('Y-m-d');
    }
    public static function apply(array &$s,array $e):void{
        $p=$e['payload'];CareerInstitutionLedger::fields($p,['career','kind']);$id=$p['career'];$kind=$p['kind'];$day=$e['day'];
        CareerInstitutionLedger::check(self::enabled($s)&&is_string($id)&&is_string($kind)&&isset(self::TYPES[$kind],$s['careers'][$id])
            &&$s['careers'][$id]['account']!==null&&$s['careers'][$id]['account']===$e['actor']&&$s['careers'][$id]['admitted_on']<=$day
            &&$e['evidence_sha']===self::digest($e),'campaign authority denied');
        $next=self::nextDay($s,$id);
        CareerInstitutionLedger::check($next===null||$day>=$next,'campaign cooldown');
        $repeated=0;foreach($s['campaign_activities'][$id]??[] as $at=>$a)
            if($a['kind']===$kind&&self::age($at,$day)<28)$repeated++;
        $raw=max(20,self::TYPES[$kind]-50*$repeated);
        // No client-supplied result, money or random seed. Replaying a key
        // or changing it cannot reroll an accepted activity.
        if($kind==='debate'&&hexdec(substr(hash('sha256',self::VERSION.'|'.$s['world'].'|'.$id.'|'.$day),0,4))%5===0)$raw=-120;
        $s['campaign_activities'][$id][$day]=['kind'=>$kind,'points_bps'=>$raw];
    }
    /** Cutoff exclusive: an activity after first round can never alter it. */
    public static function bonus(array $s,string $id,string $cutoff):int{
        if(!self::enabled($s))return 0;$total=0;
        foreach($s['campaign_activities'][$id]??[] as $day=>$a){
            $age=self::age($day,$cutoff);
            if($age<=0||$age>self::WINDOW)continue;
            $total+=intdiv($a['points_bps']*(self::WINDOW-$age+1),self::WINDOW);
        }
        return max(-1000,min(2500,$total));
    }
    public static function adjust(array $s,string $id,string $cutoff,int $weight):int{
        CareerInstitutionLedger::check($weight>0&&$weight<=1000000,'invalid campaign base weight');
        return max(1,intdiv($weight*(10000+self::bonus($s,$id,$cutoff)),10000));
    }
    public static function view(array $s,?string $id,string $day):?array{
        if(!self::enabled($s)||$id===null)return null;
        $next=self::nextDay($s,$id);$tomorrow=(new \DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
        return ['version'=>self::VERSION,'classification'=>'fictional_relative_support_modifier','support_modifier_bps'=>self::bonus($s,$id,$tomorrow),
            'next_available_day'=>$next,'can_act'=>$next===null||$day>=$next,'cooldown_days'=>self::COOLDOWN,'decay_days'=>self::WINDOW,
            'activity_types'=>array_keys(self::TYPES),'recent'=>array_slice($s['campaign_activities'][$id]??[],-8,null,true)];
    }
}
