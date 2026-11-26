<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/EuropeanWithdrawal.php';

/** Illustrative aggregate trade channel, not a WTO tariff schedule, actual
 * preferential agreement, customs union, exchange rate or forecast. */
final class EuropeanTradeFeedback
{
    public const VERSION='wp54r-eu-trade-v1';
    public static function observe(array $read,string $world,string $day):array
    {
        $s=$read['state'];$sha=trim($read['etag'],'"');
        if($s['world_id']!==$world||$s['day']!==$day||!isset($s['eu_withdrawal']))throw new \DomainException('Missing committed EU source');
        $eu=EuropeanWithdrawal::restore($s['eu_withdrawal']);
        if($eu['day']!==$day||!isset($eu['trade_regime_version']))throw new \DomainException('EU trade model not pinned');
        $terms=$eu['trade_regime'];
        $active=!$eu['membership']&&$terms!==null&&$terms['effective_on']<=$day;
        $out=['version'=>self::VERSION,'world_id'=>$world,'day'=>$day,'organization_sha'=>$sha,
            'status'=>$eu['membership']?'member':($active?'certified_scenario':'unmodelled_post_exit'),
            'regime'=>$active?$terms['regime']:null,'import_duty_bps'=>$active?$terms['import_duty_bps']:0,
            'import_friction_bps'=>$active?$terms['import_friction_bps']:0,'export_friction_bps'=>$active?$terms['export_friction_bps']:0];
        self::validate($out,$world,$day);return $out;
    }
    public static function validate(array $v,string $world,string $day):void
    {
        if(array_keys($v)!==['version','world_id','day','organization_sha','status','regime','import_duty_bps','import_friction_bps','export_friction_bps']
            ||$v['version']!==self::VERSION||$v['world_id']!==$world||$v['day']!==$day||!is_string($v['organization_sha'])
            ||!preg_match('/^[a-f0-9]{64}$/D',$v['organization_sha'])||!in_array($v['status'],['member','certified_scenario','unmodelled_post_exit'],true))
            throw new \DomainException('Invalid EU trade observation');
        foreach(['import_duty_bps','import_friction_bps','export_friction_bps'] as $k)
            if(!is_int($v[$k])||$v[$k]<0||$v[$k]>3000)throw new \DomainException('Invalid illustrative trade rate');
        if($v['status']==='certified_scenario'){
            if(!in_array($v['regime'],['transition','free_trade','customs_scenario'],true)||($v['regime']!=='customs_scenario'&&$v['import_duty_bps']!==0))
                throw new \DomainException('Invalid certified trade scenario');
        }elseif($v['regime']!==null||$v['import_duty_bps']!==0||$v['import_friction_bps']!==0||$v['export_friction_bps']!==0)
            throw new \DomainException('No automatic trade terms from membership change');
    }
    private static function scale(int $n,int $r,int $d=10000):int{return intdiv($n,$d)*$r+intdiv(($n%$d)*$r,$d);}
    /** The old restriction channel has already reduced aggregate trade. Apply
     * friction only to the remaining illustrative EU share; cap each loss.
     * Industrial-project imports are explicitly outside this first tax base.
     * Existing tariffs/tax base, real sector shares and trade diversion are
     * not calibrated here. Nominal duties and real resource costs differ. */
    public static function settle(array $v,int $exports,int $imports,int $price):array
    {
        $euX=self::scale($exports,5000);$euM=self::scale($imports,5000);
        $lossX=self::scale($euX,$v['export_friction_bps']);
        $lossM=self::scale($euM,min(3000,$v['import_friction_bps']+intdiv($v['import_duty_bps'],2)));
        $base=self::scale($euM-$lossM,$price,100000000);
        $duty=self::scale($base,$v['import_duty_bps']);
        return ['classification'=>'fictional_aggregate_uncalibrated','status'=>$v['status'],'regime'=>$v['regime'],
            'eu_import_share_assumption_bps'=>5000,'eu_export_share_assumption_bps'=>5000,
            'real_export_loss_minor'=>$lossX,'real_import_loss_minor'=>$lossM,
            'nominal_taxable_imports_minor'=>$v['status']==='certified_scenario'?$base:0,'customs_revenue_minor'=>$duty,
            'real_import_cost_minor'=>self::scale($duty,100000000,$price),
            'partner_export_loss_proxy_minor'=>$lossM,'partner_import_loss_proxy_minor'=>$lossX,
            'industrial_imports_excluded'=>true,'not_a_forecast'=>true];
    }
}
