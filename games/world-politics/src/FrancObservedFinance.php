<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancNativeBudget.php';

/** Observed stock benchmarks. Never add the published banking balance or
 * foreign holdings to the synthetic debt or monetary ledgers a second time. */
final class FrancObservedFinance
{
    public const VERSION='wp54r-fr-financial-observations-v1';
    public static function load():array
    {
        $raw=file_get_contents(__DIR__.'/../data/2026-11-26-fr-financial-observations-v1.json');
        if($raw===false)throw new \DomainException('Missing observed financial pack');
        $p=json_decode($raw,true,64,JSON_THROW_ON_ERROR);$b=$p['bank'];$r=$p['reserves'];
        if($p['schema']!==self::VERSION||$b['unit']!=='EUR_billion'||$r['unit']!=='EUR_million'||$p['external']['unit']!=='EUR_100million'
            ||array_sum($b['assets'])!==$b['total']||array_sum($b['liabilities_including_equity'])+$b['accounting_balance_rounding_residual_billion']!==$b['total']
            ||$r['gold']+$r['gross_fx']+$r['imf_position']+$r['sdr']+$r['other']!==$r['gross']
            ||abs($r['gold']+$r['net_fx']+$r['imf_position']+$r['sdr']+$r['other']-$r['net'])>1)throw new \DomainException('Observed financial stock mismatch');
        return $p;
    }
    public static function digest():string
    {return hash('sha256',json_encode(self::load(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));}
    public static function reserveCeiling():int
    {return self::load()['reserves']['net_fx']*100000000;}
    /** Allocation at opening parity is a scenario choice, not an observation
     * of EUR holdings in an already existing post-exit central bank. */
    public static function validateAllocation(array $eu):void
    {
        $sum=$eu['franc_fx_terms']['official_eur_minor']??0;
        foreach($eu['franc_external_facilities']??[] as $f)$sum+=FrancNativeBudget::scale($f['opening_reserve_minor'],$f['eur_minor_per_10000_units'],10000,true);
        if($sum>self::reserveCeiling())throw new \DomainException('Scenario opening reserve allocation exceeds observed net FX equivalent');
    }
    // Exact integer basis points without multiplying national-scale minor units by 10000.
    private static function basisPoints(int $n,int $d):int
    {
        if($n<0||$d<=0)throw new \DomainException('Invalid capital ratio');
        $q=intdiv($n,$d);$r=$n%$d;$lo=0;$hi=9999;
        while($lo<$hi){
            $mid=intdiv($lo+$hi+1,2);$tail=($d%10000)*$mid;
            $threshold=intdiv($d,10000)*$mid+intdiv($tail,10000)+($tail%10000!==0?1:0);
            if($threshold<=$r)$lo=$mid;else $hi=$mid-1;
        }
        if($q>intdiv(PHP_INT_MAX-$lo,10000))throw new \DomainException('Capital ratio overflow');
        return $q*10000+$lo;
    }
    public static function bank(array $slice,int $ratio):array
    {
        $b=self::load()['bank'];$unit=100000000000*$ratio;
        $delta=$slice['equity_frf_minor']-$slice['opening_capital_proxy_frf_minor'];
        $equity=$b['liabilities_including_equity']['equity']*$unit+$delta;
        $cet1=$b['cet1']*$unit+$delta;$rwa=$b['rwa']*$unit;
        // Same sovereign P&L applied once to existing aggregate accounting
        // equity, not to an independently duplicated set of bank assets.
        $ratioBps=$cet1>=0?self::basisPoints($cet1,$rwa):-self::basisPoints(-$cet1,$rwa);
        $loss=max(0,-$delta);$depletion=self::basisPoints(min($loss,$b['cet1']*$unit),$b['cet1']*$unit);
        $credit=max(0,10000-$depletion);
        if($ratioBps<$b['minimum_pillar1_cet1_bps'])$credit=min($credit,5000);
        if($cet1<=0)$credit=0;
        return ['version'=>self::VERSION,'source_sha'=>self::digest(),'opening_observation_period'=>'2025-12-31',
            'opening_accounting_equity_at_parity_frf_minor'=>$b['liabilities_including_equity']['equity']*$unit,
            'opening_assets_at_parity_frf_minor'=>$b['total']*$unit,'opening_cet1_at_parity_frf_minor'=>$b['cet1']*$unit,'fixed_rwa_at_parity_frf_minor'=>$rwa,
            'modelled_sovereign_slice_equity_delta_frf_minor'=>$delta,'scenario_accounting_equity_frf_minor'=>$equity,'scenario_cet1_frf_minor'=>$cet1,
            'scenario_cet1_bps'=>$ratioBps,'observed_lcr_bps'=>$b['lcr_bps'],'observed_nsfr_bps'=>$b['nsfr_bps'],'capital_depletion_bps'=>$depletion,'credit_availability_bps'=>$credit,
            'classification'=>'observed_aggregate_anchor_plus_modelled_sovereign_slice_sensitivity','rwa_held_fixed'=>true,'actual_future_regulatory_ratio'=>false,'commercial_pilot_added_to_observed_stock'=>false];
    }
    public static function report():array
    {
        $p=self::load();$e=$p['external'];$a=0;$l=0;$countries=[];
        foreach($e['mfi_other_investment_by_country'] as $id=>$v){
            if(!preg_match('/^[A-Z]{2}$/D',$id)||count($v)!==2||min($v)<0)throw new \DomainException('Invalid external country observation');
            $a+=$v[0];$l+=$v[1];$countries[$id]=['assets_eur_minor'=>$v[0]*10000000000,'liabilities_eur_minor'=>$v[1]*10000000000,'net_eur_minor'=>($v[0]-$v[1])*10000000000];
        }
        $total=$e['mfi_other_investment_total'];
        if($a>$total['assets']||$l>$total['liabilities'])throw new \DomainException('External country total mismatch');
        $foreign=$e['public_securities_nonresident_by_year']['2025'];
        return ['version'=>self::VERSION,'source_sha'=>self::digest(),'observations'=>$p,
            'bank_external_other_investment_by_country'=>$countries,'unallocated_bank_external_other_investment_eur_minor'=>['assets'=>($total['assets']-$a)*10000000000,'liabilities'=>($total['liabilities']-$l)*10000000000],
            'sovereign_nonresident_foreign_currency_securities_market_value_eur_minor'=>$foreign['other_currency']*10000000000,
            'sovereign_nonresident_securities_market_value_eur_minor'=>$foreign['total']*10000000000,
            'sovereign_nonresident_other_currency_share_bps'=>intdiv($foreign['other_currency']*10000,$foreign['total']),
            'net_fx_allocation_ceiling_eur_equivalent_minor'=>self::reserveCeiling(),'bank_country_positions_are_not_sovereign_debt'=>true,'currency_observation_not_added_to_existing_debt'=>true,
            'individual_sovereign_creditors_known'=>false,'sovereign_country_rows_retrieved'=>false];
    }
}
