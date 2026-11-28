<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CareerInstitutionLedger.php';

/** Ordinary scheduled French legislative career pilot. Districts and counts
 * are synthetic game fixtures, not a model of actual constituencies or polls.
 * No dissolution election, party list, Senate renewal or coalition model. */
final class CareerAssemblyElection{
    public const VERSION='wp127-assembly-career-v1';
    public static function enabled(array $s):bool{return $s['country']==='FR'&&$s['mode']==='career'&&($s['cabinet_autonomy']??null)===CareerInstitutionLedger::CABINET_V4;}
    public static function digest(array $e):string{return CareerInstitutionLedger::digest(['kind'=>self::VERSION,'world'=>$e['world'],'day'=>$e['day'],'actor'=>$e['actor'],'operation'=>$e['operation'],'payload'=>$e['payload']]);}
    public static function next(array $s,string $day):?string{
        if(!self::enabled($s))return null;
        foreach(['2029-06-10','2034-06-10','2039-06-10','2044-06-10'] as $d)
            if($day<=$d&&!isset($s['assembly_elections'][$d]))return $d;
        return null;
    }
    private static function cutoff(string $election):string{return (new \DateTimeImmutable($election))->modify('-7 days')->format('Y-m-d');}
    private static function eligibleOffice(array $s,string $id):bool{
        foreach($s['active'] as $o=>$mid)if($s['mandates'][$mid]['career']===$id&&!str_starts_with($o,'deputy-'))return false;
        return true;
    }
    public static function view(array $s,string $day,?string $id):?array{
        $next=self::next($s,$day);if($next===null)return null;
        return ['classification'=>'synthetic_district_election','election_day'=>$next,'candidacy_deadline'=>self::cutoff($next),
            'can_declare'=>$id!==null&&$day<=self::cutoff($next)&&$s['assembly']==='active'&&self::eligibleOffice($s,$id),
            'own_candidacy'=>$id===null?null:($s['assembly_candidacies'][$next][$id]??null),
            'last_election'=>array_key_last($s['assembly_elections']??[])];
    }
    public static function declare(array &$s,array $e):void{
        $p=$e['payload'];CareerInstitutionLedger::fields($p,['career','seat','election_day']);
        $id=$p['career'];$day=$e['day'];$next=self::next($s,$day);
        CareerInstitutionLedger::check(self::enabled($s)&&$next!==null&&$p['election_day']===$next&&$day<=self::cutoff($next)
            &&is_string($id)&&isset($s['careers'][$id])&&$s['careers'][$id]['account']!==null&&$s['careers'][$id]['account']===$e['actor']
            &&$s['assembly']==='active'&&self::eligibleOffice($s,$id)&&is_int($p['seat'])&&$p['seat']>=1&&$p['seat']<=577
            &&$e['evidence_sha']===self::digest($e),'assembly candidacy denied');
        $s['assembly_candidacies'][$next][$id]=['seat'=>$p['seat'],'declared_on'=>$day];
    }
    public static function due(array $s,string $day):bool{return self::next($s,$day)===$day&&$s['assembly']==='active';}
    public static function plan(array $s,string $day,array $eligible):?array{
        if(!self::due($s,$day))return null;
        $sorted=$eligible;sort($sorted,SORT_STRING);
        CareerInstitutionLedger::check(array_is_list($eligible)&&$sorted===$eligible&&count($eligible)===count(array_unique($eligible))&&count($eligible)<=64,'assembly eligible membership list');
        foreach($eligible as $id)CareerInstitutionLedger::check(isset($s['careers'][$id])&&$s['careers'][$id]['account']!==null,'unknown human candidate');
        $close=[];foreach($s['active'] as $o=>$mid)if(str_starts_with($o,'deputy-'))$close[]=$o;
        sort($close,SORT_STRING);$contests=[];
        foreach($s['assembly_candidacies'][$day]??[] as $id=>$c){
            if(!in_array($id,$eligible,true)||!self::eligibleOffice($s,$id)||$c['declared_on']>self::cutoff($day))continue;
            $contests[$c['seat']][]=$id;
        }
        ksort($contests,SORT_NUMERIC);$grants=[];$results=[];
        $end=min((new \DateTimeImmutable($day))->modify('+5 years')->format('Y-m-d'),(new \DateTimeImmutable(CareerScoreContract::START))->modify('+'.CareerScoreContract::HORIZON.' days')->format('Y-m-d'));
        foreach($contests as $seat=>$ids){
            // Plurality of synthetic two-round-finalist support weights;
            // equal highest weight yields no human winner, never a tie-break
            // granting a human office. Nonrepresented seats remain fixtures.
            $weights=['npc'=>4500+hexdec(substr(hash('sha256',self::VERSION.'|'.$s['world'].'|'.$day.'|'.$seat.'|npc'),0,4))%3001];
            foreach($ids as $id)$weights[$id]=3500+hexdec(substr(hash('sha256',self::VERSION.'|'.$s['world'].'|'.$day.'|'.$seat.'|'.$id),0,4))%5001;
            arsort($weights,SORT_NUMERIC);$rank=array_keys($weights);$winner=$weights[$rank[0]]>$weights[$rank[1]]?$rank[0]:'npc';
            $results[]=['seat'=>$seat,'winner'=>$winner==='npc'?null:$winner,'support_weights'=>$weights];
            if($winner!=='npc')$grants[]=['office'=>'deputy-'.$seat,'career'=>$winner,'expires'=>$end,'interim'=>false];
        }
        return ['close'=>$close,'grants'=>$grants,'contests'=>$results,'classification'=>'synthetic_district_election'];
    }
}
