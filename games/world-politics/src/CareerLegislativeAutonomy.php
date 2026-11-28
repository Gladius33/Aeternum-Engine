<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PdoCareerInstitutionStore.php';
require_once __DIR__.'/CareerProgrammeAutonomy.php';
require_once __DIR__.'/InstitutionalSimulation.php';

/** Bounded ordinary-law career route. The independent chamber fixture is
 * deterministic, not calibrated opinion. No 49.3, budget, decree, cabinet
 * substitution or human legislator impersonation. */
final class CareerLegislativeAutonomy
{
    public const VERSION='career-ordinary-v1';
    private static function plus(string $day,int $n):string{return (new \DateTimeImmutable($day))->modify('+'.$n.' days')->format('Y-m-d');}
    private static function validMandate(array $s,string $mid,string $office,string $day,string $ability):bool{
        $m=$s['mandates'][$mid]??null;
        return $m!==null&&$m['office']===$office&&($s['active'][$office]??null)===$mid&&$m['end']===null&&!$m['interim']&&$m['start']<=$day&&$day<$m['expires']&&in_array($ability,$m['powers'],true);
    }
    private static function id(string $world,string $policy,string $day,string $step):string{return 'cl-'.substr(hash('sha256',self::VERSION.'|'.$world.'|'.$policy.'|'.$day.'|'.$step),0,48);}
    public static function ballot(string $world,string $bill,string $chamber):array{
        if(!in_array($chamber,['assembly','senate'],true))throw new \DomainException('Unknown chamber');
        $n=$chamber==='assembly'?577:348;$v=['yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0];
        $support=4300+hexdec(substr(hash('sha256',self::VERSION.'|'.$world.'|'.$bill.'|'.$chamber),0,4))%2201;
        for($i=0;$i<$n;$i++){
            $h=hexdec(substr(hash('sha256',$world.'|'.$bill.'|'.$chamber.'|'.$i),0,8));
            $x=$h%10000;if($x>=9500)continue;
            $v[$x>=9000?'abstain':($x<$support?'yes':'no')]++;
        }
        return $v;
    }
    /** Proposals are linked to the actual PM mandate and its durable receipt.
     * Once filed the ordinary procedure may continue independently of the
     * original sponsor. A represented human chamber is not automated. */
    public static function advanceOne(InstitutionalSimulation $sim,array $s,string $policy,array $proposal,string $sponsor):?array{
        $is=$sim->snapshot();$day=$is['day'];$world=$is['world_id'];$l=$is['legislation']??[];
        if(($l['career_ordinary']??null)!==self::VERSION||$s['world']!==$world||$s['country']!=='FR'||$s['mode']!=='career'||$s['day']>$day||$s['assembly']!=='active'||$is['legislature']!=='sitting')return null;
        if($policy!=='education-reform'&&!SocialProgramme::isPolicy($policy))return null;
        if($proposal['authorized_on']!==null||$proposal['proposed_on']>=$day||$proposal['effective_on']<=$day)return null;
        $id=self::id($world,$policy,$proposal['proposed_on'],'bill');$b=$l['bills'][$id]??null;
        foreach($l['bills'] as $other)if($other['policy_id']===$policy&&$other['id']!==$id)return null;
        $session=ParliamentaryLifecycle::ordinarySession($day)!==null;
        $pm=self::validMandate($s,$sponsor,'prime-minister',$day,'government.coordinate');
        if($b===null){
            if(!$pm||!$session||self::plus($day,73)>=$proposal['effective_on'])return null;
            $r=$sim->legislation('scenario_bill_author','legislation_draft',['policy_id'=>$policy],$id);
            return ['policy_id'=>$policy,'stage'=>'draft','result'=>$r];
        }
        $kind=null;$data=[];$sha=$b['texts']['standard'];
        if($b['status']==='draft'){
            if(!$pm||!$session)return null;
            $kind='file';$data=['government_project'=>true,'accelerated'=>false,'joint_opposition'=>false,'filing_certified'=>true];
        }elseif($b['status']==='navette'){
            $ch=$b['next_chamber'];
            if(!$session||$b['readings'][$ch]!==0||($ch==='senate'&&!($b['last_votes']['assembly']['vote']['passed']??false)))return null;
            foreach($s['active'] as $office=>$mid)if(str_starts_with($office,$ch==='assembly'?'deputy-':'senator-'))return null;
            $from=$ch==='assembly'?$b['filed_on']:$b['last_votes']['assembly']['day'];
            if($day<self::plus($from,$ch==='assembly'?42:28))return null;
            $kind='reading';$data=['chamber'=>$ch,'version'=>'standard','text_sha'=>$sha,...self::ballot($world,$id,$ch),'amendment_certified'=>false,'reading_delay_certified'=>true];
        }elseif($b['status']==='adopted'&&$b['final_version']==='standard'){
            if($b['transmitted_on']===null&&$day>$b['adopted_on']){
                $kind='transmit';$data=['text_sha'=>$sha];
            }elseif($b['transmitted_on']!==null&&$day>=$b['transmitted_on']&&$day<=self::plus($b['transmitted_on'],15)&&$b['review']==='none'){
                $pres=$s['active']['president']??null;
                if($pres===null||!self::validMandate($s,$pres,'president',$day,'president.represent'))return null;
                // Ordinary mandatory publication fixture; no invented
                // constitutional review or discretionary presidential veto.
                $kind='promulgate';$data=['text_sha'=>$sha,'presidential_act_certified'=>true];
            }
        }elseif($b['status']==='promulgated'&&$day>$b['promulgated_on']){
            $kind='publish';$data=['text_sha'=>$sha,'effective_on'=>$proposal['effective_on'],'publication_certified'=>true];
        }
        if($kind===null)return null;
        $stage=$kind.($kind==='reading'?'-'.$data['chamber']:'');
        $event=self::id($world,$policy,$proposal['proposed_on'],$stage);
        $e=['world_id'=>$world,'country'=>'FR','day'=>$day,'evidence_id'=>$event,'bill_id'=>$id,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
        $r=$sim->legislation('legislation_clerk','legislation_evidence',$e,$event);
        return ['policy_id'=>$policy,'stage'=>$stage,'result'=>$r];
    }
    public static function advanceClock(\PDO $db,InstitutionalSimulation $sim,string $world,string $actor,string $target):?array{
        $is=$sim->snapshot();$day=$is['day'];
        if(!$db->inTransaction()||$actor!=='career-clock-'.$world||$world!==$is['world_id']||$target!==self::plus($day,1))throw new \DomainException('Career legal clock denied');
        $s=PdoCareerInstitutionStore::verifiedForClock($db,$world,$day,$target);
        $q=$db->prepare('SELECT state_json,state_sha FROM wp_domestic_states WHERE world_id=?');$q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);
        if($r===false||!hash_equals($r['state_sha'],hash('sha256',$r['state_json'])))throw new \DomainException('Career proposal source digest');
        $dom=json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR);
        if($dom['world_id']!==$world||$dom['day']!==$day||!isset($dom['daily_legal_version']))throw new \DomainException('Career proposal source scope');
        $q=$db->prepare("SELECT principal_id,result_json FROM wp_domestic_journal WHERE world_id=? AND operation='propose' ORDER BY revision");$q->execute([$world]);
        foreach($q as $row){
            $a=$row['principal_id'];
            $mid=str_starts_with($a,'career-auto-')?substr($a,12):(str_starts_with($a,'career-')?substr($a,7):'');
            $m=$s['mandates'][$mid]??null;if($m===null||$m['office']!=='prime-minister')continue;
            $p=json_decode($row['result_json'],true,64,JSON_THROW_ON_ERROR);
            $id=$p['policy_id']??null;$current=$dom['policies'][$id]??null;
            if($current===null||$current['authorized_on']!==null||$current!==($p['policy']??null))continue;
            $result=self::advanceOne($sim,$s,$id,$current,$mid);
            if($result!==null)return $result;
        }
        return null;
    }
}
