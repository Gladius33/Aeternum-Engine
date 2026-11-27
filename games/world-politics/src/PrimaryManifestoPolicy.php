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
            ['nicolas-dupont-aignan-1','tax','reinvestment-tax',['reduction_bps'=>5000],['annual_tax_on_reinvested_profit_eur'=>12000000000]],
            ['jean-lassalle-1','tax','fuel-vat',['rate_bps'=>550],['reference_rate_bps'=>2000,'annual_tax_exclusive_base_eur'=>40000000000]],
            ['marine-le-pen-2','tax','business-property-tax',[],['annual_reference_cfe_eur'=>8000000000]],
            ['valerie-pecresse-2','social','rsa-activity',['hours'=>15],['recipients'=>1900000,'participation_bps'=>5000,'annual_support_cost_eur'=>2000,'placement_bps'=>500]],
            ['philippe-poutou-3','transport','free-transit',[],['annual_fare_revenue_eur'=>7000000000,'extra_service_cost_bps'=>2000]],
            ['eric-zemmour-1','tax','pension-levy',['monthly_threshold_eur'=>1300],['eligible_share_bps'=>3000,'reference_monthly_pension_eur'=>1100,'effective_levy_bps'=>400]],
            ['eric-zemmour-2','wages','overtime-relief',[],['workers_share_bps'=>1500,'annual_gross_overtime_eur'=>2000,'effective_tax_and_levy_bps'=>2000]],
            ['eric-zemmour-4','energy','fuel-cap',['minor_eur_per_litre'=>180],['reference_price_minor'=>195,'annual_litres'=>35000000000,'duration_days'=>180]],
            ['nathalie-arthaud-1','wages','wage-indexation',[],[]],
            ['nathalie-arthaud-2','employment','work-sharing',[],['assumed_hours_reduction_bps'=>1000,'coverage_bps'=>5000,'replacement_hiring_bps'=>5000]],
            ['marine-le-pen-1','industry','sme-procurement',[],['annual_procurement_eur'=>100000000000,'reference_sme_share_bps'=>3000,'scenario_target_share_bps'=>4000,'administration_cost_bps'=>50]],
            ['marine-le-pen-3','trade','local-supply',[],['annual_eligible_turnover_eur'=>40000000000,'target_shift_bps'=>1000,'support_rate_bps'=>2000,'ramp_years'=>5,'domestic_value_added_bps'=>4500,'annual_output_per_job_eur'=>90000]],
            ['jean-luc-melenchon-4','energy','renewable-transition',['target_bps'=>10000,'target_year'=>2050],['reference_renewable_share_bps'=>2300,'annual_final_energy_gwh'=>1500000,'investment_eur_per_annual_gwh'=>900000,'public_finance_share_bps'=>4000,'annual_maintenance_bps'=>100,'imported_equipment_bps'=>3000,'annual_output_per_job_eur'=>90000]],
            ['philippe-poutou-4','diplomacy','nato-withdrawal',[],[]],
            ['nathalie-arthaud-3','ownership','collective-ownership',[],['large_firm_weight_min'=>200,'review_days'=>30,'worker_board_bps'=>5000,'target_stake_bps'=>10000]],
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
    public static function calculate(array $d,array $p,string $day,array $macro,int $netFloor,int $recipients,int $labour,int $employed,?array $prior=null):array
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
        }elseif($f==='eldercare-capacity'){
            $n=intdiv($t['annual_places']*min($elapsed+1,365*$t['years']),365);
            $r['planned_capacity_proxy']=$n;$r['annual_expense_delta_eur']=$n*$a['annual_cost_per_place_eur'];
            $r['construction_and_capacity_delivery_certified']=false;
        }elseif($f==='home-care'){
            $r['annual_expense_delta_eur']=intdiv($a['annual_reference_eur']*$t['increase_bps'],10000);
        }elseif($f==='wealth-tax'){
            $r['annual_revenue_delta_eur']=intdiv($t['annual_revenue_eur']*$a['collection_bps'],10000);
        }elseif($f==='contribution-cut'){
            $employee=$t['annual_employee_relief_eur'];$employer=$t['annual_employer_relief_eur'];
            $r['annual_revenue_delta_eur']=-($employee+$employer);
            $r['annual_employee_relief_eur']=$employee;$r['annual_employer_relief_eur']=$employer;
            $r['wage_premium_bps']=intdiv($employee*10000,max(1,$employed*$a['reference_annual_net_pay_eur']));
            $r['offsetting_savings_automatically_booked']=false;
        }elseif($f==='industrial-grants'){
            $r['annual_expense_delta_eur']=$t['annual_eur'];
            $r['grant_appropriation_proxy']=true;$r['investment_returns_certified']=false;
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
        }elseif($f==='reinvestment-tax'){
            $r['annual_revenue_delta_eur']=-intdiv($a['annual_tax_on_reinvested_profit_eur']*$t['reduction_bps'],10000);
        }elseif($f==='fuel-vat'){
            $r['annual_revenue_delta_eur']=intdiv($a['annual_tax_exclusive_base_eur']*($t['rate_bps']-$a['reference_rate_bps']),10000);
            $r['price_including_tax_index_bps']=intdiv((10000+$t['rate_bps'])*10000,10000+$a['reference_rate_bps']);
        }elseif($f==='business-property-tax'){
            $r['annual_revenue_delta_eur']=-$a['annual_reference_cfe_eur'];
            $r['local_authority_compensation_modelled']=false;
        }elseif($f==='rsa-activity'){
            $n=intdiv($a['recipients']*$a['participation_bps'],10000);
            $r['affected_persons_proxy']=$n;$r['weekly_activity_hours_proxy']=$n*$t['hours'];
            $r['annual_expense_delta_eur']=$n*$a['annual_support_cost_eur'];
            $r['placement_proxy']=intdiv($n*$a['placement_bps'],10000);
            $r['employment_delta_bps']=intdiv($r['placement_proxy']*10000,max(1,$labour));
        }elseif($f==='free-transit'){
            $r['annual_expense_delta_eur']=$a['annual_fare_revenue_eur']+intdiv($a['annual_fare_revenue_eur']*$a['extra_service_cost_bps'],10000);
            $r['fare_minor']=0;
        }elseif($f==='pension-levy'){
            $n=intdiv($recipients*$a['eligible_share_bps'],10000);
            $r['affected_persons_proxy']=$n;
            $r['annual_revenue_delta_eur']=-intdiv($n*$a['reference_monthly_pension_eur']*12*$a['effective_levy_bps'],10000);
            $r['benefit_floor_cash_minor']=-intdiv($r['annual_revenue_delta_eur']*100,365);
        }elseif($f==='overtime-relief'&&isset($t['annual_threshold_hours'])){
            // Explicit synthetic residual contribution rates exclude CSG/CRDS
            // and broad relief already assumed in this marginal scenario.
            $n=intdiv($employed*$a['workers_share_bps'],10000);
            $hours=max(0,$a['reference_annual_hours']-$t['annual_threshold_hours']);
            $base=$n*$hours*$a['reference_hourly_gross_eur'];
            $employee=intdiv($base*$a['remaining_employee_rate_bps'],10000);
            $employer=intdiv($base*$a['remaining_employer_rate_bps'],10000);
            $r['annual_revenue_delta_eur']=-($employee+$employer);
            $r['affected_persons_proxy']=$n;$r['annual_eligible_hours_proxy']=$n*$hours;
            $r['annual_employee_relief_eur']=$employee;$r['annual_employer_relief_eur']=$employer;
            $r['wage_premium_bps']=intdiv($employee*10000,max(1,$employed*30000));
            $r['csg_crds_retained']=true;$r['additional_hours_revenue_booked']=false;
        }elseif($f==='overtime-relief'){
            $n=intdiv($employed*$a['workers_share_bps'],10000);$r['affected_persons_proxy']=$n;
            $r['annual_revenue_delta_eur']=-intdiv($n*$a['annual_gross_overtime_eur']*$a['effective_tax_and_levy_bps'],10000);
            $r['wage_premium_bps']=intdiv($a['annual_gross_overtime_eur']*$a['effective_tax_and_levy_bps']*$a['workers_share_bps'],10000*30000);
        }elseif($f==='fuel-cap'){
            // Temporary compensated ceiling. No permanent subsidy after expiry.
            $gap=max(0,$a['reference_price_minor']-$t['minor_eur_per_litre']);
            $r['ceiling_active']=$elapsed<$a['duration_days'];
            $r['annual_expense_delta_eur']=$r['ceiling_active']?intdiv($a['annual_litres']*$gap,100):0;
            $r['ceiling_minor_per_litre']=$t['minor_eur_per_litre'];
        }elseif($f==='wage-indexation'){
            // The existing macro wage target tracks prices. Measure the
            // remaining purchasing-power gap; do not compound an annual
            // percentage on every simulated day.
            $price=$prior['macro']['price_index_scaled']??100000000;
            $wage=$prior['macro']['wage_index_scaled']??100000000;
            $r['price_indexation_gap_bps']=max(0,intdiv($price*10000,max(1,$wage))-10000);
            $r['wage_premium_bps']=min(3000,$r['price_indexation_gap_bps']);
        }elseif($f==='work-sharing'){
            $covered=intdiv($employed*$a['coverage_bps'],10000);
            $n=intdiv(intdiv($covered*$a['assumed_hours_reduction_bps'],10000)*$a['replacement_hiring_bps'],10000);
            $r['affected_persons_proxy']=$covered;$r['replacement_jobs_proxy']=$n;$r['employment_delta_bps']=intdiv($n*10000,max(1,$labour));
            $r['weekly_hours_reduction_bps']=$a['assumed_hours_reduction_bps'];
            $r['monthly_wage_preserved']=true;
        }elseif($f==='sme-procurement'){
            // Redirect existing procurement, not the entire value as new
            // spending. The additional administrative cost is explicit.
            $r['annual_contract_value_redirected_eur']=intdiv($a['annual_procurement_eur']*($a['scenario_target_share_bps']-$a['reference_sme_share_bps']),10000);
            $r['annual_expense_delta_eur']=intdiv($r['annual_contract_value_redirected_eur']*$a['administration_cost_bps'],10000);
            $r['procurement_legal_compliance_qualified']=false;
        }elseif($f==='local-supply'){
            // Support domestic production and short supply chains rather
            // than falsely claim a unilateral tariff or mandatory origin
            // discrimination is compatible with EU single-market law.
            $phase=min(10000,intdiv(($elapsed+1)*10000,365*$a['ramp_years']));
            $shift=intdiv(intdiv($a['annual_eligible_turnover_eur']*$a['target_shift_bps'],10000)*$phase,10000);
            $valueAdded=intdiv($shift*$a['domestic_value_added_bps'],10000);
            $r['annual_supply_chain_shift_eur']=$shift;$r['annual_domestic_value_added_proxy_eur']=$valueAdded;
            $r['annual_expense_delta_eur']=intdiv($shift*$a['support_rate_bps'],10000);
            $r['production_delta_bps']=intdiv($valueAdded*10000,max(1,intdiv($macro['daily_real_gdp']*365,100)));
            $r['employment_delta_bps']=intdiv(intdiv($valueAdded,$a['annual_output_per_job_eur'])*10000,max(1,$labour));
            $r['implementation_scope']='domestic_capacity_and_short_chain_support';
            $r['tariff_or_origin_restriction_implemented']=false;
        }elseif($f==='renewable-transition'){
            // A dated end target does not delay legislative commencement
            // until that year. The planned stock and flow are separate.
            $end=$t['target_year'].'-12-31';
            $start=$p['effective_on'];
            $days=$start<$end?(int)(new \DateTimeImmutable($start))->diff(new \DateTimeImmutable($end))->days+1:1;
            $phase=min(10000,intdiv(($elapsed+1)*10000,$days));
            $gap=max(0,$t['target_bps']-$a['reference_renewable_share_bps']);
            $additional=intdiv($a['annual_final_energy_gwh']*$gap,10000);
            $capital=$additional*$a['investment_eur_per_annual_gwh'];
            $dailyBuild=$elapsed<$days?intdiv($capital*100,$days):0;
            $publicDaily=intdiv($dailyBuild*$a['public_finance_share_bps'],10000);
            $built=intdiv($capital*$phase,10000);
            $maintenance=intdiv(intdiv($built*$a['annual_maintenance_bps'],10000)*$a['public_finance_share_bps'],10000);
            $r['planned_renewable_share_bps']=$a['reference_renewable_share_bps']+intdiv($gap*$phase,10000);
            $r['planned_additional_annual_gwh']=intdiv($additional*$phase,10000);
            $r['planned_total_capital_eur']=$capital;$r['daily_total_investment_minor']=$dailyBuild;
            $r['daily_public_investment_minor']=$publicDaily;
            $r['daily_imported_equipment_minor']=intdiv($dailyBuild*$a['imported_equipment_bps'],10000);
            $r['annual_expense_delta_eur']=intdiv($publicDaily*365,100)+$maintenance;
            $r['employment_delta_bps']=intdiv(intdiv(intdiv(($dailyBuild-$r['daily_imported_equipment_minor'])*365,100),$a['annual_output_per_job_eur'])*10000,max(1,$labour));
            $r['target_achievement_certified']=false;
            $r['physical_grid_and_storage_qualified']=false;
        }elseif($f==='nato-withdrawal'){
            // The ordinary law merely authorizes the executive notice.
            // Actual treaty status is held by OrganizationSimulation.
            $r['withdrawal_authorization_only']=true;
            $r['treaty_status_domain']='organizations.nato_withdrawal';
        }elseif($f==='collective-ownership'){
            $r['statutory_authorization_only']=true;$r['financial_settlement_domain']='public_equity.collective';
            $r['compensation_is_financial_exchange']=true;
        }else throw new \DomainException('No quantitative route for primary commitment');
        $r['daily_revenue_delta_minor']=intdiv($r['annual_revenue_delta_eur']*100,365);
        $r['daily_expense_delta_minor']=intdiv($r['annual_expense_delta_eur']*100,365);
        return $r;
    }
}
