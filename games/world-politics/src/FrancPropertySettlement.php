<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/IndustrialProgrammeCatalog.php';

/** Scenario-only statutory transfer of a synthetic domestic equity stake.
 * A request alone conveys no title. Independent legal and compensation
 * evidence is mandatory; no actual company assets or accounts are identified. */
final class FrancPropertySettlement
{
    public const VERSION='wp54r-frf-property-settlement-v1';
    public static function validate(array $v,string $day):void
    {
        $keys=array_keys($v);sort($keys);
        if($keys!==['company_id','compensation_reference_minor','effective_on','instrument_sha','public_interest_sha','review_sha','valuation_sha']||
            !is_string($v['company_id'])||!IndustrialProgrammeCatalog::hasCompany($v['company_id'])||
            !is_string($v['effective_on'])||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v['effective_on']))===false||
            $d->format('Y-m-d')!==$v['effective_on']||$v['effective_on']<=$day||
            !is_int($v['compensation_reference_minor'])||$v['compensation_reference_minor']<=0||$v['compensation_reference_minor']>100000000000000)
            throw new \DomainException('Invalid prospective statutory transfer');
        foreach(['instrument_sha','public_interest_sha','review_sha','valuation_sha'] as $k)if(!is_string($v[$k])||!preg_match('/^[a-f0-9]{64}$/D',$v[$k]))throw new \DomainException('Missing independent transfer evidence');
    }
    public static function apply(array $equity,array $corporations,array $order,string $day,int $cash,bool $current):array
    {
        self::validate($order,(new \DateTimeImmutable($day))->modify('-1 day')->format('Y-m-d'));
        if($order['effective_on']!==$day||$cash<0)throw new \DomainException('Wrong statutory settlement day');
        $id=$order['company_id'];$request=$corporations['company_requests'][$id]??null;
        $status='no_prior_request';$paid=0;
        if($request!==null&&$request['day']<$day){
            $status='unfunded';
            if(isset($equity['positions'][$id])||($equity['offers'][$id]['status']??null)==='awaiting_response')$status='existing_or_pending_equity';
            elseif($current&&$order['compensation_reference_minor']<=$cash){
                $paid=$order['compensation_reference_minor'];$status='settled';
                $equity['positions'][$id]=['stake_bps'=>5100,'cost_minor'=>$paid,'scope'=>'fictional_domestic_equity','acquired_on'=>$day,'method'=>'certified_compensated_statutory_transfer'];
                $equity['paid_today_minor']+=$paid;$equity['total_paid_minor']+=$paid;$equity['financial_assets_at_cost_minor']+=$paid;
                ksort($equity['positions'],SORT_STRING);
                $corporations['company_requests'][$id]['ownership_transferred']=true;
            }
            $corporations['company_requests'][$id]['status']=$status==='settled'?'compensated_transfer_settled':$status;
            $corporations['company_requests'][$id]['valuation']=$order['compensation_reference_minor'];
            $corporations['company_requests'][$id]['domestic_asset_scope']='fictional_domestic_equity_51_percent';
            $corporations['company_requests'][$id]['public_interest_review']='certified_scenario';
        }
        if($equity['financial_assets_at_cost_minor']!==array_sum(array_column($equity['positions'],'cost_minor')))throw new \DomainException('Statutory equity stock mismatch');
        return ['equity'=>$equity,'corporations'=>$corporations,'receipt'=>['version'=>self::VERSION,'company_id'=>$id,'status'=>$status,'instrument_sha'=>$order['instrument_sha'],'compensation_reference_minor'=>$paid,'seller_consent_required'=>false,'title_transferred'=>$status==='settled','actual_company_valuation'=>false]];
    }
}
