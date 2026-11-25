<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FinanceBudget.php';

/** WP47 — deterministic read-side macro model, not national statistics.
 * Daily flows in EUR minor units, indexes/rates in basis points.
 * All transmission coefficients are fictional and version-pinned.
 */
final class MacroeconomicProjection
{
    public const VERSION='wp47-macro-v1';
    private const BASE_OUTPUT=25000000;
    private const BASE_REVENUE=10000000;
    private const BASE_EXPENSE=11000000;
    public static function assumptions():array
    {
        return ['version'=>self::VERSION,'classification'=>'fictional_scenario','currency'=>'EUR','unit'=>'minor',
            'baseline_daily_real_gdp'=>self::BASE_OUTPUT,'baseline_daily_consumption'=>13000000,
            'baseline_daily_investment'=>3000000,'baseline_daily_government_consumption'=>8000000,
            'baseline_daily_exports'=>5000000,'baseline_daily_imports'=>4000000,
            'baseline_daily_revenue'=>self::BASE_REVENUE,'baseline_daily_primary_expense'=>self::BASE_EXPENSE,
            'initial_price_index_bps'=>10000,'initial_unemployment_bps'=>750,
            'initial_annual_inflation_bps'=>200,'trade_exposure_cap_bps'=>2000,
            'trade_exposure_pass_through_divisor'=>10,'daily_tax_consumption_pass_through_bps'=>5000,
            'public_spending_consumption_share_bps'=>5000,'revenue_proxy_elasticity_bps'=>8000,
            'annual_interest_bps'=>300,'opening_debt'=>2000000000,
            'annualization_days'=>365,'not_a_forecast'=>true];
    }
    private static function clamp(int $v,int $lo,int $hi):int{return max($lo,min($hi,$v));}
    private static function weight(int $v,int $bps):int{return intdiv($v,10000)*$bps+intdiv(($v%10000)*$bps,10000);}
    private static function towards(int $v,int $t,int $divisor):int
    {return $v===$t?$v:$v+($t>$v?1:-1)*max(1,intdiv(abs($t-$v),$divisor));}
    /** Sources must first be verified by their domain stores. No USD-to-EUR
     * conversion: policy bps only enter a bounded scenario exposure proxy.
     */
    public static function project(array $dom,array $econ,array $finance):array
    {
        if($dom['world_id']!==$econ['world_id']||$dom['day']!==$econ['day']
            ||$finance['version']!==FinanceBudget::VERSION||count($dom['days'])>3650)
            throw new \DomainException('Macro source scope/date mismatch');
        $treasury=FinanceBudget::project($finance,$dom);
        $history=[];$price=100000000;$unemployment=750;$inflation=200;$previous=self::BASE_OUTPUT;
        $total=0;$nominalTotal=0;$potentialRevenue=0;$known=[];$missing=[];
        foreach($econ['projections'] as $id=>$p){
            $policy=$econ['policies'][$id]??throw new \DomainException('Missing trade policy');
            if($policy['from']!=='state_fr'&&$policy['to']!=='state_fr')continue;
            if($policy['status']!=='effective'||$p['effective_on']!==$policy['effective_on']||$p['effective_on']>$econ['day'])
                throw new \DomainException('Unverified trade projection');
            if($p['central_usd']===null)$missing[]=$id;
            else $known[$id]=['day'=>$p['effective_on'],'bps'=>$p['model_assumption_bps'],
                'outbound'=>$policy['from']==='state_fr'];
        }
        sort($missing,SORT_STRING);
        $priorDay=null;
        foreach($dom['days'] as $i=>$d){
            if($priorDay!==null&&$d['day']!==(new \DateTimeImmutable($priorDay))->modify('+1 day')->format('Y-m-d'))
                throw new \DomainException('Nonconsecutive macro ledger');
            $priorDay=$d['day'];
            $tradeExport=0;$tradeImport=0;
            foreach($known as $p)if($p['day']<=$d['day']){
                if($p['outbound'])$tradeExport+=$p['bps'];else $tradeImport+=$p['bps'];
            }
            // A bilateral restriction does not imply an equivalent aggregate
            // trade loss; use a deliberately damped and capped exposure.
            $xbps=min(2000,intdiv($tradeExport,10));$mbps=min(2000,intdiv($tradeImport,10));
            $taxDelta=$d['revenue']-self::BASE_REVENUE;
            $spendDelta=$d['expense']-self::BASE_EXPENSE;
            $production=$d['economic']['production_index_bps']??10000;
            $employment=$d['economic']['employment_index_bps']??10000;
            $c=max(0,13000000-intdiv($taxDelta,2)+self::weight(13000000,self::clamp($employment-10000,-2000,2000))-self::weight(13000000,intdiv($mbps,2)));
            $invest=max(0,3000000+self::weight(3000000,self::clamp($production-10000,-2000,2000))-self::weight(3000000,intdiv($xbps+$mbps,2)));
            $g=max(0,8000000+intdiv($spendDelta,2));
            $x=5000000-self::weight(5000000,$xbps);
            $m=4000000-self::weight(4000000,$mbps);
            $real=$c+$invest+$g+$x-$m;
            if($real<=0)throw new \DomainException('Nonpositive fictional output');
            $gap=self::clamp(intdiv(($real-self::BASE_OUTPUT)*10000,self::BASE_OUTPUT),-3000,3000);
            $inflation=self::towards($inflation,self::clamp(200+intdiv($gap,4)+intdiv($mbps,2),-500,2000),20);
            // Price level carries sub-bps precision, avoiding zero daily
            // change from integer rounding of a small positive annual rate.
            $price=max(10000000,$price+intdiv($price*$inflation,3650000));
            $unemployment=self::towards($unemployment,self::clamp(750-intdiv($gap,4)-intdiv($employment-10000,5),200,2500),30);
            $nominal=intdiv($real*$price,100000000);
            $taxProxy=max(0,$d['revenue']+self::weight(self::BASE_REVENUE,intdiv($gap*8,10)));
            $t=$treasury['days'][$i];
            $total+=$real;$nominalTotal+=$nominal;$potentialRevenue+=$taxProxy;
            $annualOutput=$nominal*365;
            $row=['day'=>$d['day'],'real_gdp'=>$real,'nominal_gdp'=>$nominal,
                'consumption'=>$c,'investment'=>$invest,'government_consumption'=>$g,'exports'=>$x,'imports'=>$m,
                'net_exports'=>$x-$m,'real_output_index_bps'=>intdiv($real*10000,self::BASE_OUTPUT),
                'daily_real_growth_bps'=>intdiv(($real-$previous)*10000,$previous),
                'annual_inflation_bps'=>$inflation,'price_index_bps'=>intdiv($price,10000),'unemployment_bps'=>$unemployment,
                'export_exposure_bps'=>$xbps,'import_exposure_bps'=>$mbps,
                'actual_revenue'=>$d['revenue'],'tax_revenue_proxy'=>$taxProxy,'primary_expense'=>$d['expense'],
                'primary_balance'=>$t['primary_balance'],'interest'=>$t['interest'],'new_borrowing'=>$t['new_borrowing'],
                'cash'=>$t['cash'],'debt'=>$t['debt'],'debt_to_annualized_nominal_gdp_bps'=>intdiv($t['debt']*10000,$annualOutput)];
            $history[]=$row;$previous=$real;
        }
        if($priorDay!==null&&$priorDay!==$dom['day'])throw new \DomainException('Macro ledger not at committed day');
        return ['version'=>self::VERSION,'classification'=>'fictional_macro_projection','world_id'=>$dom['world_id'],
            'day'=>$dom['day'],'assumptions'=>self::assumptions(),'trade_reference_missing'=>$missing,
            'trade_reference_used'=>array_keys($known),'coverage_complete'=>$missing===[],
            'latest'=>$history===[]?null:$history[array_key_last($history)],'days'=>$history,
            'cumulative_real_gdp'=>$total,'cumulative_nominal_gdp'=>$nominalTotal,
            'cumulative_tax_revenue_proxy'=>$potentialRevenue,'not_a_forecast'=>true,
            'feedback_to_domestic_ledger'=>false];
    }
}
