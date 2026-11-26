<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Explicitly fictional payable obligations. Not a real contract inventory,
 * cash ledger, treaty ratification engine or official forecast. EUR cents. */
final class InternationalCommitments
{
    public const VERSION='wp66-67-commitments-v1';
    private array $s;
    private static function date(string $d):\DateTimeImmutable
    {
        $x=\DateTimeImmutable::createFromFormat('!Y-m-d',$d,new \DateTimeZone('UTC'));
        if(!$x||$x->format('Y-m-d')!==$d)throw new \InvalidArgumentException('Invalid commitment date');
        return $x;
    }
    private static function plus(string $d,int $days):string
    {return self::date($d)->modify('+'.$days.' days')->format('Y-m-d');}
    public function __construct(string $world,string $start)
    {
        self::date($start);
        if(!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$world))throw new \InvalidArgumentException('Invalid world');
        $records=[];
        // These are test scenarios, not additional French expenditure. In a
        // later version verified contract-level observations replace proxies.
        foreach([
            ['ua-civil-fixture','state_ua','bilateral_grant',true,1000000000],
            ['ua-supply-fixture','state_ua','supply_contract',true,2000000000],
            ['eu-contribution-fixture','eu','multilateral_contribution',false,5000000000]
        ] as [$id,$partner,$kind,$consent,$amount]){
            $items=[];
            foreach([30,90,180] as $i=>$delta)$items[]=['id'=>'payment-'.($i+1),'original_due'=>self::plus($start,$delta),'due'=>self::plus($start,$delta),'amount_minor'=>$amount,'status'=>'future','settled_on'=>null];
            $records[$id]=['partner'=>$partner,'kind'=>$kind,'mutual_amendment_supported'=>$consent,'policy'=>'honor','pending'=>null,'installments'=>$items];
        }
        ksort($records,SORT_STRING);
        $this->s=['version'=>self::VERSION,'world_id'=>$world,'start_day'=>$start,'day'=>$start,
            'classification'=>'fictional_contract_fixtures_not_observed','currency'=>'EUR','minor_unit'=>2,
            'treasury_booked'=>false,'actual_inventory_complete'=>false,'records'=>$records,'commands'=>[],'events'=>[]];
    }
    public function decide(string $id,string $action,string $command):array
    {
        if(!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$command)||!in_array($action,['withhold','resume','request_deferral','request_termination'],true))
            throw new \InvalidArgumentException('Unsupported commitment decision');
        $r=$this->s['records'][$id]??throw new \DomainException('Unknown commitment');
        if(isset($this->s['commands'][$command])){
            $prior=$this->s['commands'][$command];
            if($prior['id']!==$id||$prior['action']!==$action)throw new \DomainException('Commitment command collision');
            return $prior['result']+['commitment_id'=>$id];
        }
        if(count($this->s['commands'])>=2000)throw new \DomainException('Commitment command cap');
        $day=$this->s['day'];
        if($action==='withhold'||$action==='resume'){
            $policy=$action==='withhold'?'withhold':'honor';
            if($r['policy']===$policy)throw new \DomainException('Payment policy already selected');
            $r['policy']=$policy;
            $result=['status'=>'payment_policy_changed','legal_release'=>false,'effective_on'=>self::plus($day,1)];
        }else{
            if(!$r['mutual_amendment_supported']||$r['pending']!==null)throw new \DomainException('Negotiation route unavailable or pending');
            if(!in_array('future',array_column($r['installments'],'status'),true))throw new \DomainException('No future obligation to negotiate');
            $r['pending']=['command_id'=>$command,'action'=>$action,'requested_on'=>$day,'response_on'=>self::plus($day,7)];
            $result=['status'=>'negotiation_requested','legal_release'=>false,'response_on'=>$r['pending']['response_on']];
        }
        $this->s['records'][$id]=$r;
        $this->s['commands'][$command]=['id'=>$id,'action'=>$action,'day'=>$day,'result'=>$result];
        return $result+['commitment_id'=>$id];
    }
    private function event(string $day,string $id,string $kind,array $extra=[]):void
    {$this->s['events'][]=['day'=>$day,'commitment_id'=>$id,'kind'=>$kind]+$extra;}
    /** Event-based advancement: a jump and successive daily ticks agree. */
    public function advance(string $target):void
    {
        self::date($target);
        if($target<$this->s['day'])throw new \DomainException('Commitment clock regression');
        while($this->s['day']<$target){
            $current=$this->s['day'];$next=$target;
            foreach($this->s['records'] as $r){
                if($r['pending']!==null)$next=min($next,$r['pending']['response_on']);
                foreach($r['installments'] as $i){
                    if($i['status']==='future')$next=min($next,$i['due']);
                    if($i['status']==='arrears'&&$r['policy']==='honor')$next=min($next,self::plus($current,1));
                }
            }
            if($next<=$current)throw new \LogicException('Unprocessed commitment event');
            foreach($this->s['records'] as $id=>&$r){
                if($r['pending']!==null&&$r['pending']['response_on']===$next){
                    $p=$r['pending'];$r['pending']=null;
                    // Explicit scenario negotiation, not an assertion of actual
                    // counterparty consent. Pre-existing arrears cannot be erased.
                    $draw=hexdec(substr(hash('sha256',implode('|',[self::VERSION,$this->s['world_id'],$id,$p['action'],$p['requested_on']])),0,8))%10000;
                    $arrears=in_array('arrears',array_column($r['installments'],'status'),true);
                    $consent=!$arrears&&$draw<($p['action']==='request_deferral'?7500:5000);
                    $changed=0;
                    if($consent)foreach($r['installments'] as &$i){
                        if($i['status']!=='future'||$i['due']<=$next)continue;
                        if($p['action']==='request_deferral')$i['due']=self::plus($i['due'],30);
                        else $i['status']='released_by_scenario_agreement';
                        $changed+=$i['amount_minor'];
                    }unset($i);
                    $this->event($next,$id,'negotiation_response',['request'=>$p['command_id'],'accepted'=>$consent,'affected_future_minor'=>$changed,
                        'classification'=>'fictional_counterparty_decision','existing_arrears_released'=>false]);
                }
                foreach($r['installments'] as &$i){
                    $due=$i['status']==='future'&&$i['due']===$next;
                    $arrears=$i['status']==='arrears'&&$r['policy']==='honor';
                    if(!$due&&!$arrears)continue;
                    if($r['policy']==='withhold'){
                        $i['status']='arrears';$this->event($next,$id,'payment_withheld',['installment'=>$i['id'],'amount_minor'=>$i['amount_minor']]);
                    }else{
                        $i['status']='scenario_settled';$i['settled_on']=$next;
                        $this->event($next,$id,'scenario_settlement',['installment'=>$i['id'],'amount_minor'=>$i['amount_minor'],'treasury_booked'=>false]);
                    }
                }unset($i);
            }unset($r);
            $this->s['day']=$next;
        }
    }
    public function snapshot():array
    {
        $s=$this->s;$tot=['original_minor'=>0,'future_minor'=>0,'arrears_minor'=>0,'scenario_settled_minor'=>0,'released_minor'=>0];
        foreach($s['records'] as $id=>$r){
            $t=array_fill_keys(array_keys($tot),0);
            foreach($r['installments'] as $i){
                $amount=$i['amount_minor'];$t['original_minor']+=$amount;
                $key=match($i['status']){'future'=>'future_minor','arrears'=>'arrears_minor','scenario_settled'=>'scenario_settled_minor','released_by_scenario_agreement'=>'released_minor'};
                $t[$key]+=$amount;
            }
            if($t['original_minor']!==$t['future_minor']+$t['arrears_minor']+$t['scenario_settled_minor']+$t['released_minor'])throw new \LogicException('Commitment reconciliation failed');
            foreach($t as $k=>$v)$tot[$k]+=$v;$s['records'][$id]['totals']=$t;
        }
        return $s+['totals'=>$tot,'loans_and_guarantees_actual_minor'=>null,'budget_delta_minor'=>null];
    }
    public static function restore(array $s):self
    {
        if(!is_array($s['commands']??null)||count($s['commands'])>2000)throw new \DomainException('Invalid commitment journal');
        $x=new self($s['world_id'],$s['start_day']);
        foreach($s['commands'] as $id=>$e){
            $x->advance($e['day']);$x->decide($e['id'],$e['action'],$id);
        }
        $x->advance($s['day']);
        if($x->snapshot()!==$s)throw new \DomainException('Commitment snapshot replay mismatch');
        return $x;
    }
}
