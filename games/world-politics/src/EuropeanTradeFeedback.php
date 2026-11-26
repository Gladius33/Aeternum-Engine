<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/EuropeanWithdrawal.php';
require_once __DIR__.'/FrancCommittedBudget.php';

/** Illustrative aggregate trade channel, not a WTO tariff schedule, actual
 * preferential agreement, customs union, exchange rate or forecast. */
final class EuropeanTradeFeedback
{
    public const VERSION='wp54r-eu-trade-v1';
    public const VERSION_V2='wp54r-eu-trade-v2';
    public const VERSION_V3='wp54r-eu-trade-v3-frf';
    public const VERSION_V4='wp54r-eu-trade-v4-frf-fiscal';
    public static function observe(array $read,string $world,string $day,string $version=self::VERSION,?array $priorDom=null):array
    {
        $s=$read['state'];$sha=trim($read['etag'],'"');
        if($s['world_id']!==$world||$s['day']!==$day||!isset($s['eu_withdrawal']))throw new \DomainException('Missing committed EU source');
        $eu=EuropeanWithdrawal::restore($s['eu_withdrawal']);
        if($eu['day']!==$day||!isset($eu['trade_regime_version']))throw new \DomainException('EU trade model not pinned');
        $terms=$eu['trade_regime'];
        $active=!$eu['membership']&&$terms!==null&&$terms['effective_on']<=$day;
        $out=['version'=>$version,'world_id'=>$world,'day'=>$day,'organization_sha'=>$sha,
            'status'=>$eu['membership']?'member':($active?'certified_scenario':'unmodelled_post_exit'),
            'regime'=>$active?$terms['regime']:null,'import_duty_bps'=>$active?$terms['import_duty_bps']:0,
            'import_friction_bps'=>$active?$terms['import_friction_bps']:0,'export_friction_bps'=>$active?$terms['export_friction_bps']:0];
        if(in_array($version,[self::VERSION_V3,self::VERSION_V4],true)){
            if($priorDom===null||$priorDom['state']['world_id']!==$world
                ||$priorDom['state']['day']!==(new \DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d'))
                throw new \DomainException('Missing prior committed domestic FX source');
            $priorSha=trim($priorDom['etag'],'"');
            $book=CurrencyTransitionLedger::project($eu,$priorDom['state']['days']);
            $out['frf_value_index_bps']=$book['new_currency_value_index_bps'];
            $out['prior_domestic_sha']=$priorSha;
            if($version===self::VERSION_V4){
                $ft=$eu['franc_fiscal_terms']??null;
                $out['frf_fiscal_terms']=$ft!==null&&$book['currency_conversion_simulated']&&$day>=$ft['effective_on']?$ft:null;
                $out['frf_parity_units_per_eur']=$out['frf_fiscal_terms']===null?null:$book['units_per_eur_at_parity'];
                $native=FrancCommittedBudget::observe($eu,$priorDom['state'],$book,$day);
                if($native!==null)$out['frf_committed_observation']=$native;
            }
        }
        self::validate($out,$world,$day,$version);return $out;
    }
    public static function validate(array $v,string $world,string $day,?string $expectedVersion=null):void
    {
        if(array_keys($v)!==['version','world_id','day','organization_sha','status','regime','import_duty_bps','import_friction_bps','export_friction_bps',...( in_array($v['version']??null,[self::VERSION_V3,self::VERSION_V4],true)?['frf_value_index_bps','prior_domestic_sha']:[]),...(($v['version']??null)===self::VERSION_V4?['frf_fiscal_terms','frf_parity_units_per_eur',...(array_key_exists('frf_committed_observation',$v)?['frf_committed_observation']:[])]:[])]
            ||!in_array($v['version'],[self::VERSION,self::VERSION_V2,self::VERSION_V3,self::VERSION_V4],true)||($expectedVersion!==null&&$v['version']!==$expectedVersion)||$v['world_id']!==$world||$v['day']!==$day||!is_string($v['organization_sha'])
            ||!preg_match('/^[a-f0-9]{64}$/D',$v['organization_sha'])||!in_array($v['status'],['member','certified_scenario','unmodelled_post_exit'],true))
            throw new \DomainException('Invalid EU trade observation');
        if(in_array($v['version'],[self::VERSION_V3,self::VERSION_V4],true)&&(!is_string($v['prior_domestic_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$v['prior_domestic_sha'])
            ||($v['frf_value_index_bps']!==null&&(!is_int($v['frf_value_index_bps'])||$v['frf_value_index_bps']<5000||$v['frf_value_index_bps']>15000||$v['status']==='member'))))
            throw new \DomainException('Invalid FRF observation');
        if($v['version']===self::VERSION_V4){
            $ft=$v['frf_fiscal_terms'];$ratio=$v['frf_parity_units_per_eur'];
            if($ft===null){if($ratio!==null)throw new \DomainException('Unexpected fiscal parity');}
            else{
                if(!is_array($ft)||!is_int($ratio)||$ratio<1||$ratio>10||$v['frf_value_index_bps']===null
                    ||!is_string($ft['effective_on']??null)||$ft['effective_on']>$day)
                    throw new \DomainException('Invalid fiscal observation');
                // Revalidate the immutable certified terms against a day
                // before their effective date; the organization ETag binds provenance.
                FrancFiscalLedger::validateTerms($ft,(new \DateTimeImmutable($ft['effective_on']))->modify('-1 day')->format('Y-m-d'),
                    ['currency_terms'=>['effective_on'=>$ft['effective_on']]]);
            }
        }
        if(array_key_exists('frf_committed_observation',$v)){
            if($v['version']!==self::VERSION_V4||$v['frf_fiscal_terms']===null||!is_array($v['frf_committed_observation']))throw new \DomainException('Native budget requires fiscal observation');
            FrancCommittedBudget::validate($v['frf_committed_observation'],$day);
        }
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
