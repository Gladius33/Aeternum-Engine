<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrenchSittingCalendar.php';
require_once __DIR__.'/ElectoralCycle.php';
require_once __DIR__.'/AssemblyRollCalls.php';

final class ParliamentaryLifecycle
{
    public const VERSION='wp43-parliamentary-lifecycle-v1';
    public static function initial(string $day):array
    {
        return ['version'=>self::VERSION,'session'=>self::ordinarySession($day),'slots'=>[],
            'archives'=>[],'election_digest'=>null,'installed_on'=>null,'coalition_history'=>[]];
    }
    /** Pilot ordinary calendar uses Monday-Friday as working days; statutory
     * holidays and exceptional sittings require a certified external calendar. */
    public static function ordinarySession(string $day):?array
    {
        $d=new \DateTimeImmutable($day,new \DateTimeZone('Europe/Paris'));
        $year=(int)$d->format('Y');if((int)$d->format('n')<10)$year--;
        $start=new \DateTimeImmutable($year.'-10-01',new \DateTimeZone('Europe/Paris'));
        while((int)$start->format('N')>5)$start=$start->modify('+1 day');
        $end=new \DateTimeImmutable(($year+1).'-06-30',new \DateTimeZone('Europe/Paris'));
        while((int)$end->format('N')>5)$end=$end->modify('-1 day');
        if($day<$start->format('Y-m-d')||$day>$end->format('Y-m-d'))return null;
        return ['id'=>'ordinary-'.$year,'kind'=>'ordinary','start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d')];
    }
    public static function quiet(array $r):bool
    {
        $s=$r['crisis'];
        if($r['pending']!==null||$s['confidence']!==null)return false;
        foreach(['motions','spontaneous_motions'] as $key)foreach($s[$key] as $m)if($m['status']==='filed')return false;
        return true;
    }
    public static function slot(array $r,string $motionId,string $voteAt,array $days):array
    {
        $c=$r['crisis'];$m=$c['motions'][$motionId]??$c['spontaneous_motions'][$motionId]??null;
        if($m===null||$m['status']!=='filed'||isset($r['lifecycle']['slots'][$motionId]))throw new \DomainException('No schedulable motion');
        $session=$r['lifecycle']['session'];
        if($session===null)throw new \DomainException('No active session');
        foreach($days as $day)if(!is_string($day)||$day<$session['start']||$day>$session['end'])throw new \DomainException('Sitting outside pinned session');
        $cal=new FrenchSittingCalendar($c['world_id'],$days,'fictional_scenario',
            'https://www.assemblee-nationale.fr/dyn/17/divers/texte_reference/02_reglement_assemblee_nationale.html');
        $slot=$cal->schedule($motionId,$m['filed_at'],$m['earliest_vote_at'],$voteAt);
        if(!str_ends_with($voteAt,'Z'))throw new \DomainException('Pilot uses UTC vote instants');
        return ['calendar'=>$cal->snapshot(),'slot'=>$slot,'vote_at'=>$voteAt,'status'=>'scheduled'];
    }
    public static function electionConfig(string $day):array
    {
        $c=ElectoralScenario::config('FR',$day);
        $templates=array_values($c['districts']);$c['districts']=[];
        for($i=0;$i<577;$i++)$c['districts'][sprintf('district-%03d',$i+1)]=$templates[$i%100];
        return $c;
    }
    public static function digest(array $e):string
    {
        return hash('sha256',json_encode(['world'=>$e['world_id'],'config'=>$e['config'],'result'=>$e['result']],
            JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }
    public static function install(array $r,array $electoral,string $day):array
    {
        $e=ElectoralCycle::restore($electoral)->snapshot();
        if($e['world_id']!==$r['crisis']['world_id']||$e['country']!=='FR'||$e['status']!=='results'||$e['day']!==$day||$e['seats_total']!==577
            ||$e['config']!==ElectoralCycle::restore((new ElectoralCycle($e['world_id'],'FR',$e['start_day'],self::electionConfig($e['start_day'])))->snapshot())->snapshot()['config'])
            throw new \DomainException('Unverified parliamentary election source');
        if(!self::quiet($r)||$r['lifecycle']['election_digest']!==null)throw new \DomainException('Cannot replace Assembly now');
        $old=FrenchParliament::restore($r['assembly']);$new=AssemblyRollCalls::fromElection($e,$day);
        $r['lifecycle']['archives'][]=['retired_on'=>$day,'assembly'=>$old->snapshot(),'sha'=>$old->hash()];
        $r['assembly']=$new->snapshot();$r['lifecycle']['election_digest']=self::digest($e);$r['lifecycle']['installed_on']=$day;
        // No new government or constitutional session is implied by election.
        return $r;
    }
}
