<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Conditional fiscal calendar, not enacted policy or a persisted game tick.
 * All amounts are explicit minor units. Uniform daily allocation is an
 * illustrative convention; debt principal uses dated contractual amounts. */
final class NationalFiscalCalendar
{
    private const MAX=100000000000000000;
    private const ANNUAL=['tax_received','other_received','primary_due','interest_due'];
    private const ARREARS=['interest','principal','primary'];

    private static function amounts(array $a,array $keys):void
    {
        $actual=array_keys($a);sort($actual);sort($keys);
        if($actual!==$keys)throw new \DomainException('Invalid fiscal calendar fields');
        foreach($a as $v)if(!is_int($v)||$v<0||$v>self::MAX)
            throw new \DomainException('Invalid fiscal calendar amount');
    }

    private static function date(string $day):\DateTimeImmutable
    {
        if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$day))
            throw new \DomainException('Invalid fiscal date');
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day,new \DateTimeZone('UTC'));
        if($date===false||$date->format('Y-m-d')!==$day||(int)$date->format('Y')<1900||(int)$date->format('Y')>2199)
            throw new \DomainException('Invalid fiscal date');
        return $date;
    }

    /** Split integer annual amounts without multiplication overflow or loss
     * of minor units. First remainder days receive one extra minor unit. */
    public static function daily(int $annual,string $day):int
    {
        if($annual<0||$annual>self::MAX)throw new \DomainException('Invalid annual amount');
        $date=self::date($day);$days=$date->format('L')==='1'?366:365;
        return intdiv($annual,$days)+((int)$date->format('z')<$annual%$days?1:0);
    }

    /** Advance exactly one contiguous day. Principal arrears are already
     * included in gross debt. Credit is a bounded facility for the scenario,
     * not an inferred market offer; no reuse after draw. */
    public static function tick(array $state,string $day,array $annual,array $principalSchedule,int $ceiling):array
    {
        self::amounts($annual,self::ANNUAL);
        $date=self::date($day);
        $previous=self::date($state['day']??'');
        if($previous->modify('+1 day')->format('Y-m-d')!==$day)
            throw new \DomainException('Non-contiguous fiscal calendar');
        $stocks=$state['stocks']??[];$arrears=$state['arrears']??[];
        self::amounts($stocks,['cash','gross_debt']);self::amounts($arrears,self::ARREARS);
        $credit=$state['credit_remaining']??null;
        if(!is_int($credit)||$credit<0||$credit>self::MAX||$ceiling<0||$ceiling>self::MAX)
            throw new \DomainException('Invalid credit facility');
        foreach($principalSchedule as $dueDay=>$value){
            self::date((string)$dueDay);
            if(!is_int($value)||$value<0||$value>self::MAX)
                throw new \DomainException('Invalid scheduled principal');
        }
        $fresh=[];foreach(self::ANNUAL as $key)$fresh[$key]=self::daily($annual[$key],$day);
        $fresh['principal_due']=$principalSchedule[$day]??0;
        $due=$fresh;
        foreach(['primary','interest','principal'] as $key)$due[$key.'_due']+=$arrears[$key];
        $plan=NationalFiscalLedger::plan($stocks,$due,['committed_credit_available'=>$credit,'gross_debt_ceiling'=>$ceiling]);
        $next=['day'=>$day,'stocks'=>$plan['cash_subledger']['closing'],'arrears'=>$plan['unpaid'],
            'credit_remaining'=>$credit-$plan['credit_draw_scenario']];
        if($next['arrears']['principal']>$next['stocks']['gross_debt'])
            throw new \DomainException('Principal arrears exceed outstanding debt');
        return ['version'=>'wp130-national-fiscal-calendar-v1','classification'=>'conditional_daily_fiscal_scenario_not_persisted',
            'newly_due'=>$fresh,'previous_arrears'=>$arrears,'settlement'=>$plan,'next'=>$next,'rankable'=>false];
    }
}
