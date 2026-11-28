<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/NationalFiscalCalendar.php';
require_once __DIR__.'/NationalHouseholdMacro.php';
require_once __DIR__.'/NationalInvestitureForecast.php';

/** Conditional budget impact study from a committed state.
 * No law, policy authority, state mutation, or ranked result. */
final class NationalBudgetPreview
{
    public const VERSION='wp130-budget-impact-preview-v1';
    private const BASE=1000000000;
    public static function validate(array $params):void{
        $keys=array_keys($params);sort($keys);
        if($keys!==['primary_spending_change_bps','receipts_change_bps'])
            throw new \InvalidArgumentException('Budget preview fields');
        foreach($params as $v)if(!is_int($v)||$v< -1000||$v>1000)
            throw new \InvalidArgumentException('Budget preview bounds');
    }
    public static function compare(array $cfg,array $fiscal,array $macro,array $trade,array $params):array{
        self::validate($params);
        $base=self::project($cfg,$fiscal,$macro,$trade,0,0);
        $alternative=self::project($cfg,$fiscal,$macro,$trade,$params['receipts_change_bps'],$params['primary_spending_change_bps']);
        $delta=[];
        foreach($base['end'] as $key=>$value){
            $other=$alternative['end'][$key];
            $delta[$key]=$value===null||$other===null?null:$other-$value;
        }
        return ['version'=>self::VERSION,'classification'=>'conditional_uncalibrated_budget_scenario_not_enacted',
            'opening_day'=>$fiscal['day'],'end_day'=>$base['day'],'days'=>365,'parameters'=>$params,
            'unit'=>'normalized_reference_GDP_units','index_base'=>self::BASE,
            'assumptions'=>['parameter_unit'=>'basis_points_relative_to_annual_envelope_not_GDP',
                'trade'=>'opening_committed_exposure_held_constant','receipts'=>'unclassified_public_receipts_no_direct_household_tax_mapping',
                'primary_spending'=>'paid_amount_relative_to_unmodified_daily_envelope; existing fiscal multiplier retained',
                'forecast'=>'2027_forecast_held_constant','calibrated'=>false],
            'trade_input'=>$trade,'baseline'=>$base,'alternative'=>$alternative,'delta_end'=>$delta,
            'rankable'=>false,'persisted'=>false,'legal_effect'=>false];
    }
    private static function project(array $cfg,array $f,array $m,array $trade,int $receipts,int $spending):array{
        $day=new \DateTimeImmutable($f['day'],new \DateTimeZone('UTC'));
        $sum=['receipts'=>0,'primary_paid'=>0,'interest_paid'=>0,'principal_repaid'=>0,'borrowing_received'=>0];
        $hh=null;
        for($i=0;$i<365;$i++){
            $day=$day->modify('+1 day');$d=$day->format('Y-m-d');
            $a=$cfg['annual'];
            $nominal=NationalHouseholdMacro::mul($m['real_output_index'],min($m['price_index'],10000000000));
            $a['other_received']=NationalHouseholdMacro::mul($a['other_received'],max(100000000,min(10000000000,$nominal)));
            $a['primary_due']=NationalHouseholdMacro::mul($a['primary_due'],min($m['price_index'],10000000000));
            $a['interest_due']=intdiv($f['stocks']['gross_debt']*200,10000);
            $referenceDue=NationalFiscalCalendar::daily($a['primary_due'],$d);
            $a['other_received']=intdiv($a['other_received']*(10000+$receipts),10000);
            $a['primary_due']=intdiv($a['primary_due']*(10000+$spending),10000);
            $t=NationalFiscalCalendar::tick($f,$d,$a,$cfg['principal_schedule'],$cfg['ceiling']);
            $flows=$t['settlement']['cash_subledger']['executed_flows'];
            $paid=min($t['newly_due']['primary_due'],$flows['primary_paid']);
            // Signed change vs unchanged planned daily envelope. At zero
            // adjustments this reproduces the live v3 fiscal-impulse input.
            $m=NationalHouseholdMacro::step($m,$cfg['opening_forecast'],$referenceDue-$paid,$trade);
            $hh=NationalHouseholdMacro::household($m,$cfg['opening_forecast'],$t['newly_due']['primary_due'],$flows['primary_paid']);
            $f=$t['next'];$sum['receipts']+=$flows['tax_received']+$flows['other_received'];
            foreach(['primary_paid','interest_paid','principal_repaid','borrowing_received'] as $k)$sum[$k]+=$flows[$k];
        }
        return ['day'=>$f['day'],'flows'=>$sum,'end'=>[
            'cash'=>$f['stocks']['cash'],'gross_debt'=>$f['stocks']['gross_debt'],'credit_remaining'=>$f['credit_remaining'],
            'primary_arrears'=>$f['arrears']['primary'],'interest_arrears'=>$f['arrears']['interest'],'principal_arrears'=>$f['arrears']['principal'],
            'real_output_index'=>$m['real_output_index'],'price_index'=>$m['price_index'],'unemployment_scaled'=>$m['unemployment_scaled'],
            'purchasing_power_index'=>$hh['purchasing_power_index']]];
    }
}
