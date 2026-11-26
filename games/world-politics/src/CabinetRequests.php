<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** A nomination request does not dismiss an incumbent. The fictional
 * appointment service acts only following a certified resignation. */
final class CabinetRequests
{
    public const VERSION='wp59-cabinet-requests-v1';
    public static function request(array $s,string $id,mixed $candidate):array
    {
        if(!is_string($candidate)||!str_starts_with($candidate,'pm-')||!isset(FrenchCabinet::catalog()[$candidate]))throw new \DomainException('Unknown fictional prime minister');
        $r=$s['legislation']['responsibility'];
        if(!in_array($r['cabinet']['status'],['caretaker','resignation_required'],true)||$s['legislature']!=='sitting'||count($s['cabinet_requests'])>=100)throw new \DomainException('No cabinet vacancy or required resignation');
        foreach($s['cabinet_requests'] as $q)if($q['status']==='requested')throw new \DomainException('Pending appointment');
        return ['version'=>self::VERSION,'id'=>$id,'candidate'=>$candidate,'requested_on'=>$s['day'],'due_on'=>(new \DateTimeImmutable($s['day']))->modify('+1 day')->format('Y-m-d'),'status'=>'requested','decided_on'=>null,'evidence_id'=>null];
    }
    public static function validate(array $s):void
    {
        if(!isset($s['senate'])||$s['country_code']!=='FR'||count($s['cabinet_requests'])>100)throw new \DomainException('Cabinet request scope');
        foreach($s['cabinet_requests'] as $id=>$r){
            if($r['id']!==$id||$r['version']!==self::VERSION||!isset(FrenchCabinet::catalog()[$r['candidate']])||!in_array($r['status'],['requested','appointed','refused'],true)||$r['requested_on']>$s['day']||$r['due_on']!==(new \DateTimeImmutable($r['requested_on']))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Invalid cabinet request');
            if($r['status']==='appointed'){
                $found=false;foreach($s['legislation']['history'] as $h)if(($h['evidence']['evidence_id']??null)===$r['evidence_id']&&$h['evidence']['kind']==='assembly_cabinet_appoint'&&$h['evidence']['data']['prime_minister']===$r['candidate']&&$h['day']===$r['decided_on'])$found=true;
                if(!$found)throw new \DomainException('Missing appointment evidence');
            }
        }
    }
    public static function advance(array $s):array
    {
        $r=$s['legislation']['responsibility'];
        // The Article 50 obligation belongs to the government, not the owner.
        // Only a committed censure/confidence outcome can create this state.
        if($r['cabinet']['status']==='resignation_required'&&$r['crisis']['government']==='resignation_required'&&ParliamentaryLifecycle::quiet($r)){
            $day=$s['day'];$hash=substr(hash('sha256',$s['world_id'].'|article50|'.$r['cabinet']['generation']),0,32);
            $l=BicameralLegislation::restore($s['legislation']);
            $e=['world_id'=>$s['world_id'],'country'=>'FR','day'=>$day,'evidence_id'=>'art50-'.$hash,'bill_id'=>null,'kind'=>'assembly_cabinet_resign','procedure_certified'=>true,
                'data'=>['at'=>$day.'T12:00:00Z','prime_minister'=>$r['cabinet']['prime_minister'],'resignation_id'=>'resign-'.$hash,'resignation_presented'=>true]];
            $l->certify($e);$s['legislation']=$l->snapshot();
        }
        foreach($s['cabinet_requests'] as $id=>$q){
            if($q['status']!=='requested'||$q['due_on']>$s['day'])continue;
            $r=$s['legislation']['responsibility'];
            if($s['legislature']!=='sitting'||!ParliamentaryLifecycle::quiet($r))continue;
            $day=$s['day'];$hash=substr(hash('sha256',$s['world_id'].'|'.$id),0,32);$at=$day.'T12:00:00Z';
            if($r['cabinet']['status']!=='caretaker'||$r['cabinet']['resignation_on']===null){
                // A censure is not itself a recorded presentation of resignation.
                if($r['cabinet']['status']==='resignation_required')continue;
                $q['status']='refused';
            }else{
                if($r['cabinet']['resignation_on']===$day)continue;
                $l=BicameralLegislation::restore($s['legislation']);
                $e=['world_id'=>$s['world_id'],'country'=>'FR','day'=>$day,'evidence_id'=>'cabinet-'.$hash,'bill_id'=>null,'kind'=>'assembly_cabinet_appoint','procedure_certified'=>true,
                    'data'=>['at'=>$at,'appointment_id'=>'appoint-'.$hash,'prime_minister'=>$q['candidate'],'president'=>$r['cabinet']['president'],'resignation_presented'=>true,'presidential_act_certified'=>true,'incompatibilities_cleared'=>true]];
                $l->certify($e);$s['legislation']=$l->snapshot();$q['status']='appointed';$q['evidence_id']=$e['evidence_id'];
            }
            $q['decided_on']=$day;$s['cabinet_requests'][$id]=$q;
        }
        return $s;
    }
}
