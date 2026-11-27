<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
final class ResearchBillingError extends \RuntimeException{
    public function __construct(public readonly int $status,string $code){parent::__construct($code);}
}
/** Prepaid accounting units and hard admission ceilings. No money movement,
 * payment credentials, client-side plan claims or unlimited tier. */
final class ResearchBilling
{
    public const VERSION='research-meter-v1';
    private const MAX=1000000000;
    private static function q(\PDO $db,string $sql,array $args=[]):\PDOStatement{$q=$db->prepare($sql);$q->execute($args);return $q;}
    public static function mode(string $mode):void{if(!in_array($mode,['monthly','payg'],true))throw new \DomainException('Invalid billing mode');}
    public static function included(string $plan):int{return match($plan){'standard'=>10000,'pro'=>50000,default=>throw new \DomainException('Invalid plan')};}
    public static function operation(string $method,string $path):string{
        if($method==='GET'&&in_array($path,['/research/v1/usage','/research/v1/entitlements'],true))return 'usage';
        if(str_starts_with($path,'/research/v1/datasets/global')||str_starts_with($path,'/research/v1/datasets/game-worlds'))return 'global_export';
        if(str_contains($path,'/dataset')||str_ends_with($path,'/csv'))return 'export';
        return 'request';
    }
    private static function cost(string $operation):int{return match($operation){'usage'=>0,'request'=>1,'export'=>5,'global_export'=>20,'compute'=>10,default=>throw new \DomainException('Invalid meter operation')};}
    private static function account(\PDO $db,string $tenant):array{
        $r=self::q($db,'SELECT * FROM wp_research_tenants WHERE tenant_id=? FOR UPDATE',[$tenant])->fetch(\PDO::FETCH_ASSOC);
        if($r===false)throw new \DomainException('Unknown tenant');return $r;
    }
    private static function event(\PDO $db,string $tenant,string $key,string $kind,string $fingerprint,int $included,int $prepaid,string $operation):void{
        self::q($db,'INSERT INTO wp_research_billing_events(tenant_id,event_key,event_kind,fingerprint,included_delta,prepaid_delta,operation,created_at) VALUES(?,?,?,?,?,?,?,?)',
            [$tenant,$key,$kind,$fingerprint,$included,$prepaid,$operation,time()]);
    }
    /** Fixed UTC windows, serialized across all application nodes. Per-scope
     * rows are bounded (one per period), no unbounded timestamp buckets. */
    private static function window(\PDO $db,string $scope,int $period,int $maxRequests,int $maxUnits,int $units,int $requests):void{
        $now=intdiv(time(),$period);
        self::q($db,'INSERT IGNORE INTO wp_research_capacity(scope_key,period_seconds,window_no,requests,units) VALUES(?,?,?,0,0)',[$scope,$period,$now]);
        $r=self::q($db,'SELECT window_no,requests,units FROM wp_research_capacity WHERE scope_key=? AND period_seconds=? FOR UPDATE',[$scope,$period])->fetch(\PDO::FETCH_ASSOC);
        $n=(int)$r['window_no']===$now?(int)$r['requests']:0;$u=(int)$r['window_no']===$now?(int)$r['units']:0;
        if($n+$requests>$maxRequests||$u+$units>$maxUnits)throw new ResearchBillingError(429,'capacity_limit');
        self::q($db,'UPDATE wp_research_capacity SET window_no=?,requests=?,units=? WHERE scope_key=? AND period_seconds=?',[$now,$n+$requests,$u+$units,$scope,$period]);
    }
    private static function debit(\PDO $db,array $r,string $operation,string $key,int $requests):void{
        if(!$db->inTransaction())throw new \LogicException('Meter requires transaction');
        $tenant=$r['tenant_id'];$cost=self::cost($operation);
        if($operation==='global_export'&&$r['plan']!=='pro')throw new ResearchBillingError(403,'research_pro_required');
        if((int)$r['paid_until']<=time()||(int)$r['enabled']!==1)throw new ResearchBillingError(401,'research_entitlement_expired');
        $included=$r['billing_mode']==='monthly'?min($cost,(int)$r['included_units']):0;
        $prepaid=$cost-$included;
        if($prepaid>0&&($r['billing_mode']==='monthly'&&!(bool)$r['allow_overage']||$prepaid>(int)$r['prepaid_units']))
            throw new ResearchBillingError(402,'insufficient_usage_credits');
        $pro=$r['plan']==='pro';
        // All writers acquire tenant first, global second; same period order.
        foreach([[1,$pro?40:30,$pro?800:300],[60,120,$pro?2400:600],[3600,$pro?3000:1200,$pro?30000:6000],[86400,$pro?20000:5000,$pro?150000:30000]] as [$period,$req,$units])
            self::window($db,$tenant,$period,$req,$units,$cost,$requests);
        foreach([[1,100,2000],[60,1000,10000],[3600,20000,200000],[86400,150000,1000000]] as [$period,$req,$units])
            self::window($db,'global',$period,$req,$units,$cost,$requests);
        if($operation==='compute'){
            // The global capacity row is locked already: serialize queue admission.
            if((int)self::q($db,"SELECT COUNT(*) FROM wp_research_jobs WHERE status='queued'")->fetchColumn()>=100)
                throw new ResearchBillingError(429,'global_queue_limit');
        }
        if($cost===0)return;
        self::q($db,'UPDATE wp_research_tenants SET included_units=included_units-?,prepaid_units=prepaid_units-? WHERE tenant_id=?',[$included,$prepaid,$tenant]);
        self::event($db,$tenant,$key,'usage',hash('sha256',self::VERSION.'|'.$operation),-$included,-$prepaid,$operation);
    }
    public static function admit(\PDO $db,string $tenant,string $operation):void{
        self::debit($db,self::account($db,$tenant),$operation,hash('sha256','request|'.bin2hex(random_bytes(32))),1);
    }
    public static function compute(\PDO $db,string $tenant,string $job):void{
        self::debit($db,self::account($db,$tenant),'compute',hash('sha256','compute|'.$job),0);
    }
    public static function fund(\PDO $db,string $tenant,string $reference,string $kind,int $units,?int $until,?string $plan,?string $mode,bool $overage):array{
        if(!$db->inTransaction()||!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$reference)||!in_array($kind,['renewal','topup'],true)||$units<0||$units>self::MAX)
            throw new \DomainException('Invalid payment event');
        $r=self::account($db,$tenant);$key=hash('sha256','payment|'.$reference);
        $fingerprint=hash('sha256',json_encode([$kind,$units,$until,$plan,$mode,$overage],JSON_THROW_ON_ERROR));
        $old=self::q($db,'SELECT fingerprint FROM wp_research_billing_events WHERE tenant_id=? AND event_key=?',[$tenant,$key])->fetchColumn();
        if(is_string($old)){if(!hash_equals($old,$fingerprint))throw new \DomainException('Payment event conflict');return self::usage($db,$tenant);}
        if($kind==='topup'){
            if($units===0||$until!==null||$plan!==null||$mode!==null||$overage||(int)$r['prepaid_units']+ $units>self::MAX)throw new \DomainException('Invalid topup');
            self::q($db,'UPDATE wp_research_tenants SET prepaid_units=prepaid_units+? WHERE tenant_id=?',[$units,$tenant]);
            self::event($db,$tenant,$key,$kind,$fingerprint,0,$units,'operator_topup');
        }else{
            if($mode===null||$plan===null||$until===null||$until<=time()||$until>time()+366*86400)throw new \DomainException('Invalid renewal');
            self::mode($mode);self::included($plan);
            if($mode==='payg'&&$units!==0)throw new \DomainException('PAYG has no included units');
            self::q($db,'UPDATE wp_research_tenants SET plan=?,billing_mode=?,paid_until=?,included_units=?,allow_overage=? WHERE tenant_id=?',
                [$plan,$mode,$until,$units,(int)$overage,$tenant]);
            self::event($db,$tenant,$key,$kind,$fingerprint,$units-(int)$r['included_units'],0,'operator_renewal');
        }
        return self::usage($db,$tenant);
    }
    public static function usage(\PDO $db,string $tenant):array{
        $r=self::account($db,$tenant);
        $tot=self::q($db,"SELECT COALESCE(-SUM(included_delta),0) AS included_used,COALESCE(-SUM(prepaid_delta),0) AS prepaid_used FROM wp_research_billing_events WHERE tenant_id=? AND event_kind='usage'",[$tenant])->fetch(\PDO::FETCH_ASSOC);
        return ['meter_version'=>self::VERSION,'plan'=>$r['plan'],'billing_mode'=>$r['billing_mode'],'paid_until'=>(int)$r['paid_until'],
            'included_remaining'=>(int)$r['included_units'],'prepaid_remaining'=>(int)$r['prepaid_units'],'allow_overage'=>(bool)$r['allow_overage'],
            'lifetime_included_used'=>(int)$tot['included_used'],'lifetime_prepaid_used'=>(int)$tot['prepaid_used'],
            'unit_costs'=>['request'=>1,'export'=>5,'global_export'=>20,'new_experiment'=>10,'usage'=>0]];
    }
}
