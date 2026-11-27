<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Versioned quantitative scenario routes for selected primary-source
 * commitments. Costs, take-up and coverage are explicit assumptions, not
 * official costings or an empirical prediction of political outcomes. */
final class PrimaryManifestoPolicy
{
    public const VERSION='wp114-primary-quantitative-v1';
    public static function definitions():array
    {
        static $out=null;if($out!==null)return $out;
        // Immutable allocation starting at bit 34. Do not reorder.
        $rows=[
            ['nicolas-dupont-aignan-2','health','hospital-beds',['beds'=>20000],['annual_cost_per_bed_eur'=>180000,'ramp_years'=>5]],
            ['nicolas-dupont-aignan-3','family','childcare',['places'=>250000,'years'=>5],['annual_cost_per_place_eur'=>12000]],
            ['anne-hidalgo-1','retirement','pension',['age'=>62],[]],
            ['anne-hidalgo-2','housing','social-housing',['annual_units'=>150000],['public_cost_per_unit_eur'=>40000,'reference_annual_units'=>90000]],
            ['anne-hidalgo-3','eldercare','home-care',['increase_bps'=>5000],['annual_reference_eur'=>4000000000]],
            ['anne-hidalgo-4','wages','wage-floor',['monthly_increase_eur'=>200],[]],
            ['yannick-jadot-1','wages','wage-floor',['monthly_net_eur'=>1500],[]],
            ['yannick-jadot-2','education','teacher-hiring',['hires'=>65000],['annual_cost_per_hire_eur'=>62000,'ramp_years'=>5]],
            ['yannick-jadot-3','housing','social-housing',['units'=>700000,'years'=>5],['public_cost_per_unit_eur'=>40000,'reference_annual_units'=>90000]],
            ['yannick-jadot-4','tax','wealth-tax',['annual_revenue_eur'=>15000000000],['collection_bps'=>7000]],
            ['jean-lassalle-2','health','hospital-beds',['beds'=>20000],['annual_cost_per_bed_eur'=>180000,'ramp_years'=>5]],
            ['jean-lassalle-3','health','health-hiring',['hires'=>100000],['annual_cost_per_hire_eur'=>55000,'ramp_years'=>5]],
            ['emmanuel-macron-1','retirement','pension-floor',['monthly_eur'=>1100],['eligible_share_bps'=>1500,'reference_eligible_monthly_eur'=>950]],
            ['emmanuel-macron-2','eldercare','eldercare-hiring',['hires'=>50000],['annual_cost_per_hire_eur'=>50000,'ramp_years'=>5]],
            ['emmanuel-macron-3','housing','renovation',['annual_units'=>700000],['public_cost_per_unit_eur'=>10000,'reference_annual_units'=>300000]],
            ['emmanuel-macron-4','justice','justice-hiring',['hires'=>8500,'target_year'=>2027],['annual_cost_per_hire_eur'=>65000,'ramp_years'=>1]],
            ['jean-luc-melenchon-1','wages','wage-floor',['monthly_net_eur'=>1400],[]],
            ['jean-luc-melenchon-2','retirement','pension-floor',['monthly_eur'=>1063],['eligible_share_bps'=>500,'reference_eligible_monthly_eur'=>950]],
            ['jean-luc-melenchon-3','social','disability-benefit',['monthly_eur'=>1400],['eligible_persons'=>1200000,'reference_monthly_eur'=>1000]],
            ['valerie-pecresse-1','justice','justice-hiring',['hires'=>16000],['annual_cost_per_hire_eur'=>65000,'ramp_years'=>5]],
            ['valerie-pecresse-3','tax','production-tax',['annual_reduction_eur'=>10000000000],[]],
            ['valerie-pecresse-4','social','training-benefit',['monthly_eur'=>670],['eligible_persons'=>250000,'reference_monthly_eur'=>300]],
            ['philippe-poutou-1','wages','wage-floor',['monthly_net_eur'=>1800],[]],
            ['philippe-poutou-2','social','youth-benefit',['smic_share_bps'=>7500],['eligible_persons'=>1000000,'reference_monthly_eur'=>300]],
            ['fabien-roussel-1','public_services','public-hiring',['hires'=>500000],['annual_cost_per_hire_eur'=>58000,'ramp_years'=>5]],
            ['fabien-roussel-2','wages','public-pay',['increase_bps'=>3000],['workers'=>5700000,'reference_monthly_net_eur'=>2500,'employer_cost_bps'=>16667]],
            ['fabien-roussel-3','agriculture','agriculture-fund',['annual_eur'=>10000000000],[]],
            ['fabien-roussel-4','culture','culture-budget',['gdp_share_bps'=>100],['reference_gdp_share_bps'=>40]],
            ['eric-zemmour-3','transport','fuel-refund',['share_bps'=>5000,'monthly_cap_eur'=>40],['eligible_persons'=>12000000,'reference_monthly_fuel_eur'=>100]],
        ];
        $out=[];
        foreach($rows as $n=>$row){
            [$suffix,$domain,$family,$target,$parameters]=$row;
            $out['pf2022-'.$suffix]=['bit'=>34+$n,'domain'=>$domain,'family'=>$family,'target'=>$target,
                'parameters'=>$parameters,'assumptions'=>['tax_delta_bps'=>0,'benefit_delta_bps'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0,'workers_pressure_delta'=>0,'retired_pressure_delta'=>0,'aggregate_wage_premium_bps'=>0],
                'classification'=>'fictional_quantitative_primary_source_proxy','model_version'=>self::VERSION];
        }
        return $out;
    }
    public static function isPolicy(string $id):bool{return isset(self::definitions()[$id]);}
    /** Incremental annual flows divided into daily cents. Capacity and
     * staffing commitments use explicit linear construction/hiring ramps.
     * No assumption that appropriated funds guarantee delivery. */
    public static function calculate(array $d,array $p,string $day,array $macro,int $netFloor,int $recipients,int $labour,int $employed):array
    {
        $t=$d['target'];$a=$d['parameters'];$f=$d['family'];
        $elapsed=(int)(new \DateTimeImmutable($p['effective_on']))->diff(new \DateTimeImmutable($day))->days;
        $r=['annual_revenue_delta_eur'=>0,'annual_expense_delta_eur'=>0,'affected_persons_proxy'=>0,
            'employment_delta_bps'=>0,'wage_premium_bps'=>0,'pension_transfer_delta_minor'=>0,'parameters'=>$a,'implementation_target_only'=>true];
        if(isset($t['hires'])){
            $n=min($t['hires'],intdiv($t['hires']*min($elapsed+1,365*$a['ramp_years']),365*$a['ramp_years']));
            $r['planned_positions_proxy']=$n;$r['affected_persons_proxy']=$n;$r['annual_expense_delta_eur']=$n*$a['annual_cost_per_hire_eur'];
            $r['employment_delta_bps']=intdiv($n*10000,max(1,$labour));
        }elseif(isset($t['beds'])||isset($t['places'])){
            $key=isset($t['beds'])?'beds':'places';$years=$t['years']??$a['ramp_years'];
            $n=intdiv($t[$key]*min($elapsed+1,$years*365),$years*365);
            $r['planned_capacity_proxy']=$n;$r['annual_expense_delta_eur']=$n*$a[$key==='beds'?'annual_cost_per_bed_eur':'annual_cost_per_place_eur'];
        }elseif($f==='social-housing'||$f==='renovation'){
            $annual=$t['annual_units']??intdiv($t['units'],$t['years']);
            if(!isset($t['years'])||$elapsed<$t['years']*365){
                $r['annual_incremental_units_proxy']=max(0,$annual-$a['reference_annual_units']);
                $r['annual_expense_delta_eur']=$r['annual_incremental_units_proxy']*$a['public_cost_per_unit_eur'];
            }
        }elseif($f==='home-care'){
            $r['annual_expense_delta_eur']=intdiv($a['annual_reference_eur']*$t['increase_bps'],10000);
        }elseif($f==='wealth-tax'){
            $r['annual_revenue_delta_eur']=intdiv($t['annual_revenue_eur']*$a['collection_bps'],10000);
        }elseif($f==='production-tax'){
            $r['annual_revenue_delta_eur']=-$t['annual_reduction_eur'];
        }elseif($f==='pension-floor'){
            $n=intdiv($recipients*$a['eligible_share_bps'],10000);$r['affected_persons_proxy']=$n;
            $r['annual_expense_delta_eur']=$n*max(0,$t['monthly_eur']-$a['reference_eligible_monthly_eur'])*12;
            $r['pension_transfer_delta_minor']=intdiv($r['annual_expense_delta_eur']*100,365);
        }elseif(in_array($f,['disability-benefit','training-benefit','youth-benefit'],true)){
            $monthly=$t['monthly_eur']??intdiv($netFloor*$t['smic_share_bps'],1000000);
            $r['affected_persons_proxy']=$a['eligible_persons'];$r['annual_expense_delta_eur']=$a['eligible_persons']*max(0,$monthly-$a['reference_monthly_eur'])*12;
            $r['benefit_floor_cash_minor']=intdiv($r['annual_expense_delta_eur']*100,365);
        }elseif($f==='public-pay'){
            $net=intdiv($a['reference_monthly_net_eur']*$t['increase_bps'],10000);
            $r['affected_persons_proxy']=$a['workers'];$r['annual_expense_delta_eur']=intdiv($net*$a['employer_cost_bps'],10000)*$a['workers']*12;
            $r['wage_premium_bps']=intdiv($t['increase_bps']*min($a['workers'],$employed),max(1,$employed));
        }elseif($f==='agriculture-fund'){
            $r['annual_expense_delta_eur']=$t['annual_eur'];
        }elseif($f==='culture-budget'){
            $daily=intdiv($macro['daily_real_gdp']*max(0,$t['gdp_share_bps']-$a['reference_gdp_share_bps']),10000);
            $r['annual_expense_delta_eur']=intdiv($daily*365,100);
        }elseif($f==='fuel-refund'){
            $monthly=min($t['monthly_cap_eur'],intdiv($a['reference_monthly_fuel_eur']*$t['share_bps'],10000));
            $r['affected_persons_proxy']=$a['eligible_persons'];$r['annual_expense_delta_eur']=$monthly*$a['eligible_persons']*12;
            $r['benefit_floor_cash_minor']=intdiv($r['annual_expense_delta_eur']*100,365);
        }else throw new \DomainException('No quantitative route for primary commitment');
        $r['daily_revenue_delta_minor']=intdiv($r['annual_revenue_delta_eur']*100,365);
        $r['daily_expense_delta_minor']=intdiv($r['annual_expense_delta_eur']*100,365);
        return $r;
    }
}
