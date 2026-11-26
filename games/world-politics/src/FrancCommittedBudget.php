<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancNativeBudgetProjection.php';

/** Prospective, replayed daily native treasury, with its own financing
 * choices. Legacy fiscal policy and GDP remain reference-denominated. */
final class FrancCommittedBudget
{
    public const VERSION='wp54r-frf-committed-budget-v1';
    private static function requireAmount(mixed $v):void
    {if(!is_int($v)||$v<0||$v>10000000000000000)throw new \DomainException('Invalid committed budget amount');}
    private static function fields(array $v,array $expected):void
    {$k=array_keys($v);sort($k);sort($expected);if($k!==$expected)throw new \DomainException('Invalid native observation fields');}
    public static function observe(array $eu,array $prior,array $book,string $day):?array
    {
        $terms=$eu['franc_native_budget_terms']??null;
        if(($terms['commit_daily']??false)!==true||$eu['membership']||!$book['currency_conversion_simulated'])return null;
        $start=max($terms['effective_on'],$eu['exit_on']);if($day<$start)return null;
        // The production rule uses only preceding real GDP. The current
        // placeholder is not consumed by the current day's issuance decision.
        $rows=$prior['days'];$g=$prior['macro_baseline']['daily_real_gdp'];
        $rows[]=['day'=>$day,'macro'=>['real_gdp'=>$g]];
        $money=FrancProductionRule::project($eu,['day'=>$day,'days'=>$rows,'macro_baseline'=>$prior['macro_baseline']]);
        $f=$money['latest']['funding'];$fx=$eu['franc_fx_terms'];
        $v=['version'=>self::VERSION,'start'=>$start,'instrument_sha'=>$terms['instrument_sha'],'reserve_authorized'=>$terms['reserve_purchase_authorized'],
            'opening_fx'=>['official_eur_reserves_minor'=>$fx['official_eur_minor'],'official_franc_pool_minor'=>$fx['official_franc_pool_minor'],'official_eur_reserve_floor_minor'=>$fx['minimum_official_eur_minor'],'counterparty_eur_assets_minor'=>$fx['counterparty_eur_minor'],'counterparty_franc_pool_minor'=>$fx['counterparty_franc_pool_minor']],
            'funding'=>['advance'=>$f['settled_public_spending_franc_minor'],'repay'=>$f['settled_levy_and_repayment_franc_minor'],'liability'=>$f['treasury']['advance_liability_franc_minor'],'asset'=>$f['central_bank']['treasury_advance_asset_franc_minor']],
            'fx_order'=>isset($eu['franc_fx_orders'][$day])?array_intersect_key($eu['franc_fx_orders'][$day],['kind'=>true,'eur_minor'=>true]):null];
        self::validate($v,$day);return $v;
    }
    public static function validate(array $v,string $day):void
    {
        self::fields($v,['version','start','instrument_sha','reserve_authorized','opening_fx','funding','fx_order']);
        if($v['version']!==self::VERSION||!is_string($v['start'])||($dt=\DateTimeImmutable::createFromFormat('!Y-m-d',$v['start']))===false||$dt->format('Y-m-d')!==$v['start']||$v['start']>$day||!is_string($v['instrument_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$v['instrument_sha'])||!is_bool($v['reserve_authorized']))throw new \DomainException('Invalid committed budget observation');
        self::fields($v['opening_fx'],['official_eur_reserves_minor','official_franc_pool_minor','official_eur_reserve_floor_minor','counterparty_eur_assets_minor','counterparty_franc_pool_minor']);
        self::fields($v['funding'],['advance','repay','liability','asset']);
        foreach([...array_values($v['opening_fx']),...array_values($v['funding'])] as $a)self::requireAmount($a);
        if($v['funding']['asset']!==$v['funding']['liability']||($v['funding']['advance']>0&&$v['funding']['repay']>0)||$v['opening_fx']['official_eur_reserves_minor']<$v['opening_fx']['official_eur_reserve_floor_minor'])throw new \DomainException('Unbalanced native observation');
        if($v['fx_order']!==null){self::fields($v['fx_order'],['kind','eur_minor']);self::requireAmount($v['fx_order']['eur_minor']);if(!in_array($v['fx_order']['kind'],['buy_eur','sell_eur'],true)||$v['fx_order']['eur_minor']===0)throw new \DomainException('Invalid committed FX order');}
    }
    /** Financing from native cash needs and capacity; no use of the old
     * reference borrowing volume. FRF primary obligations have first call
     * on market capacity, then EUR debt, then domestic debt service. */
    public static function finance(array $s,array $f,array $m,int $ratio,bool $paused,?string $action):array
    {
        $arrears=FrancNativeBudget::arrears($s['claims']);$stress=$paused||$s['primary_arrears']>0||array_sum($arrears['FRF'])>0||array_sum($arrears['EUR'])>0;
        $due=['FRF'=>0,'EUR'=>0];$maturityReference=0;
        foreach($s['claims'] as $id=>$c){$cur=$c['currency'];$mat=$f['maturities'][$id]??0;$due[$cur]+=$c['interest_due_minor']+$c['principal_due_minor']+($f['coupons'][$id]??0)+$mat-($f['haircuts'][$id]??0);$maturityReference+=$cur==='FRF'?intdiv($mat,$ratio):$mat;}
        $cap=$paused?0:($stress?$m['stress_daily_base_capacity']:$m['normal_daily_base_capacity'])+FrancNativeBudget::scale($maturityReference,$stress?$m['stress_refinancing_bps']:$m['normal_refinancing_bps'],10000);
        $primary=max(0,$s['primary_arrears']+$f['primary_expense']-$s['accounts']['treasury_frf_minor']-$f['revenue']);
        $frfNeed=$primary;if(!$paused)$frfNeed=max(0,$s['primary_arrears']+$f['primary_expense']+$due['FRF']-$s['accounts']['treasury_frf_minor']-$f['revenue']);
        $eurNeed=$paused?0:max(0,$due['EUR']-$s['accounts']['treasury_eur_minor']);
        $dom=min($cap,intdiv($primary+$ratio-1,$ratio));$ext=min($cap-$dom,$eurNeed);$dom+=min($cap-$dom-$ext,max(0,intdiv($frfNeed+$ratio-1,$ratio)-$dom));
        $support=0;if($action==='request-support'&&!$paused&&array_sum(array_column($arrears,'interest'))===0)$support=min($m['support_limit']??0,max(0,$eurNeed-$ext));
        $prefix=$stress?'new-stress-':'new-normal-';$issues=[];
        foreach([[$prefix.'domestic-FRF','FRF',$dom*$ratio],[$prefix.'external-EUR','EUR',$ext],['new-support-external-EUR','EUR',$support]] as [$id,$cur,$n])if($n>0)$issues[$id]=['currency'=>$cur,'amount'=>$n];
        return ['issues'=>$issues,'market_stress'=>$stress,'market_capacity_at_parity_eur_minor'=>$cap,'market_issued_at_parity_eur_minor'=>$dom+$ext,'support_eur_minor'=>$support,'native_cash_need_frf_minor'=>$frfNeed,'native_cash_need_eur_minor'=>$eurNeed];
    }
    public static function risk(array $r,int $gdp,int $ratio,int $mark,?array $previous):array
    {
        if($gdp<=0)throw new \DomainException('Missing real GDP anchor');
        $a=$r['arrears'];$old=$previous['arrears']['EUR']??['principal'=>0,'interest'=>0];$external=array_sum($a['EUR']);
        $value=array_sum($a['FRF'])+$r['budget']['primary_arrears_frf_minor']+FrancNativeBudget::scale($external,$ratio*10000,$mark);
        $stress=min(3000,intdiv($value,max(1,intdiv($gdp*$ratio,10000))));
        return ['classification'=>'bounded_uncalibrated_lagged_risk','arrears_to_daily_real_gdp_proxy_bps'=>$stress,'credit_availability_bps'=>10000-$stress,'sovereign_spread_proxy_bps'=>intdiv($stress,3),'new_unpaid_eur_demand_proxy_minor'=>max(0,$external-array_sum($old))];
    }
    public static function step(array $row,?array $prior,array $model,array $obs,int $ratio,int $mark):array
    {
        self::validate($obs,$row['day']);$previous=$prior['frf_committed_budget']??null;
        if($previous!==null&&($previous['instrument_sha']!==$obs['instrument_sha']||$previous['effective_from']!==$obs['start']||$previous['reserve_purchase_authorized']!==$obs['reserve_authorized']||$previous['ratio']!==$ratio))throw new \DomainException('Native budget terms changed');
        if($previous===null&&$row['day']!==$obs['start'])throw new \DomainException('Missing opening committed native budget day');
        $f=$obs['funding'];$money=['funding'=>['settled_public_spending_franc_minor'=>$f['advance'],'settled_levy_and_repayment_franc_minor'=>$f['repay'],'treasury'=>['advance_liability_franc_minor'=>$f['liability']],'central_bank'=>['treasury_advance_asset_franc_minor'=>$f['asset']]]];
        $state=$previous['closing_state']??FrancNativeBudgetProjection::opening($row['frf_fiscal'],$prior,$obs['opening_fx'],$money,$ratio,$prior['frf_fiscal']['value_index_bps']??10000);
        $flow=FrancNativeBudgetProjection::flow($row,$state,$model,$money,$obs['fx_order'],$ratio);
        $paused=$row['treasury']['crisis']['moratorium']??false;$action=$row['treasury']['crisis']['action']??null;
        $finance=self::finance($state,$flow,$model,$ratio,$paused,$action);$flow['issues']=$finance['issues'];
        $result=FrancNativeBudget::step($state,$flow,$ratio,$mark,$obs['reserve_authorized'],$paused);
        return [...$result,'instrument_sha'=>$obs['instrument_sha'],'effective_from'=>$obs['start'],'reserve_purchase_authorized'=>$obs['reserve_authorized'],'ratio'=>$ratio,'financing'=>$finance,'risk'=>self::risk($result,$row['macro']['real_gdp'],$ratio,$mark,$previous)];
    }
    public static function project(array $dom):?array
    {
        $days=[];$active=false;
        foreach($dom['days'] as $d){if(isset($d['frf_committed_budget'])){$active=true;$days[]=['day'=>$d['day'],...$d['frf_committed_budget']];}elseif($active)throw new \DomainException('Committed native budget history gap');}
        if(!$active)return null;$last=$days[array_key_last($days)];
        return ['version'=>self::VERSION,'status'=>'active','currency'=>'FRF','classification'=>'committed_native_treasury_with_reference_policy_inputs','effective_from'=>$days[0]['day'],'instrument_sha'=>$last['instrument_sha'],'latest'=>$last,'days'=>$days,'months'=>FrancNativeBudgetProjection::aggregate($days,7),'years'=>FrancNativeBudgetProjection::aggregate($days,4),'reference_financing_policy_retained'=>false,'shared_reserves_within_projection'=>true,'daily_state_committed'=>true,'national_reference_ledger_changed'=>false,'macro_feedback_from_native_arrears_modelled'=>true,'other_currencies_modelled'=>false];
    }
}
