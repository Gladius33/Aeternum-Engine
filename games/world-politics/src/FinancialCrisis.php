<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Fictional aggregate crisis pilot; no legal act or real creditor targeting. */
final class FinancialCrisis
{
    public const VERSION='wp50-financial-crisis-v1';
    public const ACTIONS=['moratorium','resume','request-support','request-restructuring'];
    public static function validate(array $events,string $start,string $day):void
    {
        if(!array_is_list($events)||count($events)>128)throw new \DomainException('Invalid crisis decision history');
        $last=null;$paused=false;$support=false;$restructure=false;
        foreach($events as $e){
            if(!is_array($e)||array_keys($e)!==['day','action']||!is_string($e['day'])||!is_string($e['action'])
                ||!in_array($e['action'],self::ACTIONS,true)||$e['day']<$start||$e['day']>$day||($last!==null&&$e['day']<=$last))
                throw new \DomainException('Invalid crisis decision');
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$e['day']);
            if($date===false||$date->format('Y-m-d')!==$e['day'])throw new \DomainException('Invalid crisis day');
            switch($e['action']){
                case 'moratorium':if($paused)throw new \DomainException('Already in moratorium');$paused=true;break;
                case 'resume':if(!$paused)throw new \DomainException('No active moratorium');$paused=false;break;
                case 'request-support':if($support)throw new \DomainException('Pilot support request already used');$support=true;break;
                case 'request-restructuring':if($restructure||$paused)throw new \DomainException('Restructuring unavailable');$restructure=true;break;
            }
            $last=$e['day'];
        }
    }
    public static function actionFor(array $events,string $target):?string
    {
        $day=(new \DateTimeImmutable($target))->modify('-1 day')->format('Y-m-d');
        foreach($events as $e)if($e['day']===$day)return $e['action'];
        return null;
    }
    /** Financial sector sensitivity, not a real bank balance sheet.
     * Sovereign claim mark is a valuation proxy only, not a realised loss. */
    public static function banking(array $config,array $t,bool $paused):array
    {
        $bankClaims=$t['creditors']['legacy-state-french_credit_institutions'];
        $openingClaims=$config['opening_holders']['legacy-state-french_credit_institutions'];
        $capital=intdiv($openingClaims,5);
        $haircut=$paused?2000:($t['principal_arrears']>0||$t['interest_arrears']>0?1000:0);
        $loss=intdiv($bankClaims,10000)*$haircut+intdiv(($bankClaims%10000)*$haircut,10000);
        $equity=$capital-$loss;
        $credit=max(2500,min(10000,10000-intdiv($haircut*2,1)-($equity<=0?2000:0)));
        return ['classification'=>'fictional_sector_sensitivity','sovereign_nominal_claims'=>$bankClaims,
            'initial_equity_proxy'=>$capital,'valuation_discount_bps'=>$haircut,'unrealized_valuation_loss'=>$loss,
            'equity_proxy'=>$equity,'credit_availability_bps'=>$credit];
    }
}
