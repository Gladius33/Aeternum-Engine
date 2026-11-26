<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Fictional institutional agents, no client-supplied ballots. Model parameters
 * are deliberately generic and not derived from actual politicians. */
final class RevisionAutonomy
{
    public const VERSION='wp57-revision-autonomy-v1';
    private static function shift(string $d,int $n):string{return (new \DateTimeImmutable($d))->modify('+'.$n.' days')->format('Y-m-d');}
    public static function request(array $s,string $id,string $route):array
    {
        foreach($s['revision_requests'] as $old)if(!in_array($old['status'],['effective','rejected','declined','superseded'],true))throw new \DomainException('Another revision is in progress');
        if(!in_array($route,['congress','referendum'],true))throw new \DomainException('Invalid route');
        return ['version'=>self::VERSION,'id'=>$id,'route'=>$route,'requested_on'=>$s['day'],'next_on'=>self::shift($s['day'],7),'status'=>'pending_pm','observations'=>[]];
    }
    public static function validate(array $s):void
    {
        if($s['country_code']!=='FR'||!isset($s['recurring_elections'],$s['revision'])||!is_array($s['revision_requests'])||count($s['revision_requests'])>100)throw new \DomainException('Invalid autonomous revision scope');
        foreach($s['revision_requests'] as $id=>$r){
            if($r['id']!==$id||$r['version']!==self::VERSION||!isset($s['revision']['procedures'][$id])||!in_array($r['route'],['congress','referendum'],true)||!in_array($r['status'],['pending_pm','filed','assembly_approved','chambers_approved','ratification_pending','adopted','effective','rejected','declined','superseded'],true)||count($r['observations'])>7)
                throw new \DomainException('Invalid autonomous revision request');
            $p=$s['revision']['procedures'][$id];$expected=match($r['status']){'pending_pm','declined'=>'draft','assembly_approved'=>'filed','superseded'=>$p['status'],default=>$r['status']};
            if($p['status']!==$expected)throw new \DomainException('Revision request and legal procedure differ');
        }
    }
    private static function ballot(string $seed,array $assembly,string $chamber,int $approval):array
    {
        $v=['yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0];
        $agents=$chamber==='assembly'?$assembly['deputies']:array_fill(0,348,['group'=>'synthetic-senator','attendance_bps'=>9500]);
        foreach($agents as $id=>$d){
            $h=hexdec(substr(hash('sha256',$seed.'|'.$chamber.'|'.$id),0,8));
            $alignment=$assembly['groups'][$d['group']]['alignment']??'nonaligned';
            $threshold=match($alignment){'government'=>6000,'opposition'=>4000,default=>5000};
            // Fictional aggregate sentiment, not a model of real voter persuasion.
            $threshold=max(1000,min(9000,$threshold+2*($approval-500)));
            if($h%10000>=$d['attendance_bps'])continue;
            $draw=($h>>14)%10000;$v[$draw<500?'abstain':($draw<$threshold?'yes':'no')]++;
        }
        return $v;
    }
    public static function advance(array $s,?array $opinion):array
    {
        self::validate($s);$day=$s['day'];$r=ConstitutionalRevision::restore($s['revision']);$ctx=$s['revision']['context'];
        foreach($s['revision_requests'] as $id=>$req){
            if(in_array($req['status'],['effective','rejected','declined','superseded'],true)||$req['next_on']>$day)continue;
            $p=$r->snapshot()['procedures'][$id];
            if($p['base_version']!==$r->snapshot()['constitution_version']){$req['status']='superseded';$s['revision_requests'][$id]=$req;continue;}
            if($s['legislature']!=='sitting'||!$ctx['territorial_integrity']||$ctx['presidential_vacancy']||$ctx['definitive_incapacity'])continue;
            $prior=self::shift($day,-1);
            if($opinion===null||($opinion['day']??null)!==$prior||($opinion['world_id']??null)!==$s['world_id']||!is_int($opinion['approval']??null)||$opinion['approval']<0||$opinion['approval']>1000||!preg_match('/^[a-f0-9]{64}$/D',$opinion['sha']??''))throw new \DomainException('Prior committed opinion required');
            $assembly=$s['legislation']['responsibility']['assembly'];$status=$req['status'];$sha=$p['text_sha'];$approval=$opinion['approval'];$seed=$s['world_id'].'|'.$id;
            $kind=null;$data=[];$next=0;$after=null;
            switch($status){
                case 'pending_pm':
                    if(($s['legislation']['responsibility']['cabinet']['status']??null)!=='in_office'){$req['status']='declined';$s['revision_requests'][$id]=$req;continue;}
                    $kind='file';$data=['initiative'=>'government_project','sponsor'=>'president','prime_minister_proposal'=>true,'republican_form_preserved'=>true];$next=30;$after='filed';break;
                case 'filed':$kind='chamber_vote';$data=['chamber'=>'assembly','text_sha'=>$sha,...self::ballot($seed,$assembly,'assembly',$approval)];$next=30;$after='assembly_approved';break;
                case 'assembly_approved':$kind='chamber_vote';$data=['chamber'=>'senate','text_sha'=>$sha,...self::ballot($seed,$assembly,'senate',$approval)];$next=15;$after='chambers_approved';break;
                case 'chambers_approved':$kind='route';$data=['route'=>$req['route'],'presidential_decision'=>true];$next=$req['route']==='congress'?30:60;$after='ratification_pending';break;
                case 'ratification_pending':
                    if($req['route']==='congress'){
                        $a=self::ballot($seed.'|congress',$assembly,'assembly',$approval);$b=self::ballot($seed.'|congress',$assembly,'senate',$approval);
                        $v=[];foreach($a as $k=>$n)$v[$k]=$n+$b[$k];
                    }else{$yes=40000+20*($approval-500);$v=['yes'=>$yes,'no'=>80000-$yes,'abstain'=>0,'blank_invalid'=>1000];}
                    $kind='ratify';$data=['route'=>$req['route'],'text_sha'=>$sha,...$v];$next=1;$after='adopted';break;
                case 'adopted':$kind='promulgate';$data=['text_sha'=>$sha,'published_on'=>$day];$after='effective';break;
                default:throw new \DomainException('Unknown revision stage');
            }
            $e=['world_id'=>$s['world_id'],'country'=>'FR','day'=>$day,'evidence_id'=>'auto89-'.substr(hash('sha256',$seed.'|'.$status),0,32),'revision_id'=>$id,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
            $result=$r->certify($e);$req['observations'][]=['day'=>$day,'stage'=>$status,'opinion'=>$opinion,'assembly_sha'=>hash('sha256',json_encode($assembly,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),'evidence_id'=>$e['evidence_id']];
            $req['status']=$result['procedure']['status']==='rejected'?'rejected':$after;$req['next_on']=self::shift($day,$next);
            $s['revision_requests'][$id]=$req;
        }
        $s['revision']=$r->snapshot();return $s;
    }
}
