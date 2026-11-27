<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/InstitutionalSimulation.php';

/** WP114: closed, fictional executable programme catalogue. The president
 * requests measures; the independent legal aggregates decide their outcomes.
 * No free-text inference, fabricated client evidence or direct enactment. */
final class PresidentialProgramme
{
    public const VERSION='wp114-presidential-programme-v1';
    public const PROFILES=['manual','education','administration','education-law','administration-law','governance','governance-reduce','governance-increase'];
    /** Compact canonical agenda code fits the existing 24-byte profile column.
     * Immutable action rows retain the exact configuration that produced them. */
    public static function encodeAgenda(array $a):string
    {
        $keys=array_keys($a);sort($keys);
        if($keys!==['administration','defense','education','health','ukraine_aid']||!is_bool($a['education'])||!is_bool($a['administration']))
            throw new \InvalidArgumentException('Invalid agenda fields');
        $budget=['none'=>'n','maintain'=>'m','reduce'=>'r','increase'=>'i'];
        $health=['none'=>'n','reduce'=>'r','increase'=>'i'];
        $aid=['none'=>'n','maintain'=>'m','reduce'=>'r','increase'=>'i','stop'=>'s'];
        foreach(['defense'=>$budget,'health'=>$health,'ukraine_aid'=>$aid] as $k=>$options)
            if(!is_string($a[$k])||!isset($options[$a[$k]]))throw new \InvalidArgumentException('Unknown agenda objective');
        return 'custom-'.(int)$a['education'].(int)$a['administration'].'-'.$budget[$a['defense']].'-'.$health[$a['health']].'-'.$aid[$a['ukraine_aid']];
    }
    public static function agenda(string $p):?array
    {
        if(!preg_match('/^custom-([01])([01])-([nmri])-([nri])-([nmris])$/D',$p,$m))return null;
        $values=['n'=>'none','m'=>'maintain','r'=>'reduce','i'=>'increase','s'=>'stop'];
        return ['education'=>$m[1]==='1','administration'=>$m[2]==='1','defense'=>$values[$m[3]],'health'=>$values[$m[4]],'ukraine_aid'=>$values[$m[5]]];
    }
    public static function executable(string $p):bool
    {return self::agenda($p)!==null||in_array($p,['education-law','administration-law','governance','governance-reduce','governance-increase'],true);}
    public static function known(string $p):bool
    {return in_array($p,self::PROFILES,true)||self::agenda($p)!==null;}
    private static function plus(string $d,int $n):string
    {return (new \DateTimeImmutable($d))->modify('+'.$n.' days')->format('Y-m-d');}
    private static function stance(string $profile):?string
    {
        $a=self::agenda($profile);
        if($a!==null)return $a['defense']==='none'?null:$a['defense'];
        return match($profile){'governance'=>'maintain','governance-reduce'=>'reduce','governance-increase'=>'increase',default=>null};
    }
    public static function aidAction(string $profile):?string
    {
        $a=self::agenda($profile);
        return $a===null||$a['ukraine_aid']==='none'?null:'ukraine-aid-'.$a['ukraine_aid'];
    }
    public static function permits(string $profile,string $id):bool
    {
        if(!self::known($profile))return false;
        $a=self::agenda($profile);
        if($a!==null){
            if($id==='education-reform')return $a['education'];
            if($id==='administration-report')return $a['administration'];
            if(preg_match('/^corrective-(20[0-9]{2})-health-(reduce|increase)$/D',$id,$m))
                return (int)$m[1]>=2027&&(int)$m[1]<=FinanceBudget::LAST_YEAR&&$m[2]===$a['health'];
        }
        if($id==='education-reform')return in_array($profile,['education','education-law','governance','governance-reduce','governance-increase'],true);
        if($id==='administration-report')return in_array($profile,['administration','administration-law','governance','governance-reduce','governance-increase'],true);
        return self::stance($profile)!==null&&preg_match('/^budget-(20[0-9]{2})-defense-(maintain|reduce|increase)$/D',$id,$m)===1
            &&(int)$m[1]>=2028&&(int)$m[1]<=FinanceBudget::LAST_YEAR&&$m[2]===self::stance($profile);
    }
    /** At most one new proposal per tick. Existing user proposals are not
     * adopted by this planner and a previously filed annual budget excludes
     * any competing variant in the same year. */
    public static function nextProposal(string $profile,string $day,array $policies):?array
    {
        if(!self::known($profile))throw new \DomainException('Unknown programme');
        foreach(['education-reform','administration-report'] as $id){
            if(self::permits($profile,$id)&&!isset($policies[$id])){
                $lead=!self::executable($profile)?90:($id==='education-reform'?120:14);
                return ['policy_id'=>$id,'effective_day'=>self::plus($day,$lead)];
            }
        }
        $a=self::agenda($profile);$current=(int)substr($day,0,4);
        if($a!==null&&$a['health']!=='none'&&$current>=2027&&$current<=FinanceBudget::LAST_YEAR&&self::plus($day,45)<$current.'-10-01'){
            $conflict=false;foreach(array_keys($policies) as $id)if(str_starts_with($id,'corrective-'.$current.'-'))$conflict=true;
            if(!$conflict)return ['policy_id'=>'corrective-'.$current.'-health-'.$a['health'],'effective_day'=>$current.'-10-01'];
        }
        $stance=self::stance($profile);$year=$current+1;
        if($stance===null||$year<2028||$year>FinanceBudget::LAST_YEAR||self::plus($day,45)>=$year.'-01-01')return null;
        foreach(array_keys($policies) as $id)if(str_starts_with($id,'budget-'.$year.'-'))return null;
        return ['policy_id'=>'budget-'.$year.'-defense-'.$stance,'effective_day'=>$year.'-01-01'];
    }
    public static function validAction(array $a):bool
    {
        if(!self::permits($a['programme'],$a['policy_id'])||$a['effective_day']<=$a['decision_day'])return false;
        if(FinanceBudget::isCorrective($a['policy_id']))return $a['effective_day']===substr($a['policy_id'],11,4).'-10-01'
            &&substr($a['decision_day'],0,4)===substr($a['policy_id'],11,4)
            &&self::plus($a['decision_day'],45)<$a['effective_day'];
        if(str_starts_with($a['policy_id'],'budget-'))return $a['effective_day']===substr($a['policy_id'],7,4).'-01-01'
            &&(int)substr($a['decision_day'],0,4)+1===(int)substr($a['policy_id'],7,4)
            &&self::plus($a['decision_day'],45)<$a['effective_day'];
        // Keep the old proposal-only presets and their historical 90-day lead.
        $lead=!self::executable($a['programme'])?90:($a['policy_id']==='education-reform'?120:14);
        return self::plus($a['decision_day'],$lead)===$a['effective_day'];
    }
    private static function id(string $world,array $a,string $stage):string
    {return 'pres-programme-'.substr(hash('sha256',self::VERSION.'|'.$world.'|'.$a['policy_id'].'|'.$a['decision_day'].'|'.$stage),0,32);}
    private static function ballot(array $s,string $bill,string $chamber):array
    {
        $a=$s['legislation']['responsibility']['assembly'];
        $agents=$chamber==='assembly'?$a['deputies']:$s['senate']['agents'];
        $v=['yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0];
        foreach($agents as $key=>$m){
            $alignment=$chamber==='assembly'?($a['groups'][$m['group']]['alignment']??'nonaligned'):'nonaligned';
            $threshold=match($alignment){'government'=>7200,'opposition'=>3800,default=>5600};
            $h=hexdec(substr(hash('sha256',self::VERSION.'|'.$s['world_id'].'|'.$bill.'|'.$chamber.'|'.$key),0,8));
            if($h%10000>=$m['attendance_bps'])continue;
            $draw=($h>>14)%10000;$v[$draw<500?'abstain':($draw<$threshold?'yes':'no')]++;
        }
        return $v;
    }
    /** One legal step on the prior committed date. Ordinary parliamentary
     * disagreement or a missing competent cabinet blocks progress, not
     * the world clock. An accepted presidential request starts a separate
     * institutional process which may continue independently of its sponsor. */
    public static function advanceOne(InstitutionalSimulation $sim,array $a,array $proposal):?array
    {
        $s=$sim->snapshot();$day=$s['day'];$world=$s['world_id'];
        if($s['country_code']!=='FR'||!self::validAction($a)||!self::executable($a['programme'])
            ||$proposal['proposed_on']!==$a['decision_day']||$proposal['effective_on']!==$a['effective_day'])
            throw new \DomainException('Unverified programme proposal');
        if($proposal['authorized_on']!==null||$a['decision_day']>=$day||$a['effective_day']<=$day
            ||$s['legislature']!=='sitting'||!isset($s['senate'],$s['legislation']['responsibility']['cabinet']))return null;
        $cab=$s['legislation']['responsibility']['cabinet'];
        if($cab['status']!=='in_office')return null;
        $p=$a['policy_id'];$l=$s['legislation'];$effective=$a['effective_day'];
        $kind=null;$bill=null;$data=[];$stage=null;
        if(str_starts_with($p,'budget-')||FinanceBudget::isCorrective($p)){
            $corrective=FinanceBudget::isCorrective($p);$year=(int)substr($p,$corrective?11:7,4);
            if($corrective&&!FinanceBudget::eligibleCorrective($l['finance'],$year))return null;
            foreach($s['finance_requests']??[] as $r)if($r['year']===$year&&FinanceBudget::isCorrective($r['policy_id'])===$corrective)return null;
            foreach($l['finance']['bills'] as $b)if(str_starts_with($b['policy_id'],($corrective?'corrective-':'budget-').$year.'-'))return null;
            if(self::plus($day,30)>=$effective)return null;
            $id=self::id($world,$a,'budget-request');
            try{$r=$sim->decide('scenario_president_requester',$corrective?'request_corrective_budget':'request_annual_budget',['policy_id'=>$p],$id);}
            catch(\DomainException|\InvalidArgumentException $e){return null;}
            return ['policy_id'=>$p,'stage'=>'budget_requested','id'=>$id,'result'=>$r];
        }
        if($p==='administration-report'){
            $id=self::id($world,$a,'sign');$act=$l['executive']['acts'][$id]??null;
            foreach($l['executive']['acts'] as $other)if($other['policy_id']===$p&&$other['id']!==$id)return null;
            if($act===null){
                $kind='executive_sign';$stage='sign';
                $data=['template'=>'reporting-decree','kind'=>'pm_decree','habilitation'=>null,'signer'=>$cab['prime_minister'],
                    'prime_minister'=>$cab['prime_minister'],'minister'=>$cab['ministers']['economy'],
                    'council_deliberated'=>false,'state_council_consulted'=>false,'procedure_reviewed'=>true];
            }elseif($act['status']==='signed'&&$day>$act['signed_on']){
                $kind='executive_publish';$stage='publish';
                $data=['act_id'=>$id,'text_sha'=>$act['text_sha'],'publication_certified'=>true,'effective_on'=>$effective];
            }
        }elseif($p==='education-reform'){
            $bill=self::id($world,$a,'draft');$b=$l['bills'][$bill]??null;
            foreach($l['bills'] as $other)if($other['policy_id']===$p&&$other['id']!==$bill)return null;
            if($b===null){
                try{$r=$sim->legislation('scenario_bill_author','legislation_draft',['policy_id'=>$p],$bill);}
                catch(\DomainException|\InvalidArgumentException $e){return null;}
                return ['policy_id'=>$p,'stage'=>'bill_drafted','id'=>$bill,'result'=>$r];
            }
            $sha=$b['texts']['standard'];
            if($b['status']==='draft'){
                $kind='file';$stage='file';
                $data=['government_project'=>true,'accelerated'=>false,'joint_opposition'=>false,'filing_certified'=>true,'prime_minister_id'=>$cab['prime_minister']];
            }elseif($b['status']==='navette'&&!$b['accelerated']&&$b['cmp']===null){
                $ch=$b['next_chamber'];
                // No repeated votes to manufacture a majority. Disagreement
                // remains a pending parliamentary issue for human negotiation.
                if($b['readings'][$ch]!==0||($ch==='senate'&&!($b['last_votes']['assembly']['vote']['passed']??false)))return null;
                $from=$ch==='assembly'?$b['filed_on']:($b['last_votes']['assembly']['day']??null);
                if($from===null||$day<self::plus($from,$ch==='assembly'?42:28))return null;
                $kind='reading';$stage='reading-'.$ch;
                $data=['chamber'=>$ch,'version'=>'standard','text_sha'=>$sha,...self::ballot($s,$bill,$ch),'amendment_certified'=>false,'reading_delay_certified'=>true];
            }elseif($b['status']==='adopted'&&$b['final_version']==='standard'){
                if($b['transmitted_on']===null&&$day>$b['adopted_on']){
                    $kind='transmit';$stage='transmit';$data=['text_sha'=>$sha];
                }elseif($b['transmitted_on']!==null&&$b['review']==='none'&&$day>$b['transmitted_on']){
                    $kind='refer_review';$stage='review-referral';$data=['referral_certified'=>true];
                }elseif($b['review']==='pending'&&$day>=self::plus($b['review_on'],15)){
                    // The only reviewed text is the pinned, fictional
                    // standard education template, not an arbitrary law.
                    $kind='review_result';$stage='review-result';$data=['constitutional'=>true,'text_sha'=>$sha];
                }elseif($b['review']==='cleared'&&$day>=self::plus($b['review_on'],$b['review_days']+1)){
                    $kind='promulgate';$stage='promulgate';$data=['text_sha'=>$sha,'presidential_act_certified'=>true];
                }
            }elseif($b['status']==='promulgated'&&$b['final_version']==='standard'&&$b['review']==='cleared'&&$day>$b['promulgated_on']){
                $kind='publish';$stage='publish';$data=['text_sha'=>$sha,'effective_on'=>$effective,'publication_certified'=>true];
            }
        }
        if($kind===null||$stage===null)return null;
        $id=self::id($world,$a,$stage);
        $e=['world_id'=>$world,'country'=>'FR','day'=>$day,'evidence_id'=>$id,'bill_id'=>$bill,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
        try{$r=$sim->legislation('legislation_clerk','legislation_evidence',$e,$id);}
        catch(\DomainException|\InvalidArgumentException $err){return null;}
        return ['policy_id'=>$p,'stage'=>$stage,'id'=>$id,'result'=>$r];
    }
}
