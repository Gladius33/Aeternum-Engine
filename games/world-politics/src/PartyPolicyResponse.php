<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Independent fictional programme responses, never certified votes or real party forecasts. */
final class PartyPolicyResponse
{
    public const VERSION='wp54r-party-policy-v1';
    public static function initial(string $day):array
    {
        return ['version'=>self::VERSION,'day'=>$day,'classification'=>'fictional_uncalibrated','groups'=>[],
            'seat_counts_available'=>false,'seat_stances'=>null,'legal_effect'=>false];
    }
    public static function observe(array $r,array $i):array
    {
        $el=$i['electoral']??throw new \DomainException('Missing electoral context');$groups=[];
        foreach($el['config']['parties'] as $id=>$p){
            $groups[$id]=['economy'=>$p['programme']['economy'],'services'=>$p['programme']['services'],'tolerance'=>$p['tolerance'],
                'seats'=>$el['status']==='results'?$el['result']['seats'][$id]:null];
        }
        ksort($groups,SORT_STRING);
        $e=['groups'=>$groups,'corporation_mode'=>$r['corporations']['mode']??'pluralist',
            'industrial_mode'=>$r['industrial']['mode']??'not_started','funding_bps'=>$r['services']['primary_funding_bps']??10000,
            'output_bps'=>$r['macro']['real_output_index_bps']??10000];
        self::check($e);return $e;
    }
    public static function check(array $e):void
    {
        if(array_keys($e)!==['groups','corporation_mode','industrial_mode','funding_bps','output_bps']
            ||!is_array($e['groups'])||count($e['groups'])<2||count($e['groups'])>30
            ||!in_array($e['corporation_mode'],['pluralist','parallel','exclusive_contested'],true)
            ||!in_array($e['industrial_mode'],['not_started','active','paused','completed'],true)
            ||!is_int($e['funding_bps'])||$e['funding_bps']<0||$e['funding_bps']>10000
            ||!is_int($e['output_bps'])||$e['output_bps']<0||$e['output_bps']>1300000)
            throw new \InvalidArgumentException('Invalid party-policy observation');
        $known=null;$total=0;
        foreach($e['groups'] as $id=>$p){
            if(!is_string($id)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)||!is_array($p)||array_keys($p)!==['economy','services','tolerance','seats'])throw new \InvalidArgumentException('Invalid group');
            foreach(['economy','services','tolerance'] as $k)if(!is_int($p[$k])||$p[$k]<0||$p[$k]>1000)throw new \InvalidArgumentException('Invalid programme axis');
            if($p['seats']!==null&&(!is_int($p['seats'])||$p['seats']<0||$p['seats']>1000))throw new \InvalidArgumentException('Invalid seats');
            if($known!==null&&$known!==($p['seats']!==null))throw new \InvalidArgumentException('Mixed seat evidence');
            $known=$p['seats']!==null;$total+=$p['seats']??0;
        }
        if($known&&($total<1||$total>1000))throw new \InvalidArgumentException('Invalid seat total');
    }
    public static function advance(array $s,string $day,array $e):array
    {
        self::check($e);
        if($s['version']!==self::VERSION||$day!==(new \DateTimeImmutable($s['day']))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Invalid political response day');
        $groups=[];$counts=['support'=>0,'negotiate'=>0,'oppose'=>0];$known=true;
        // Explicit fictional programme coding. No real party or demographic
        // targeting. The score concerns a simulated policy, not voter behaviour.
        $economy=match($e['corporation_mode']){'parallel'=>400,'exclusive_contested'=>250,default=>500};
        if($e['industrial_mode']==='active')$economy=max(0,$economy-80);
        $services=500+($e['industrial_mode']==='active'?100:0);
        $conflict=$e['corporation_mode']==='exclusive_contested'?180:0;
        $stress=min(150,intdiv(max(0,10000-$e['output_bps']),20)+intdiv(10000-$e['funding_bps'],100));
        foreach($e['groups'] as $id=>$p){
            $distance=max(abs($p['economy']-$economy),abs($p['services']-$services));
            $target=max(-1000,min(1000,2*($p['tolerance']-$distance)-$conflict-$stress));
            $old=$s['groups'][$id]['alignment_index']??0;$diff=$target-$old;
            $value=$old+($diff===0?0:($diff>0?1:-1)*max(1,intdiv(abs($diff),5)));
            $stance=$value>=80?'support':($value<=-80?'oppose':'negotiate');
            $groups[$id]=['seats'=>$p['seats'],'alignment_index'=>$value,'stance'=>$stance,'programme_distance'=>$distance,
                'legal_concern'=>$conflict>0,'position_since'=>($s['groups'][$id]['stance']??null)===$stance?$s['groups'][$id]['position_since']:$day];
            if($p['seats']===null)$known=false;else $counts[$stance]+=$p['seats'];
        }
        ksort($groups,SORT_STRING);
        return ['version'=>self::VERSION,'day'=>$day,'classification'=>'fictional_uncalibrated','groups'=>$groups,
            'seat_counts_available'=>$known,'seat_stances'=>$known?$counts:null,'legal_effect'=>false];
    }
}
