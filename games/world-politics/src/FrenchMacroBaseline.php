<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/EuropeanTradeFeedback.php';
require_once __DIR__.'/FrancMacroFeedback.php';

/** WP48: version-pinned observations and a lagged, fictional fiscal loop.
 * The observations are not predictions, nor all dated to the game start.
 * EUR minor units, integer arithmetic, no external service at runtime.
 */
final class FrenchMacroBaseline
{
    public const VERSION='wp48-fr-baseline-v1';
    public static function load():array
    {
        $pack=json_decode(file_get_contents(__DIR__.'/../data/2026-11-25-france-observations-v2.json'),true,32,JSON_THROW_ON_ERROR);
        return self::build($pack);
    }
    public static function build(array $pack):array
    {
        if(($pack['schema']??null)!=='wp-fr-reference-v2'||($pack['country']??null)!=='FR'
            ||($pack['as_of']??null)!=='2026-11-25'||!is_array($pack['observations']??null)||count($pack['observations'])>256)
            throw new \DomainException('Invalid French source pack');
        $rows=[];
        foreach($pack['observations'] as $o){
            if(($o['kind']??null)!=='observed'||!is_int($o['value']??null)||!is_string($o['metric']??null)
                ||!is_string($o['period']??null)||!is_string($o['unit']??null)||!isset($pack['sources'][$o['source_id']??'']))
                throw new \DomainException('Unattributed observation');
            $src=$pack['sources'][$o['source_id']];
            if(!is_string($src['url']??null)||!str_starts_with($src['url'],'https://www.insee.fr/')
                ||($src['published_on']!==null&&$src['published_on']>$pack['as_of'])
                ||($o['released_on']??$pack['as_of'])>$pack['as_of'])
                throw new \DomainException('Unverified observation source/date');
            $k=$o['metric'].'/'.$o['period'];
            if(isset($rows[$k]))throw new \DomainException('Duplicate French observation');
            $rows[$k]=$o;
        }
        $get=function(string $metric,string $period,string $unit)use($rows):int{
            $r=$rows[$metric.'/'.$period]??throw new \DomainException('Missing required observation');
            if($r['unit']!==$unit||$r['value']<0)throw new \DomainException('Wrong observation unit');
            return $r['value'];
        };
        $annual=fn(string $k):int=>$get($k,'2025','EUR');
        $gdp=$annual('gdp_nominal');$c=$annual('household_consumption');$npish=$annual('npish_consumption');
        $g=$annual('government_individual_consumption')+$annual('government_collective_consumption');
        $i=$annual('gross_fixed_capital_formation')+$annual('inventory_change');
        $x=$annual('exports_goods_services');$m=$annual('imports_goods_services');
        $residual=$gdp-($c+$npish+$i+$g+$x-$m);
        if(abs($residual)>1000000000)throw new \DomainException('Excessive national accounts residual');
        $daily=fn(int $v):int=>intdiv($v*100,365);
        $revenue=$annual('public_revenue');$expense=$annual('public_expenditure');$interest=$annual('public_interest_excluding_sifim_correction');
        if($revenue-$expense!==-$annual('public_deficit')||$interest>$expense)throw new \DomainException('Public accounts mismatch');
        $b=['version'=>self::VERSION,'classification'=>'observed_anchors_with_fictional_initialization',
            'source_sha'=>hash('sha256',json_encode($pack,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),
            'source_pack'=>$pack,'currency'=>'EUR','unit'=>'minor','annual_reference_period'=>'2025',
            'daily_real_gdp'=>$daily($gdp),'daily_consumption'=>$daily($c),'daily_npish_consumption'=>$daily($npish),'daily_investment'=>$daily($i),
            'daily_government_consumption'=>$daily($g),'daily_exports'=>$daily($x),'daily_imports'=>$daily($m),
            'annual_rounding_residual_eur'=>$residual,
            'daily_revenue'=>$daily($revenue),'daily_primary_expense'=>$daily($expense-$interest),
            'daily_tax_base'=>$daily($annual('public_taxes_and_social_contributions')),
            'daily_social_benefits'=>$daily($annual('public_social_benefits')),
            'opening_debt'=>$get('public_gross_debt','2026-Q2','EUR')*100,'opening_debt_period'=>'2026-Q2',
            'initial_unemployment_bps'=>$get('unemployment_ilo','2026-Q3','basis_points'),
            'initial_annual_inflation_bps'=>$get('consumer_price_yoy','2026-10','basis_points'),
            'assumed_opening_cash'=>1000000000000,'assumed_annual_interest_bps'=>200,
            'coefficient_status'=>'fictional_uncalibrated','tax_elasticity_bps'=>8000,
            'benefit_unemployment_sensitivity_bps'=>3000,'benefit_price_pass_through_bps'=>5000];
        $b['daily_statistical_discrepancy']=$b['daily_real_gdp']-($b['daily_consumption']+$b['daily_npish_consumption']+$b['daily_investment']
            +$b['daily_government_consumption']+$b['daily_exports']-$b['daily_imports']);
        return $b;
    }
    public static function assertValid(array $b):void
    {
        if(!isset($b['source_pack'])||self::build($b['source_pack'])!==$b)
            throw new \DomainException('French baseline replay mismatch');
    }
}

final class MacroFiscalLoop
{
    public const VERSION='wp48-fiscal-loop-v1';
    private static function clamp(int $n,int $min,int $max):int{return max($min,min($max,$n));}
    private static function scale(int $n,int $ratio,int $denom=10000):int
    {return intdiv($n,$denom)*$ratio+intdiv(($n%$denom)*$ratio,$denom);}
    private static function move(int $v,int $target,int $div):int
    {return $v===$target?$v:$v+($target>$v?1:-1)*max(1,intdiv(abs($target-$v),$div));}
    private static function gap(int $actual,int $base):int
    {return intdiv(($actual-$base)*10000,$base);}
    /** Called after statutory policy deltas, using only the prior committed
     * day's macro row. No uncommitted values, implicit circular equation
     * or read-side mutation. No trade/monetary feedback in this first loop.
     */
    public static function step(array $b,?array $previous,int $cash,int $revenue,int $expense,int $production,int $employment,?array $debtModel=null,?string $target=null,?string $crisisAction=null,int $serviceTransferDelta=0,?int $wagePremiumBps=null,?int $tradeExposureBps=null,int $industrialCapex=0,?array $eu=null):array
    {
        if(!empty($debtModel['crisis_enabled'])){
            $credit=$previous['treasury']['banking']['credit_availability_bps']??10000;
            $production-=intdiv(10000-$credit,2);
            $employment-=intdiv(10000-$credit,4);
        }
        $prior=$previous['macro']??null;$debt=$previous['treasury']['debt']??$b['opening_debt'];
        $price=$prior['price_index_scaled']??100000000;
        $wage=$prior['wage_index_scaled']??100000000;
        $unemployment=$prior['unemployment_bps']??$b['initial_unemployment_bps'];
        $inflation=$prior['annual_inflation_bps']??$b['initial_annual_inflation_bps'];
        $priorReal=$prior['real_gdp']??$b['daily_real_gdp'];
        $priorNominal=$prior['nominal_gdp']??$b['daily_real_gdp'];
        $nominalGap=self::clamp(self::gap($priorNominal,$b['daily_real_gdp']),-3000,3000);
        $taxFeedback=self::scale($b['daily_tax_base'],self::scale($nominalGap,$b['tax_elasticity_bps']));
        $jobGap=$unemployment-$b['initial_unemployment_bps'];
        $payroll=null;$costGap=0;
        if($wagePremiumBps!==null){
            if($wagePremiumBps<0||$wagePremiumBps>3000)throw new \DomainException('Invalid wage premium');
            // Split the existing tax base rather than adding a second tax on GDP.
            $payrollTaxBase=intdiv($b['daily_tax_base'],3);
            $employmentGap=self::clamp(-$jobGap,-3000,3000);
            $nominalWageGap=self::clamp(intdiv($wage-100000000,10000),-3000,5000);
            $payrollGap=self::clamp($nominalWageGap+$employmentGap+self::scale($nominalWageGap,$employmentGap),-5000,7000);
            $payrollTaxFeedback=self::scale($payrollTaxBase,$payrollGap);
            $taxFeedback=self::scale($b['daily_tax_base']-$payrollTaxBase,self::scale($nominalGap,$b['tax_elasticity_bps']))+$payrollTaxFeedback;
            $realisedPremium=max(0,intdiv($wage*10000,$price)-10000);
            $productivityProxy=max(0,min(3000,($prior['real_output_index_bps']??10000)-10000));
            $costGap=max(0,$realisedPremium-$productivityProxy);
            $production-=intdiv($costGap,2);$employment-=intdiv($costGap,2);
            $basePayroll=self::scale($b['daily_real_gdp'],3500);
            $grossPayroll=self::scale($basePayroll,10000+$payrollGap);
            $payroll=['classification'=>'fictional_private_payroll_proxy','target_premium_bps'=>$wagePremiumBps,
                'realised_real_wage_premium_bps'=>$realisedPremium,'gross_daily_payroll_minor'=>$grossPayroll,
                'employer_daily_cost_proxy_minor'=>$grossPayroll+self::scale($grossPayroll,3000),
                'household_net_pay_proxy_minor'=>$grossPayroll-self::scale($grossPayroll,2000),
                'payroll_tax_base_minor'=>$payrollTaxBase,'payroll_tax_feedback_minor'=>$payrollTaxFeedback,
                'nonpayroll_tax_feedback_minor'=>$taxFeedback-$payrollTaxFeedback,'labour_cost_gap_bps'=>$costGap];
        }
        $trade=$tradeExposureBps??0;
        if($trade<0||$trade>2000)throw new \DomainException('Invalid trade exposure');
        $employment-=intdiv($trade,2);$production-=intdiv($trade,2);
        $socialFeedback=self::scale($b['daily_social_benefits'],self::scale($jobGap,$b['benefit_unemployment_sensitivity_bps'])
            +self::scale(self::clamp(intdiv($price-100000000,10000),-2000,4000),$b['benefit_price_pass_through_bps']));
        $actualRevenue=max(0,$revenue+$taxFeedback);$primaryExpense=max(0,$expense+$socialFeedback);
        $taxFeedback=$actualRevenue-$revenue;$socialFeedback=$primaryExpense-$expense;
        $realWageBps=intdiv($wage*10000,$price);
        $consumption=max(0,$b['daily_consumption']-intdiv($revenue-$b['daily_revenue'],2)
            +self::scale($b['daily_consumption'],self::clamp($realWageBps-10000-intdiv($jobGap,2)+intdiv($employment-10000,2),-2500,2500))
            +intdiv($socialFeedback,3)-self::scale($b['daily_consumption'],intdiv($trade,2)));
        $investment=max(0,$b['daily_investment']+self::scale($b['daily_investment'],
            self::clamp(intdiv(self::gap($priorReal,$b['daily_real_gdp']),3)+$production-10000,-2500,2500)));
        if($industrialCapex<0||$industrialCapex>$expense)throw new \DomainException('Invalid industrial capital expense');
        $realCapex=self::scale($industrialCapex,100000000,$price);
        $investment+=$realCapex;
        $government=max(0,$b['daily_government_consumption']+intdiv($expense-$industrialCapex-$b['daily_primary_expense']-$serviceTransferDelta,2));
        $exports=$b['daily_exports']-self::scale($b['daily_exports'],$trade);
        $ordinaryImports=$b['daily_imports']-self::scale($b['daily_imports'],$trade);
        $fx=null;$fxMark=$eu['frf_value_index_bps']??null;
        if($fxMark!==null){
            $priorMark=$prior['frf_feedback']['value_index_bps']??10000;
            $fx=FrancMacroFeedback::volumes($priorMark,$exports,$ordinaryImports,$consumption,max(0,$investment-$realCapex),$priorReal,$b['daily_real_gdp']);
            $ordinaryImports-=$fx['real_import_loss_minor'];$exports+=$fx['real_export_delta_minor'];
            $consumption-=$fx['real_consumption_shortfall_minor'];$investment-=$fx['real_investment_shortfall_minor'];
            $fx=['version'=>FrancMacroFeedback::VERSION,'value_index_bps'=>$fxMark,...$fx];
        }
        $euResult=$eu===null?null:EuropeanTradeFeedback::settle($eu,$exports,$ordinaryImports,$price);
        if($euResult!==null){
            $exports-=$euResult['real_export_loss_minor'];$ordinaryImports-=$euResult['real_import_loss_minor'];
            $actualRevenue+=$euResult['customs_revenue_minor'];
            if(in_array($eu['version'],[EuropeanTradeFeedback::VERSION_V2,EuropeanTradeFeedback::VERSION_V3],true)){
                // No import substitution has been financed or produced in this
                // channel. Missing imported goods reduce final demand by the
                // same real value, not create domestic value added mechanically.
                $loss=$euResult['real_import_loss_minor'];
                $cLoss=min($consumption,intdiv($loss*3,5));$iLoss=min(max(0,$investment-$realCapex),$loss-$cLoss);
                $cLoss+=min($consumption-$cLoss,$loss-$cLoss-$iLoss);
                if($cLoss+$iLoss!==$loss)throw new \DomainException('Unallocated import demand loss');
                $consumption-=$cLoss;$investment-=$iLoss;
                $euResult['real_consumption_import_shortfall_minor']=$cLoss;
                $euResult['real_investment_import_shortfall_minor']=$iLoss;
                $euResult['automatic_import_substitution_minor']=0;
                $consumption=max(0,$consumption-intdiv($euResult['real_import_cost_minor'],2));
                $investment=max($realCapex,$investment-intdiv($euResult['real_export_loss_minor'],4));
            }else{
                $consumption=max(0,$consumption-intdiv($euResult['real_import_cost_minor'],2)-intdiv($euResult['real_import_loss_minor'],2));
                $investment=max($realCapex,$investment-intdiv($euResult['real_export_loss_minor'],4));
            }
        }
        $imports=$ordinaryImports+self::scale($realCapex,3000);
        $real=$consumption+$b['daily_npish_consumption']+$investment+$government+$exports-$imports+$b['daily_statistical_discrepancy'];
        if($real<=0)throw new \DomainException('Nonpositive output');
        $outputGap=self::clamp(self::gap($real,$b['daily_real_gdp']),-3000,3000);
        $inflation=self::move($inflation,self::clamp($b['initial_annual_inflation_bps']+intdiv($outputGap,4)+intdiv($costGap,3)+intdiv($trade,2)+intdiv($eu['import_duty_bps']??0,4)+intdiv($eu['import_friction_bps']??0,4),-500,2000),20);
        $price=max(10000000,$price+intdiv($price*$inflation,3650000));
        if($fx!==null){
            $fp=FrancMacroFeedback::price($fxMark,$priorMark,$price);$price=$fp['price'];unset($fp['price']);
            $fx+=$fp;
        }
        $wage=self::move($wage,$wagePremiumBps===null?$price:self::scale($price,10000+$wagePremiumBps),180);
        $unemployment=self::move($unemployment,self::clamp($b['initial_unemployment_bps']-intdiv($outputGap,4)-intdiv($employment-10000,5),100,3000),30);
        $nominal=self::scale($real,$price,100000000);
        $interest=self::scale($debt,$b['assumed_annual_interest_bps'],3650000);
        $primary=$actualRevenue-$primaryExpense;
        $unfinanced=$cash+$primary-$interest;
        $borrow=max(0,-$unfinanced);$closing=$unfinanced+$borrow;$closingDebt=$debt+$borrow;
        if($closingDebt>1000000000000000||$closing>1000000000000000)throw new \DomainException('Treasury magnitude limit');
        $treasury=['opening_cash'=>$cash,'opening_debt'=>$debt,'primary_balance'=>$primary,'interest'=>$interest,
            'new_borrowing'=>$borrow,'cash'=>$closing,'debt'=>$closingDebt];
        if($debtModel!==null){
            if($target===null)throw new \DomainException('Missing debt clock day');
            $treasury=SovereignDebtLedger::step($debtModel,$target,$previous,$cash,$actualRevenue,$primaryExpense,$crisisAction);
            $closing=$treasury['cash'];
        }
        if($euResult!==null)$treasury['customs_revenue_minor']=$euResult['customs_revenue_minor'];
        return ['revenue'=>$actualRevenue,'expense'=>$primaryExpense,'balance'=>$closing,
            'macro'=>['version'=>self::VERSION,'real_gdp'=>$real,'nominal_gdp'=>$nominal,'consumption'=>$consumption,
                'npish_consumption'=>$b['daily_npish_consumption'],'investment'=>$investment,'government_consumption'=>$government,'exports'=>$exports,'imports'=>$imports,
                'statistical_discrepancy'=>$b['daily_statistical_discrepancy'],'real_output_index_bps'=>self::gap($real,$b['daily_real_gdp'])+10000,
                'daily_real_growth_bps'=>self::gap($real,$priorReal),'price_index_scaled'=>$price,'wage_index_scaled'=>$wage,
                'annual_inflation_bps'=>$inflation,'unemployment_bps'=>$unemployment,'real_wage_index_bps'=>intdiv($wage*10000,$price),
                'statutory_revenue'=>$revenue,'statutory_primary_expense'=>$expense,'tax_feedback'=>$taxFeedback,
                'social_expense_feedback'=>$socialFeedback,'lagged_nominal_gap_bps'=>$nominalGap,'lagged_unemployment_gap_bps'=>$jobGap,...($payroll===null?[]:['corporation_payroll'=>$payroll]),...($tradeExposureBps===null?[]:['trade_exposure_bps'=>$trade]),...($industrialCapex===0?[]:['industrial_capex_minor'=>$industrialCapex,'industrial_real_investment_minor'=>$realCapex,'industrial_real_imports_minor'=>self::scale($realCapex,3000)]),...($euResult===null?[]:['eu_trade'=>$euResult]),...($fx===null?[]:['frf_feedback'=>$fx])],
            'treasury'=>$treasury];
    }
    public static function project(array $dom):array
    {
        $b=$dom['macro_baseline'];$rows=[];
        foreach($dom['days'] as $d)$rows[]=['day'=>$d['day'],...$d['macro'],...$d['treasury'],
            'actual_revenue'=>$d['revenue'],'primary_expense'=>$d['expense']];
        return ['version'=>self::VERSION,'classification'=>'fictional_simulation_observed_anchors','world_id'=>$dom['world_id'],
            'day'=>$dom['day'],'baseline'=>$b,'latest'=>$rows===[]?null:$rows[array_key_last($rows)],'days'=>$rows,
            'feedback_to_domestic_ledger'=>true,'trade_feedback_enabled'=>isset($dom['trade_feedback_version']),'not_a_forecast'=>true];
    }
}
