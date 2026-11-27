<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ConstitutionalRevision.php';

/** Fictional constitutional implementation. Neither actual electorate
 * demographics nor actual public opinion is asserted. */
final class CivicReform
{
    public const VERSION='wp114-civic-reform-v1';
    public const SIGNATURES=500000;
    public const WORKER_ELECTORATE_BPS=500;
    public const QUESTION='fictional-annual-public-services-report';
    public static function proof(array $revision,string $world,string $day,string $type):?array
    {
        $r=ConstitutionalRevision::restore($revision)->snapshot();
        if($r['world_id']!==$world||$r['day']!==$day)throw new \DomainException('Civic revision scope mismatch');
        $template=match($type){'initiative'=>'citizen-initiative-500k','franchise'=>'resident-worker-franchise',default=>throw new \InvalidArgumentException('Unknown civic reform')};
        $c=$r['effective_clauses'][ConstitutionalRevision::catalog()[$template]['article']]??null;
        if($c===null)return null;
        $p=$r['procedures'][$c['revision_id']]??null;
        if(($p['template']??null)!==$template||$p['status']!=='effective'||$p['route']!=='referendum')throw new \DomainException('Invalid civic revision');
        $v=['type'=>$type,'revision_id'=>$c['revision_id'],'text_sha'=>$c['text_sha'],'effective_on'=>$c['effective_on']];
        self::validateProof($v,$day,$type);return $v;
    }
    public static function validateProof(array $p,string $day,string $type):void
    {
        $keys=array_keys($p);sort($keys);
        if($keys!==['effective_on','revision_id','text_sha','type']||$p['type']!==$type
            ||!is_string($p['revision_id'])||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$p['revision_id']))throw new \DomainException('Invalid civic proof');
        $template=match($type){'initiative'=>'citizen-initiative-500k','franchise'=>'resident-worker-franchise',default=>throw new \InvalidArgumentException('Unknown civic reform')};
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$p['effective_on']);
        if($date===false||$date->format('Y-m-d')!==$p['effective_on']||$p['effective_on']>$day
            ||$p['text_sha']!==hash('sha256',ConstitutionalRevision::catalog()[$template]['text']))throw new \DomainException('Unpublished civic reform');
    }
    private static function plus(string $d,int $n):string{return (new \DateTimeImmutable($d))->modify('+'.$n.' days')->format('Y-m-d');}
    public static function initial(string $world,array $proof):array
    {
        self::validateProof($proof,$proof['effective_on'],'initiative');
        $day=$proof['effective_on'];
        return ['version'=>self::VERSION,'world_id'=>$world,'day'=>$day,'proof'=>$proof,'question'=>self::QUESTION,
            'classification'=>'fictional_independent_citizen_agents','signatures'=>0,'threshold'=>self::SIGNATURES,'deadline'=>self::plus($day,180),
            'status'=>'collecting','poll_day'=>null,'ballot'=>null,'effective_on'=>null,'next_report_due'=>null,'reports'=>[],
            'observations'=>[]];
    }
    public static function advance(array $s,string $day,?array $opinion):array
    {
        if($day!==self::plus($s['day'],1))throw new \DomainException('Civic clock requires consecutive day');
        $s['day']=$day;
        if(in_array($s['status'],['collecting','poll_scheduled'],true)){
            if($opinion===null||($opinion['world_id']??null)!==$s['world_id']||($opinion['day']??null)!==self::plus($day,-1)
                ||!is_int($opinion['approval']??null)||$opinion['approval']<0||$opinion['approval']>1000||!preg_match('/^[a-f0-9]{64}$/D',$opinion['sha']??''))
                throw new \DomainException('Prior opinion required for independent civic agents');
            $s['observations'][]=$opinion;
            if($s['status']==='collecting'){
                // Synthetic unique verified signatures, never player-supplied.
                $n=1500+hexdec(substr(hash('sha256',$s['world_id'].'|'.$day.'|'.self::QUESTION),0,6))%2501;
                $n+=intdiv($opinion['approval'],2);
                $s['signatures']=min(self::SIGNATURES,$s['signatures']+$n);
                if($s['signatures']===self::SIGNATURES){$s['status']='poll_scheduled';$s['poll_day']=self::plus($day,60);}
                elseif($day>=$s['deadline'])$s['status']='threshold_not_met';
            }elseif($day===$s['poll_day']){
                // A separate fictional issue ballot: no executive approval
                // or override. Participation is not a real-world forecast.
                $yes=400000+200*($opinion['approval']-500);$no=800000-$yes;
                $s['ballot']=['registered'=>1000000,'yes'=>$yes,'no'=>$no,'blank_invalid'=>10000,'abstentions'=>190000];
                $s['status']=$yes>$no?'approved':'rejected';
                if($s['status']==='approved'){$s['effective_on']=self::plus($day,1);$s['next_report_due']=self::plus($s['effective_on'],365);}
            }
        }
        if($s['status']==='approved'&&$day===$s['effective_on'])$s['status']='effective';
        if($s['status']==='effective'&&$day===$s['next_report_due']){
            $s['reports'][]=['due_on'=>$day,'kind'=>self::QUESTION,'status'=>'due_for_publication'];
            $s['next_report_due']=self::plus($day,365);
        }
        return $s;
    }
    public static function restore(array $s):array
    {
        $v=self::initial($s['world_id'],$s['proof']);$i=0;
        while($v['day']<$s['day']){
            $day=self::plus($v['day'],1);
            if(in_array($v['status'],['collecting','poll_scheduled'],true)){
                if(!isset($s['observations'][$i]))throw new \DomainException('Missing civic observation');
                $o=$s['observations'][$i++];
            }else $o=null;
            $v=self::advance($v,$day,$o);
        }
        if($v!==$s)throw new \DomainException('Civic history mismatch');
        return $v;
    }
    /** Equal participation and ballot shares for a fictional added cohort.
     * No real foreign-worker count or political preference inferred. */
    public static function expandDistrict(array $d):array
    {
        $extra=static fn(int $n):int=>intdiv($n*self::WORKER_ELECTORATE_BPS,10000);
        $d['registered']+=$extra($d['registered']);
        foreach(['first','second'] as $round){
            if($d[$round]===null)continue;
            foreach($d[$round]['votes'] as &$v)$v+=$extra($v);unset($v);
            $d[$round]['blank_invalid']+=$extra($d[$round]['blank_invalid']);
        }
        return $d;
    }
    public static function expandConfig(array $c):array
    {
        foreach($c['districts'] as &$d)$d=self::expandDistrict($d);unset($d);
        return $c;
    }
}
