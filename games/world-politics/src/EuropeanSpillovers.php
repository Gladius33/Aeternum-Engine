<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/EuropeanWithdrawal.php';
require_once __DIR__.'/EuropeanMonetaryStress.php';
require_once __DIR__.'/FrancCommittedBudget.php';

/** Versioned, read-only counterfactual ledger from committed legal and trade
 * states. The dated budget/GDP anchors are from the existing WP80 study.
 * No actual EU budget, transfer, foreign GDP or central-bank asset is mutated. */
final class EuropeanSpillovers
{
    public const VERSION=EuropeanWithdrawal::SPILLOVERS_VERSION;
    private const GDP_EUR=18800000000000;
    private const FR_SHARE_BPS=1580;
    private const GROSS_MINOR=2590000000000;
    private const RETURNS_MINOR=1640000000000;
    private static function scale(int $n,int $r,int $d=10000):int
    {return intdiv($n,$d)*$r+intdiv(($n%$d)*$r,$d);}
    private static function days(string $from,string $to):int
    {return $to<$from?0:(int)(new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days+1;}
    private static function portion(int $total,array $weights):array
    {
        $out=[];$c=0;$previous=0;
        foreach(EuropeanWithdrawal::PARTNERS as $iso){
            $c+=$weights[$iso];$next=self::scale($total,$c);
            $out[$iso]=$next-$previous;$previous=$next;
        }
        if($previous!==$total)throw new \DomainException('Partner allocation does not conserve flows');
        return $out;
    }
    public static function budget(array $eu):array
    {
        $terms=$eu['budget_terms'];$start=$eu['membership']||$terms===null?null:max($eu['exit_on'],$terms['effective_on']);
        $days=$start===null?0:self::days($start,$eu['day']);
        $gross=$terms===null?0:self::scale(self::GROSS_MINOR,10000-$terms['gross_retained_bps']);
        $returns=$terms===null?0:self::scale(self::RETURNS_MINOR,10000-$terms['returns_retained_bps']);
        $flow=static function(int $n)use($gross,$returns,$terms):array{
            $g=intdiv($gross*$n,365);$r=intdiv($returns*$n,365);
            $settlement=$terms===null?0:min($n,$terms['settlement_days'])*$terms['settlement_daily_minor'];
            $gap=$g-$r-$settlement;
            return ['gross_contributions_removed_minor'=>$g,'receipts_removed_minor'=>$r,
                'settlement_paid_by_france_minor'=>$settlement,'remaining_eu_financing_gap_minor'=>$gap,
                'france_counterpart_net_transfer_relief_minor'=>$gap,'consolidated_net_delta_minor'=>0];
        };
        $cumulative=$flow($days);$prior=$flow(max(0,$days-1));$daily=[];
        foreach($cumulative as $k=>$v)$daily[$k]=$v-$prior[$k];
        return ['classification'=>'counterfactual_not_booked','currency'=>'EUR','unit'=>'minor',
            'status'=>$eu['membership']?'member':($days>0?'certified_scenario':($terms===null?'unmodelled_post_exit':'pending_effective_date')),
            'anchor_period'=>'2025','anchor_gross_minor'=>self::GROSS_MINOR,'anchor_returns_minor'=>self::RETURNS_MINOR,
            'anchor_net_minor'=>self::GROSS_MINOR-self::RETURNS_MINOR,
            'anchor_scope'=>'French accounting method; excludes NGEU returns; rounded annual observations',
            'source'=>'https://www.senat.fr/rap/l26-156-21/l26-156-216.html',
            'effective_from'=>$start,'elapsed_modelled_days'=>$days,'today'=>$daily,'cumulative'=>$cumulative,
            'annual_static_gap_before_settlement_minor'=>$days>0?$gross-$returns:null,
            'automatic_financing_allocated_minor'=>0,'outstanding_obligations_cancelled'=>false,
            'national_treasury_booked'=>false,'eu_treasury_booked'=>false];
    }
    public static function project(array $org,array $dom):array
    {
        $os=$org['state'];$ds=$dom['state'];$day=$os['day'];$world=$os['world_id'];
        if($ds['world_id']!==$world||$ds['day']!==$day||!isset($os['eu_withdrawal'],$ds['eu_trade_version']))
            throw new \DomainException('Incoherent EU projection');
        $eu=EuropeanWithdrawal::restore($os['eu_withdrawal']);
        if(($eu['spillovers_version']??null)!==self::VERSION||$eu['day']!==$day)throw new \DomainException('Unpinned EU spillovers');
        $orgSha=trim($org['etag'],'"');$domSha=trim($dom['etag'],'"');
        foreach([$orgSha,$domSha] as $sha)if(!preg_match('/^[a-f0-9]{64}$/D',$sha))throw new \DomainException('Invalid source digest');
        $allocation=$eu['partner_allocation'];$start=$eu['membership']||$allocation===null?null:max($eu['exit_on'],$allocation['effective_on']);
        $allX=0;$allM=0;$allocatedX=0;$allocatedM=0;$known=0;$unknown=0;$latest=null;$todayX=null;$todayM=null;
        foreach($ds['days'] as $r){
            if($eu['membership']||$r['day']<$eu['exit_on'])continue;
            $v=$r['macro']['eu_trade']??null;
            if($v===null||$v['status']!=='certified_scenario'){$unknown++;continue;}
            // French import loss is foreign gross export loss, and vice versa.
            $x=$v['partner_export_loss_proxy_minor'];$m=$v['partner_import_loss_proxy_minor'];
            if(!is_int($x)||!is_int($m)||$x<0||$m<0||$x!==$v['real_import_loss_minor']||$m!==$v['real_export_loss_minor'])
                throw new \DomainException('Non-conserving mirror trade');
            $allX+=$x;$allM+=$m;$known++;
            if($r['day']===$day)$latest=['gross_export_loss_proxy_minor'=>$x,'gross_import_loss_proxy_minor'=>$m];
            if($start!==null&&$r['day']>=$start){
                $allocatedX+=$x;$allocatedM+=$m;
                if($r['day']===$day){$todayX=self::portion($x,$allocation['export_loss_weights']);$todayM=self::portion($m,$allocation['import_loss_weights']);}
            }
        }
        $hasAllocation=$start!==null&&$start<=$day;
        $cx=$hasAllocation?self::portion($allocatedX,$allocation['export_loss_weights']):[];
        $cm=$hasAllocation?self::portion($allocatedM,$allocation['import_loss_weights']):[];
        $partners=[];
        foreach(EuropeanWithdrawal::PARTNERS as $iso)$partners[$iso]=[
            'classification'=>$hasAllocation?'fictional_directional_allocation':'missing_directional_exposure',
            'cumulative_gross_export_loss_proxy_minor'=>$cx[$iso]??null,'cumulative_gross_import_loss_proxy_minor'=>$cm[$iso]??null,
            'today_gross_export_loss_proxy_minor'=>$todayX[$iso]??null,'today_gross_import_loss_proxy_minor'=>$todayM[$iso]??null,
            'real_gdp_change_minor'=>null,'bank_exposure_minor'=>null,'actual_bilateral_exposure_minor'=>null];
        $fr=self::scale(self::GDP_EUR,self::FR_SHARE_BPS);
        $currency=isset($eu['currency_contract_version'])?CurrencyTransitionLedger::project($eu,$ds['days']):null;
        $franc=isset($eu['franc_rule_version'])?FrancProductionRule::project($eu,$ds):null;
        $fx=isset($eu['franc_fx_version'])?FrancExchangeReserves::project($eu,$currency,$franc):null;
        $native=null;
        if(isset($eu['franc_native_budget_terms'])){
            $t=$eu['franc_native_budget_terms'];
            if(($t['commit_daily']??false)&&!$eu['membership']&&$day>=max($t['effective_on'],$eu['exit_on'])){
                $native=FrancCommittedBudget::project($ds);
                if($native===null||$native['instrument_sha']!==$t['instrument_sha']||$native['latest']['day']!==$day)throw new \DomainException('Missing committed native budget');
                $latest=$native['latest'];$fund=$franc['latest']['funding'];
                if($latest['closing_state']['advance_liability']!==$fund['treasury']['advance_liability_franc_minor']||$latest['closing_state']['mark']!==$currency['new_currency_value_index_bps'])throw new \DomainException('Native monetary and FX reconciliation failure');
                if(isset($latest['financial_version'])){
                    $bank=$franc['commercial_banking']['latest']??null;$combined=$latest['bank_balance']['combined_modelled_slices']??null;
                    if(($bank===null)!==($combined===null)||($combined!==null&&($combined['deposit_liabilities_frf_minor']!==$bank['bank_deposit_liabilities_franc_minor']||$combined['reserves_frf_minor']!==$bank['bank_reserves_franc_minor']||$combined['balance_difference_frf_minor']!==0)))throw new \DomainException('Native bank projection reconciliation failure');
                }
            }else $native=FrancNativeBudgetProjection::project($eu,$ds,$currency,$franc,$fx);
        }
        return ['version'=>self::VERSION,'world_id'=>$world,'day'=>$day,'classification'=>'simulation_sensitivity_not_forecast',
            'source_organization_sha'=>$orgSha,'source_domestic_sha'=>$domSha,
            'membership_scope'=>['initial_eu_members'=>27,'current_modelled_eu_members'=>$eu['membership']?27:26,
                'other_member_withdrawals_modelled'=>false,'initial_eu_gdp_2025_rounded_eur'=>self::GDP_EUR,
                'france_share_2025_rounded_bps'=>self::FR_SHARE_BPS,'mechanical_scope_removed_eur'=>$eu['membership']?0:$fr,
                'same_anchor_current_members_eur'=>$eu['membership']?self::GDP_EUR:self::GDP_EUR-$fr,
                'fixed_geography_real_gdp_change_minor'=>null,'remaining_eu_real_gdp_change_minor'=>null,
                'source'=>'https://ec.europa.eu/eurostat/fr/web/products-eurostat-news/w/wdn-20260806-1'],
            'trade'=>['unit'=>'real_eur_minor','modelled_post_exit_days'=>$known,'unmodelled_post_exit_days'=>$unknown,
                'latest'=>$latest,'cumulative_gross_export_loss_proxy_minor'=>$allX,'cumulative_gross_import_loss_proxy_minor'=>$allM,
                'unallocated_export_loss_proxy_minor'=>$allX-$allocatedX,'unallocated_import_loss_proxy_minor'=>$allM-$allocatedM,
                'allocation_classification'=>$hasAllocation?'fictional_weights_not_observed':'missing',
                'allocation_effective_from'=>$start,'partners'=>$partners],
            'budget'=>self::budget($eu),
            'monetary'=>isset($eu['monetary_stress_version'])?EuropeanMonetaryStress::project($eu):['currency_conversion_modelled'=>false,'euro_collapse_inferred'=>false,
                'exchange_rate_change_bps'=>null,'ecb_balance_sheet_change_minor'=>null,'target_settlement_minor'=>null,
                'other_currency_exits_inferred'=>false],
            ... (isset($eu['currency_contract_version'])?['currency_contracts'=>$currency]:[]),
            ...(isset($eu['franc_rule_version'])?['franc_monetary_rule'=>$franc]:[]),
            ...(isset($eu['franc_fx_version'])?['franc_exchange_reserves'=>$fx]:[]),
            ...($native===null?[]:['franc_native_budget'=>$native,'franc_exchange_reserves_reference_only'=>$native['status']==='active']),
            'not_a_forecast'=>true];
    }
}
