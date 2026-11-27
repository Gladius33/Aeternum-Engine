<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ComparativeElectionCalendar.php';
require_once __DIR__.'/PoliticalNegotiator.php';

/** Version-pinned ordinary recurring foreign ballot fixtures.
 * No early dissolution or executive succession is inferred from a poll.
 * Non-US future poll days are scenario choices, not legal deadlines.
 */
final class ForeignRecurringElections
{
    public const VERSION='wp62-foreign-recurring-v1';
    public const DOCUMENTARY='wp119-foreign-recurring-v2';
    public const START='2027-05-14';
    private const TERMS=['DE'=>4,'ES'=>4,'GB'=>5,'IT'=>5,'JP'=>4,'US'=>2];
    public static function bootstrap(array $s):array
    {
        $cc=$s['country_code'];
        if(!isset(self::TERMS[$cc],$s['electoral'],$s['political_ai'],$s['accountability'])
            ||$s['day']!==self::START||$s['events']!==[]||isset($s['foreign_recurring']))
            throw new \DomainException('Foreign recurrence must be pinned at investiture bootstrap');
        $s['foreign_recurring']=['version'=>isset($s['electoral']['config']['documentary_cast'])?self::DOCUMENTARY:self::VERSION,'archive'=>[]];
        self::validate($s);
        return $s;
    }
    private static function poll(string $cc,int $index):string
    {
        if(!isset(self::TERMS[$cc])||$index<0||$index>100)throw new \DomainException('Unsupported ordinary recurrence');
        $first=ComparativeElectionCalendar::report()['countries'][$cc]['scenario_poll'];
        if($index===0)return $first;
        $d=(new \DateTimeImmutable($first,new \DateTimeZone('UTC')))->modify('+'.(self::TERMS[$cc]*$index).' years');
        if($cc==='US'){
            $d=new \DateTimeImmutable($d->format('Y').'-11-01',new \DateTimeZone('UTC'));
            while($d->format('N')!=='1')$d=$d->modify('+1 day');
            $d=$d->modify('+1 day');
        }else{
            // Retain the weekday of the initial scenario poll; not a legal window calculation.
            $weekday=(new \DateTimeImmutable($first))->format('N');
            while($d->format('N')!==$weekday)$d=$d->modify('-1 day');
        }
        return $d->format('Y-m-d');
    }
    private static function prior(string $day):string
    {return (new \DateTimeImmutable($day,new \DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');}
    private static function cycle(string $world,string $cc,int $index,string $version):ElectoralCycle
    {
        $day=$index===0?self::START:self::prior(self::poll($cc,$index));
        $c=$index===0?ComparativeElectionCalendar::config($cc,$day):ElectoralScenario::config($cc,$day);
        $c['poll_day']=self::poll($cc,$index);
        if($version===self::DOCUMENTARY)$c=DocumentaryForeignBallot::config($cc,$c);
        return new ElectoralCycle($world,$cc,$day,$c);
    }
    public static function validate(array $s):void
    {
        $r=$s['foreign_recurring'];$cc=$s['country_code'];$world=$s['world_id'];
        if(!is_array($r)||array_keys($r)!==['version','archive']||!in_array($r['version'],[self::VERSION,self::DOCUMENTARY],true)||!isset(self::TERMS[$cc],$s['electoral'],$s['political_ai'],$s['accountability'])
            ||!is_array($r['archive'])||!array_is_list($r['archive'])||count($r['archive'])>=100)
            throw new \DomainException('Invalid foreign renewal archive');
        foreach([...$r['archive'],$s['electoral']] as $i=>$e){
            $expected=self::cycle($world,$cc,$i,$r['version'])->snapshot();
            if($e['world_id']!==$world||$e['country']!==$cc||$e['start_day']!==$expected['start_day']||$e['config']!==$expected['config'])
                throw new \DomainException('Foreign cycle provenance mismatch');
            $next=self::poll($cc,$i+1);
            if($i<count($r['archive'])){
                if($e['status']!=='results'||$e['day']!==self::prior($next))throw new \DomainException('Incomplete or mistimed archive');
                ElectoralCycle::restore($e);
            }elseif($e['day']!==$s['day']||$e['day']>=$next)throw new \DomainException('Foreign current cycle mismatch');
        }
    }
    /** Advance only the electoral part; caller owns transaction and clock. */
    public static function advance(array $s,string $target):array
    {
        $cc=$s['country_code'];$r=$s['foreign_recurring'];$index=count($r['archive']);
        $el=ElectoralCycle::restore($s['electoral']);
        if($target<$el->snapshot()['day'])throw new \DomainException('Foreign electoral clock cannot regress');
        while(self::poll($cc,$index+1)<=$target){
            if($index>=98)throw new \DomainException('Foreign recurrence archive limit');
            self::finish($el,self::prior(self::poll($cc,$index+1)),$s);
            $r['archive'][]=$el->snapshot();$index++;
            $el=self::cycle($s['world_id'],$cc,$index,$r['version']);
        }
        self::finish($el,$target,$s);
        $s['foreign_recurring']=$r;$s['electoral']=$el->snapshot();
        $s['political_ai']=PoliticalNegotiator::evaluate($s['electoral']);
        return $s;
    }
    private static function finish(ElectoralCycle $el,string $day,array &$s):void
    {
        $e=$el->snapshot();$poll=$e['config']['poll_day'];
        if($e['status']==='scheduled'&&$day>=$poll){
            $el->advance($poll);
            $suggestion=PoliticalNegotiator::evaluate($el->snapshot())['recommendation'];
            if($suggestion!==null){
                $id='ai-ordinary-coalition-'.$poll;
                $result=$el->coalition($id,$suggestion['parties'],$suggestion['programme'],$suggestion['candidate']);
                $s['events'][]=['id'=>$id,'on'=>$poll,'kind'=>'electoral_coalition','actor_role'=>'simulation_political_ai','result'=>$result];
            }
        }
        $el->advance($day);
    }
}
