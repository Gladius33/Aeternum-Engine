<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/SocialProgramme.php';

/** Reproducible representative-cohort scenario, not statutory payroll or
 * individual pension advice. Observed population/service anchors are
 * combined with explicitly fictional participation and take-up assumptions.
 * All cash amounts are EUR cents; fiscal changes are incremental. */
final class SocialEconomy
{
    public const VERSION='wp114f-h-social-economy-v1';
    public const ASSUMPTIONS=[
        'reference_retirement_age_months'=>768,'reference_net_smic_minor'=>142600,
        'reference_workweek_hours'=>35,'employee_rate_bps'=>2200,'employer_rate_bps'=>3000,
        'contribution_relief_bps'=>500,'wage_floor_share_bps'=>1500,'incentive_takeup_bps'=>3000,
        'long_career_share_bps'=>2000,'arduous_share_bps'=>1000,'retirement_takeup_bps'=>6500,
        'employment_participation_bps'=>7500,'job_guarantee_takeup_bps'=>2000,
        'targeted_job_share_bps'=>3500,'apprentice_share_bps'=>300,
        'apprentice_base_smic_bps'=>6000,'sme_hiring_share_bps'=>1000,'annual_hiring_rate_bps'=>800,
        'occupational_health_claims_per_100000'=>50,'annual_claim_minor'=>600000,
        'job_service_annual_cost_minor'=>20000000000,'job_service_placement_bps'=>200,
        'worker_representation_reference_bps'=>1000,'sector_bargaining_takeup_bps'=>2500
    ];
    private static function scale(int $v,int $bp):int{return intdiv($v*$bp,10000);}
    private static function limit(int $v,int $lo,int $hi):int{return max($lo,min($hi,$v));}
    /** Fictional accrual calculation for a representative career; no claim
     * to reproduce actual French rules for special schemes or minimums. */
    public static function pension(array $person,array $target):array
    {
        foreach(['age_months','entry_age_months','contributed_months','reference_monthly_gross_minor'] as $k)
            if(!is_int($person[$k]??null)||$person[$k]<0)throw new \InvalidArgumentException('Invalid career parameter');
        if($person['age_months']>1440||$person['entry_age_months']>$person['age_months']
            ||$person['contributed_months']>$person['age_months']-$person['entry_age_months']||$person['reference_monthly_gross_minor']>100000000
            ||!is_bool($person['arduous']??null))throw new \InvalidArgumentException('Invalid career history');
        $age=($target['age']??64)*12;
        if(isset($target['arduous_age'])&&$person['arduous'])$age=$target['arduous_age']*12;
        $years=$target['contribution_years']??43;$required=$years*12;
        $special=!isset($target['entry_age_before'])||($person['entry_age_months']<$target['entry_age_before']*12&&$person['contributed_months']>=$required);
        if(!$special){$age=768;$required=516;}
        $eligible=$person['age_months']>=$age;
        $accrual=min(10000,intdiv($person['contributed_months']*10000,$required));
        $monthly=$eligible?self::scale(intdiv($person['reference_monthly_gross_minor'],2),$accrual):0;
        return ['eligible'=>$eligible,'threshold_months'=>$age,'required_contribution_months'=>$required,'accrual_bps'=>$accrual,
            'monthly_pension_minor'=>$monthly,'classification'=>'representative_fictional_career'];
    }
    public static function payroll(int $net,int $employeeRate=2200,int $employerRate=3000):array
    {
        if($net<0||$net>100000000||$employeeRate<0||$employeeRate>=10000||$employerRate<0||$employerRate>10000)throw new \InvalidArgumentException('Invalid payroll');
        $gross=intdiv($net*10000+9999-$employeeRate,10000-$employeeRate);
        $employer=self::scale($gross,$employerRate);
        return ['net_minor'=>$net,'gross_minor'=>$gross,'employee_contribution_minor'=>$gross-$net,
            'employer_contribution_minor'=>$employer,'employer_cost_minor'=>$gross+$employer];
    }
    private static function agePopulation(array $cohorts,int $from,int $to):int
    {
        $n=0;
        foreach($cohorts as $key=>$count){
            if(!preg_match('~^(female|male)/([0-9]{2})-([0-9]{2})$~D',$key,$m))continue;
            $lo=(int)$m[2];$hi=(int)$m[3]+1;
            $overlap=max(0,min($to,$hi)-max($from,$lo));
            $n+=intdiv($count*$overlap,$hi-$lo);
        }
        return $n;
    }
    /** Prior-day anchors; no dependence on today's not-yet-calculated GDP.
     * Multiple active laws may affect distinct families only. */
    public static function calculate(array $active,array $policies,string $day,array $population,array $services,array $macro,?array $prior,array $sensitivity=[]):array
    {
        // Sensitivity is an offline analytical tool. The authoritative
        // daily clock never passes this parameter, preserving replay.
        $a=self::ASSUMPTIONS;
        foreach($sensitivity as $key=>$value){
            if(!in_array($key,['employment_participation_bps','job_guarantee_takeup_bps','retirement_takeup_bps','long_career_share_bps','arduous_share_bps','wage_floor_share_bps','incentive_takeup_bps','apprentice_share_bps','sme_hiring_share_bps','annual_hiring_rate_bps','targeted_job_share_bps','job_service_placement_bps','sector_bargaining_takeup_bps'],true)
                ||!is_int($value)||$value<0||$value>10000)throw new \InvalidArgumentException('Unreviewed sensitivity parameter');
            $a[$key]=$value;
        }
        $cohorts=$prior['population']['cohorts']??$population['opening_cohorts'];
        $ind=$prior['population']['indicators']??$population['opening_indicators'];
        $u=self::limit($prior['macro']['unemployment_bps']??$macro['initial_unemployment_bps'],0,3000);
        $labour=self::scale($ind['age_20_64'],$a['employment_participation_bps']);
        $unemployed=self::scale($labour,$u);$employed=$labour-$unemployed;
        $recipients=$services['pension_recipients'];$pensionDaily=$services['baseline_daily_public_minor']['pensions'];
        $referencePension=intdiv($pensionDaily*365,max(1,$recipients)*12);
        $smic=$a['reference_net_smic_minor'];$hours=$a['reference_workweek_hours'];
        $netFloor=$smic;
        foreach($active as $id){$d=SocialProgramme::definition($id);if(($d['family']??null)==='wage-floor'&&!SocialProgramme::isRepeal($id)){$t=$d['target'];$netFloor=max($smic,isset($t['increase_bps'])?self::scale($smic,10000+$t['increase_bps']):(isset($t['monthly_increase_eur'])?$smic+$t['monthly_increase_eur']*100:($t['monthly_net_eur']??$t['monthly_floor_eur'])*100));}}
        $tax=0;$benefits=0;$pensionTransfer=0;$production=0;$employment=0;$wage=0;$details=[];
        foreach($active as $id){
            $d=SocialProgramme::definition($id);if($d===null)continue;
            if(SocialProgramme::isRepeal($id))continue;
            $t=$d['target'];$family=$d['family'];
            $r=['id'=>$id,'family'=>$family,'target'=>$t,'daily_revenue_delta_minor'=>0,'daily_expense_delta_minor'=>0,'affected_persons_proxy'=>0];
            if($family==='pension'){
                $age=$t['age'];$coverage=isset($t['entry_age_before'])?$a['long_career_share_bps']:10000;
                $elapsed=(int)(new \DateTimeImmutable($policies[$id]['effective_on']))->diff(new \DateTimeImmutable($day))->days;
                $phase=SocialProgramme::phaseBps($id,$elapsed);
                $people=self::scale(self::scale(self::agePopulation($cohorts,min(64,$age),max(64,$age)),$coverage),$a['retirement_takeup_bps']);
                if(isset($t['arduous_age']))$people+=self::scale(self::scale(self::agePopulation($cohorts,$t['arduous_age'],$age),$a['arduous_share_bps']),$a['retirement_takeup_bps']);
                $people=self::scale($people,$phase)*($age>64?-1:1);
                $cash=intdiv($people*$referencePension*12,365);
                $r['affected_persons_proxy']=abs($people);$r['additional_recipients_proxy']=$people;
                $r['daily_expense_delta_minor']=$cash;$pensionTransfer+=$cash;
                $r['daily_revenue_delta_minor']=-intdiv($people*self::payroll($smic)['employee_contribution_minor']*12,365);
                $employment-=intdiv($people*10000,max(1,$employed));
                $r['reference_monthly_pension_minor']=$referencePension;$r['phase_bps']=$phase;
            }elseif($family==='wage-floor'){
                $netFloor=max($smic,isset($t['increase_bps'])?self::scale($smic,10000+$t['increase_bps']):(isset($t['monthly_increase_eur'])?$smic+$t['monthly_increase_eur']*100:($t['monthly_net_eur']??$t['monthly_floor_eur'])*100));
                $covered=self::scale($employed,$a['wage_floor_share_bps']);$old=self::payroll($smic);$new=self::payroll($netFloor);
                $monthly=$netFloor-$smic;$r['affected_persons_proxy']=$covered;
                $r['monthly_net_floor_minor']=$netFloor;$r['monthly_payroll']=$new;
                $wage+=self::scale(intdiv($monthly*10000,$smic),intdiv($covered*10000,max(1,$employed)));
                // Payroll revenue feedback is already generated by the macro
                // wage channel; do not count this indicative cash a second time.
                $r['indicative_daily_contribution_gain_minor']=intdiv($covered*($new['employee_contribution_minor']+$new['employer_contribution_minor']-$old['employee_contribution_minor']-$old['employer_contribution_minor'])*12,365);
                if(isset($t['scope'])){
                    $delta=max(0,$netFloor-$referencePension);
                    $pensionCash=intdiv(self::scale($recipients,2500)*$delta*12,365);
                    $otherCash=intdiv(self::scale($unemployed,5000)*max(0,$netFloor-90000)*12,365);
                    $r['daily_expense_delta_minor']=$pensionCash+$otherCash;$pensionTransfer+=$pensionCash;
                    $r['pension_floor_cash_minor']=$pensionCash;$r['benefit_floor_cash_minor']=$otherCash;
                }
            }elseif($family==='wage-incentive'){
                $covered=self::scale($employed,$a['incentive_takeup_bps']);
                $referencePay=self::scale($smic,15000);$gross=self::payroll($referencePay)['gross_minor'];
                $r['affected_persons_proxy']=$covered;$wage+=self::scale($t['increase_bps'],$a['incentive_takeup_bps']);
                $r['daily_revenue_delta_minor']=-intdiv($covered*self::scale($gross,$a['contribution_relief_bps'])*12,365);
            }elseif($family==='job-guarantee'){
                $pool=isset($t['weekly_fraction_bps'])?self::scale($unemployed,$a['targeted_job_share_bps']):$unemployed;
                $n=self::scale($pool,$a['job_guarantee_takeup_bps']);$fraction=$t['weekly_fraction_bps']??10000;
                $pay=self::payroll(self::scale($netFloor,$fraction));
                $r['affected_persons_proxy']=$n;$r['full_time_equivalent_proxy']=self::scale($n,$fraction);
                $r['daily_expense_delta_minor']=intdiv($n*$pay['employer_cost_minor']*12,365);
                $employment+=intdiv($n*10000,max(1,$labour));
            }elseif($family==='apprenticeship'){
                $n=self::scale($employed,$a['apprentice_share_bps']);$r['affected_persons_proxy']=$n;
                $r['monthly_net_target_minor']=$netFloor;$r['monthly_baseline_minor']=self::scale($smic,$a['apprentice_base_smic_bps']);
                $wage+=self::scale(intdiv(($netFloor-$r['monthly_baseline_minor'])*10000,$smic),intdiv($n*10000,max(1,$employed)));
            }elseif($family==='hiring-relief'){
                $n=self::scale(self::scale($employed,$a['annual_hiring_rate_bps']),$a['sme_hiring_share_bps']);
                $r['affected_persons_proxy']=$n;$r['maximum_eligible_hires_per_sme']=$t['first_hires'];
                $r['daily_revenue_delta_minor']=-intdiv($n*self::payroll($smic)['employer_contribution_minor']*12,365);
                $employment+=intdiv($n*10000,max(1,$labour));
            }elseif($family==='work-time'){
                $hours=$t['weekly_hours']??35;
                $r['affected_persons_proxy']=isset($t['setting'])?self::scale($employed,$a['sector_bargaining_takeup_bps']):$employed;
                $r['weekly_hours']=$hours;$r['days_per_week']=$t['days_per_week']??5;
                $r['weekly_hours_lost_proxy']=$r['affected_persons_proxy']*(35-$hours);
                $production-=intdiv($r['weekly_hours_lost_proxy']*10000,max(1,$employed*35*2));
                $employment+=intdiv($r['weekly_hours_lost_proxy']*10000,max(1,$labour*35*4));
            }elseif($family==='job-service'){
                $n=self::scale($unemployed,$a['job_service_placement_bps']);$r['affected_persons_proxy']=$n;
                $r['agency']=$t['agency'];$r['daily_expense_delta_minor']=intdiv($a['job_service_annual_cost_minor'],365);
                $employment+=intdiv($n*10000,max(1,$labour));
            }elseif($family==='occupational-health'){
                $n=intdiv($employed*$a['occupational_health_claims_per_100000'],100000);
                $r['affected_persons_proxy']=$n;$r['annual_claims_proxy']=$n;
                $r['daily_expense_delta_minor']=intdiv($n*$a['annual_claim_minor'],365);
            }elseif($family==='representation'){
                $r['affected_persons_proxy']=$employed;$r['worker_representation_bps']=$t['worker_representation_bps'];
            }elseif(PrimaryManifestoPolicy::isPolicy(SocialProgramme::objectiveId($id))){
                $v=PrimaryManifestoPolicy::calculate($d,$policies[$id],$day,$macro,$netFloor,$recipients,$labour,$employed);
                $r=array_replace($r,$v);$employment+=$v['employment_delta_bps'];$wage+=$v['wage_premium_bps'];$pensionTransfer+=$v['pension_transfer_delta_minor'];
            }else throw new \DomainException('Unimplemented social family');
            $tax+=$r['daily_revenue_delta_minor'];$benefits+=$r['daily_expense_delta_minor'];$details[]=$r;
        }
        $totalIndex=null;$sectorHires=0;$sectorCost=0;
        foreach($details as $i=>$detail){
            if($detail['family']==='public-hiring')$totalIndex=$i;
            elseif(isset($detail['planned_positions_proxy'])){$sectorHires+=$detail['planned_positions_proxy'];$sectorCost+=$detail['daily_expense_delta_minor'];}
        }
        if($totalIndex!==null&&$sectorHires>0){
            // Broad target includes these public jobs; cost the residual
            // at its own representative unit cost, not the sector costs twice.
            $r=&$details[$totalIndex];$n=min($sectorHires,$r['planned_positions_proxy']);
            $removed=intdiv($n*$r['parameters']['annual_cost_per_hire_eur']*100,365);
            $r['overlap_positions_excluded']=$n;$r['planned_positions_proxy']-=$n;$r['affected_persons_proxy']-=$n;
            $r['annual_expense_delta_eur']-=$n*$r['parameters']['annual_cost_per_hire_eur'];
            $r['daily_expense_delta_minor']-=$removed;$benefits-=$removed;
            $delta=intdiv($n*10000,max(1,$labour));$r['employment_delta_bps']-=$delta;$employment-=$delta;unset($r);
        }
        return ['version'=>self::VERSION,'classification'=>'fictional_cohort_model_observed_demography_anchors',
            'assumptions_sha'=>hash('sha256',json_encode($a,JSON_THROW_ON_ERROR)),'day'=>$day,'labour_force_proxy'=>$labour,
            'employed_proxy'=>$employed,'unemployed_proxy'=>$unemployed,'reference_net_smic_minor'=>$smic,
            'monthly_net_floor_minor'=>$netFloor,'weekly_hours'=>$hours,'daily_revenue_delta_minor'=>$tax,'daily_expense_delta_minor'=>$benefits,
            'pension_transfer_delta_minor'=>$pensionTransfer,'production_delta_bps'=>self::limit($production,-2000,2000),
            'employment_delta_bps'=>self::limit($employment,-2000,2000),'wage_premium_bps'=>self::limit($wage,0,3000),
            'measures'=>$details,'statutory_entitlements_qualified'=>false,'behavioral_calibration_qualified'=>false];
    }
}
