<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Verified daily game-time participation, not wall-clock activity.
 * An enabled account with its admitted career and membership intact at
 * fenced daily publication participates even if its AI made the decisions.
 * No retrospective backfill or inferred participation. */
final class CareerParticipation
{
    public const VERSION='wp119-daily-participation-v1';
    public static function digest(array $e):string{
        return CareerInstitutionLedger::digest(['kind'=>self::VERSION,'world'=>$e['world'],'day'=>$e['day'],
            'revision'=>$e['world_revision'],'operation'=>$e['operation'],'payload'=>$e['payload']]);
    }
    public static function apply(array &$s,array $e):void{
        $p=$e['payload'];$day=$e['day'];
        CareerInstitutionLedger::check($s['country']==='FR'&&$s['mode']==='career'&&$e['actor']==='career-participation-clerk'
            &&$e['evidence_sha']===self::digest($e),'participation evidence scope');
        if($e['operation']==='participation_enable'){
            CareerInstitutionLedger::fields($p,['version']);
            CareerInstitutionLedger::check(!isset($s['participation'])&&$day===CareerScoreContract::START&&$e['world_revision']===0
                &&$s['series']===[]&&$p['version']===self::VERSION,'participation must be pinned at opening');
            $s['participation']=['version'=>self::VERSION,'through'=>$day,'careers'=>[],'series'=>[]];return;
        }
        CareerInstitutionLedger::fields($p,['through','members']);
        $state=$s['participation']??null;$next=(new \DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
        CareerInstitutionLedger::check(is_array($state)&&$state['through']===$day&&$p['through']===$next
            &&$e['world_revision']===CareerScoreContract::elapsed(CareerScoreContract::START,$day)
            &&is_array($p['members'])&&array_is_list($p['members'])&&count($p['members'])<=64,'participation interval or membership list');
        $sorted=$p['members'];sort($sorted,SORT_STRING);
        CareerInstitutionLedger::check($sorted===$p['members']&&count(array_unique($sorted))===count($sorted),'noncanonical participation list');
        foreach($sorted as $id)CareerInstitutionLedger::check(is_string($id)&&isset($s['careers'][$id])&&$s['careers'][$id]['account']!==null,'invalid participating career');
        foreach($s['careers'] as $id=>$c){
            if($c['account']===null)continue;
            $joined=$c['claimed_on']??$c['admitted_on'];
            CareerInstitutionLedger::check($joined<=$day,'future human admission');
            $present=in_array($id,$sorted,true);
            $state['careers'][$id]=self::step($state['careers'][$id]??null,$joined,$day,$next,$present);
        }
        $mid=$s['active']['president']??null;
        if($mid!==null){
            $m=$s['mandates'][$mid];$c=$s['careers'][$m['career']];
            if(!$m['interim']&&$m['series']!==null&&$c['account']!==null){
                $from=max($s['series'][$m['series']]['start'],$c['claimed_on']??$c['admitted_on']);
                $state['series'][$m['series']]=self::step($state['series'][$m['series']]??null,$from,$day,$next,in_array($m['career'],$sorted,true));
            }
        }
        $state['through']=$next;$s['participation']=$state;
    }
    private static function step(?array $old,string $from,string $day,string $next,bool $present):array{
        $old??=['from'=>$from,'through'=>$from,'verified_days'=>0,'continuous'=>true];
        CareerInstitutionLedger::check($old['from']===$from&&$old['through']<=$day,'participation history mismatch');
        return ['from'=>$from,'through'=>$next,'verified_days'=>$old['verified_days']+($present?1:0),
            'continuous'=>$old['continuous']&&$old['through']===$day&&$present];
    }
    /** Seal the pre-mandate interval exactly once, before first human
     * accession. A missing interval stays unverified; late entry earns zero. */
    public static function seal(array &$s,string $seriesId,string $day):void{
        $p=$s['participation']??null;$series=$s['series'][$seriesId];$c=$s['careers'][$series['career']];
        if($p===null||$c['account']===null||$p['through']!==$day)return;
        $joined=$c['claimed_on']??$c['admitted_on'];
        if(!$series['first']||$joined!==CareerScoreContract::START){
            $s['series'][$seriesId]['credit_days']=0;$s['series'][$seriesId]['credit_verified']=true;return;
        }
        $days=CareerScoreContract::elapsed($joined,$day);
        $history=$p['careers'][$series['career']]??null;
        if($days===0||($history!==null&&$history['from']===$joined&&$history['through']===$day
            &&$history['continuous']&&$history['verified_days']===$days)){
            $s['series'][$seriesId]['credit_days']=$days;$s['series'][$seriesId]['credit_verified']=true;
        }
    }
    public static function tenureVerified(array $s,string $seriesId,string $from,string $through):bool{
        $p=$s['participation']??null;if($p===null||$p['through']<$through)return false;
        $d=CareerScoreContract::elapsed($from,$through);
        if($d===0)return true;
        $r=$p['series'][$seriesId]??null;
        return $r!==null&&$r['from']===$from&&$r['through']===$through&&$r['continuous']&&$r['verified_days']===$d;
    }
}
