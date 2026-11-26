<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Read-only audit. Publication reference and playable state are distinct.
 * Never inject a newer observation into a saved scenario. */
final class ReferenceDataAudit
{
    // Insee 2025 exports EUR 1,009.4bn + imports EUR 1,023.8bn,
    // translated at the ECB 2025 annual USD/EUR rate 1.1300.
    public const FR_TRADE_TOTAL_USD_2025=2297516000000;
    public static function report():array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-26-fr-institutional-observations-v1.json'),true,32,JSON_THROW_ON_ERROR);
        $a=$p['assembly'];$occupied=array_sum($a['groups']);
        if($occupied!==574||$occupied+$a['vacant']!==$a['statutory_seats'])throw new \DomainException('Assembly reference arithmetic mismatch');
        $p['assembly']['occupied_seats']=$occupied;
        $p['assembly']['majority_of_occupied_seats']=intdiv($occupied,2)+1;
        $p['assembly']['majority_of_statutory_seats']=intdiv($a['statutory_seats'],2)+1;
        $s=$p['senate'];if(array_sum($s['groups'])!==$s['occupied_seats'])throw new \DomainException('Senate reference arithmetic mismatch');
        return ['as_of'=>'2026-11-26','classification'=>'read_only_audit_not_scenario_state',
            'observations'=>$p,'national_trade_basis_usd'=>self::FR_TRADE_TOTAL_USD_2025,
            'domains'=>[
                'fr_macro'=>['status'=>'observed_anchors_in_calculation','remaining'=>'behavioral calibration'],
                'fr_demography_services'=>['status'=>'observed_anchors_in_calculation','remaining'=>'regional and longitudinal calibration'],
                'fr_debt'=>['status'=>'observed_anchors_in_calculation','remaining'=>'individual creditor identities not publicly established'],
                'frf_monetary_banking'=>['status'=>'observed_financial_baseline_opt_in','remaining'=>'scenario parameters and historical calibration'],
                'global_population_gdp'=>['status'=>'pinned_published_default_new_worlds','remaining'=>'GDP for 140 states and population for Holy See; foreign GDP dynamics not fully calibrated'],
                'fr_trade'=>['status'=>'four_observed_pairs_in_weighted_feedback','remaining'=>'other partners, products, bilateral services and energy-specific transmission'],
                'fr_constitution'=>['status'=>'rule_models_and_observed_group_reference','remaining'=>'dated individual mandate and coalition initialisation'],
                'foreign_institutions_elections'=>['status'=>'comparative_scenario_models','remaining'=>'complete historical electoral results and rosters'],
                'international_organizations'=>['status'=>'observed_membership_reference','remaining'=>'full real treaty rules and executable membership state'],
                'fr_eu_exit'=>['status'=>'scenario_legal_and_economic_models','remaining'=>'empirical calibration of counterfactual spillovers'],
                'fr_ru_relations'=>['status'=>'observed_goods_trade_in_generic_feedback','remaining'=>'sector-specific energy and treaty mechanics'],
                'fr_companies'=>['status'=>'observed_identities_fictional_behavior','remaining'=>'verified company accounts, valuations and legal asset coverage'],
                'defense_conscripting'=>['status'=>'budget_reference_only','remaining'=>'national defense budgets and lawful manpower simulation'],
                'civil_crisis'=>['status'=>'fictional_response_parameters','remaining'=>'empirical calibration'],
            ]];
    }
}
