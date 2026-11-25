<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CampaignLifecycle.php';

/** WP38: presidential term and fictional national ballots, independent of
 * parliamentary seats. No real candidates, predictive electoral data or
 * player-supplied tallies. Dates are game days, not certified legal instants.
 */
final class PresidentialMandate
{
    public const VERSION='wp38-fr-presidency-v1';
    public const SOURCE='https://www.legifrance.gouv.fr/loda/texte_lc/LEGITEXT000006071194/';
    public static function fixture():array
    {
        return ['term_start'=>'2021-12-21','consecutive_terms'=>1,'cycles'=>[
            ['registered'=>100000,'first'=>['votes'=>['campaign-president'=>42000,'challenger-a'=>35000,'challenger-b'=>16000],'blank_invalid'=>1000],
                'second'=>['votes'=>['campaign-president'=>48000,'challenger-a'=>45000],'blank_invalid'=>1000]],
            ['registered'=>100000,'first'=>['votes'=>['challenger-a'=>40000,'challenger-b'=>35000,'challenger-c'=>15000],'blank_invalid'=>1000],
                'second'=>['votes'=>['challenger-a'=>46000,'challenger-b'=>45000],'blank_invalid'=>1000]]
        ]];
    }
    public static function initial(string $world,string $start,array $config):array
    {
        self::day($start);self::fields($config,['term_start','consecutive_terms','cycles']);self::day($config['term_start']);
        $terms=$config['consecutive_terms'];
        if(!is_int($terms)||$terms<1||$terms>2||!is_array($config['cycles'])||!array_is_list($config['cycles'])||count($config['cycles'])!==3-$terms)
            throw new \InvalidArgumentException('Invalid presidential term configuration');
        foreach($config['cycles'] as $i=>$cycle)self::countElection($cycle,$terms+$i<2);
        $end=self::termEnd($config['term_start']);
        $poll=self::shift($end,-25);
        if($start<$config['term_start']||$start>=$poll)throw new \DomainException('Start must precede scheduled first round');
        return ['version'=>self::VERSION,'classification'=>'fictional_scenario','source'=>self::SOURCE,
            'world_id'=>$world,'day'=>$start,'config'=>$config,'term_start'=>$config['term_start'],'term_end'=>$end,
            'consecutive_terms'=>$terms,'eligible_for_re_election'=>$terms<2,'cycle_index'=>0,'status'=>'scheduled',
            'first_round_day'=>$poll,'second_round_day'=>self::shift($poll,14),'first_result'=>null,'result'=>null,
            'officeholder'=>CampaignLifecycle::PLAYER,'successor'=>null,'handover_on'=>null,'reason'=>null,'elections'=>[]];
    }
    public static function advance(array $s,string $day):array
    {
        self::day($day);
        if($s['version']!==self::VERSION||$s['status']==='ended'||$day!==self::shift($s['day'],1))
            throw new \DomainException('Presidential date or office state invalid');
        $s['day']=$day;
        if($day===$s['first_round_day']||$day===$s['second_round_day']){
            $counts=self::countElection($s['config']['cycles'][$s['cycle_index']],$s['eligible_for_re_election']);
            if($day===$s['first_round_day']){
                $s['first_result']=$counts['first'];
                if($counts['first']['winner']!==null){$s['result']=$counts['first'];$s['status']='result_known';}
                elseif($counts['first']['finalists']===[]){$s['status']='result_unresolved';}
                else $s['status']='runoff_pending';
            }elseif($s['status']==='runoff_pending'){
                $s['result']=$counts['second'];$s['status']=$counts['second']['winner']===null?'result_unresolved':'result_known';
            }
        }
        if($day===$s['term_end']){
            $winner=$s['result']['winner']??null;
            $s['elections'][]=['term_end'=>$day,'first_result'=>$s['first_result'],'result'=>$s['result'],'winner'=>$winner];
            if($winner===CampaignLifecycle::PLAYER&&$s['eligible_for_re_election']){
                $s['consecutive_terms']++;$s['eligible_for_re_election']=false;$s['cycle_index']++;
                $s['term_start']=$day;$s['term_end']=self::termEnd($day);
                $s['first_round_day']=self::shift($s['term_end'],-25);
                $s['second_round_day']=self::shift($s['first_round_day'],14);
                $s['status']='scheduled';$s['first_result']=null;$s['result']=null;$s['handover_on']=$day;
            }else{
                $s['status']='ended';$s['officeholder']=$winner;$s['successor']=$winner;$s['handover_on']=$day;
                $s['reason']=($winner!==null&&$s['eligible_for_re_election'])?'presidential_election_lost':'presidential_mandate_ended';
            }
        }
        return $s;
    }
    /** A non-electoral terminal campaign outcome supersedes an uncompleted
     * scheduled election. Interim legal office is intentionally unresolved.
     */
    public static function interrupt(array $s,string $day,string $reason):array
    {
        if($s['status']==='ended'||$day!==$s['day'])throw new \DomainException('Invalid mandate interruption');
        $s['status']='ended';$s['officeholder']=null;$s['successor']=null;$s['handover_on']=$day;$s['reason']=$reason;return $s;
    }
    public static function countElection(array $c,bool $eligible):array
    {
        self::fields($c,['registered','first','second']);
        if(!is_int($c['registered'])||$c['registered']<1||$c['registered']>100000000)throw new \InvalidArgumentException('Invalid electorate');
        $first=self::ballot($c['first'],$c['registered']);
        if(count($first['votes'])<2||count($first['votes'])>30||isset($first['votes'][CampaignLifecycle::PLAYER])!==$eligible)
            throw new \DomainException('Invalid presidential candidate eligibility');
        $rank=array_keys($first['votes']);usort($rank,fn($a,$b)=>$first['votes'][$b]<=>$first['votes'][$a]?:strcmp($a,$b));
        $winner=$first['votes'][$rank[0]]*2>$first['valid_votes']?$rank[0]:null;
        $finalists=$winner===null?array_slice($rank,0,2):[];
        // Tie at the qualification boundary needs certified adjudication.
        if($winner===null&&isset($rank[2])&&$first['votes'][$rank[1]]===$first['votes'][$rank[2]])$finalists=[];
        $first+=['round'=>1,'winner'=>$winner,'finalists'=>$finalists];
        if($finalists===[]){
            if($c['second']!==null)throw new \DomainException('Unexpected runoff ballot');
            return ['first'=>$first,'second'=>null];
        }
        if(!is_array($c['second']))throw new \DomainException('Missing runoff ballot');
        $second=self::ballot($c['second'],$c['registered']);
        $keys=array_keys($second['votes']);sort($keys);$f=$finalists;sort($f);
        if($keys!==$f)throw new \DomainException('Runoff candidate mismatch');
        $winner=null;foreach($second['votes'] as $id=>$votes)if($votes*2>$second['valid_votes'])$winner=$id;
        $second+=['round'=>2,'winner'=>$winner,'finalists'=>$finalists];
        return ['first'=>$first,'second'=>$second];
    }
    private static function ballot(array $b,int $registered):array
    {
        self::fields($b,['votes','blank_invalid']);
        if(!is_array($b['votes'])||count($b['votes'])<2||count($b['votes'])>30||!is_int($b['blank_invalid'])||$b['blank_invalid']<0||$b['blank_invalid']>$registered)
            throw new \InvalidArgumentException('Invalid presidential ballot');
        foreach($b['votes'] as $id=>$n)
            if(!is_string($id)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)||!is_int($n)||$n<0||$n>$registered)
                throw new \InvalidArgumentException('Invalid candidate tally');
        $total=array_sum($b['votes']);if($total===0||$total+$b['blank_invalid']>$registered)throw new \DomainException('Invalid turnout');
        ksort($b['votes'],SORT_STRING);
        return ['votes'=>$b['votes'],'valid_votes'=>$total,'blank_invalid'=>$b['blank_invalid'],'registered'=>$registered,
            'ballots'=>$total+$b['blank_invalid'],'abstentions'=>$registered-$total-$b['blank_invalid']];
    }
    private static function termEnd(string $start):string
    {
        // Clamp Feb 29 to Feb 28 in the non-leap anniversary year.
        $date=new \DateTimeImmutable($start);$year=(int)$date->format('Y')+5;$month=(int)$date->format('m');
        $first=$date->setDate($year,$month,1);$day=min((int)$date->format('d'),(int)$first->format('t'));
        return $first->setDate($year,$month,$day)->format('Y-m-d');
    }
    private static function shift(string $day,int $n):string{return (new \DateTimeImmutable($day))->modify(($n>=0?'+':'').$n.' days')->format('Y-m-d');}
    private static function day(mixed $day):void{if(!is_string($day)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$day))===false||$d->format('Y-m-d')!==$day)throw new \InvalidArgumentException('Invalid presidential date');}
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Invalid presidential fields');}
}
