<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CareerInstitutionLedger.php';

/** Indirect Senate election scenario, not official department results.
 * Real institutional structure: 348 seats, six-year terms, 170/178
 * alternating series. Source:
 * https://www.senat.fr/connaitre-le-senat/role-et-fonctionnement/mode-delection-des-senateurs.html
 * Numbered seats and the electoral-college support weights are synthetic.
 * September 30 is a game handover convention, not an announced poll date.
 * Age, nationality and actual departmental/list eligibility are not certified. */
final class CareerSenateElection{
    public const VERSION='wp127-senate-career-v1';
    public static function enabled(array $s):bool{return $s['country']==='FR'&&$s['mode']==='career'&&in_array($s['cabinet_autonomy']??null,[CareerInstitutionLedger::CABINET_V5,CareerInstitutionLedger::CABINET_V6],true);}
    public static function digest(array $e):string{return CareerInstitutionLedger::digest(['kind'=>self::VERSION,'world'=>$e['world'],'day'=>$e['day'],'actor'=>$e['actor'],'operation'=>$e['operation'],'payload'=>$e['payload']]);}
    public static function next(array $s,string $day):?string{
        if(!self::enabled($s))return null;
        for($y=2029;$y<=2044;$y+=3){$d=$y.'-09-30';if($day<=$d&&!isset($s['senate_elections'][$d]))return $d;}
        return null;
    }
    private static function series(string $day):array{
        return ((int)substr($day,0,4)-2029)%6===0?['series'=>1,'min'=>1,'max'=>170]:['series'=>2,'min'=>171,'max'=>348];
    }
    private static function cutoff(string $d):string{return (new \DateTimeImmutable($d))->modify('-7 days')->format('Y-m-d');}
    private static function eligibleOffice(array $s,string $id,array $series):bool{
        foreach($s['active'] as $office=>$mid){
            if($s['mandates'][$mid]['career']!==$id)continue;
            if(!str_starts_with($office,'senator-'))return false;
            $seat=(int)substr($office,8);
            if($seat<$series['min']||$seat>$series['max'])return false;
        }
        return true;
    }
    public static function view(array $s,string $day,?string $id):?array{
        $next=self::next($s,$day);if($next===null)return null;$series=self::series($next);
        return ['classification'=>'synthetic_indirect_election','election_day'=>$next,'candidacy_deadline'=>self::cutoff($next),...$series,
            'can_declare'=>$id!==null&&$day<=self::cutoff($next)&&$s['senate']==='active'&&self::eligibleOffice($s,$id,$series),
            'own_candidacy'=>$id===null?null:($s['senate_candidacies'][$next][$id]??null),'last_election'=>array_key_last($s['senate_elections']??[])];
    }
    public static function declare(array &$s,array $e):void{
        $p=$e['payload'];CareerInstitutionLedger::fields($p,['career','seat','election_day']);
        $id=$p['career'];$day=$e['day'];$next=self::next($s,$day);
        CareerInstitutionLedger::check(self::enabled($s)&&$next!==null&&$p['election_day']===$next&&$day<=self::cutoff($next),'Senate candidacy window');
        $series=self::series($next);
        CareerInstitutionLedger::check(is_string($id)&&isset($s['careers'][$id])&&$s['careers'][$id]['account']!==null&&$s['careers'][$id]['account']===$e['actor']
            &&$s['senate']==='active'&&self::eligibleOffice($s,$id,$series)&&is_int($p['seat'])&&$p['seat']>=$series['min']&&$p['seat']<=$series['max']
            &&$e['evidence_sha']===self::digest($e),'Senate candidacy denied');
        $s['senate_candidacies'][$next][$id]=['seat'=>$p['seat'],'declared_on'=>$day];
    }
    public static function due(array $s,string $day):bool{return self::next($s,$day)===$day&&$s['senate']==='active';}
    public static function plan(array $s,string $day,array $eligible):?array{
        if(!self::due($s,$day))return null;
        $series=self::series($day);$sorted=$eligible;sort($sorted,SORT_STRING);
        CareerInstitutionLedger::check(array_is_list($eligible)&&$sorted===$eligible&&count($eligible)===count(array_unique($eligible))&&count($eligible)<=64,'Senate eligibility list');
        foreach($eligible as $id)CareerInstitutionLedger::check(isset($s['careers'][$id])&&$s['careers'][$id]['account']!==null,'unknown Senate candidate');
        $close=[];foreach($s['active'] as $o=>$mid)if(str_starts_with($o,'senator-')){
            $seat=(int)substr($o,8);if($seat>=$series['min']&&$seat<=$series['max'])$close[]=$o;
        }
        sort($close,SORT_STRING);$contests=[];
        foreach($s['senate_candidacies'][$day]??[] as $id=>$c){
            if(!in_array($id,$eligible,true)||!self::eligibleOffice($s,$id,$series)||$c['declared_on']>self::cutoff($day))continue;
            $contests[$c['seat']][]=$id;
        }
        ksort($contests,SORT_NUMERIC);$grants=[];$results=[];
        $end=min((new \DateTimeImmutable($day))->modify('+6 years')->format('Y-m-d'),(new \DateTimeImmutable(CareerScoreContract::START))->modify('+'.CareerScoreContract::HORIZON.' days')->format('Y-m-d'));
        foreach($contests as $seat=>$ids){
            $weights=['npc'=>4500+hexdec(substr(hash('sha256',self::VERSION.'|'.$s['world'].'|'.$day.'|'.$seat.'|npc'),0,4))%3001];
            foreach($ids as $id)$weights[$id]=3500+hexdec(substr(hash('sha256',self::VERSION.'|'.$s['world'].'|'.$day.'|'.$seat.'|'.$id),0,4))%5001;
            if(($s['cabinet_autonomy']??null)===CareerInstitutionLedger::CABINET_V6){
                require_once __DIR__.'/CareerCampaignActivity.php';
                foreach($ids as $id)$weights[$id]=CareerCampaignActivity::adjust($s,$id,$day,$weights[$id]);
            }
            arsort($weights,SORT_NUMERIC);$rank=array_keys($weights);$winner=$weights[$rank[0]]>$weights[$rank[1]]?$rank[0]:'npc';
            $results[]=['seat'=>$seat,'winner'=>$winner==='npc'?null:$winner,'college_support_weights'=>$weights];
            if($winner!=='npc')$grants[]=['office'=>'senator-'.$seat,'career'=>$winner,'expires'=>$end,'interim'=>false];
        }
        return ['series'=>$series['series'],'close'=>$close,'grants'=>$grants,'contests'=>$results,'classification'=>'synthetic_indirect_election'];
    }
}
