<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/EuropeanWithdrawal.php';

/** Independent FX valuation and newly-refinanced interest sensitivity.
 * No endogenous market forecast, currency redenomination or actual booking. */
final class EuropeanMonetaryStress
{
    public const VERSION=EuropeanWithdrawal::MONETARY_STRESS_VERSION;
    private const RESERVE_SHARE_BPS=2020;
    private static function scale(int $n,int $r,int $d):int
    {return intdiv($n,$d)*$r+intdiv(($n%$d)*$r,$d);}
    public static function reserveValuation(int $move):int
    {
        if($move< -2000||$move>2000)throw new \DomainException('FX stress out of range');
        // Constant currency quantities, all non-EUR FX values unchanged.
        // No portfolio reallocation or observed future reserve share.
        $value=self::RESERVE_SHARE_BPS*(10000+$move);
        return intdiv($value*10000,$value+(10000-self::RESERVE_SHARE_BPS)*10000);
    }
    public static function refinance(array $case,int $days,int $window,bool $active):array
    {
        $principal=$case['tranche_minor'];$spread=$case['spread_change_bps'];$interest=0;$today=0;$refinanced=0;$prior=0;
        for($i=1;$i<=$days;$i++){
            $prior=$refinanced;$refinanced=self::scale($principal,$i,$window);
            // Daily accrual on the newly refinanced, outstanding tranche
            // only. Existing debt is unchanged. No maturities in this window.
            $today=self::scale($refinanced,$spread,3650000);
            $interest+=$today;
        }
        return ['classification'=>'fictional_refinancing_sensitivity','tranche_minor'=>$principal,'spread_change_bps'=>$spread,
            'refinanced_principal_minor'=>$refinanced,'unrefinanced_principal_minor'=>$principal-$refinanced,
            'new_refinancing_today_minor'=>$active?$refinanced-$prior:0,
            'interest_delta_today_minor'=>$active?$today:0,'cumulative_interest_delta_minor'=>$interest,
            'annualized_interest_delta_at_end_minor'=>self::scale($refinanced,$spread,10000),
            'existing_debt_repriced'=>false,'actual_treasury_booked'=>false];
    }
    public static function project(array $eu):array
    {
        if(($eu['monetary_stress_version']??null)!==self::VERSION)throw new \DomainException('Unpinned monetary stress');
        $terms=$eu['monetary_stress'];$elapsed=0;$active=false;
        if($terms!==null&&$eu['day']>=$terms['effective_on']){
            $n=(int)(new \DateTimeImmutable($terms['effective_on']))->diff(new \DateTimeImmutable($eu['day']))->days+1;
            $elapsed=min($n,$terms['refinancing_days']);$active=$n<=$terms['refinancing_days'];
        }
        $status=$terms===null?'not_configured':($elapsed===0?'pending_effective_date':($active?'active':'completed'));
        $countries=[];$total=0;$today=0;$coverage=0;$roster=[...EuropeanWithdrawal::PARTNERS,'FR'];sort($roster,SORT_STRING);
        foreach($roster as $iso){
            $case=$terms['country_refinancing'][$iso]??null;
            if($case===null){
                $countries[$iso]=['classification'=>'missing_refinancing_exposure','tranche_minor'=>null,'spread_change_bps'=>null,
                    'refinanced_principal_minor'=>null,'unrefinanced_principal_minor'=>null,'new_refinancing_today_minor'=>null,
                    'interest_delta_today_minor'=>null,'cumulative_interest_delta_minor'=>null,'annualized_interest_delta_at_end_minor'=>null,
                    'existing_debt_repriced'=>false,'actual_treasury_booked'=>false];continue;
            }
            $r=self::refinance($case,$elapsed,$terms['refinancing_days'],$active);$countries[$iso]=$r;$coverage++;
            $total+=$r['cumulative_interest_delta_minor'];$today+=$r['interest_delta_today_minor'];
        }
        $move=$elapsed>0?$terms['euro_fx_move_bps']:null;
        $grid=[];foreach([-2000,-1000,0,1000,2000] as $v)$grid[]=['euro_fx_move_bps'=>$v,'mechanical_reserve_share_bps'=>self::reserveValuation($v)];
        return ['version'=>self::VERSION,'classification'=>'fictional_independent_sensitivity_not_forecast','status'=>$status,
            'currency_conversion_modelled'=>false,'euro_collapse_inferred'=>false,'other_currency_exits_inferred'=>false,
            'exchange_rate_change_bps'=>null,'assumed_euro_fx_move_bps'=>$move,'ecb_balance_sheet_change_minor'=>null,'target_settlement_minor'=>null,
            'effective_on'=>$terms['effective_on']??null,'elapsed_refinancing_days'=>$elapsed,
            'reserve_valuation'=>['reference_period'=>'2025-Q4','reference_euro_share_bps'=>self::RESERVE_SHARE_BPS,
                'source'=>'https://www.ecb.europa.eu/press/other-publications/ire/html/ecb.ire202606.en.html',
                'mechanical_share_bps'=>$move===null?null:self::reserveValuation($move),'portfolio_reallocation_modelled'=>false,'sensitivity_grid'=>$grid],
            'refinancing'=>['currency'=>'EUR','unit'=>'minor','countries'=>$countries,'covered_countries'=>$coverage,
                'missing_countries'=>27-$coverage,'covered_cumulative_interest_delta_minor'=>$total,
                'covered_interest_delta_today_minor'=>$today,'eu_total_interest_delta_minor'=>null,
                'actual_booked'=>false,'unmodelled_maturities_after_window'=>true],
            'not_a_forecast'=>true];
    }
}
