<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Fictional voluntary domestic-equity purchase, not statutory nationalisation.
 * Cash-funded only; not a valuation, legal conclusion or forecast about any
 * real company. Existing output, public spending and gross debt are unchanged
 * by the financial exchange itself.
 */
final class PublicEquityLedger
{
    public const VERSION='wp54r-public-equity-v1';
    public static function initial(string $day):array
    {
        return ['version'=>self::VERSION,'day'=>$day,'classification'=>'fictional_uncalibrated',
            'positions'=>[],'offers'=>[],'financial_assets_at_cost_minor'=>0,'total_paid_minor'=>0,'paid_today_minor'=>0];
    }
    public static function quote(string $id,array $config):array
    {
        if(!isset($config[$id]))throw new \InvalidArgumentException('Unknown fictional company');
        // Abstract domestic stake, not the consolidated real group or its price.
        $price=5000000000+$config[$id]['weight']*100000000;
        return ['stake_bps'=>5100,'price_minor'=>$price,'currency'=>'EUR','scope'=>'fictional_domestic_equity',
            'classification'=>'synthetic_price_not_market_value'];
    }
    public static function validateAction(array $state,string $action,array $config):void
    {
        if(!str_starts_with($action,'offer-voluntary-equity:'))return;
        $id=substr($action,strlen('offer-voluntary-equity:'));self::quote($id,$config);
        if(isset($state['positions'][$id])||($state['offers'][$id]['status']??null)==='awaiting_response')
            throw new \DomainException('Existing equity position or pending offer');
    }
    public static function advance(array $s,string $day,?string $action,array $config,int $cash,bool $ordinaryPaymentsCurrent):array
    {
        if($s['version']!==self::VERSION||$day!==(new \DateTimeImmutable($s['day']))->modify('+1 day')->format('Y-m-d')||$cash<0)
            throw new \DomainException('Invalid equity settlement transition');
        $s['day']=$day;$s['paid_today_minor']=0;
        if($action!==null&&str_starts_with($action,'offer-voluntary-equity:')){
            self::validateAction($s,$action,$config);
            $id=substr($action,strlen('offer-voluntary-equity:'));$q=self::quote($id,$config);
            $s['offers'][$id]=['status'=>'awaiting_response','offered_on'=>$day,
                'decision_on'=>(new \DateTimeImmutable($day))->modify('+30 days')->format('Y-m-d'),
                'quote'=>$q,'seller_consent'=>null,'settled_on'=>null];
        }
        ksort($s['offers'],SORT_STRING);
        foreach($s['offers'] as $id=>&$offer){
            if($offer['status']!=='awaiting_response'||$offer['decision_on']!==$day)continue;
            // Independent illustrative seller preferences. No real behaviour
            // or relationship between willingness and political dissent.
            $consent=($config[$id]['resilience']%3)!==0;
            $offer['seller_consent']=$consent;
            if(!$consent){$offer['status']='declined';continue;}
            $price=$offer['quote']['price_minor'];
            if(!$ordinaryPaymentsCurrent||$price>$cash-$s['paid_today_minor']){
                $offer['status']='unfunded';continue;
            }
            $s['positions'][$id]=['stake_bps'=>$offer['quote']['stake_bps'],'cost_minor'=>$price,
                'scope'=>'fictional_domestic_equity','acquired_on'=>$day];
            $s['paid_today_minor']+=$price;$s['total_paid_minor']+=$price;
            $s['financial_assets_at_cost_minor']+=$price;$offer['status']='settled';$offer['settled_on']=$day;
        }unset($offer);
        ksort($s['positions'],SORT_STRING);
        if($s['financial_assets_at_cost_minor']!==array_sum(array_column($s['positions'],'cost_minor'))
            ||$s['total_paid_minor']!==$s['financial_assets_at_cost_minor']||$s['paid_today_minor']>$cash)
            throw new \DomainException('Public equity stock-flow mismatch');
        return $s;
    }
}
