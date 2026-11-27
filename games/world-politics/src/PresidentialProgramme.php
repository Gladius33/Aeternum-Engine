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
    private const EXT=[
        'cabinet'=>['none'=>'n','civic'=>'c','social'=>'s','liberal'=>'l','independent'=>'i'],
        'revision'=>['none'=>'n','environment-report'=>'e','institutional-report'=>'i','remove-presidential-term-limit'=>'t'],
        'ratification'=>['congress'=>'c','referendum'=>'r'],
        'industry'=>['none'=>'n','active'=>'a','paused'=>'p'],
        'commitments'=>['none'=>'n','request_deferral'=>'d','request_termination'=>'t'],
    ];
    /** Explicit stable measure IDs, never inferred from a person's name or
     * political text. Import is a deliberate player configuration command. */
    public static function measureCatalog():array
    {
        $m=['education-reform'=>['field'=>'education','value'=>true,'route'=>'ordinary_legislation'],
            'administration-report'=>['field'=>'administration','value'=>true,'route'=>'regulatory_act']];
        foreach(['maintain','reduce','increase'] as $v)$m['annual-defense-'.$v]=['field'=>'defense','value'=>$v,'route'=>'annual_finance'];
        foreach(['reduce','increase'] as $v)$m['corrective-health-'.$v]=['field'=>'health','value'=>$v,'route'=>'corrective_finance'];
        foreach(['maintain','reduce','increase','stop'] as $v)$m['ukraine-aid-'.$v]=['field'=>'ukraine_aid','value'=>$v,'route'=>'discretionary_aid'];
        foreach(['civic','social','liberal','independent'] as $v)$m['cabinet-'.$v]=['field'=>'cabinet','value'=>$v,'route'=>'vacancy_appointment'];
        foreach(['environment-report','institutional-report','remove-presidential-term-limit'] as $v)
            foreach(['congress','referendum'] as $route)$m['revision-'.$v.'-'.$route]=['field'=>'revision','value'=>$v,'ratification'=>$route,'route'=>'article89'];
        foreach(['active','paused'] as $v)$m['industry-'.$v]=['field'=>'industry','value'=>$v,'route'=>'industrial_pilot'];
        foreach(['request_deferral','request_termination'] as $v)$m['commitment-'.str_replace('_','-',$v)]=['field'=>'commitments','value'=>$v,'route'=>'bilateral_negotiation'];
        return $m;
    }
    public static function compileMeasures(array $ids):array
    {
        if(!array_is_list($ids)||count($ids)>count(self::measureCatalog()))throw new \InvalidArgumentException('Invalid measure list');
        $a=['education'=>false,'administration'=>false,'defense'=>'none','health'=>'none','ukraine_aid'=>'none',
            'cabinet'=>'none','revision'=>'none','ratification'=>'congress','industry'=>'none','commitments'=>'none'];
        $catalog=self::measureCatalog();$used=[];
        foreach($ids as $id){
            if(!is_string($id)||!isset($catalog[$id]))throw new \InvalidArgumentException('Measure has no reviewed execution mapping');
            $m=$catalog[$id];$key=$m['field'];
            if(isset($used[$key]))throw new \InvalidArgumentException('Duplicate or conflicting programme objective');
            $used[$key]=true;$a[$key]=$m['value'];
            if(isset($m['ratification']))$a['ratification']=$m['ratification'];
        }
        return $a;
    }
    public static function measureSupport(string $id):array
    {
        $catalog=self::measureCatalog();
        if(isset($catalog[$id]))return ['status'=>'reviewed_objective','mapping'=>$catalog[$id]];
        if(preg_match('/^(?:budget-(20[0-9]{2})-defense-(maintain|reduce|increase)|corrective-(20[0-9]{2})-health-(reduce|increase))$/D',$id,$m)){
            $annual=$m[1]!=='';
            $year=(int)($annual?$m[1]:$m[3]);
            if($year>=($annual?2028:2027)&&$year<=FinanceBudget::LAST_YEAR)
                return ['status'=>'dated_policy_requires_explicit_timing','mapping'=>null];
        }
        return ['status'=>'no_reviewed_mapping','mapping'=>null];
    }
    public static function encodeAgenda(array $a):string
    {
        $required=['administration','defense','education','health','ukraine_aid'];
        $keys=array_keys($a);
        if(array_diff($required,$keys)!==[]||array_diff($keys,[...$required,...array_keys(self::EXT)])!==[]||!is_bool($a['education'])||!is_bool($a['administration']))
            throw new \InvalidArgumentException('Invalid agenda fields');
        $budget=['none'=>'n','maintain'=>'m','reduce'=>'r','increase'=>'i'];
        $health=['none'=>'n','reduce'=>'r','increase'=>'i'];
        $aid=['none'=>'n','maintain'=>'m','reduce'=>'r','increase'=>'i','stop'=>'s'];
        foreach(['defense'=>$budget,'health'=>$health,'ukraine_aid'=>$aid] as $k=>$options)
            if(!is_string($a[$k])||!isset($options[$a[$k]]))throw new \InvalidArgumentException('Unknown agenda objective');
        $base='custom-'.(int)$a['education'].(int)$a['administration'].'-'.$budget[$a['defense']].'-'.$health[$a['health']].'-'.$aid[$a['ukraine_aid']];
        if(array_intersect($keys,array_keys(self::EXT))===[])return $base;
        $suffix='';
        foreach(self::EXT as $k=>$options){
            $v=$a[$k]??array_key_first($options);
            if(!is_string($v)||!isset($options[$v]))throw new \InvalidArgumentException('Unknown extended objective');
            $suffix.=$options[$v];
        }
        if(($a['revision']??'none')==='none'&&($a['ratification']??'congress')!=='congress')throw new \InvalidArgumentException('Ratification without revision');
        return $base.'-'.$suffix;
    }
    public static function agenda(string $p):?array
    {
        if(!preg_match('/^custom-([01])([01])-([nmri])-([nri])-([nmris])(?:-([ncsli])([neit])([cr])([nap])([ndt]))?$/D',$p,$m))return null;
        if(isset($m[6])&&$m[7]==='n'&&$m[8]!=='c')return null;
        $values=['n'=>'none','m'=>'maintain','r'=>'reduce','i'=>'increase','s'=>'stop'];
        $a=['education'=>$m[1]==='1','administration'=>$m[2]==='1','defense'=>$values[$m[3]],'health'=>$values[$m[4]],'ukraine_aid'=>$values[$m[5]]];
        if(isset($m[6])){$i=6;foreach(self::EXT as $k=>$options)$a[$k]=array_flip($options)[$m[$i++]];}
        return $a;
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
    public static function industryAction(string $profile,string $mode):?string
    {
        $v=self::agenda($profile)['industry']??'none';
        return match($v.'|'.$mode){
            'active|not_started'=>'start-industrial-pilot','active|paused'=>'resume-industrial-pilot',
            'paused|active'=>'pause-industrial-pilot',default=>null};
    }
    public static function commitmentAction(string $profile):?string
    {
        $v=self::agenda($profile)['commitments']??'none';return $v==='none'?null:$v;
    }
    public static function objectiveId(string $world,string $mandate,string $objective,string $key):string
    {return 'pres-objective-'.substr(hash('sha256',$world.'|'.$mandate.'|'.$objective.'|'.$key),0,32);}
    /** Only explicit fictional appointments and reviewed Article 89 templates.
     * The request is distinct from the appointment or constitutional result. */
    public static function institutionalObjective(InstitutionalSimulation $sim,string $profile,string $mandate):?array
    {
        $a=self::agenda($profile);$s=$sim->snapshot();$world=$s['world_id'];$day=$s['day'];
        if($a===null||$s['country_code']!=='FR'||$s['legislature']!=='sitting'||$mandate>$day)return null;
        $cab=$s['legislation']['responsibility']['cabinet'];
        $aff=$a['cabinet']??'none';
        if($aff!=='none'&&in_array($cab['status'],['caretaker','resignation_required'],true)){
            $id=self::objectiveId($world,$mandate,'cabinet',(string)$cab['generation']);
            if(!isset($s['cabinet_requests'][$id])){
                try{$r=$sim->decide('scenario_president_requester','request_cabinet_appointment',['candidate'=>'pm-'.$aff],$id);
                    return ['stage'=>'cabinet_requested','id'=>$id,'result'=>$r];}
                catch(\DomainException|\InvalidArgumentException $e){/* pending or unavailable */}
            }
        }
        // Fill vacancies only in a cabinet actually appointed by this
        // programme. Never remove an incumbent minister or create a vacancy.
        if($aff!=='none'&&$cab['status']==='in_office'&&$cab['prime_minister']==='pm-'.$aff){
            $prior=self::objectiveId($world,$mandate,'cabinet',(string)($cab['generation']-1));
            if(($s['cabinet_requests'][$prior]['status']??null)==='appointed'){
                foreach($cab['ministers'] as $office=>$person){
                    if($person!==null)continue;
                    $id=self::objectiveId($world,$mandate,'portfolio',$cab['generation'].'-'.$office);
                    foreach($s['events'] as $event)if($event['id']===$id)continue 2;
                    $e=['world_id'=>$world,'country'=>'FR','day'=>$day,'evidence_id'=>$id,'bill_id'=>null,
                        'kind'=>'assembly_cabinet_portfolio','procedure_certified'=>true,'data'=>[
                            'at'=>$day.'T23:59:00Z','prime_minister'=>$cab['prime_minister'],'president'=>$cab['president'],
                            'portfolio'=>$office,'person'=>$aff.'-'.$office,'pm_proposal_certified'=>true,
                            'presidential_act_certified'=>true,'incompatibilities_cleared'=>true]];
                    try{$r=$sim->legislation('legislation_clerk','legislation_evidence',$e,$id);
                        return ['stage'=>'portfolio_appointed','id'=>$id,'result'=>$r];}
                    catch(\DomainException|\InvalidArgumentException $err){return null;}
                }
            }
        }
        $template=$a['revision']??'none';
        if($template!=='none'&&$cab['status']==='in_office'){
            $id=self::objectiveId($world,$mandate,'revision',$template);
            foreach($s['revision']['procedures'] as $proc)if($proc['template']===$template)return null;
            foreach($s['revision_requests'] as $r)if(!in_array($r['status'],['effective','rejected','declined','superseded'],true))return null;
            try{$r=$sim->revision('scenario_revision_author','revision_draft',['template'=>$template,'ratification_route'=>$a['ratification']],$id);
                return ['stage'=>'revision_requested','id'=>$id,'result'=>$r];}
            catch(\DomainException|\InvalidArgumentException $e){return null;}
        }
        return null;
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
