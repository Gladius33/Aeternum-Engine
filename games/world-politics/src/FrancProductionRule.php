<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancPublicFunding.php';
require_once __DIR__.'/FrancCommercialBank.php';

/** Fictional franc monetary-base rule. All output inputs use the fixed
 * real national-accounts price basis, not nominal GDP or gross sales.
 * The account is a monetary pilot, not legal tender or a treasury credit. */
final class FrancProductionRule
{
    public const VERSION='wp54r-franc-real-production-v1';
    public const WINDOW=30;
    public static function validateTerms(array $v,string $day):void
    {
        $keys=array_keys($v);sort($keys);
        if($keys!==['effective_on','instrument_sha','opening_base_franc_minor']
            ||!is_string($v['effective_on'])||($date=\DateTimeImmutable::createFromFormat('!Y-m-d',$v['effective_on']))===false
            ||$date->format('Y-m-d')!==$v['effective_on']||$v['effective_on']<=$day
            ||!is_int($v['opening_base_franc_minor'])||$v['opening_base_franc_minor']<=0||$v['opening_base_franc_minor']>100000000000000
            ||!is_string($v['instrument_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$v['instrument_sha']))
            throw new \DomainException('Invalid franc monetary rule terms');
    }
    // Exact floor(a*b/d) without a national-scale intermediate product.
    private static function scale(int $a,int $b,int $d):int
    {
        if($a<0||$b<0||$d<=0)throw new \DomainException('Invalid production ratio');
        $q=intdiv($a,$d);$r=$a%$d;
        if($b>0&&$q>intdiv(PHP_INT_MAX,$b))throw new \DomainException('Production target exceeds integer capacity');
        $whole=$q*$b;$acc=0;$rem=0;$rq=0;
        while($b>0){
            if($b%2===1){$rem+=$r;$acc+=$rq;if($rem>=$d){$rem-=$d;$acc++;}}
            $b=intdiv($b,2);if($b===0)break;
            $r*=2;$rq*=2;if($r>=$d){$r-=$d;$rq++;}
        }
        if($acc>PHP_INT_MAX-$whole)throw new \DomainException('Production target exceeds integer capacity');
        return $whole+$acc;
    }
    private static function avg(array $values):int{return intdiv(array_sum($values),count($values));}
    public static function project(array $eu,array $dom):array
    {
        if(($eu['franc_rule_version']??null)!==self::VERSION||$eu['day']!==$dom['day'])throw new \DomainException('Unpinned or incoherent franc rule');
        $terms=$eu['franc_rule'];$currency=$eu['currency_terms'];
        $start=$terms!==null&&$currency!==null&&!$eu['membership']?max($terms['effective_on'],$currency['effective_on'],$eu['exit_on']):null;
        $active=$start!==null&&$dom['day']>=$start;
        $anchor=null;$stock=$terms['opening_base_franc_minor']??null;$history=[];$prior=[];
        $funding=isset($eu['franc_funding_version']);$anchorSource=null;
        $lastDay=null;
        foreach($dom['days'] as $row){
            $d=$row['day'];$g=$row['macro']['real_gdp']??null;
            if(!is_int($g)||$g<=0||$g>10000000000000||($lastDay!==null&&$d!==(new \DateTimeImmutable($lastDay))->modify('+1 day')->format('Y-m-d'))||$d>$dom['day'])
                throw new \DomainException('Invalid committed real production history');
            $lastDay=$d;
            if($active&&$d>=$start){
                // Opening parity uses observed pre-transition output. Never
                // use the day's own GDP to finance the same day's production.
                if($prior===[]){
                    // Only the new funding model permits first-tick transition.
                    // The reference is fixed-price daily GDP from pinned world
                    // assumptions, never the current day's generated GDP.
                    $output=$dom['macro_baseline']['daily_real_gdp']??null;
                    if(!$funding||!is_int($output)||$output<=0||$output>10000000000000)
                        throw new \DomainException('Missing prior real output anchor');
                    $anchorSource='pinned_opening_real_gdp';
                }else{
                    $output=self::avg($prior);
                    if($anchor===null)$anchorSource='prior_committed_real_gdp';
                }
                $anchor??=$output;
                $target=self::scale($terms['opening_base_franc_minor'],$output,$anchor);
                $opening=$stock;$issue=max(0,$target-$stock);
                // No automatic confiscation or assumed repayment in recession:
                // excess stock is recorded, future issuance suspended.
                $stock+=$issue;
                $history[]=['day'=>$d,'lagged_real_output_daily_minor'=>$output,
                    'sample_days'=>count($prior),'target_base_franc_minor'=>$target,
                    'opening_base_franc_minor'=>$opening,'issued_franc_minor'=>$issue,
                    'closing_base_franc_minor'=>$stock,'excess_base_franc_minor'=>max(0,$stock-$target),
                    'cumulative_issued_franc_minor'=>$stock-$terms['opening_base_franc_minor'],
                    'central_bank_incremental_liability_franc_minor'=>$stock-$terms['opening_base_franc_minor'],
                    'central_bank_incremental_claim_franc_minor'=>$stock-$terms['opening_base_franc_minor']];
            }
            $prior[]=$g;if(count($prior)>self::WINDOW)array_shift($prior);
        }
        if($active&&($history===[]||$history[array_key_last($history)]['day']!==$dom['day']))throw new \DomainException('Missing current production ledger');
        $settled=$funding?FrancPublicFunding::settle($history,$terms['opening_base_franc_minor']??null,$eu['franc_funding_orders']):null;
        if($funding)$history=$settled['rows'];
        $result=['version'=>self::VERSION,'classification'=>'fictional_production_indexed_monetary_base_pilot',
            'currency_name'=>'Franc','internal_currency_code'=>'XFR','status'=>$terms===null?'not_configured':($active?'active':'pending_effective_date'),
            'effective_from'=>$start,'production_measure'=>'real_gdp_fixed_price_value_added','lagged_window_days'=>self::WINDOW,
            'anchor_real_output_daily_minor'=>$anchor,'opening_base_franc_minor'=>$terms['opening_base_franc_minor']??null,
            'latest'=>$history===[]?null:$history[array_key_last($history)],'days'=>$history,
            'issuance_rule'=>$funding?'certified_spending_within_positive_production_gap':'positive_gap_to_production_target_only','contraction_rule'=>'suspend_issuance_and_report_excess',
            'incremental_claim_classification'=>'fictional_central_bank_advance','treasury_spending_booked'=>false,
            'national_currency_changed'=>false,'broad_money_modelled'=>false,'fx_peg_implied'=>false,
            'price_stability_guaranteed'=>false,...($funding?['public_funding_version'=>FrancPublicFunding::VERSION,'production_anchor_source'=>$anchorSource,'pilot_public_funding_settled'=>$active,'executed_order_days'=>$settled['executed_order_days'],'opening_legacy_balance_sheet_unmodelled'=>true,'actual_eur_treasury_booked'=>false]:[]),'not_a_forecast'=>true];
        if(isset($eu['franc_bank_version']))$result['commercial_banking']=FrancCommercialBank::project($eu,$result);
        return $result;
    }
}
