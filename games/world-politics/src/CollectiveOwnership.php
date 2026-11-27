<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PublicEquityLedger.php';

/** Synthetic compensated statutory transfer. No actual enterprise
 * valuation or adjudication. Collective control is a worker/public board
 * scenario, not a claim about the real manifesto's unspecified details. */
final class CollectiveOwnership
{
    public const VERSION='wp114-collective-ownership-v1';
    public const POLICY='pf2022-nathalie-arthaud-3';
    public const PARAMETERS=['large_firm_weight_min'=>200,'review_days'=>30,'worker_board_bps'=>5000,'target_stake_bps'=>10000];
    public static function apply(array $equity,array $config,array $active,array $policies,string $day,int $cash,bool $current):array
    {
        if($cash<0||$equity['day']!==$day||$equity['paid_today_minor']>$cash)throw new \DomainException('Invalid property settlement budget');
        $policy=null;
        foreach($active as $id)if(preg_match('/^pf2022-nathalie-arthaud-3(?:-r(?:[2-9]|[1-9][0-9]{1,2}))?$/D',$id)){$policy=$id;break;}
        if($policy===null&&!isset($equity['collective']))return $equity;
        $old=$equity['collective']??['version'=>self::VERSION,'orders'=>[],'governance'=>[]];
        if($old['version']!==self::VERSION)throw new \DomainException('Unrecognized ownership rule');
        $old['active_policy']=$policy;
        // Repeal suspends uncompleted orders, but never silently restores
        // transferred title or deletes compensation already paid.
        foreach($old['orders'] as &$order)if($order['status']==='review'&&$order['policy_id']!==$policy)$order['status']='cancelled';
        unset($order);
        if($policy!==null){
            $p=$policies[$policy]??null;
            if($p===null||$p['authorized_on']===null||$p['authorized_on']>=$day||$p['effective_on']>$day
                ||!preg_match('/^[a-f0-9]{64}$/D',$p['instrument_sha']??''))throw new \DomainException('Effective statutory authority required');
            $pending=false;
            foreach($old['orders'] as $key=>&$order){
                if($order['status']!=='review')continue;
                if($order['review_on']>$day){$pending=true;continue;}
                $id=$order['company_id'];
                $before=$equity['positions'][$id]??null;$stake=$before['stake_bps']??0;
                if(($equity['offers'][$id]['status']??null)==='awaiting_response'){$pending=true;continue;}
                $remaining=max(0,10000-$stake);
                $compensation=intdiv(PublicEquityLedger::quote($id,$config)['price_minor']*$remaining+5099,5100);
                $order['compensation_minor']=$compensation;
                if(!$current||$compensation>$cash-$equity['paid_today_minor']){$pending=true;$order['waiting_for_funds']=true;continue;}
                $cost=($before['cost_minor']??0)+$compensation;
                $equity['positions'][$id]=['stake_bps'=>10000,'cost_minor'=>$cost,'scope'=>'fictional_domestic_equity','acquired_on'=>$before['acquired_on']??$day,
                    'method'=>'fictional_collective_compensated_transfer'];
                $equity['paid_today_minor']+=$compensation;$equity['total_paid_minor']+=$compensation;$equity['financial_assets_at_cost_minor']+=$compensation;
                $order['status']='settled';$order['settled_on']=$day;$order['waiting_for_funds']=false;
                $old['governance'][$id]=['worker_board_bps'=>self::PARAMETERS['worker_board_bps'],'public_board_bps'=>10000-self::PARAMETERS['worker_board_bps'],'effective_on'=>$day,'order'=>$key];
                $pending=true;break;
            }
            unset($order);
            if(!$pending){
                ksort($config,SORT_STRING);
                foreach($config as $id=>$spec){
                    if($spec['weight']<self::PARAMETERS['large_firm_weight_min']||isset($old['governance'][$id]))continue;
                    if(($equity['offers'][$id]['status']??null)==='awaiting_response')continue;
                    $key='collective-'.substr(hash('sha256',$policy.'|'.$id),0,32);
                    if(isset($old['orders'][$key]))continue;
                    $old['orders'][$key]=['company_id'=>$id,'policy_id'=>$policy,'instrument_sha'=>$p['instrument_sha'],'opened_on'=>$day,
                        'review_on'=>(new \DateTimeImmutable($day))->modify('+'.self::PARAMETERS['review_days'].' days')->format('Y-m-d'),
                        'status'=>'review','compensation_minor'=>null,'waiting_for_funds'=>false,'settled_on'=>null,
                        'valuation_classification'=>'fictional_reference_not_independent_real_valuation'];
                    break;
                }
            }
        }
        ksort($equity['positions'],SORT_STRING);ksort($old['governance'],SORT_STRING);
        if($equity['financial_assets_at_cost_minor']!==array_sum(array_column($equity['positions'],'cost_minor'))
            ||$equity['paid_today_minor']>$cash||$equity['total_paid_minor']!==$equity['financial_assets_at_cost_minor'])throw new \DomainException('Collective equity cash-stock mismatch');
        $equity['collective']=$old;return $equity;
    }
}
