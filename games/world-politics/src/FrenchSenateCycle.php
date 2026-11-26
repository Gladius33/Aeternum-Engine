<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Official group counts, fictional agent identities and synthetic allocation
 * across the two legally defined renewal cohorts. No forecast of future votes. */
final class FrenchSenateCycle
{
    public const VERSION='wp58-fr-senate-v1';
    public static function source():array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-13-fr-senate-v1.json'),true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!=='wp58-senate-anchor-v1'||array_sum($p['groups'])!==348||$p['series'][1]['seats']!==170||$p['series'][2]['seats']!==178)throw new \DomainException('Invalid Senate anchor');
        return $p;
    }
    private static function boundary(int $year):array
    {
        $poll=new \DateTimeImmutable($year.'-09-30');
        while($poll->format('N')!=='7')$poll=$poll->modify('-1 day');
        $opening=new \DateTimeImmutable($year.'-10-01');
        while((int)$opening->format('N')>5)$opening=$opening->modify('+1 day');
        return ['poll'=>$poll->format('Y-m-d'),'effective'=>$opening->format('Y-m-d'),'classification'=>'scenario_date_not_announced'];
    }
    public static function project(string $world,string $day):array
    {
        $p=self::source();if($day<'2027-05-14'||$day>'2150-12-31')throw new \DomainException('Senate pilot date');
        $labels=[];foreach($p['groups'] as $g=>$n)for($i=0;$i<$n;$i++)$labels[]=['group'=>$g,'rank'=>hash('sha256',$world.'|senate-series|'.$g.'|'.$i)];
        usort($labels,fn($a,$b)=>strcmp($a['rank'],$b['rank']));
        $series=[];$agents=[];$renewals=[];
        foreach([1,2] as $k){
            $year=$p['series'][$k]['next_year'];$generation=0;
            while(self::boundary($year)['effective']<=$day){
                $renewals[]=['series'=>$k,'seats'=>$p['series'][$k]['seats'],'generation'=>++$generation,...self::boundary($year)];$year+=6;
            }
            $series[$k]=['seats'=>$p['series'][$k]['seats'],'generation'=>$generation,'next'=>self::boundary($year)];
        }
        usort($renewals,fn($a,$b)=>strcmp($a['effective'],$b['effective']));
        foreach($labels as $i=>$label){
            $k=$i<170?1:2;$generation=$series[$k]['generation'];$id=sprintf('senator-%d-%03d-g%d',$k,$i+1,$generation);
            $agents[$id]=['group'=>$label['group'],'series'=>$k,'generation'=>$generation,'attendance_bps'=>9500];
        }
        return ['version'=>self::VERSION,'world_id'=>$world,'day'=>$day,'source_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),'source'=>$p,'agents'=>$agents,'series'=>$series,'renewals'=>$renewals,'future_group_distribution'=>'constant_share_scenario_not_election_forecast','series_group_allocation'=>'synthetic','indirect_election_simulated'=>false];
    }
}
