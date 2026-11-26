<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Pinned fictional pass-through. Fixed-price trade volumes and a price
 * level shock are distinct. No exchange-rate-induced money issuance. */
final class FrancMacroFeedback
{
    public const VERSION='wp54r-frf-macro-feedback-v1';
    private static function scale(int $n,int $r,int $d=10000):int
    {return intdiv($n,$d)*$r+intdiv(($n%$d)*$r,$d);}
    public static function volumes(int $mark, int $exports,int $imports,int $consumption,int $ordinaryInvestment,int $priorReal,int $baselineReal):array
    {
        if($mark<5000||$mark>15000)throw new \DomainException('Invalid lagged FRF mark');
        // FX import cost at constant euro prices, relative to opening parity.
        $costGap=intdiv(100000000,$mark)-10000;
        $importLossRate=max(0,min(1000,intdiv($costGap,5)));
        $loss=self::scale($imports,$importLossRate);
        $c=min($consumption,intdiv($loss*3,5));$i=min($ordinaryInvestment,$loss-$c);
        $c+=min($consumption-$c,$loss-$c-$i);
        if($c+$i!==$loss)throw new \DomainException('Unallocated FRF import shortfall');
        // Additional exports require modeled spare output capacity, not
        // an unlimited gain from depreciation. Appreciation can cut exports.
        $exportRate=max(-500,min(500,intdiv(10000-$mark,10)));
        $exportDelta=self::scale($exports,$exportRate);
        if($exportDelta>0)$exportDelta=min($exportDelta,max(0,$baselineReal-$priorReal));
        return ['lagged_value_index_bps'=>$mark,'import_cost_level_gap_bps'=>$costGap,
            'real_import_loss_minor'=>$loss,'real_consumption_shortfall_minor'=>$c,'real_investment_shortfall_minor'=>$i,
            'real_export_delta_minor'=>$exportDelta,'export_competitiveness_proxy_bps'=>10000+$exportRate,
            'automatic_import_substitution_minor'=>0,'industrial_import_volumes_excluded'=>true];
    }
    public static function price(int $mark,int $priorMark,int $price):array
    {
        // A one-off change of the relative import price; no recurring
        // inflation shock for an unchanged exchange rate.
        $relative=intdiv($priorMark*10000,$mark)-10000;
        $shock=intdiv($relative,10); // illustrative 10% aggregate pass-through
        $next=$price+self::scale($price,$shock);
        if($next<10000000)$next=10000000;
        return ['price'=>$next,'relative_import_price_change_bps'=>$relative,
            'price_level_shock_bps'=>$shock,'price_level_delta_scaled'=>$next-$price,
            'pass_through_assumption_bps'=>1000];
    }
}
