<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancObservedFinance.php';
require_once __DIR__.'/FrancCreditorGeography.php';
require_once __DIR__.'/FinancialCrisis.php';
require_once __DIR__.'/FrancFiscalLedger.php';
require_once __DIR__.'/FrancCommittedBudget.php';

/** WP49: closed fictional nominal debt ledger anchored to public aggregates.
 * Holder allocations and maturity cohorts are synthetic, not security-level
 * observations. No real-world creditor targeting or seizure data. */
final class SovereignDebtLedger
{
    public const VERSION='wp49-debt-v1';
    public static function load(array $baseline,string $start,bool $crisis=false):array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-25-fr-sovereign-debt-staging-v1.json'),true,32,JSON_THROW_ON_ERROR);
        return self::build($p,$baseline,$start,$crisis);
    }
    // Exact floor(a*b/d) for nonnegative bounded integers, b <= d.
    // Avoid overflowing PHP integers on national-scale minor units.
    private static function share(int $a,int $b,int $d):int
    {
        if($a<0||$b<0||$d<=0||$b>$d)throw new \DomainException('Invalid allocation');
        $q=intdiv($a,$d)*$b;$r=$a%$d;$acc=0;$rem=0;$q0=0;
        while($b>0){
            if($b%2===1){$rem+=$r;$acc+=$q0;if($rem>=$d){$rem-=$d;$acc++;}}
            $b=intdiv($b,2);
            if($b===0)break;
            $r*=2;$q0=2*$q0;
            if($r>=$d){$r-=$d;$q0++;}
        }
        return $q+$acc;
    }
    private static function split(int $total,array $weights):array
    {
        $sum=array_sum($weights);$cum=0;$prev=0;$out=[];
        if($sum===0){if($total!==0)throw new \DomainException('Missing weights');return array_fill_keys(array_keys($weights),0);}
        foreach($weights as $k=>$w){$cum+=$w;$n=self::share($total,$cum,$sum);$out[$k]=$n-$prev;$prev=$n;}
        return $out;
    }
    public static function build(array $p,array $baseline,string $start,bool $crisis=false):array
    {
        if(($p['schema']??null)!=='wp-fr-sovereign-debt-staging-v1'||(($p['as_of']??null)!==$start&&!($start==='2027-05-14'&&($p['as_of']??null)==='2026-11-25'))
            ||!is_array($p['observations']??null)||count($p['observations'])>200)
            throw new \DomainException('Invalid debt source pack');
        $rows=[];
        foreach($p['observations'] as $o){
            if(!is_int($o['value']??null)||!is_string($o['metric']??null)||!is_string($o['period']??null)
                ||!in_array($o['kind']??null,['observed','forecast'],true)
                ||!is_string($p['sources'][$o['source_id']??'']['url']??null)
                ||!str_starts_with($p['sources'][$o['source_id']]['url'],'https://'))
                throw new \DomainException('Invalid debt provenance');
            $k=$o['metric'].'/'.$o['period'];if(isset($rows[$k]))throw new \DomainException('Duplicate debt observation');
            $rows[$k]=$o;
        }
        $get=function(string $metric,string $period,string $unit)use($rows):int{
            $r=$rows[$metric.'/'.$period]??throw new \DomainException('Missing debt anchor');
            if($r['unit']!==$unit||$r['kind']!=='observed'||$r['value']<0)throw new \DomainException('Wrong debt anchor');
            return $r['value'];
        };
        $debt=$get('maastricht_gross_debt','2026-Q2','EUR')*100;
        $state=$get('maastricht_contribution_state','2026-Q2','EUR')*100;
        if($debt!==$baseline['opening_debt']||$state>$debt)throw new \DomainException('Debt opening mismatch');
        $weights=[];
        foreach(['nonresident','other_french','french_credit_institutions','french_insurers','french_funds'] as $k)
            $weights['legacy-state-'.$k]=$get('state_negotiable_holder_share_'.$k,'2026-Q2','basis_points');
        // Nominal state contribution is NOT the observed negotiable market-value
        // stock. Use the shares merely as a synthetic allocation proxy.
        $holders=self::split($state,$weights);
        $holders['legacy-other-government-unknown']=$debt-$state;
        $rate=$get('state_new_medium_long_term_issuance_rate','2026-to-09-29','basis_points');
        $newWeights=['new-normal-domestic'=>1,'new-normal-external'=>1,'new-stress-domestic'=>1,'new-stress-external'=>1];
        return ['version'=>self::VERSION,'classification'=>'fictional_nominal_cohorts_observed_anchors','source_pack'=>$p,
            'source_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),
            'start_day'=>$start,...($start!==$p['as_of']?['opening_anchor_status'=>'historical_observations_carried_forward_as_scenario_assumptions','opening_anchor_vintage'=>$p['as_of']]:[]),'opening_debt'=>$debt,'opening_holders'=>$holders,'state_market_value_proxy_weights'=>$weights,
            'initial_coupon_bps'=>$baseline['assumed_annual_interest_bps'],'normal_new_coupon_bps'=>$rate,
            'stress_new_coupon_bps'=>$rate+300,'new_holder_keys'=>array_keys($newWeights),
            'first_maturity_month'=>$start==='2027-05-14'?'2027-06':'2026-12','maturity_month_count'=>120,'new_issue_maturity_years'=>10,
            'normal_daily_base_capacity'=>150000000000,'normal_refinancing_bps'=>10000,
            'stress_daily_base_capacity'=>25000000000,'stress_refinancing_bps'=>5000,
            'synthetic_schedule'=>true,'currency'=>'EUR','unit'=>'minor',
            ...($crisis?['crisis_enabled'=>true,'crisis_version'=>FinancialCrisis::VERSION,
                'support_limit'=>300000000000,'support_coupon_bps'=>450,'restructuring_overdue_reduction_bps'=>2000]:[])];
    }
    public static function assertValid(array $c,array $b,string $start):void
    {if(!isset($c['source_pack'])||self::build($c['source_pack'],$b,$start,($c['crisis_enabled']??false)===true)!==$c)throw new \DomainException('Debt config replay mismatch');}
    private static function due(array $c,string $day):array
    {
        $out=array_fill_keys(array_keys($c['opening_holders']),0);
        if(substr($day,8,2)!=='01')return $out;
        $month=(int)substr($day,0,4)*12+(int)substr($day,5,2)-((int)substr($c['first_maturity_month'],0,4)*12+(int)substr($c['first_maturity_month'],5,2));
        $n=$c['maturity_month_count'];
        if($month<0||$month>=$n)return $out;
        foreach($c['opening_holders'] as $k=>$v)$out[$k]=intdiv($v,$n)+($month<$v%$n?1:0);
        return $out;
    }
    private static function interest(int $principal,int $rate):int
    {return self::share($principal,$rate,3650000);}
    public static function step(array $c,string $day,?array $previous,int $cash,int $revenue,int $expense,?string $action=null):array
    {
        $p=$previous['treasury']??null;
        $holders=$p['creditors']??($c['opening_holders']+array_fill_keys($c['new_holder_keys'],0));
        $arrears=$p['principal_arrears_by_holder']??array_fill_keys(array_keys($holders),0);
        $crisis=($c['crisis_enabled']??false)===true;
        if(!$crisis&&$action!==null)throw new \DomainException('Crisis unavailable');
        if($crisis){$holders['new-support-external']??=0;$arrears['new-support-external']??=0;}
        $openingDebt=array_sum($holders);$openingPrincipalArrears=array_sum($arrears);
        $openingInterestArrears=$p['interest_arrears']??0;$openingPrimaryArrears=$p['primary_arrears']??0;
        $scheduled=self::due($c,$day);
        foreach($scheduled as $k=>$v){$arrears[$k]+=$v;if($arrears[$k]>$holders[$k])throw new \DomainException('Maturity exceeds principal');}
        $paused=$p['crisis']['moratorium']??false;
        if($action==='moratorium')$paused=true;
        if($action==='resume')$paused=false;
        $writtenOff=0;$restructure='none';
        if($action==='request-restructuring'){
            // Fictional creditor committee consents only after resumption,
            // for existing overdue principal. Not a unilateral writeoff.
            $restructure=(!$paused&&$openingPrincipalArrears>0)?'accepted':'declined';
            if($restructure==='accepted'){
                // Apply only to previously overdue claims, not today's maturities.
                foreach($arrears as $k=>$v){
                    $oldOverdue=$v-($scheduled[$k]??0);
                    $cut=self::share($oldOverdue,$c['restructuring_overdue_reduction_bps'],10000);
                    $holders[$k]-=$cut;$arrears[$k]-=$cut;$writtenOff+=$cut;
                }
            }
        }
        $principalDue=array_sum($arrears);$scheduledTotal=array_sum($scheduled);
        $interestDue=0;
        foreach($holders as $k=>$v){
            $rate=$k==='new-support-external'?$c['support_coupon_bps']:(str_starts_with($k,'legacy-')?$c['initial_coupon_bps']:(str_starts_with($k,'new-stress-')?$c['stress_new_coupon_bps']:$c['normal_new_coupon_bps']));
            $interestDue+=self::interest($v,$rate);
        }
        $stress=$paused||$openingPrincipalArrears>0||$openingInterestArrears>0||$openingPrimaryArrears>0;
        $capacity=($stress?$c['stress_daily_base_capacity']:$c['normal_daily_base_capacity'])
            +self::share($scheduledTotal,$stress?$c['stress_refinancing_bps']:$c['normal_refinancing_bps'],10000);
        $allPrimary=$expense+$openingPrimaryArrears;$allInterest=$interestDue+$openingInterestArrears;
        $need=max(0,$allPrimary+$allInterest+$principalDue-$cash-$revenue);
        $eligibleNeed=$paused?max(0,$allPrimary-$cash-$revenue):$need;
        if($paused)$capacity=0;
        $marketBorrow=min($eligibleNeed,$capacity);
        $supportStatus='none';$support=0;
        if($action==='request-support'){
            // One fictional external partner: no disbursement during a
            // declared moratorium or with outstanding interest arrears.
            $supportStatus=(!$paused&&$openingInterestArrears===0)?'accepted':'declined';
            if($supportStatus==='accepted')$support=min($c['support_limit'],max(0,$eligibleNeed-$marketBorrow));
        }
        $borrow=$marketBorrow+$support;$available=$cash+$revenue+$borrow;
        // Fixed fictional accounting payment priority, not a claim about law.
        $primaryPaid=min($available,$allPrimary);$available-=$primaryPaid;
        $interestPaid=$paused?0:min($available,$allInterest);$available-=$interestPaid;
        $principalPaid=$paused?0:min($available,$principalDue);$available-=$principalPaid;
        foreach(self::split($principalPaid,$arrears) as $k=>$v){$holders[$k]-=$v;$arrears[$k]-=$v;}
        $prefix=$stress?'new-stress-':'new-normal-';
        foreach(self::split($marketBorrow,[$prefix.'domestic'=>1,$prefix.'external'=>1]) as $k=>$v)$holders[$k]+=$v;
        if($crisis)$holders['new-support-external']+=$support;
        $closingDebt=array_sum($holders);
        if($closingDebt!==$openingDebt+$borrow-$principalPaid-$writtenOff||$closingDebt>1000000000000000||$available>1000000000000000)
            throw new \DomainException('Debt stock mismatch or magnitude limit');
        $ip=$allInterest-$interestPaid;$pp=$allPrimary-$primaryPaid;$pa=array_sum($arrears);
        $row=['opening_cash'=>$cash,'opening_debt'=>$openingDebt,'primary_balance'=>$revenue-$expense,
            'interest'=>$interestPaid,'interest_due'=>$interestDue,'opening_interest_arrears'=>$openingInterestArrears,'interest_arrears'=>$ip,
            'primary_paid'=>$primaryPaid,'opening_primary_arrears'=>$openingPrimaryArrears,'primary_arrears'=>$pp,
            'scheduled_principal'=>$scheduledTotal,'principal_due'=>$principalDue,'principal_paid'=>$principalPaid,
            'opening_principal_arrears'=>$openingPrincipalArrears,'principal_arrears'=>$pa,
            'new_borrowing'=>$borrow,'funding_need'=>$need,'funding_capacity'=>$capacity,'funding_gap'=>$need-$borrow,
            'new_issue_coupon_bps'=>$stress?$c['stress_new_coupon_bps']:$c['normal_new_coupon_bps'],
            'cash'=>$available,'debt'=>$closingDebt,'debt_change'=>$closingDebt-$openingDebt,
            'creditors'=>$holders,'principal_arrears_by_holder'=>$arrears,
            'status'=>$pa>0||$ip>0?'debt_service_arrears':($pp>0?'primary_payment_arrears':'current'),
            'market_stress'=>$stress];
        if($crisis){
            $row['crisis']=['action'=>$action,'moratorium'=>$paused,'support_decision'=>$supportStatus,
                'support_disbursed'=>$support,'market_borrowing'=>$marketBorrow,'restructuring_decision'=>$restructure,
                'principal_written_off'=>$writtenOff,'classification'=>'fictional_aggregate_decisions'];
            $row['banking']=FinancialCrisis::banking($c,$row,$paused);
        }
        return $row;
    }
    public static function project(array $dom):array
    {
        $c=$dom['debt_model'];$days=[];$months=[];$years=[];
        foreach($dom['days'] as $r){
            $t=$r['treasury'];$d=$r['day'];
            $row=['day'=>$d,...array_diff_key($t,['creditors'=>true,'principal_arrears_by_holder'=>true])];$days[]=$row;
            foreach(['month','year'] as $type){
                $key=substr($d,0,$type==='month'?7:4);
                $bucket=$type==='month'?($months[$key]??null):($years[$key]??null);
                if($bucket===null)$bucket=['period'=>$key,'first_day'=>$d,'last_day'=>$d,'days'=>0,'opening_debt'=>$t['opening_debt'],
                    'closing_debt'=>$t['opening_debt'],'debt_change'=>0,'new_borrowing'=>0,'principal_paid'=>0,'scheduled_principal'=>0,
                    'interest_paid'=>0,'interest_due'=>0,'funding_gap'=>0,'closing_principal_arrears'=>0,'closing_interest_arrears'=>0,'closing_primary_arrears'=>0];
                $bucket['last_day']=$d;$bucket['days']++;$bucket['closing_debt']=$t['debt'];$bucket['debt_change']=$t['debt']-$bucket['opening_debt'];
                foreach(['new_borrowing','principal_paid','scheduled_principal','interest_due','funding_gap'] as $k)$bucket[$k]+=$t[$k];
                if(isset($t['crisis'])){
                    $bucket['principal_written_off']=($bucket['principal_written_off']??0)+$t['crisis']['principal_written_off'];
                    $bucket['support_disbursed']=($bucket['support_disbursed']??0)+$t['crisis']['support_disbursed'];
                }
                if(isset($t['customs_revenue_minor']))$bucket['customs_revenue_minor']=($bucket['customs_revenue_minor']??0)+$t['customs_revenue_minor'];
                if(isset($t['equity_acquisition_minor'])){
                    $bucket['equity_acquisition_minor']=($bucket['equity_acquisition_minor']??0)+$t['equity_acquisition_minor'];
                    $bucket['closing_public_equity_at_cost_minor']=$t['public_equity_at_cost_minor'];
                }
                $bucket['interest_paid']+=$t['interest'];$bucket['closing_principal_arrears']=$t['principal_arrears'];
                $bucket['closing_interest_arrears']=$t['interest_arrears'];$bucket['closing_primary_arrears']=$t['primary_arrears'];
                if($type==='month')$months[$key]=$bucket;else $years[$key]=$bucket;
            }
        }
        $last=$dom['days']===[]?null:$dom['days'][array_key_last($dom['days'])]['treasury'];
        $reference=['version'=>self::VERSION,'classification'=>'fictional_debt_ledger_observed_anchors','day'=>$dom['day'],
            'currency'=>'EUR','unit'=>'minor','opening_debt'=>$c['opening_debt'],'debt'=>$last['debt']??$c['opening_debt'],
            'creditors'=>$last['creditors']??($c['opening_holders']+array_fill_keys($c['new_holder_keys'],0)+(!empty($c['crisis_enabled'])?['new-support-external'=>0]:[])),
            'observed_financial_reference'=>FrancObservedFinance::report(),'creditor_geography_reference'=>FrancCreditorGeography::reference(),'source_sha'=>$c['source_sha'],'reference'=>$c['source_pack'],'assumptions'=>array_diff_key($c,['source_pack'=>true]),
            'latest'=>$last,'days'=>$days,'months'=>array_values($months),'years'=>array_values($years),...(($dom['eu_trade_version']??null)==='wp54r-eu-trade-v4-frf-fiscal'?['frf_fiscal'=>FrancFiscalLedger::project($dom)]:[])];
        $native=FrancCommittedBudget::project($dom);
        return $native===null?$reference:[...$native,'day'=>$dom['day'],'unit'=>'minor','debt'=>$native['consolidated_gross_debt_frf_minor']??$native['latest']['valuation']['closing_gross_debt_frf_minor'],'creditors'=>$native['latest']['closing_state']['claims'],'reference_eur_only'=>$reference];
    }
}
