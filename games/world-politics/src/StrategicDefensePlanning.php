<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Aggregate fiscal planning only. No tactical capabilities or deployment model. */
final class StrategicDefensePlanning
{
    public const VERSION='wp70-defense-reference-v1';
    public static function reference():array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-26-defense-reference-v1.json'),true,32,JSON_THROW_ON_ERROR);
        self::validate($p);return $p;
    }
    private static function validate(array $p):void
    {
        $o=$p['observations'];$a=$p['scenario']['allocation_bps'];
        if($p['schema']!==self::VERSION||$p['currency']!=='EUR'||$o['mission_defense_plf_2027_minor']-$o['mission_defense_2026_comparator_minor']!==$o['planned_increase_minor']
            ||array_keys($a)!==['personnel','maintenance_and_services','capital_and_research','administration']||array_sum($a)!==10000)
            throw new \DomainException('Invalid defense reference');
        foreach($o as $v)if(!is_int($v)||$v<0||$v>10000000000000)throw new \DomainException('Invalid reference value');
        foreach($a as $v)if(!is_int($v)||$v<0||$v>10000)throw new \DomainException('Invalid scenario allocation');
    }
    public static function pin():array
    {
        $p=self::reference();return ['pack'=>$p,'sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES))];
    }
    public static function report(array $pin,string $world,string $day):array
    {
        $p=$pin['pack'];self::validate($p);
        if(!hash_equals($pin['sha'],hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES))))throw new \DomainException('Defense reference digest mismatch');
        $o=$p['observations'];$base=$o['mission_defense_plf_2027_minor'];$options=[];
        foreach([-1000,0,1000] as $delta){
            $total=intdiv($base*(10000+$delta),10000);
            $parts=[];$left=$total;
            foreach($p['scenario']['allocation_bps'] as $k=>$bps){$v=intdiv($total*$bps,10000);$parts[$k]=$v;$left-=$v;}
            $parts['administration']+=$left;
            $options[]=['variation_bps'=>$delta,'annual_planning_envelope_minor'=>$total,'difference_from_plf_minor'=>$total-$base,'illustrative_allocation_minor'=>$parts,
                'requires_budget_authorization'=>true,'enacted'=>false,'booked'=>false];
        }
        return ['version'=>self::VERSION,'world_id'=>$world,'day'=>$day,'reference_sha'=>$pin['sha'],'reference'=>$p,
            'budget_options'=>$options,'actual_2027_appropriation_minor'=>null,'actual_expenditure_minor'=>null,'additional_budget_delta_minor'=>null,
            'national_service'=>['call_up_suspended_at_reference_date'=>true,'reinstatement_requires_law'=>true,'player_can_unilaterally_activate'=>false,'scenario_activation_implemented'=>false],
            'classification'=>'fiscal_planning_only_not_observed_spending','operational_readiness_inferred'=>false];
    }
}
