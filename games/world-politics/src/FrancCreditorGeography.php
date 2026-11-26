<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Country allocation is a proxy, never a security-level or beneficial-owner book.
 * Country of reported holdings is not necessarily the ultimate owner's country. */
final class FrancCreditorGeography
{
    public const VERSION='wp54r-fr-creditor-geography-v1';
    private const FIELDS=['principal_outstanding_minor','principal_arrears_minor','interest_arrears_minor','principal_received_minor','interest_received_minor'];
    public static function reference():array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-26-fr-creditor-geography-v1.json'),true,64,JSON_THROW_ON_ERROR);
        $g=$p['geographic_proxy'];$weights=$g['weights_in_bps_of_all_public_securities'];
        if($p['schema']!==self::VERSION||array_sum($weights)!==$g['nonresident_total_bps']||$g['nonresident_total_bps']+$g['resident_total_bps']!==10000||$weights['unallocated']<=0)throw new \DomainException('Invalid creditor geography source');
        foreach($weights as $id=>$w)if(!is_int($w)||$w<0||!preg_match('/^(?:[A-Z]{2}|other_countries|unallocated)$/D',$id))throw new \DomainException('Invalid geographic weight');
        return ['version'=>self::VERSION,'source_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),'observations'=>$p,
            'classification'=>'historical_sector_observations_and_secondary_geographic_proxy',
            'country_weights_are_not_measured_sovereign_positions'=>true,'ultimate_owners_known'=>false,
            'currency_scope'=>'Gross foreign-currency securities at market value before hedges are not net FX exposure; Eurostat reports EUR denomination or swaps into EUR for France at end 2025. Do not add these stocks.'];
    }
    public static function project(array $claims):array
    {
        $ref=self::reference();$g=$ref['observations']['geographic_proxy'];$w=$g['weights_in_bps_of_all_public_securities'];$den=$g['nonresident_total_bps'];
        $out=[];$covered=[];$excluded=[];
        foreach(['FRF','EUR'] as $cur){
            $id='legacy-state-nonresident-'.$cur;
            $c=$claims[$id]??null;
            $covered[$cur]=array_fill_keys(self::FIELDS,0);$excluded[$cur]=array_fill_keys(self::FIELDS,0);
            foreach($claims as $key=>$v){
                if($v['currency']!==$cur)continue;
                foreach(self::FIELDS as $field){
                    $n=$v[$field]??0;
                    if(!is_int($n)||$n<0)throw new \DomainException('Invalid creditor stock or flow');
                    if($key===$id)$covered[$cur][$field]+=$n;else $excluded[$cur][$field]+=$n;
                }
            }
            foreach(self::FIELDS as $field){
                $n=$covered[$cur][$field];$cum=0;$prev=0;
                foreach($w as $country=>$weight){
                    $cum+=$weight;
                    $next=intdiv($n,$den)*$cum+intdiv(($n%$den)*$cum,$den);
                    $out[$country][$cur][$field]=$next-$prev;$prev=$next;
                }
                if($prev!==$n)throw new \DomainException('Geographic allocation mismatch');
            }
        }
        return ['reference'=>$ref,'classification'=>'scenario_proxy_from_secondary_all_securities_key',
            'covered_cohort'=>'legacy-state-nonresident','covered_by_currency_minor'=>$covered,
            'other_cohorts_not_allocated_by_currency_minor'=>$excluded,'countries_and_residuals'=>$out,
            'allocation_weights_of_all_public_securities_bps'=>$w,'weight_denominator_bps'=>$den,
            'preserves_each_modelled_stock_and_flow'=>true,'new_issues_geography_unknown'=>true,
            'actual_country_sovereign_claims_observed'=>false,'individual_owners_known'=>false];
    }
}
