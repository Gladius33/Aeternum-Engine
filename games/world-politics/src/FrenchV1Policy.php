<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** New, explicitly provisional source-bound targets. Old bit allocations,
 * historical source records and old scenario catalogues are immutable. */
final class FrenchV1Policy
{
    public const VERSION='fr-public-policy-proxies-v1';
    public static function definitions():array{
        $zero=array_fill_keys(['tax_delta_bps','benefit_delta_bps','production_delta_bps','employment_delta_bps','workers_pressure_delta','retired_pressure_delta','aggregate_wage_premium_bps'],0);
        $rows=[
            ['frv1-glucksmann-smic',128,'wages','wage-floor',['monthly_net_eur'=>1600],[]],
            ['frv1-branco-smic',129,'wages','wage-floor',['monthly_net_eur'=>1500],[]],
            ['frv1-hollande-pension',130,'retirement','pension',['age'=>63],[]],
            ['frv1-villepin-eldercare',131,'eldercare','eldercare-capacity',['annual_places'=>10000,'years'=>10],['annual_cost_per_place_eur'=>35000]],
            ['frv1-kazib-workweek',132,'employment','work-time',['weekly_hours'=>28,'salary_maintained'=>true],[]],
            ['frv1-equinoxe-adaptation',133,'environment','adaptation-fund',['annual_eur'=>9500000000,'budget_year'=>2027],[]],
            ['frv1-lisnard-production-tax',134,'tax','production-tax',['annual_reduction_eur'=>40000000000],[]],
            ['frv1-becht-pension-floor',135,'retirement','pension-floor',['monthly_eur'=>1200],['eligible_share_bps'=>1500,'reference_eligible_monthly_eur'=>950]],
            ['frv1-ruffin-smic',136,'wages','wage-floor',['monthly_net_eur'=>1600],[]],
            ['frv1-faure-smic',137,'wages','wage-floor',['monthly_net_eur'=>1700],[]],
            ['frv1-faure-pension',138,'retirement','pension',['age'=>62],[]],
        ];
        $out=[];foreach($rows as [$id,$bit,$domain,$family,$target,$parameters])$out[$id]=['bit'=>$bit,'domain'=>$domain,'family'=>$family,'target'=>$target,'parameters'=>$parameters,'assumptions'=>$zero,'classification'=>'fictional_public_source_cohort_proxy','model_version'=>self::VERSION];
        return $out;
    }
    public static function ids():array{return array_keys(self::definitions());}
    public static function isPolicy(string $id):bool{return isset(self::definitions()[$id]);}
}
