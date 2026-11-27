<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/BicameralLegislation.php';

/** Ordinary fictional parliamentary budget pathway, with distinct votes.
 * No real politician behaviour, no executive bypass, no client certification.
 * The trusted domestic policy proposal remains separately required. */
final class FinanceRequests
{
    public const VERSION='wp82-finance-requests-v1';
    private const NEXT=['requested'=>'filed','filed'=>'assembly','assembly'=>'transferred','transferred'=>'senate','senate'=>'adopted','adopted'=>'reviewed','reviewed'=>'published'];
    private const KIND=['requested'=>'finance_file','filed'=>'finance_assembly','assembly'=>'finance_transfer','transferred'=>'finance_senate','senate'=>'finance_final','adopted'=>'finance_review','reviewed'=>'finance_publish'];
    private const DELAY=['requested'=>7,'filed'=>1,'assembly'=>7,'transferred'=>3,'senate'=>3,'adopted'=>1,'reviewed'=>0];
    private static function plus(string $day,int $n):string{return (new \DateTimeImmutable($day))->modify('+'.$n.' days')->format('Y-m-d');}
    private static function terminal(string $s):bool{return in_array($s,['published','rejected','expired','blocked'],true);}
    public static function request(array $s,string $id,mixed $policy,bool $corrective=false):array
    {
        $f=$s['legislation']['finance'];$day=$s['day'];$y=$corrective?(int)substr($day,0,4):FinanceBudget::filingYear($f,$day);
        $cutoff=$y.($corrective?'-10-01':'-01-01');
        $catalog=$corrective?FinanceBudget::correctiveCatalog($y):FinanceBudget::catalog($y);
        if($corrective&&!FinanceBudget::eligibleCorrective($f,$y))throw new \DomainException('Corrective route not enabled');
        if($f['version']!==FinanceBudget::SERIES||$s['legislature']!=='sitting'||!is_string($policy)||!isset($catalog[$policy])
            ||$day<($corrective?$y:$y-1).'-01-01'||self::plus($day,30)>=$cutoff||count($s['finance_requests'])>=100||isset($s['finance_requests'][$id]))
            throw new \DomainException('No eligible annual budget request');
        foreach($s['finance_requests'] as $r)if($r['year']===$y&&FinanceBudget::isCorrective($r['policy_id'])===$corrective&&!self::terminal($r['status']))throw new \DomainException('Annual budget request pending');
        foreach($f['bills'] as $b)if(str_starts_with($b['policy_id'],($corrective?'corrective-':'budget-').$y.'-')&&$b['status']!=='rejected')throw new \DomainException('Annual bill already exists');
        return ['version'=>self::VERSION,'id'=>$id,'policy_id'=>$policy,'year'=>$y,'requested_on'=>$day,'due_on'=>self::plus($day,1),
            'status'=>'requested','steps'=>[],'reason'=>null];
    }
    private static function evidenceId(string $world,string $id,string $stage):string{return 'budget-auto-'.substr(hash('sha256',$world.'|'.$id.'|'.$stage),0,32);}
    private static function ballot(array $s,string $id,string $chamber,string $policy,string $round):array
    {
        $a=$s['legislation']['responsibility']['assembly'];
        $agents=$chamber==='assembly'?$a['deputies']:$s['senate']['agents'];
        $v=['yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0];
        foreach($agents as $key=>$d){
            $alignment=$chamber==='assembly'?($a['groups'][$d['group']]['alignment']??'nonaligned'):'nonaligned';
            $threshold=match($alignment){'government'=>7500,'opposition'=>3500,default=>5500};
            if(str_ends_with($policy,'-maintain'))$threshold+=1000;
            $h=hexdec(substr(hash('sha256',self::VERSION.'|'.$s['world_id'].'|'.$id.'|'.$chamber.'|'.$round.'|'.$key),0,8));
            if($h%10000>=$d['attendance_bps'])continue;
            $draw=($h>>14)%10000;
            $v[$draw<500?'abstain':($draw<$threshold?'yes':'no')]++;
        }
        return $v;
    }
    public static function advance(array $s):array
    {
        $day=$s['day'];
        foreach($s['finance_requests'] as $id=>$r){
            if(self::terminal($r['status'])||$r['due_on']>$day)continue;
            $stage=$r['status'];$year=$r['year'];$l=BicameralLegislation::restore($s['legislation']);
            $billId=self::evidenceId($s['world_id'],$id,'requested');
            $b=$s['legislation']['finance']['bills'][$billId]??null;
            if($day>=$year.(FinanceBudget::isCorrective($r['policy_id'])?'-10-01':'-01-01')||($b!==null&&in_array($stage,['filed','assembly','transferred','senate'],true)&&$day>$b['parliament_deadline'])){
                $r['status']='expired';$r['reason']='deadline';$s['finance_requests'][$id]=$r;continue;
            }
            if($s['legislature']!=='sitting')continue;
            $cab=$s['legislation']['responsibility']['cabinet'];
            if(($cab['status']??null)!=='in_office')continue;
            $sha=FinanceBudget::digest($r['policy_id'],$year);$kind=self::KIND[$stage];
            $data=match($stage){
                'requested'=>['policy_id'=>$r['policy_id'],'prime_minister'=>$cab['prime_minister'],'finance_minister'=>$cab['ministers']['economy'],'government_project_certified'=>true],
                'filed'=>['bill_id'=>$billId,'text_sha'=>$sha,...self::ballot($s,$id,'assembly',$r['policy_id'],'first')],
                'assembly'=>['bill_id'=>$billId,'prime_minister'=>$cab['prime_minister'],'transmission_certified'=>true],
                'transferred'=>['bill_id'=>$billId,'text_sha'=>$sha,...self::ballot($s,$id,'senate',$r['policy_id'],'first')],
                'senate'=>['bill_id'=>$billId,'text_sha'=>$sha,'route'=>($b['assembly_vote']['passed']&&$b['senate_vote']['passed'])?'identical':'assembly_last_word','navette_certified'=>true,
                    ...(($b['assembly_vote']['passed']&&$b['senate_vote']['passed'])?['yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0]:self::ballot($s,$id,'assembly',$r['policy_id'],'final'))],
                'adopted'=>['bill_id'=>$billId,'text_sha'=>$sha,'constitutional'=>true,'decision_certified'=>true],
                'reviewed'=>['bill_id'=>$billId,'text_sha'=>$sha,'president'=>$cab['president'],'promulgation_certified'=>true,'publication_certified'=>true],
            };
            $e=['world_id'=>$s['world_id'],'country'=>'FR','day'=>$day,'evidence_id'=>self::evidenceId($s['world_id'],$id,$stage),'bill_id'=>null,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
            try{$result=$l->certify($e);}
            catch(\DomainException|\InvalidArgumentException $err){$r['status']='blocked';$r['reason']='procedure_not_available';$s['finance_requests'][$id]=$r;continue;}
            $s['legislation']=$l->snapshot();
            $r['steps'][]=['stage'=>$stage,'day'=>$day,'evidence_id'=>$e['evidence_id']];
            $r['status']=$result['budget']['status']==='rejected'?'rejected':self::NEXT[$stage];
            $r['due_on']=self::plus($day,self::DELAY[$stage]);
            $s['finance_requests'][$id]=$r;
        }
        return $s;
    }
    public static function validate(array $s):void
    {
        if($s['country_code']!=='FR'||!isset($s['senate'])||$s['legislation']['finance']['version']!==FinanceBudget::SERIES||count($s['finance_requests'])>100)throw new \DomainException('Invalid finance autonomy scope');
        $evidence=[];foreach($s['legislation']['history'] as $h)if(isset($h['evidence']))$evidence[$h['evidence']['evidence_id']]=$h['evidence'];
        $used=[];
        foreach($s['finance_requests'] as $id=>$r){
            if($r['id']!==$id||$r['version']!==self::VERSION||!isset((FinanceBudget::isCorrective($r['policy_id'])?FinanceBudget::correctiveCatalog($r['year']):FinanceBudget::catalog($r['year']))[$r['policy_id']])||(FinanceBudget::isCorrective($r['policy_id'])&&!FinanceBudget::eligibleCorrective($s['legislation']['finance'],$r['year']))||$r['requested_on']>$s['day']||count($r['steps'])>7)
                throw new \DomainException('Invalid budget request');
            $expected='requested';$due=self::plus($r['requested_on'],1);
            foreach($r['steps'] as $step){
                $eid=self::evidenceId($s['world_id'],$id,$expected);$e=$evidence[$eid]??null;
                if($step['stage']!==$expected||$step['evidence_id']!==$eid||$step['day']<$due||$step['day']>$s['day']||$e===null||$e['kind']!==self::KIND[$expected]||$e['day']!==$step['day']
                    ||($expected==='requested'?$e['data']['policy_id']!==$r['policy_id']:$e['data']['bill_id']!==self::evidenceId($s['world_id'],$id,'requested')))
                    throw new \DomainException('Budget request evidence mismatch');
                if(isset($used[$eid]))throw new \DomainException('Duplicated autonomous budget evidence');
                $used[$eid]=true;
                $due=self::plus($step['day'],self::DELAY[$expected]);$expected=self::NEXT[$expected];
            }
            if($r['due_on']!==$due||(!in_array($r['status'],['rejected','expired','blocked'],true)&&$r['status']!==$expected))throw new \DomainException('Budget request stage mismatch');
            $b=$s['legislation']['finance']['bills'][self::evidenceId($s['world_id'],$id,'requested')]??null;
            if($r['status']==='published'&&($b['status']??null)!=='published'||$r['status']==='rejected'&&($b['status']??null)!=='rejected')throw new \DomainException('Budget terminal evidence mismatch');
            $reason=match($r['status']){'expired'=>'deadline','blocked'=>'procedure_not_available',default=>null};
            if($r['reason']!==$reason||($r['status']==='expired'&&$s['day']<$r['due_on']))throw new \DomainException('Invalid budget termination reason');
        }
        foreach($evidence as $eid=>$record)
            if(str_starts_with($eid,'budget-auto-')&&!isset($used[$eid]))
                throw new \DomainException('Orphaned autonomous budget evidence');
    }
}
