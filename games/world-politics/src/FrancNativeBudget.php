<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancDebtSettlement.php';

/** Counterfactual native budget; fiscal inputs and FX quote are external.
 * All amounts are integer minor units of the indicated currency. */
final class FrancNativeBudget
{
    public const VERSION='wp54r-frf-native-budget-v1';
    private const LIMIT=10000000000000000;
    private static function amount(mixed $v):int
    {if(!is_int($v)||$v<0||$v>self::LIMIT)throw new \DomainException('Invalid native budget amount');return $v;}
    public static function scale(int $n,int $r,int $d,bool $ceil=false):int
    {if($n<0||$n>self::LIMIT||$r<0||$r>100000||$d<1||$d>3650000)throw new \DomainException('Invalid native budget ratio');$q=intdiv($n,$d);$rem=($n%$d)*$r;$tail=intdiv($rem,$d)+($ceil&&$rem%$d!==0?1:0);if($r>0&&$q>intdiv(PHP_INT_MAX-$tail,$r))throw new \DomainException('Native budget ratio overflow');return $q*$r+$tail;}
    public static function stocks(array $claims):array
    {
        $s=['FRF'=>0,'EUR'=>0];foreach($claims as $c){if(!isset($s[$c['currency']]))throw new \DomainException('Invalid claim currency');$s[$c['currency']]+=self::amount($c['principal_outstanding_minor']);}
        foreach($s as $v)self::amount($v);return $s;
    }
    public static function arrears(array $claims):array
    {$out=['FRF'=>['interest'=>0,'principal'=>0],'EUR'=>['interest'=>0,'principal'=>0]];foreach($claims as $c){$out[$c['currency']]['interest']+=self::amount($c['interest_due_minor']);$out[$c['currency']]['principal']+=self::amount($c['principal_due_minor']);}return $out;}
    private static function value(array $stock,int $ratio,int $mark):int
    {return $stock['FRF']+self::scale($stock['EUR'],$ratio*10000,$mark);}
    public static function validateTerms(array $v,string $day,array $eu):void
    {
        $k=array_keys($v);sort($k);$d=$v['effective_on']??null;
        if(!in_array($k,[['effective_on','instrument_sha','reserve_purchase_authorized'],['commit_daily','effective_on','instrument_sha','reserve_purchase_authorized'],['commit_daily','effective_on','instrument_sha','reserve_purchase_authorized','sector_integration'],['commit_daily','effective_on','financial_integration','instrument_sha','reserve_purchase_authorized','sector_integration'],['commit_daily','effective_on','financial_integration','instrument_sha','observed_financial_baseline_sha','reserve_purchase_authorized','sector_integration']],true)|| (array_key_exists('commit_daily',$v)&&$v['commit_daily']!==true)||!is_string($d)||($date=\DateTimeImmutable::createFromFormat('!Y-m-d',$d))===false||$date->format('Y-m-d')!==$d||$d<=$day||!is_bool($v['reserve_purchase_authorized'])||!is_string($v['instrument_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$v['instrument_sha']))throw new \DomainException('Invalid native budget authorization');
        foreach(['franc_fiscal_terms','franc_fx_terms','franc_rule','currency_terms'] as $key)if(!isset($eu[$key])||$d<$eu[$key]['effective_on'])throw new \DomainException('Native budget prerequisites missing');
        if(array_key_exists('sector_integration',$v)&&($v['sector_integration']!==true||($v['commit_daily']??false)!==true))throw new \DomainException('Sector integration requires committed budget');
        if(array_key_exists('financial_integration',$v)&&($v['financial_integration']!==true||($v['sector_integration']??false)!==true))throw new \DomainException('Financial integration requires native sectors');
        if(isset($v['observed_financial_baseline_sha'])){
            require_once __DIR__.'/FrancObservedFinance.php';
            if(($v['financial_integration']??false)!==true||$v['observed_financial_baseline_sha']!==FrancObservedFinance::digest())throw new \DomainException('Invalid pinned financial observation pack');
            FrancObservedFinance::validateAllocation($eu);
        }
        if(!isset($eu['franc_funding_version']))throw new \DomainException('Funding ledger required');
        if(($v['commit_daily']??false)===true)foreach(['franc_fiscal_terms','franc_fx_terms','franc_rule','currency_terms'] as $key)if($d!==$eu[$key]['effective_on'])throw new \DomainException('Committed budget requires aligned prospective transition dates');
    }
    /** Official market order uses the same reserve and counterparty accounts
     * as debt service. Market intervention precedes sovereign settlement. */
    public static function intervene(array $a,int $counterEur,int $counterFrf,?array $order,int $ratio,int $mark):array
    {
        $n=$ratio*10000;$kind=$order['kind']??null;$ask=self::amount($order['eur_minor']??0);$eur=0;$frf=0;
        if($kind==='sell_eur'){
            $eur=min($ask,$a['official_eur_reserves_minor']-$a['minimum_official_eur_minor'],self::scale($counterFrf,$mark,$n));
            $frf=self::scale($eur,$n,$mark,true);$a['official_eur_reserves_minor']-=$eur;$counterEur+=$eur;$a['official_franc_pool_minor']+=$frf;$counterFrf-=$frf;
        }elseif($kind==='buy_eur'){
            $eur=min($ask,$counterEur,self::scale($a['official_franc_pool_minor'],$mark,$n));$frf=self::scale($eur,$n,$mark,true);
            $a['official_eur_reserves_minor']+=$eur;$counterEur-=$eur;$a['official_franc_pool_minor']-=$frf;$counterFrf+=$frf;
        }elseif($order!==null)throw new \DomainException('Invalid market order');
        return ['accounts'=>$a,'counter_eur'=>$counterEur,'counter_frf'=>$counterFrf,'kind'=>$kind,'eur'=>$eur,'frf'=>$frf,'unfilled_eur'=>$ask-$eur];
    }
    public static function step(array $state,array $flow,int $ratio,int $mark,bool $reserveAuthorized,bool $moratorium):array
    {
        if($ratio<1||$ratio>10||$mark<5000||$mark>15000||$state['mark']<5000||$state['mark']>15000)throw new \DomainException('Invalid budget quote');
        $a=$state['accounts'];$claims=$state['claims'];$old=self::stocks($claims);$oldCb=self::amount($state['advance_liability']);
        foreach($a as $v)self::amount($v);if($a['official_eur_reserves_minor']<$a['minimum_official_eur_minor'])throw new \DomainException('Invalid reserve floor');
        $cpE=self::amount($state['counter_eur']);$cpF=self::amount($state['counter_frf']);
        $revenue=self::amount($flow['revenue']);$primaryDue=self::amount($state['primary_arrears'])+self::amount($flow['primary_expense']);
        $advance=self::amount($flow['advance']);$repay=self::amount($flow['repay']);$equityAsk=self::amount($flow['equity']);
        if($repay>$oldCb||($advance>0&&$repay>0))throw new \DomainException('Invalid earmarked advance flows');
        // Central-bank issuance already financed beneficiaries; a matched
        // budget receipt/outlay is recorded exactly once, not free cash.
        $cb=$oldCb+$advance-$repay;if($cb!==$flow['closing_advance'])throw new \DomainException('Unreconciled advance liability');
        $issued=['FRF'=>0,'EUR'=>0];$written=['FRF'=>0,'EUR'=>0];
        foreach($flow['haircuts'] as $id=>$v){$v=self::amount($v);if(!isset($claims[$id])||$v>$claims[$id]['principal_due_minor'])throw new \DomainException('Invalid haircut');$claims[$id]['principal_due_minor']-=$v;$claims[$id]['principal_outstanding_minor']-=$v;$written[$claims[$id]['currency']]+=$v;}
        foreach(['coupons'=>'interest_due_minor','maturities'=>'principal_due_minor'] as $field=>$due){foreach($flow[$field] as $id=>$v){if(!isset($claims[$id]))throw new \DomainException('Unknown scheduled claim');$claims[$id][$due]+=self::amount($v);}}
        foreach($claims as $c)if($c['principal_due_minor']>$c['principal_outstanding_minor'])throw new \DomainException('Maturity exceeds existing principal');
        foreach($flow['issues'] as $id=>$issue){$cur=$issue['currency'];$v=self::amount($issue['amount']);if(!isset($issued[$cur]))throw new \DomainException('Invalid issue currency');$claims[$id]??=['currency'=>$cur,'principal_outstanding_minor'=>0,'principal_due_minor'=>0,'interest_due_minor'=>0];if($claims[$id]['currency']!==$cur)throw new \DomainException('Issue currency mismatch');$claims[$id]['principal_outstanding_minor']+=$v;$issued[$cur]+=$v;}
        $a['treasury_frf_minor']+=$revenue+$issued['FRF'];$a['treasury_eur_minor']+=$issued['EUR'];
        $primaryPaid=min($primaryDue,$a['treasury_frf_minor']);$a['treasury_frf_minor']-=$primaryPaid;
        $fx=self::intervene($a,$cpE,$cpF,$flow['fx_order'],$ratio,$mark);
        $settled=FrancDebtSettlement::step($fx['accounts'],$claims,$ratio,$mark,$reserveAuthorized,$moratorium);
        $a=$settled['closing_accounts'];$claims=$settled['closing_claims'];$new=self::stocks($claims);$arrears=self::arrears($claims);
        $paid=['FRF'=>0,'EUR'=>0];foreach($settled['creditor_receipts'] as $v)$paid[$v['currency']]+=$v['principal_paid_minor'];
        $primaryArrears=$primaryDue-$primaryPaid;$current=$primaryArrears===0&&array_sum($arrears['FRF'])===0&&array_sum($arrears['EUR'])===0&&!$moratorium;
        $equityPaid=$current&&$equityAsk<=$a['treasury_frf_minor']?$equityAsk:0;$a['treasury_frf_minor']-=$equityPaid;
        $receipts=$settled['creditor_receipts_by_currency_minor'];$opening=$state['accounts'];
        foreach(['FRF','EUR'] as $cur)if($new[$cur]!==$old[$cur]+$issued[$cur]-$paid[$cur]-$written[$cur])throw new \DomainException('Native debt stock mismatch');
        if($a['treasury_frf_minor']+$a['official_franc_pool_minor']+$fx['counter_frf']+$receipts['FRF']!==$opening['treasury_frf_minor']+$opening['official_franc_pool_minor']+$cpF+$revenue+$issued['FRF']-$primaryPaid-$equityPaid||$a['treasury_eur_minor']+$a['official_eur_reserves_minor']+$fx['counter_eur']+$receipts['EUR']!==$opening['treasury_eur_minor']+$opening['official_eur_reserves_minor']+$cpE+$issued['EUR'])throw new \DomainException('Native budget cash conservation failure');
        $openingValue=self::value($old,$ratio,$state['mark'])+$oldCb;$marked=self::value($old,$ratio,$mark)+$oldCb;$closingValue=self::value($new,$ratio,$mark)+$cb;
        return ['version'=>self::VERSION,'closing_state'=>['accounts'=>$a,'claims'=>$claims,'counter_eur'=>$fx['counter_eur'],'counter_frf'=>$fx['counter_frf'],'primary_arrears'=>$primaryArrears,'advance_liability'=>$cb,'mark'=>$mark],
            'opening_accounts'=>$opening,'opening_principal'=>$old,'closing_principal'=>$new,'issued_principal'=>$issued,'paid_principal'=>$paid,'written_off_principal'=>$written,'arrears'=>$arrears,
            'budget'=>['ordinary_revenue_frf_minor'=>$revenue,'ordinary_primary_paid_frf_minor'=>$primaryPaid,'primary_arrears_frf_minor'=>$primaryArrears,'equity_paid_frf_minor'=>$equityPaid,'advance_received_frf_minor'=>$advance,'advance_funded_spending_frf_minor'=>$advance,'levy_received_frf_minor'=>$repay,'advance_repaid_frf_minor'=>$repay,'opening_advance_liability_frf_minor'=>$oldCb,'closing_advance_liability_frf_minor'=>$cb],
            'valuation'=>['opening_gross_debt_frf_minor'=>$openingValue,'closing_gross_debt_frf_minor'=>$closingValue,'fx_revaluation_frf_minor'=>$marked-$openingValue,'transaction_delta_frf_minor'=>$closingValue-$marked],
            'fx_intervention'=>array_diff_key($fx,['accounts'=>true,'counter_eur'=>true,'counter_frf'=>true]),'debt_settlement'=>$settled,'debt_service_current'=>$current,'national_reference_ledger_changed'=>false];
    }
}
