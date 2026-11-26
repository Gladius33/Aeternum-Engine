<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Legal and administrative simulation only. No force generation or real
 * operational readiness, targeting, or deployment instructions. */
final class NationalServiceLaw
{
    public const VERSION='wp84-national-service-law-v1';
    public const SOURCE='https://www.legifrance.gouv.fr/loda/article_lc/LEGIARTI000006555910';
    public const RESTORE='national-service-restore';
    public const SUSPEND='national-service-suspend';
    public static function catalog():array
    {
        return [
            self::RESTORE=>['standard'=>'Fictional statute restores the legal basis for compulsory national service after a ninety-day scenario transition. Any implementation requires separate lawful administrative and budgetary authority.',
                'amended'=>'Fictional parliamentary report on national service without restoring compulsory service.'],
            self::SUSPEND=>['standard'=>'Fictional statute suspends compulsory national service after a thirty-day scenario transition.',
                'amended'=>'Fictional parliamentary report on the administration of national service without changing its legal status.']
        ];
    }
    public static function isPolicy(string $p):bool{return in_array($p,[self::RESTORE,self::SUSPEND],true);}
    private static function plus(string $d,int $n):string{return (new \DateTimeImmutable($d))->modify('+'.$n.' days')->format('Y-m-d');}
    private static function transition(string $policy):int{return $policy===self::RESTORE?90:30;}
    public static function validateDraft(array $s,string $policy):void
    {
        $r=self::report($s);
        if($r['pending_law']!==null||($policy===self::RESTORE&&$r['legal_status']!=='suspended')||($policy===self::SUSPEND&&$r['legal_status']!=='restored'))
            throw new \DomainException('National service status or pending law incompatible');
        foreach($s['bills'] as $b)if(self::isPolicy($b['policy_id'])&&!in_array($b['status'],['published','rejected'],true))
            throw new \DomainException('National service law already under consideration');
    }
    public static function validatePublication(array $s,array $b,string $effective):void
    {
        if($b['final_version']!=='standard')return;
        if($b['review']!=='cleared'||$effective<self::plus($s['day'],self::transition($b['policy_id'])))
            throw new \DomainException('National service review or scenario transition missing');
        $r=self::report($s);
        if($r['pending_law']!==null||($b['policy_id']===self::RESTORE?$r['legal_status']!=='suspended':$r['legal_status']!=='restored'))
            throw new \DomainException('Conflicting national service law');
    }
    public static function report(array $s):array
    {
        if(($s['national_service']??null)!==self::VERSION)throw new \DomainException('National service law unavailable');
        $published=[];$open=[];
        foreach($s['bills'] as $b)if(self::isPolicy($b['policy_id'])){
            if($b['status']==='published'&&$b['final_version']==='standard')$published[]=$b;
            elseif(!in_array($b['status'],['published','rejected'],true))$open[]=['id'=>$b['id'],'policy_id'=>$b['policy_id'],'status'=>$b['status'],'readings'=>$b['readings']];
        }
        usort($published,fn($a,$b)=>strcmp($a['effect_on'],$b['effect_on']));
        $status='suspended';$latest=null;$pending=null;
        foreach($published as $b){
            if($b['review']!=='cleared'||$b['effect_on']<self::plus($b['published_on'],self::transition($b['policy_id']))||$pending!==null||($b['policy_id']===self::RESTORE?$status!=='suspended':$status!=='restored'))
                throw new \DomainException('National service chronology invalid');
            $law=['id'=>$b['id'],'policy_id'=>$b['policy_id'],'text_sha'=>$b['texts']['standard'],'published_on'=>$b['published_on'],'effective_on'=>$b['effect_on']];
            if($b['effect_on']>$s['day']){$pending=$law;continue;}
            $latest=$law;$status=$b['policy_id']===self::RESTORE?'restored':'suspended';
        }
        $admin=$status==='restored'?'separate_administrative_and_budgetary_authorization_required':'no_compulsory_call_up';
        return ['version'=>self::VERSION,'world_id'=>$s['world_id'],'day'=>$s['day'],'source'=>self::SOURCE,'legal_status'=>$status,
            'effective_law'=>$latest,'pending_law'=>$pending,'open_bills'=>$open,
            'administrative_status'=>$admin,'transition_days_are_scenario_assumptions'=>true,'restore_transition_days'=>90,'suspend_transition_days'=>30,
            'actual_call_up_authorized_by_report'=>false,'personnel_added'=>null,'additional_budget_delta_minor'=>null,'operational_readiness_inferred'=>false];
    }
    private static function ballot(array $s,string $id,string $chamber):array
    {
        $a=$s['legislation']['responsibility']['assembly'];$members=$chamber==='assembly'?$a['deputies']:$s['senate']['agents'];
        $v=['yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0];
        foreach($members as $key=>$m){
            $alignment=$chamber==='assembly'?($a['groups'][$m['group']]['alignment']??'nonaligned'):'nonaligned';
            $threshold=match($alignment){'government'=>6500,'opposition'=>4000,default=>5500};
            $h=hexdec(substr(hash('sha256',self::VERSION.'|'.$s['world_id'].'|'.$id.'|'.$chamber.'|'.$key),0,8));
            if($h%10000>=$m['attendance_bps'])continue;$draw=($h>>14)%10000;
            $v[$draw<500?'abstain':($draw<$threshold?'yes':'no')]++;
        }
        return $v;
    }
    /** Ordinary identical-text route only. A disagreement leaves a real
     * pending parliamentary file, not an invented majority or forced result. */
    public static function advance(array $s):array
    {
        if($s['legislature']!=='sitting')return $s;
        $cab=$s['legislation']['responsibility']['cabinet'];if($cab['status']!=='in_office')return $s;
        $day=$s['day'];
        foreach($s['legislation']['bills'] as $id=>$b){
            if(!self::isPolicy($b['policy_id'])||in_array($b['status'],['published','rejected'],true))continue;
            $kind=null;$data=[];$sha=$b['texts']['standard'];$stage='';
            if($b['status']==='draft'){
                $kind='file';$stage='file';$data=['government_project'=>true,'accelerated'=>false,'joint_opposition'=>false,'filing_certified'=>true,'prime_minister_id'=>$cab['prime_minister']];
            }elseif($b['status']==='navette'&&!$b['accelerated']&&$b['cmp']===null){
                $ch=$b['next_chamber'];if($b['readings'][$ch]!==0)continue;
                $base=$ch==='assembly'?$b['filed_on']:($b['last_votes']['assembly']['day']??null);
                if($base===null||$day<self::plus($base,$ch==='assembly'?42:28))continue;
                $kind='reading';$stage='reading-'.$ch;$data=['chamber'=>$ch,'version'=>'standard','text_sha'=>$sha,...self::ballot($s,$id,$ch),'amendment_certified'=>false,'reading_delay_certified'=>true];
            }elseif($b['status']==='adopted'&&$b['final_version']==='standard'){
                if($b['transmitted_on']===null&&$day>$b['adopted_on']){$kind='transmit';$stage='transmit';$data=['text_sha'=>$sha];}
                elseif($b['transmitted_on']!==null&&$b['review']==='none'&&$day>$b['transmitted_on']){$kind='refer_review';$stage='review-referral';$data=['referral_certified'=>true];}
                elseif($b['review']==='pending'&&$day>=self::plus($b['review_on'],15)){$kind='review_result';$stage='review-result';$data=['constitutional'=>true,'text_sha'=>$sha];}
                elseif($b['review']==='cleared'&&$day>=self::plus($b['review_on'],$b['review_days']+1)){$kind='promulgate';$stage='promulgate';$data=['text_sha'=>$sha,'presidential_act_certified'=>true];}
            }elseif($b['status']==='promulgated'&&$b['final_version']==='standard'&&$b['review']==='cleared'&&$day>$b['promulgated_on']){
                $kind='publish';$stage='publish';$data=['text_sha'=>$sha,'effective_on'=>self::plus($day,self::transition($b['policy_id'])),'publication_certified'=>true];
            }
            if($kind===null)continue;
            $e=['world_id'=>$s['world_id'],'country'=>'FR','day'=>$day,'evidence_id'=>'ns-auto-'.substr(hash('sha256',$s['world_id'].'|'.$id.'|'.$stage),0,32),
                'bill_id'=>$id,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
            $l=BicameralLegislation::restore($s['legislation']);
            try{$l->certify($e);}catch(\DomainException|\InvalidArgumentException $err){continue;}
            $s['legislation']=$l->snapshot();
        }
        return $s;
    }
}
