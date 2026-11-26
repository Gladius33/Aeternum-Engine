<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

final class UkraineAidReference
{
    public const VERSION='wp54r-ukraine-aid-observations-v2';
    public static function report():array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-26-ukraine-aid-observations-v2.json'),true,32,JSON_THROW_ON_ERROR);
        $o=$p['observed'];$h=$o['humanitarian_latest_tenths_million'];$c=$o['civil_guide'];
        if($p['schema']!==self::VERSION||$h['ngo']+$h['international_organizations']+$h['in_kind']!==$h['total'])throw new \DomainException('Invalid humanitarian observations');
        $days=(int)(new \DateTimeImmutable('2022-02-24'))->diff(new \DateTimeImmutable('2026-07-01'))->days;
        return ['version'=>self::VERSION,'source_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),'source_pack'=>$p,
            'annual_scenario_cost_minor'=>['defense'=>$o['direct_defense_cost_2025']*100000000,'civil_reconstruction'=>$o['civil_fund_2026_new_envelope']*100000000,
                'humanitarian'=>intdiv(($h['ngo']+$h['international_organizations'])*10000000*365,$days)],
            'older_humanitarian_superseded'=>true,'economic_total_rounding_residual_million'=>$c['economic_support_rounded_total']-array_sum(array_intersect_key($c,array_fill_keys(['multilateral_guarantees','direct_budget_aid','civil_fund_grants','concessional_treasury_loans','bpifrance_export_guarantees','fasep','nuclear_safety'],true))),
            'cooperation_source_discrepancy_million'=>$c['cooperation_reported_total']-$c['student_aid']-$c['technical_assistance'],
            'not_additive'=>true,'future_contracts_and_disbursements_known'=>false,'future_loans_guarantees_eu_and_refugee_costs_minor'=>null];
    }
}
