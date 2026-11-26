<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** WP53: fictional representative bodies, not legal mandates or real organisations. */
final class SocialPartners
{
    public const VERSION='wp53-social-partners-v1';
    private const GROUPS=['employees'=>['trade_union','workers'],'health'=>['trade_union','workers'],
        'education'=>['trade_union','workers'],'employers'=>['employer_association','workers'],
        'retirees'=>['civic_association','retired']];
    public static function initial(string $day):array
    {
        $groups=[];
        foreach(self::GROUPS as $id=>$spec)$groups[$id]=['kind'=>$spec[0],'phase'=>'idle','episodes'=>0,
            'phase_since'=>$day,'stress_days'=>0,'relief_days'=>0,'meetings'=>0,'grievance'=>0,'last_outcome'=>null];
        return ['version'=>self::VERSION,'day'=>$day,'classification'=>'fictional_uncalibrated',
            'groups'=>$groups,'legal_agreement'=>false,'regime_changed'=>false];
    }
    private static function bounded(mixed $v,int $max):int
    {
        if(!is_int($v)||$v<0||$v>$max)throw new \InvalidArgumentException('Invalid partner observation');
        return $v;
    }
    /** Extract compact committed service observations; never accept player-supplied measurements. */
    public static function observe(array $r):array
    {
        $s=$r['services']??throw new \DomainException('Missing committed services');
        $ratio=static function(int $n,int $d):int{
            if($n<0||$d<0||$n>$d)throw new \DomainException('Invalid service shortfall');
            return $d===0?0:intdiv($n*10000,$d);
        };
        return ['funding_bps'=>$s['primary_funding_bps'],
            'health_shortfall_bps'=>$ratio($s['health']['unserved_bed_demand_proxy'],$s['health']['daily_bed_demand_proxy']),
            'education_shortfall_bps'=>$ratio($s['education']['unserved_pupils_proxy'],$s['education']['pupils_proxy']),
            'pension_shortfall_bps'=>$ratio($s['pensions']['daily_unpaid_proxy_minor'],$s['pensions']['daily_reference_need_minor']),
            'unemployment_bps'=>$r['macro']['unemployment_bps']];
    }
    public static function advance(array $state,string $day,array $pressure,array $baseline,array $e):array
    {
        if($state['version']!==self::VERSION||$day!==(new \DateTimeImmutable($state['day']))->modify('+1 day')->format('Y-m-d'))
            throw new \DomainException('Invalid partner transition');
        $keys=['funding_bps','health_shortfall_bps','education_shortfall_bps','pension_shortfall_bps','unemployment_bps'];
        if(count($e)!==count($keys))throw new \InvalidArgumentException('Unexpected partner evidence');
        foreach($keys as $k)self::bounded($e[$k]??null,10000);
        foreach(['workers','retired'] as $k){self::bounded($pressure[$k]??null,1000);self::bounded($baseline[$k]??null,1000);}
        $worker=max(0,$pressure['workers']-$baseline['workers']);
        $retired=max(0,$pressure['retired']-$baseline['retired']);
        $funding=intdiv(10000-$e['funding_bps'],40);
        $target=['employees'=>$worker+intdiv(max(0,$e['unemployment_bps']-800),20),
            'health'=>intdiv($worker,2)+$funding+intdiv($e['health_shortfall_bps'],20),
            'education'=>intdiv($worker,2)+$funding+intdiv($e['education_shortfall_bps'],20),
            'employers'=>intdiv(max(0,$e['unemployment_bps']-800),5),
            'retirees'=>$retired+intdiv($e['pension_shortfall_bps'],20)];
        $state['day']=$day;
        foreach(self::GROUPS as $id=>$spec){
            $g=$state['groups'][$id];$t=min(1000,$target[$id]);$delta=$t-$g['grievance'];
            if($delta!==0)$g['grievance']+=($delta>0?1:-1)*max(1,intdiv(abs($delta),4));
            $g['stress_days']=$g['grievance']>=120?min(3650,$g['stress_days']+1):0;
            $g['relief_days']=$g['grievance']<=45?min(3650,$g['relief_days']+1):0;
            $phase=$g['phase'];$next=$phase;
            if($phase==='idle'&&$g['stress_days']>=3){$next='consultation_requested';$g['episodes']++;}
            elseif($phase==='consultation_requested'){$next='dialogue_open';$g['meetings']++;}
            elseif(in_array($phase,['dialogue_open','unresolved'],true)){
                if($g['relief_days']>=3){$next='relief_observed';$g['last_outcome']=['day'=>$day,'episode'=>$g['episodes'],'kind'=>'observed_relief','legal_agreement'=>false];}
                elseif($phase==='dialogue_open'&&$g['stress_days']>=6)$next='unresolved';
            }elseif($phase==='relief_observed')$next='idle';
            if($next!==$phase){$g['phase']=$next;$g['phase_since']=$day;}
            $state['groups'][$id]=$g;
        }
        return $state;
    }
}
