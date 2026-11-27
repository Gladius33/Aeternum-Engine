<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrenchElectionCalendar.php';
require_once __DIR__.'/CivicReform.php';

final class FrenchRecurringElections
{
    public const VERSION='wp55-fr-recurring-elections-v1';
    public static function ordinary(string $start,int $previousYear,bool $franchise=false):array
    {
        $end=new \DateTimeImmutable(FrenchElectionCalendar::assemblyExpiry($previousYear));
        $second=$end->modify('-7 days');while($second->format('N')!=='7')$second=$second->modify('-1 day');
        $first=$second->modify('-7 days');
        $c=self::config($start,$first,$second);return $franchise?CivicReform::expandConfig($c):$c;
    }
    public static function early(string $dissolved,bool $franchise=false):array
    {
        $first=(new \DateTimeImmutable($dissolved))->modify('+20 days');
        while($first->format('N')!=='7')$first=$first->modify('+1 day');
        $c=self::config($dissolved,$first,$first->modify('+7 days'));return $franchise?CivicReform::expandConfig($c):$c;
    }
    private static function config(string $start,\DateTimeImmutable $first,\DateTimeImmutable $second):array
    {
        if($first->format('Y-m-d')<=$start)throw new \DomainException('Election must be prospective');
        $c=ParliamentaryLifecycle::electionConfig($start);$c['poll_day']=$first->format('Y-m-d');$c['runoff_day']=$second->format('Y-m-d');return $c;
    }
    public static function matches(array $e,?bool $franchise=null):bool
    {
        $start=$e['start_day'];$c=$e['config'];$year=(int)substr($c['poll_day'],0,4)-5;
        $candidates=[self::early($start)];
        if($year>=1958&&$year<=2150&&$start<FrenchElectionCalendar::assemblyExpiry($year)){
            try{$candidates[]=self::ordinary($start,$year);}catch(\DomainException $ignored){}
        }
        foreach($candidates as $candidate)foreach($franchise===null?[$candidate,CivicReform::expandConfig($candidate)]:[$franchise?CivicReform::expandConfig($candidate):$candidate] as $variant)
            if((new ElectoralCycle($e['world_id'],'FR',$start,$variant))->snapshot()['config']===$c)return true;
        return false;
    }
}
