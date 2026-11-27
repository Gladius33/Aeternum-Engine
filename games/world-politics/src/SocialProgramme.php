<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PrimaryManifestoPolicy.php';

/** WP114e: immutable historic-text-to-fictional-policy mapping. Rates,
 * coverage shares and cost coefficients below are scenario assumptions,
 * not estimates of actual manifestos, pension entitlements or future law.
 * Each measure has an independent reviewed simulation text and effect. */
final class SocialProgramme
{
    public const VERSION='wp114e-social-v1';
    public const REFERENCE='2022-04-10';
    /** order is persistent bit allocation; never reorder or reuse */
    public static function definitions():array
    {
        // [candidate suffix, domain, mutually exclusive family, typed
        // target, tax and benefit impact bps, production/employment target
        // delta bps, worker/retired pressure delta, aggregate wage proxy bps]
        static $cached=null;if($cached!==null)return $cached;
        $rows=[
            ['emmanuel-macron-1','retirement','pension',['age'=>65,'phase_years'=>5],40,-80,40,40,30,20,0],
            ['emmanuel-macron-2','employment','job-service',['agency'=>'France Travail'],0,5,5,25,-10,0,0],
            ['marine-le-pen-1','wages','wage-incentive',['increase_bps'=>1000,'instrument'=>'contribution_relief'], -60,0,0,10,-20,0,100],
            ['marine-le-pen-2','retirement','pension',['age'=>60,'contribution_years'=>40,'entry_age_before'=>20],-10,35,-10,-10,-5,-15,0],
            ['jean-luc-melenchon-1','retirement','pension',['age'=>60],-60,120,-60,-60,-10,-30,0],
            ['jean-luc-melenchon-2','employment','job-guarantee',['target'=>'unemployed','wage'=>'smic'],5,100,25,90,-30,0,0],
            ['eric-zemmour-1','retirement','pension',['age'=>64,'target_year'=>2030],20,-40,20,20,10,10,0],
            ['eric-zemmour-2','employment','apprenticeship',['wage'=>'smic'],5,10,-5,-5,-10,0,20],
            ['valerie-pecresse-1','retirement','pension',['age'=>65,'target_year'=>2030],40,-80,40,40,30,20,0],
            ['valerie-pecresse-2','employment','work-time',['setting'=>'sector_bargaining'],0,0,10,10,0,0,0],
            ['yannick-jadot-1','wages','wage-floor',['increase_bps'=>1000],15,10,-25,-15,-25,0,150],
            ['yannick-jadot-2','employment','representation',['worker_representation_bps'=>5000],0,0,-5,0,-15,0,0],
            ['jean-lassalle-1','wages','wage-floor',['monthly_net_eur'=>1400],0,0,0,0,0,0,0],
            ['jean-lassalle-2','employment','hiring-relief',['firm'=>'sme','first_hires'=>2],-45,0,10,35,-10,0,0],
            ['fabien-roussel-1','wages','wage-floor',['monthly_net_eur'=>1500],5,4,-10,-5,-10,0,40],
            ['fabien-roussel-2','employment','work-time',['weekly_hours'=>32],-15,15,-90,30,-20,0,0],
            ['nicolas-dupont-aignan-1','wages','wage-incentive',['increase_bps'=>800,'max_smic_multiple'=>3,'instrument'=>'contribution_relief'],-50,0,0,10,-20,0,100],
            ['nicolas-dupont-aignan-2','employment','job-guarantee',['weekly_fraction_bps'=>5000,'target'=>'long_term_unemployed_and_youth'],0,55,15,45,-20,0,0],
            ['anne-hidalgo-1','wages','wage-floor',['increase_bps'=>1500],20,15,-35,-25,-30,0,225],
            ['anne-hidalgo-2','employment','occupational-health',['recognize_burnout'=>true],0,15,0,5,-15,0,0],
            ['philippe-poutou-1','employment','work-time',['weekly_hours'=>32,'days_per_week'=>4],-15,15,-90,30,-20,0,0],
            ['philippe-poutou-2','retirement','pension',['age'=>60,'arduous_age'=>55],-70,150,-70,-70,-10,-40,0],
            ['nathalie-arthaud-1','wages','wage-floor',['monthly_floor_eur'=>2000,'scope'=>['wages','pensions','benefits']],25,180,-100,-80,-40,-40,450],
            ['nathalie-arthaud-2','retirement','pension',['age'=>60],-60,120,-60,-60,-10,-30,0],
        ];
        $out=[];
        foreach($rows as $bit=>$r){
            [$suffix,$domain,$family,$target,$tax,$benefits,$production,$employment,$workers,$retired,$wage]=$r;
            $id='hist2022-'.$suffix;
            $out[$id]=['bit'=>$bit,'domain'=>$domain,'family'=>$family,'target'=>$target,
                'assumptions'=>['tax_delta_bps'=>$tax,'benefit_delta_bps'=>$benefits,'production_delta_bps'=>$production,'employment_delta_bps'=>$employment,
                'workers_pressure_delta'=>$workers,'retired_pressure_delta'=>$retired,'aggregate_wage_premium_bps'=>$wage],
                'classification'=>'fictional_uncalibrated_aggregate_proxy'];
        }
        return $cached=$out;
    }
    /** Independently sourced 2027 commitments. Reserved bits 78–93 are
     * not used by the immutable 2022 catalog or any family reset. */
    public static function target2027():array
    {
        $historical=self::definitions();$zero=array_fill_keys(array_keys(reset($historical)['assumptions']),0);
        return [
            'pf2027-jean-luc-melenchon-smic'=>['bit'=>78,'domain'=>'wages','family'=>'wage-floor',
                'target'=>['monthly_net_eur'=>1700],'assumptions'=>$zero,
                'classification'=>'fictional_2027_cohort_proxy'],
            'pf2027-jean-luc-melenchon-pension'=>['bit'=>79,'domain'=>'retirement','family'=>'pension',
                'target'=>['age'=>60,'contribution_years'=>40],'assumptions'=>$zero,
                'classification'=>'fictional_2027_cohort_proxy'],
            'pf2027-ecologistes-renovation'=>['bit'=>80,'domain'=>'housing','family'=>'renovation',
                'target'=>['annual_units'=>500000],'assumptions'=>$zero,
                'parameters'=>['reference_annual_units'=>200000,'public_cost_per_unit_eur'=>10000],
                'classification'=>'fictional_2027_cohort_proxy'],
        ];
    }
    // Stable allocation beyond the original 24 historical bits.
    private const RESET_FAMILIES=['pension','job-service','wage-incentive','job-guarantee','apprenticeship','work-time','wage-floor','representation','hiring-relief','occupational-health'];
    public static function objectives():array
    {
        static $cached=null;if($cached!==null)return $cached;
        $d=self::definitions();
        foreach(self::RESET_FAMILIES as $n=>$family){
            $id='social-repeal-'.$family;
            $d[$id]=['bit'=>24+$n,'domain'=>'social_repeal','family'=>$family,'target'=>['repeal_family'=>$family],
                'assumptions'=>array_fill_keys(array_keys(reset($d)['assumptions']),0),'classification'=>'fictional_repeal_to_scenario_reference'];
        }
        $d+=PrimaryManifestoPolicy::definitions()+self::target2027();
        // Separate reserved range for family repeals; never move earlier IDs.
        $newFamilies=['hospital-beds','childcare','social-housing','home-care','teacher-hiring','wealth-tax','health-hiring','pension-floor','eldercare-hiring','renovation','justice-hiring','disability-benefit','production-tax','training-benefit','youth-benefit','public-hiring','public-pay','agriculture-fund','culture-budget','fuel-refund','reinvestment-tax','fuel-vat','business-property-tax','rsa-activity','free-transit','pension-levy','overtime-relief','fuel-cap','wage-indexation','work-sharing','sme-procurement'];
        foreach($newFamilies as $n=>$family)$d['social-repeal-'.$family]=[
            'bit'=>96+$n,'domain'=>'social_repeal','family'=>$family,'target'=>['repeal_family'=>$family],
            'assumptions'=>array_fill_keys(array_keys(reset($d)['assumptions']),0),'classification'=>'fictional_repeal_to_scenario_reference'];
        foreach(['local-supply'=>127,'renewable-transition'=>95,'collective-ownership'=>94] as $family=>$bit)$d['social-repeal-'.$family]=[
            'bit'=>$bit,'domain'=>'social_repeal','family'=>$family,'target'=>['repeal_family'=>$family],
            'assumptions'=>array_fill_keys(array_keys(reset($d)['assumptions']),0),'classification'=>'fictional_repeal_to_scenario_reference'];
        return $cached=$d;
    }
    public static function objectiveId(string $id):string
    {
        if(preg_match('/^(.+)-r([2-9]|[1-9][0-9]{1,2})$/D',$id,$m))return $m[1];
        return $id;
    }
    public static function definition(string $id):?array{return self::objectives()[self::objectiveId($id)]??null;}
    public static function isPolicy(string $id):bool{return self::definition($id)!==null;}
    public static function isRepeal(string $id):bool{return isset(self::definition($id)['target']['repeal_family']);}
    /** Derived assumptions do not alter the immutable archived base catalog. */
    public static function assumption(string $id,array $catalog,bool $lifecycle):?array
    {
        if(isset($catalog[$id]))return $catalog[$id];
        if(!$lifecycle||!self::isPolicy($id))return null;
        $base=self::objectiveId($id);
        if(isset($catalog[$base]))return $catalog[$base];
        if(!self::isRepeal($id))return null;
        $family=self::definition($id)['family'];
        foreach(self::definitions()+PrimaryManifestoPolicy::definitions()+self::target2027() as $key=>$d){
            if($d['family']!==$family||!isset($catalog[$key]))continue;
            $v=$catalog[$key];
            foreach(['revenue_delta','expense_delta','concession_daily_cost','production_delta_bps','employment_delta_bps'] as $k)if(isset($v[$k]))$v[$k]=0;
            foreach(['pressure_delta','concessions'] as $k)if(isset($v[$k]))foreach($v[$k] as &$x)$x=0;
            unset($x);return $v;
        }
        return null;
    }
    /** A pending proposal is never duplicated, and an unsuccessful proposal
     * is not retried endlessly. A later effective replacement or repeal
     * enables a new instance of a previously attempted objective. */
    public static function nextInstance(string $base,string $day,array $policies):?string
    {
        $d=self::objectives()[$base]??null;if($d===null)return null;
        $latest=null;$latestDate=null;$max=0;
        foreach($policies as $id=>$p){
            $def=self::definition($id);if($def===null||$def['family']!==$d['family'])continue;
            if(self::objectiveId($id)===$base){
                $max=max($max,$id===$base?1:(int)substr($id,strrpos($id,'-r')+2));
                if($latestDate===null||$p['proposed_on']>$latestDate)$latestDate=$p['proposed_on'];
            }
            if($p['effective_on']>$day)return null;
        }
        // Consider a law effective today too; it is already authorized.
        $latest=self::effectiveFamilies($policies,$day)[$d['family']]??null;
        if($latest!==null&&self::objectiveId($latest)===$base)return null;
        if(self::isRepeal($base)&&($latest===null||self::isRepeal($latest)))return null;
        if($max>0&&($latest===null||$policies[$latest]['effective_on']<=$latestDate))return null;
        if($max>=999)throw new \DomainException('Social law instance limit');
        return $max===0?$base:$base.'-r'.($max+1);
    }
    public static function encode(array $ids):string
    {
        if(!array_is_list($ids)||$ids===[]||count($ids)>128)throw new \InvalidArgumentException('Invalid social objectives');
        $d=self::objectives();$families=[];$digits=array_fill(0,32,0);$max=0;
        foreach($ids as $id){
            if(!is_string($id)||!isset($d[$id])||isset($families[$d[$id]['family']]))throw new \InvalidArgumentException('Unknown or conflicting social objective');
            $families[$d[$id]['family']]=true;$bit=$d[$id]['bit'];$max=max($max,$bit);
            $digits[intdiv($bit,4)]|=1<<($bit%4);
        }
        $length=$max<24?6:($max<36?9:($max<63?16:($max<96?24:32)));
        return 'social-'.implode('',array_map('dechex',array_reverse(array_slice($digits,0,$length))));
    }
    public static function decode(string $profile):?array
    {
        if(!preg_match('/^social-([a-f0-9]{6}|[a-f0-9]{9}|[0-7][a-f0-9]{15}|[a-f0-9]{24}|[a-f0-9]{32})$/D',$profile,$m))return null;
        $digits=strrev($m[1]);$ids=[];
        foreach(self::objectives() as $id=>$d){$bit=$d['bit'];$digit=hexdec($digits[intdiv($bit,4)]??'0');
            if(($digit&(1<<($bit%4)))!==0)$ids[]=$id;}
        try{if(self::encode($ids)!==$profile)return null;}catch(\InvalidArgumentException $e){return null;}
        return $ids;
    }
    public static function effectiveDay(string $id,string $day):string
    {
        $d=self::definition($id)??throw new \InvalidArgumentException('Unknown social measure');
        // Allow ordinary readings and review; dated promises retain their
        // target-year start, not a fabricated instantaneous policy.
        $min=(new \DateTimeImmutable($day))->modify('+120 days')->format('Y-m-d');
        return isset($d['target']['target_year'])&&$d['family']!=='renewable-transition'?max($min,$d['target']['target_year'].'-01-01'):$min;
    }
    public static function legalCatalog():array
    {
        $out=[];foreach(self::objectives() as $id=>$d)$out[$id]=self::legalText($id);
        return $out;
    }
    public static function legalText(string $id):?array
    {
        $d=self::definition($id);if($d===null)return null;
        $text=json_encode(['version'=>self::VERSION,'id'=>$id,'target'=>$d['target']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        return ['standard'=>$text,'amended'=>$text.'; implementation subject to a separate reviewed instrument'];
    }
    public static function policies(array $baseline):array
    {
        $out=[];
        foreach(self::definitions()+PrimaryManifestoPolicy::definitions()+self::target2027() as $id=>$d){
            $a=$d['assumptions'];
            // Ratio to existing daily aggregates, not added observed spending.
            $scale=static fn(int $v,int $bp):int=>intdiv($v*$bp,10000);
            $out[$id]=['revenue_delta'=>$scale($baseline['daily_tax_base'],$a['tax_delta_bps']),
                'expense_delta'=>$scale($baseline['daily_social_benefits'],$a['benefit_delta_bps']),
                'pressure_delta'=>['workers'=>$a['workers_pressure_delta'],'retired'=>$a['retired_pressure_delta']],
                'duration_days'=>7305,'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,
                'production_delta_bps'=>$a['production_delta_bps'],'employment_delta_bps'=>$a['employment_delta_bps']];
        }
        return $out;
    }
    public static function conflicts(string $id,array $policies):bool
    {
        $d=self::definitions();if(!isset($d[$id]))return false;
        foreach(array_keys($policies) as $old)if($old!==$id&&isset($d[$old])&&$d[$old]['family']===$d[$id]['family'])return true;
        return false;
    }
    /** A later published, effective law supersedes an earlier law in the
     * same family. Merely selecting a new programme does not repeal a law.
     * A later expired replacement does not revive its predecessor. */
    public static function effectiveFamilies(array $policies,string $day):array
    {
        $out=[];
        foreach($policies as $id=>$p){
            $d=self::definition($id);
            if($d===null||$p['authorized_on']===null||$p['authorized_on']>=$day||$p['effective_on']>$day)continue;
            $family=$d['family'];$old=$out[$family]??null;
            if($old===null||[$p['effective_on'],$p['proposed_on'],$id]>[$policies[$old]['effective_on'],$policies[$old]['proposed_on'],$old])$out[$family]=$id;
        }
        return $out;
    }
    public static function sameEffectiveFamily(string $id,string $day,array $policies):bool
    {
        $d=self::definition($id);if($d===null)return false;
        foreach($policies as $old=>$p)if($old!==$id&&(self::definition($old)['family']??null)===$d['family']&&$p['effective_on']===$day)return true;
        return false;
    }
    public static function phaseBps(string $id,int $elapsed):int
    {
        $d=self::definition($id);
        if($d===null||!isset($d['target']['phase_years']))return 10000;
        // Five equal annual cohorts for the explicitly progressive proposal.
        return min(10000,2000*(1+intdiv(max(0,$elapsed),365)));
    }
    public static function phasedPolicy(array $policy,int $phase):array
    {
        if($phase===10000)return $policy;
        foreach(['revenue_delta','expense_delta','production_delta_bps','employment_delta_bps','concession_daily_cost'] as $k)
            if(isset($policy[$k]))$policy[$k]=intdiv($policy[$k]*$phase,10000);
        foreach($policy['pressure_delta'] as $k=>$v)$policy['pressure_delta'][$k]=intdiv($v*$phase,10000);
        return $policy;
    }
    public static function wagePremium(array $active):int
    {
        $n=0;
        foreach($active as $id)$n+=self::definition($id)['assumptions']['aggregate_wage_premium_bps']??0;
        return min(3000,$n);
    }
    public static function report(array $state):array
    {
        $rows=[];
        $active=$state['days']===[]?[]:$state['days'][array_key_last($state['days'])]['active_policies'];
        $families=isset($state['social_economy_from'])&&$state['day']>=$state['social_economy_from']?self::effectiveFamilies($state['policies'],$state['day']):[];
        foreach($state['policies'] as $id=>$p)if(($d=self::definition($id))!==null){
            $running=in_array($id,$active,true);$elapsed=$running?(int)(new \DateTimeImmutable($p['effective_on']))->diff(new \DateTimeImmutable($state['day']))->days:0;
            $rows[]=['measure_id'=>$id,'definition'=>$d,'proposal'=>$p,'active'=>$running,
                'implementation_phase_bps'=>$running?self::phaseBps($id,$elapsed):0,
                'superseded_by'=>$p['authorized_on']!==null&&$p['effective_on']<=$state['day']&&isset($families[$d['family']])&&$families[$d['family']]!==$id?$families[$d['family']]:null];
        }
        return ['version'=>self::VERSION,'classification'=>'fictional_uncalibrated_aggregate_proxy','measures'=>$rows,
            'individual_entitlements_modelled'=>false,'economic_forecast_qualified'=>false];
    }
}
