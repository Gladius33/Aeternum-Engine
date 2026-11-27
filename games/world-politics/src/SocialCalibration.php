<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/SocialEconomy.php';

/** Offline observation/holdout and explicit scenario sensitivity.
 * Does not modify the pinned daily model or infer causal effects. */
final class SocialCalibration
{
    public const VERSION='wp114-social-calibration-report-v1';
    public static function load():array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/wp114-social-employment-reference.json'),true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!=='wp114-social-employment-reference-v1'||$p['source']['url']!=='https://www.insee.fr/fr/statistiques/8901327'
            ||$p['source']['published_on']>$p['as_of']||$p['training_through']!==2021||$p['holdout_from']!==2022
            ||$p['columns']!==['year','age_15_24','age_25_49','age_50_64','age_15_64']||count($p['employment_bps'])!==11)
            throw new \DomainException('Invalid employment observation pack');
        foreach($p['employment_bps'] as $i=>$r){
            if(!array_is_list($r)||count($r)!==5||$r[0]!==2015+$i)throw new \DomainException('Missing or unordered employment year');
            foreach(array_slice($r,1) as $x)if(!is_int($x)||$x<0||$x>10000)throw new \DomainException('Invalid employment observation');
        }
        foreach($p['activity_2025_bps'] as $k=>$v)if(!is_int($v)||$v<0||$v>10000)throw new \DomainException('Invalid participation observation');
        if(array_keys($p['activity_2025_bps'])!==array_slice($p['columns'],1))throw new \DomainException('Activity age scope mismatch');
        return $p;
    }
    public static function benchmark(array $p):array
    {
        $rows=$p['employment_bps'];$train=array_slice($rows,0,7);$test=array_slice($rows,7);
        $out=[];
        foreach(array_slice($p['columns'],1) as $k=>$key){
            $col=$k+1;$start=$train[0][$col];$last=$train[6][$col];$errP=0;$errT=0;$hold=[];
            foreach($test as $r){
                $trend=max(0,min(10000,$last+intdiv(($last-$start)*($r[0]-2021),6)));
                $errP+=abs($last-$r[$col]);$errT+=abs($trend-$r[$col]);
                $hold[]=['year'=>$r[0],'observed_bps'=>$r[$col],'persistence_bps'=>$last,'trend_bps'=>$trend];
            }
            $out[$key]=['persistence_mae_bps'=>intdiv($errP,count($test)),'trend_mae_bps'=>intdiv($errT,count($test)),'holdout'=>$hold];
        }
        return ['version'=>self::VERSION,'source_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),
            'train'=>[2015,2021],'holdout'=>[2022,2025],'observed_2025_employment_bps'=>array_combine(array_slice($p['columns'],1),array_slice($rows[10],1)),
            'observed_2025_activity_bps'=>$p['activity_2025_bps'],'age_band_backtest'=>$out,
            'classification'=>'historical_baseline_backtest_only','policy_causal_calibration_qualified'=>false];
    }
    /** Quantitative cost envelopes for the primary-source catalogue.
     * Evaluate both commencement and the five-year point, because staffing
     * and infrastructure spending can be almost zero on day one. */
    public static function primarySensitivity(array $population,array $services,array $macro):array
    {
        $out=[];
        foreach(PrimaryManifestoPolicy::definitions() as $id=>$d){
            $low=[];$high=[];
            foreach($d['parameters'] as $k=>$v)if(SocialEconomy::primarySensitivityKey($k)){
                $low[$k]=intdiv($v*3,4);$high[$k]=intdiv($v*5,4);
                if(str_ends_with($k,'_bps')&&$k!=='employer_cost_bps')$high[$k]=min(10000,$high[$k]);
            }
            $start=SocialProgramme::effectiveDay($id,'2027-05-14');$policy=[$id=>['effective_on'=>$start]];
            $periods=[];
            foreach(['opening'=>$start,'year_five'=>(new \DateTimeImmutable($start))->modify('+5 years')->format('Y-m-d')] as $period=>$day){
                $scenarios=[];
                foreach(['low'=>$low,'central'=>[],'high'=>$high] as $key=>$params)
                    $scenarios[$key]=SocialEconomy::calculate([$id],$policy,$day,$population,$services,$macro,null,[],[$id=>$params]);
                $metrics=[];
                foreach(['daily_revenue_delta_minor','daily_expense_delta_minor','pension_transfer_delta_minor','production_delta_bps','employment_delta_bps','wage_premium_bps'] as $metric){
                    $v=array_column($scenarios,$metric);
                    $metrics[$metric]=['min'=>min($v),'central'=>$scenarios['central'][$metric],'max'=>max($v)];
                }
                $periods[$period]=['day'=>$day,'metrics'=>$metrics];
            }
            $out[$id]=['family'=>$d['family'],'low_parameters'=>$low,'high_parameters'=>$high,'periods'=>$periods,
                'financial_flow_only'=>in_array($d['family'],['collective-ownership','nato-withdrawal'],true)];
        }
        return ['classification'=>'joint_parameter_scenarios_not_confidence_intervals','measures'=>$out,'causal_calibration_qualified'=>false];
    }
    /** Historical reference comparison with explicit scope, not fitted
     * policy elasticities or a claim of exact 2027 salaries. */
    public static function observedAnchors(array $services):array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/wp114-observed-social-anchors.json'),true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!=='wp114-observed-social-anchors-v1'||count($p['observations'])!==4)throw new \DomainException('Invalid observed anchors');
        $v=array_column($p['observations'],'value','id');
        $pay=PrimaryManifestoPolicy::definitions()['pf2022-fabien-roussel-2']['parameters']['reference_monthly_net_eur'];
        $pension=intdiv($services['baseline_daily_public_minor']['pensions']*365,max(1,$services['pension_recipients'])*12);
        return ['source_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),
            'observations'=>$p['observations'],'public_pay_reference_eur'=>$pay,'public_pay_observed_net_eur'=>$v['public-net-pay-2024'],
            'public_pay_reference_delta_bps'=>intdiv(($pay-$v['public-net-pay-2024'])*10000,$v['public-net-pay-2024']),
            'scenario_pension_transfer_per_recipient_minor'=>$pension,'observed_direct_pension_gross_minor'=>$v['direct-pension-gross-2023']*100,
            'pension_scope_comparable'=>false,'qualification'=>'observed_reference_diagnostics_not_policy_effect_validation'];
    }
    public static function sensitivity(array $population,array $services,array $macro):array
    {
        $keys=['employment_participation_bps','job_guarantee_takeup_bps','retirement_takeup_bps','long_career_share_bps','arduous_share_bps',
            'wage_floor_share_bps','incentive_takeup_bps','apprentice_share_bps','sme_hiring_share_bps','annual_hiring_rate_bps',
            'targeted_job_share_bps','job_service_placement_bps','sector_bargaining_takeup_bps'];
        $low=[];$high=[];foreach($keys as $k){$a=SocialEconomy::ASSUMPTIONS[$k];$low[$k]=intdiv($a*3,4);$high[$k]=min(10000,intdiv($a*5,4));}
        $out=[];
        foreach(SocialProgramme::definitions() as $id=>$d){
            $day=SocialProgramme::effectiveDay($id,'2027-05-14');$policy=[$id=>['effective_on'=>$day]];
            $scenarios=[];foreach(['low'=>$low,'central'=>[],'high'=>$high] as $name=>$parameters)
                $scenarios[$name]=SocialEconomy::calculate([$id],$policy,$day,$population,$services,$macro,null,$parameters);
            $measures=[];
            foreach(['daily_revenue_delta_minor','daily_expense_delta_minor','pension_transfer_delta_minor','production_delta_bps','employment_delta_bps','wage_premium_bps'] as $metric){
                $v=array_column($scenarios,$metric);
                $measures[$metric]=['min'=>min($v),'central'=>$scenarios['central'][$metric],'max'=>max($v)];
            }
            $out[$id]=$measures;
        }
        return ['classification'=>'joint_parameter_scenarios_not_statistical_interval','low_parameters'=>$low,'high_parameters'=>$high,'measures'=>$out];
    }
}
