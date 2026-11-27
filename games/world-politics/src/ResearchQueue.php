<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ResearchExperiment.php';
require_once __DIR__.'/ResearchBilling.php';
require_once __DIR__.'/OperatorAccess.php';

final class ResearchQueue
{
    private readonly string $evidenceKey;
    public function __construct(private readonly \PDO $db){
        $key=getenv('WP_RESEARCH_EVIDENCE_KEY');
        if(!is_string($key)||strlen($key)<32||$key===getenv('WP_CAREER_EVIDENCE_KEY'))throw new \DomainException('Independent research evidence key required');
        $this->evidenceKey=$key;
    }
    private function seal(array $p):string{return hash_hmac('sha256',ResearchExperiment::json($p),$this->evidenceKey);}
    private function requestSeal(string $id,string $tenant,string $request,string $manifest,?string $world=null):string{
        return $this->seal(['kind'=>'research-request-v1','job'=>$id,'tenant'=>$tenant,'world'=>$world,'request'=>$request,'manifest'=>$manifest]);
    }
    private function resultSeal(string $id,string $tenant,string $requestSignature,string $result):string{
        return $this->seal(['kind'=>'research-result-v1','job'=>$id,'tenant'=>$tenant,'request_signature'=>$requestSignature,'result'=>$result]);
    }
    private function q(string $sql,array $p=[]):\PDOStatement{$q=$this->db->prepare($sql);$q->execute($p);return $q;}
    private function tx(callable $f):mixed{
        $this->db->beginTransaction();try{$r=$f();$this->db->commit();return $r;}
        catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private static function id(string $id):void{if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new \DomainException('Invalid ID');}
    public function createTenant(string $plan='standard',int $days=30,bool $share=false,string $billing='monthly'):array{
        self::plan($plan,$days);ResearchBilling::mode($billing);
        return $this->tx(function()use($plan,$days,$share,$billing){
            $id=bin2hex(random_bytes(16));$this->q('INSERT INTO wp_research_tenants(tenant_id,created_at,plan,paid_until,share_dataset,billing_mode,included_units) VALUES(?,?,?,?,?,?,?)',[$id,time(),$plan,time()+86400*$days,(int)$share,$billing,$billing==='monthly'&&$days>0?ResearchBilling::included($plan):0]);
            return ['tenant_id'=>$id,'token'=>$this->issueLocked($id)];
        });
    }
    /** Operator CLI only. No ordinary client key is issued for this tenant. */
    public function createAdmin(string $plan,string $billing):array{
        self::plan($plan,0);ResearchBilling::mode($billing);
        return $this->tx(function()use($plan,$billing){
            $id=bin2hex(random_bytes(16));
            $this->q('INSERT INTO wp_research_tenants(tenant_id,created_at,plan,paid_until,billing_mode,admin_exempt) VALUES(?,?,?,?,?,1)',[$id,time(),$plan,0,$billing]);
            return ['tenant_id'=>$id,'plan'=>$plan,'billing_mode'=>$billing,'operator_exempt'=>true];
        });
    }
    private static function plan(string $plan,int $days):void{
        if(!in_array($plan,['standard','pro'],true)||$days<0||$days>366)throw new \DomainException('Invalid research entitlement');
    }
    /** Operator/billing integration only. Expiry and downgrade apply to all
     * existing credentials, without trusting claims embedded in a token. */
    public function changePlan(string $tenant,string $plan,int $days,bool $share):array{
        self::id($tenant);self::plan($plan,$days);
        return $this->tx(function()use($tenant,$plan,$days,$share){
            if($this->q('SELECT tenant_id FROM wp_research_tenants WHERE tenant_id=? FOR UPDATE',[$tenant])->fetchColumn()===false)throw new \DomainException('Unknown tenant');
            $until=time()+86400*$days;
            $this->q('UPDATE wp_research_tenants SET plan=?,paid_until=?,share_dataset=? WHERE tenant_id=?',[$plan,$until,(int)$share,$tenant]);
            return ['tenant_id'=>$tenant,'plan'=>$plan,'paid_until'=>$until,'share_dataset'=>$share];
        });
    }
    /** Trusted operator event: exact UTC expiry, unique payment reference.
     * No public endpoint can credit or renew its own balance. */
    public function fund(string $tenant,string $reference,string $kind,int $units,?int $until=null,?string $plan=null,?string $mode=null,bool $overage=false):array{
        self::id($tenant);
        return $this->tx(fn()=>ResearchBilling::fund($this->db,$tenant,$reference,$kind,$units,$until,$plan,$mode,$overage));
    }
    public function configureCapacity(string $profile,int $period,int $requests,int $units):array{
        return $this->tx(fn()=>ResearchBilling::configure($this->db,$profile,$period,$requests,$units));
    }
    public function usage(string $token):array{
        return $this->tx(fn()=>ResearchBilling::usage($this->db,$this->authorized($token)));
    }
    private function entitlement(string $tenant):array{
        $r=$this->q('SELECT plan,paid_until,share_dataset,admin_exempt FROM wp_research_tenants WHERE tenant_id=? AND enabled=1 AND billing_hold=0 AND (paid_until>? OR admin_exempt=1 OR billing_mode=\'payg\') FOR UPDATE',[$tenant,time()])->fetch(\PDO::FETCH_ASSOC);
        if($r===false)throw new \DomainException('Research entitlement expired');
        return $r;
    }
    public function capabilities(string $token):array{
        return $this->tx(function()use($token){
            $r=$this->entitlement($this->authorized($token));
            return ['plan'=>$r['plan'],'paid_until'=>(int)$r['paid_until'],'own_worlds'=>true,'own_experiments'=>true,
                'global_dataset'=>$r['plan']==='pro','share_dataset'=>(bool)$r['share_dataset'],'operator_exempt'=>(bool)$r['admin_exempt']];
        });
    }
    public function createWorld(string $token,string $key):array{
        if(!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key))throw new \DomainException('Invalid idempotency key');
        return $this->tx(function()use($token,$key){
            $tenant=$this->authorized($token);$key=hash('sha256',$key);
            $old=$this->q('SELECT world_id FROM wp_research_worlds WHERE tenant_id=? AND request_key=?',[$tenant,$key])->fetchColumn();
            if(is_string($old))return ['world_id'=>$old];
            if((int)$this->q('SELECT COUNT(*) FROM wp_research_worlds WHERE tenant_id=?',[$tenant])->fetchColumn()>=100)throw new \DomainException('World quota');
            $id=bin2hex(random_bytes(16));$this->q('INSERT INTO wp_research_worlds(world_id,tenant_id,request_key,created_at) VALUES(?,?,?,?)',[$id,$tenant,$key,time()]);
            return ['world_id'=>$id];
        });
    }
    private function ownWorld(string $tenant,string $world):void{
        self::id($world);
        if($this->q('SELECT 1 FROM wp_research_worlds WHERE world_id=? AND tenant_id=?',[$world,$tenant])->fetchColumn()===false)throw new \DomainException('World not accessible');
    }
    public function worlds(string $token):array{
        return $this->tx(function()use($token){
            $tenant=$this->authorized($token);
            $rows=$this->q('SELECT world_id,created_at FROM wp_research_worlds WHERE tenant_id=? ORDER BY world_id',[$tenant])->fetchAll(\PDO::FETCH_ASSOC);
            return ['worlds'=>$rows,'classification'=>'research_experiment_collections_not_persistent_game_worlds'];
        });
    }
    public function simulation(string $token,string $world,string $operation,array $body=[],string $key='',int $after=0):array{
        require_once __DIR__.'/ResearchPersistentStore.php';
        return $this->tx(function()use($token,$world,$operation,$body,$key,$after){
            $tenant=$this->authorized($token);$this->ownWorld($tenant,$world);
            $store=new ResearchPersistentStore($this->db,$this->evidenceKey);
            return match($operation){
                'init'=>$store->init($world,$tenant),
                'read'=>$store->read($world,$tenant),
                'step'=>$store->step($world,$tenant,$key,$body),
                'dataset'=>$store->dataset($world,$tenant,$after),
                default=>throw new \DomainException('Unknown simulation operation')
            };
        });
    }
    public function globalTransitions(string $token,int $after=0,?int $through=null):array{
        require_once __DIR__.'/ResearchPersistentStore.php';
        return $this->tx(function()use($token,$after,$through){
            $r=$this->entitlement($this->authorized($token));
            if($r['plan']!=='pro')throw new ResearchBillingError(403,'research_pro_required');
            return (new ResearchPersistentStore($this->db,$this->evidenceKey))->globalDataset($after,$through);
        });
    }
    private function issueLocked(string $tenant):string{
        $token=bin2hex(random_bytes(32));$this->q('INSERT INTO wp_research_keys(key_sha,tenant_id,expires_at) VALUES(?,?,?)',[hash('sha256',$token),$tenant,time()+86400*90]);
        return $token;
    }
    public function rotate(string $tenant):array{
        self::id($tenant);
        return $this->tx(function()use($tenant){
            if((int)$this->q('SELECT enabled FROM wp_research_tenants WHERE tenant_id=? AND admin_exempt=0 FOR UPDATE',[$tenant])->fetchColumn()!==1)throw new \DomainException('Inactive tenant');
            $this->q('UPDATE wp_research_keys SET revoked=1 WHERE tenant_id=?',[$tenant]);
            return ['tenant_id'=>$tenant,'token'=>$this->issueLocked($tenant)];
        });
    }
    public function authenticate(string $token,bool $lock=false):?string{
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
        $admin=OperatorAccess::research($token);
        if($admin!==null){
            $id=$this->q('SELECT tenant_id FROM wp_research_tenants WHERE tenant_id=? AND plan=? AND billing_mode=? AND admin_exempt=1 AND enabled=1'.($lock?' FOR UPDATE':''),[$admin['tenant'],$admin['plan'],$admin['mode']])->fetchColumn();
            return is_string($id)?$id:null;
        }
        $r=$this->q('SELECT k.tenant_id FROM wp_research_keys k JOIN wp_research_tenants t USING(tenant_id) WHERE k.key_sha=? AND k.expires_at>? AND k.revoked=0 AND t.enabled=1 AND t.billing_hold=0 AND t.admin_exempt=0 AND (t.paid_until>? OR t.billing_mode=\'payg\')'.($lock?' FOR UPDATE':''),[hash('sha256',$token),time(),time()])->fetchColumn();
        return is_string($r)?$r:null;
    }
    /** Authenticated request admission, serialized with tenant/key changes. */
    /** Billing-only identity permits a still-valid ordinary key even when a
     * subscription has expired. Never confers Research data rights. */
    public function billingIdentity(string $token):string{
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new ResearchBillingError(401,'billing_authentication_required');
        return $this->tx(function()use($token){
            $id=$this->q('SELECT k.tenant_id FROM wp_research_keys k JOIN wp_research_tenants t USING(tenant_id)
                WHERE k.key_sha=? AND k.expires_at>? AND k.revoked=0 AND t.enabled=1 AND t.billing_hold=0 AND t.admin_exempt=0 FOR UPDATE',
                [hash('sha256',$token),time()])->fetchColumn();
            if(!is_string($id))throw new ResearchBillingError(401,'billing_authentication_required');
            $window=intdiv(time(),60);
            foreach([[$id,5],['global',60]] as [$scope,$limit]){
                $this->q('INSERT IGNORE INTO wp_research_checkout_rate(scope_key,minute_no,requests) VALUES(?,?,0)',[$scope,$window]);
                $r=$this->q('SELECT minute_no,requests FROM wp_research_checkout_rate WHERE scope_key=? FOR UPDATE',[$scope])->fetch(\PDO::FETCH_ASSOC);
                $n=(int)$r['minute_no']===$window?(int)$r['requests']:0;
                if($n>=$limit)throw new ResearchBillingError(429,'billing_rate_limited');
                $this->q('UPDATE wp_research_checkout_rate SET minute_no=?,requests=? WHERE scope_key=?',[$window,$n+1,$scope]);
            }
            return $id;
        });
    }
    public function selectPayg(string $token,string $plan):array{
        if(!in_array($plan,['standard','pro'],true))throw new \DomainException('Invalid PAYG plan');
        $tenant=$this->billingIdentity($token);
        return $this->tx(function()use($tenant,$token,$plan){
            $a=$this->q('SELECT t.* FROM wp_research_tenants t JOIN wp_research_keys k USING(tenant_id) WHERE t.tenant_id=? AND k.key_sha=? AND k.expires_at>? AND k.revoked=0 AND t.enabled=1 AND t.billing_hold=0 AND t.admin_exempt=0 FOR UPDATE',[$tenant,hash('sha256',$token),time()])->fetch(\PDO::FETCH_ASSOC);
            if($a===false)throw new ResearchBillingError(401,'billing_authentication_required');
            if(($a['billing_mode']==='monthly'&&(int)$a['paid_until']>time())||$this->q("SELECT 1 FROM wp_stripe_research_orders WHERE tenant_id=? AND mode='subscription' AND ended_at IS NULL LIMIT 1 FOR UPDATE",[$tenant])->fetchColumn()!==false)
                throw new \DomainException('Active or pending subscription');
            if($a['plan']!==$plan&&((int)$a['prepaid_units']>0||$this->q("SELECT 1 FROM wp_stripe_research_orders WHERE tenant_id=? AND mode='payment' AND settled_at IS NULL AND ended_at IS NULL LIMIT 1 FOR UPDATE",[$tenant])->fetchColumn()!==false))
                throw new \DomainException('Existing tier credit balance or pending pack');
            $this->q("UPDATE wp_research_tenants SET plan=?,billing_mode='payg',included_units=0,allow_overage=0,paid_until=0 WHERE tenant_id=?",[$plan,$tenant]);
            return ['plan'=>$plan,'billing_mode'=>'payg','prepaid_remaining'=>(int)$a['prepaid_units']];
        });
    }
    public function stripeBilling(string $token,string $op,string $offer='',string $key=''):array{
        require_once __DIR__.'/ResearchStripe.php';
        $id=$this->billingIdentity($token);StripeConfiguration::ready();$c=new StripeTestClient();
        $s=new ResearchStripe($this->db,$c->request(...));
        return $op==='portal'?$s->portal($id):($op==='checkout'?$s->checkout($id,$offer,$key):throw new \DomainException('Invalid billing operation'));
    }
    public function admit(string $token,string $operation='read'):?bool{
        return $this->tx(function()use($token,$operation){
            $tenant=$this->authenticate($token,true);if($tenant===null)return null;
            $minute=intdiv(time(),60);
            $r=$this->q('SELECT minute_no,requests FROM wp_research_rate WHERE tenant_id=? FOR UPDATE',[$tenant])->fetch(\PDO::FETCH_ASSOC);
            $count=$r!==false&&(int)$r['minute_no']===$minute?(int)$r['requests']:0;
            if($count>=120)return false;
            ResearchBilling::admit($this->db,$tenant,$operation);
            $this->q('INSERT INTO wp_research_rate(tenant_id,minute_no,requests) VALUES(?,?,?) ON DUPLICATE KEY UPDATE minute_no=VALUES(minute_no),requests=VALUES(requests)',
                [$tenant,$minute,$count+1]);
            return true;
        });
    }
    private function authorized(string $token):string{
        $tenant=$this->authenticate($token,true);if($tenant===null)throw new \DomainException('Invalid research credential');return $tenant;
    }
    public function submit(string $token,string $key,array $request,?string $world=null):array{
        if(!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key))throw new \DomainException('Invalid idempotency key');
        $request=ResearchExperiment::normalize($request);$sha=ResearchExperiment::hash($request);
        return $this->tx(function()use($token,$key,$request,$sha,$world){
            $tenant=$this->authorized($token);if($world!==null)$this->ownWorld($tenant,$world);$key=hash('sha256',$key);
            $old=$this->q('SELECT job_id,request_sha,world_id FROM wp_research_jobs WHERE tenant_id=? AND request_key=?',[$tenant,$key])->fetch(\PDO::FETCH_ASSOC);
            if($old!==false){if($old['world_id']!==$world||!hash_equals($old['request_sha'],$sha))throw new \DomainException('Idempotency conflict');return ['job_id'=>$old['job_id']];}
            $n=(int)$this->q('SELECT COUNT(*) FROM wp_research_jobs WHERE tenant_id=? AND created_at>=?',[$tenant,time()-86400])->fetchColumn();
            $all=(int)$this->q('SELECT COUNT(*) FROM wp_research_jobs WHERE tenant_id=?',[$tenant])->fetchColumn();
            if($n>=20||$all>=1000)throw new \DomainException('Tenant quota');
            if((int)$this->q("SELECT COUNT(*) FROM wp_research_jobs WHERE tenant_id=? AND status='queued'",[$tenant])->fetchColumn()>=5)throw new ResearchBillingError(429,'tenant_queue_limit');
            // Validate the bounded deterministic contract before admitting a job.
            $pin=ResearchExperiment::pin();ResearchExperiment::run($request,$pin);
            $id=bin2hex(random_bytes(16));
            ResearchBilling::compute($this->db,$tenant,$id);
            $requestJson=ResearchExperiment::json($request);$manifestJson=ResearchExperiment::json($pin);
            $this->q('INSERT INTO wp_research_jobs(job_id,tenant_id,world_id,request_key,request_sha,request_json,manifest_json,request_signature,created_at) VALUES(?,?,?,?,?,?,?,?,?)',
                [$id,$tenant,$world,$key,$sha,$requestJson,$manifestJson,$this->requestSeal($id,$tenant,$requestJson,$manifestJson,$world),time()]);
            return ['job_id'=>$id];
        });
    }
    public function read(string $token,string $id):?array{
        self::id($id);
        return $this->tx(function()use($token,$id){
            $tenant=$this->authorized($token);
            $r=$this->q('SELECT job_id,world_id,status,created_at,completed_at,result_json,result_sha,result_signature,request_json,manifest_json,request_signature,error_code FROM wp_research_jobs WHERE job_id=? AND tenant_id=?',[$id,$tenant])->fetch(\PDO::FETCH_ASSOC);
            if($r===false)return null;
            if(!hash_equals($r['request_signature'],$this->requestSeal($id,$tenant,$r['request_json'],$r['manifest_json'],$r['world_id'])))throw new \DomainException('Request seal mismatch');
            $result=null;if($r['result_json']!==null){
                if($r['status']!=='complete'||!is_string($r['result_sha'])||!is_string($r['result_signature'])
                    ||!hash_equals($r['result_sha'],hash('sha256',$r['result_json']))
                    ||!hash_equals($r['result_signature'],$this->resultSeal($id,$tenant,$r['request_signature'],$r['result_json'])))
                    throw new \DomainException('Result digest or seal mismatch');
                $result=json_decode($r['result_json'],true,64,JSON_THROW_ON_ERROR);
            }elseif($r['status']==='complete')throw new \DomainException('Missing completed result');
            return ['job_id'=>$r['job_id'],'status'=>$r['status'],'created_at'=>(int)$r['created_at'],
                'completed_at'=>$r['completed_at']===null?null:(int)$r['completed_at'],'result'=>$result,'error_code'=>$r['error_code']];
        });
    }
    public function cancel(string $token,string $id):bool{
        self::id($id);return $this->tx(function()use($token,$id){
            $tenant=$this->authorized($token);
            return $this->q("UPDATE wp_research_jobs SET status='cancelled',completed_at=? WHERE job_id=? AND tenant_id=? AND status='queued'",[time(),$id,$tenant])->rowCount()===1;
        });
    }
    /** Export verified, bounded experiment records. No personal identifiers,
     * token, label, account data or private reports in the global feed. */
    public function dataset(string $token,string $scope,?string $world,int $after=0,?int $through=null):array{
        if(!in_array($scope,['own','global'],true)||$after<0||($through!==null&&$through<$after)||($scope==='global'&&$world!==null))throw new \DomainException('Invalid dataset scope');
        return $this->tx(function()use($token,$scope,$world,$after,$through){
            $tenant=$this->authorized($token);$plan=$this->entitlement($tenant);
            if($scope==='global'&&$plan['plan']!=='pro')throw new \DomainException('Research Pro required');
            if($world!==null)$this->ownWorld($tenant,$world);
            $max=$through??(int)$this->q('SELECT COALESCE(MAX(seq),0) FROM wp_research_dataset')->fetchColumn();
            $where=$scope==='own'?' AND j.tenant_id=?':' AND t.share_dataset=1';
            $args=[$after,$max];if($scope==='own')$args[]=$tenant;
            if($world!==null){$where.=' AND j.world_id=?';$args[]=$world;}
            $raw=$this->q("SELECT d.seq,j.* FROM wp_research_dataset d JOIN wp_research_jobs j USING(job_id)
                JOIN wp_research_tenants t USING(tenant_id) WHERE d.seq>? AND d.seq<=? AND j.status='complete'".$where.' ORDER BY d.seq LIMIT 21',$args)->fetchAll(\PDO::FETCH_ASSOC);
            $out=[];$next=null;$scanned=0;
            foreach(array_slice($raw,0,20) as $r){
                $seq=(int)$r['seq'];$scanned=$seq;
                if(!hash_equals($r['request_signature'],$this->requestSeal($r['job_id'],$r['tenant_id'],$r['request_json'],$r['manifest_json'],$r['world_id']))
                    ||!is_string($r['result_json'])||!is_string($r['result_signature'])||!is_string($r['result_sha'])
                    ||!hash_equals($r['result_sha'],hash('sha256',$r['result_json']))
                    ||!hash_equals($r['result_signature'],$this->resultSeal($r['job_id'],$r['tenant_id'],$r['request_signature'],$r['result_json'])))
                    throw new \DomainException('Dataset evidence invalid');
                $report=json_decode($r['result_json'],true,64,JSON_THROW_ON_ERROR);
                if($scope==='global'&&!in_array($report['request']['type'],['policy-capacity','working-hours','household-sensitivity','household-backtest','fiscal-path','family-transfer','countries','programme-coverage'],true))continue;
                // The experiment catalogue has closed, validated parameters and no free-text profiles.
                $out[]=['seq'=>$seq,'experiment'=>$scope==='own'?$r['job_id']:hash_hmac('sha256','dataset-job|'.$r['job_id'],$this->evidenceKey),
                    'world'=>$r['world_id']===null?null:($scope==='own'?$r['world_id']:hash_hmac('sha256','dataset-world|'.$r['world_id'],$this->evidenceKey)),
                    'report'=>$report];
            }
            if(count($raw)>20)$next=$scanned;
            return ['version'=>'wp-research-dataset-v1','scope'=>$scope,'through'=>$max,'next_after'=>$next,'rows'=>$out,'synthetic_simulations'=>true];
        });
    }
    /** Operator-only import of signed aggregate envelopes, idempotent by world/day. */
    public function importGameDataset(array $pack,bool $skipWithdrawn=false):array{
        require_once __DIR__.'/GameDataset.php';
        if(($pack['version']??null)!==GameDataset::VERSION||!is_array($pack['rows']??null)||!array_is_list($pack['rows'])||count($pack['rows'])>100)
            throw new \DomainException('Invalid dataset batch');
        return $this->tx(function()use($pack,$skipWithdrawn){
            $n=0;$skipped=0;foreach($pack['rows'] as $e){
                $r=GameDataset::verify($e);$json=ResearchExperiment::json($e);
                if($this->q('SELECT 1 FROM wp_research_game_dataset_withdrawn WHERE world_alias=? FOR UPDATE',[$r['world']])->fetchColumn()!==false){
                    if($skipWithdrawn){$skipped++;continue;}throw new \DomainException('Dataset withdrawn');
                }
                $old=$this->q('SELECT envelope_json FROM wp_research_game_dataset WHERE world_alias=? AND committed_day=? FOR UPDATE',[$r['world'],$r['day']])->fetchColumn();
                if(is_string($old)){if(!hash_equals($old,$json))throw new \DomainException('Dataset revision conflict');continue;}
                $this->q('INSERT INTO wp_research_game_dataset(world_alias,committed_day,envelope_json) VALUES(?,?,?)',[$r['world'],$r['day'],$json]);$n++;
            }
            return $skipWithdrawn?['inserted'=>$n,'withdrawn_skipped'=>$skipped]:['inserted'=>$n];
        });
    }
    /** Trusted operator withdrawal using the exported pseudonymous world ID.
     * Past downloads cannot be recalled. Future imports are rejected. */
    public function withdrawGameDataset(string $alias):array{
        if(!preg_match('/^[a-f0-9]{64}$/D',$alias))throw new \DomainException('Invalid world alias');
        return $this->tx(function()use($alias){
            $this->q('INSERT IGNORE INTO wp_research_game_dataset_withdrawn(world_alias,withdrawn_at) VALUES(?,?)',[$alias,time()]);
            $n=$this->q('DELETE FROM wp_research_game_dataset WHERE world_alias=?',[$alias])->rowCount();
            return ['withdrawn'=>true,'deleted_rows'=>$n];
        });
    }
    public function gameDataset(string $token,int $after=0,?int $through=null):array{
        require_once __DIR__.'/GameDataset.php';
        if($after<0||($through!==null&&$through<$after))throw new \DomainException('Invalid cursor');
        return $this->tx(function()use($token,$after,$through){
            $plan=$this->entitlement($this->authorized($token));
            if($plan['plan']!=='pro')throw new ResearchBillingError(403,'research_pro_required');
            $max=$through??(int)$this->q('SELECT COALESCE(MAX(seq),0) FROM wp_research_game_dataset')->fetchColumn();
            $raw=$this->q('SELECT seq,envelope_json FROM wp_research_game_dataset WHERE seq>? AND seq<=? ORDER BY seq LIMIT 101',[$after,$max])->fetchAll(\PDO::FETCH_ASSOC);
            $rows=[];$next=null;
            foreach(array_slice($raw,0,100) as $x){$e=json_decode($x['envelope_json'],true,32,JSON_THROW_ON_ERROR);$rows[]=['seq'=>(int)$x['seq'],'row'=>GameDataset::verify($e)];}
            if(count($raw)>100)$next=$rows[99]['seq'];
            return ['version'=>GameDataset::VERSION,'scope'=>'global_eligible_game_worlds','through'=>$max,'next_after'=>$next,'rows'=>$rows];
        });
    }
    /** One bounded job per worker transaction; interrupted work rolls back
     * to queued. No untrusted code or unbounded scenarios are executed. */
    public function work():?array{
        return $this->tx(function(){
            $r=$this->q("SELECT * FROM wp_research_jobs WHERE status='queued' ORDER BY created_at,job_id LIMIT 1 FOR UPDATE")->fetch(\PDO::FETCH_ASSOC);
            if($r===false)return null;
            $status='complete';$json=null;$error=null;
            try{
                if(!hash_equals($r['request_signature'],$this->requestSeal($r['job_id'],$r['tenant_id'],$r['request_json'],$r['manifest_json'],$r['world_id'])))
                    throw new \DomainException('Request seal');
                $request=json_decode($r['request_json'],true,32,JSON_THROW_ON_ERROR);
                if(!hash_equals($r['request_sha'],ResearchExperiment::hash($request)))throw new \DomainException('Request digest');
                $pin=json_decode($r['manifest_json'],true,32,JSON_THROW_ON_ERROR);
                $result=ResearchExperiment::run($request,$pin);$json=ResearchExperiment::json($result);
                if(strlen($json)>262144)throw new \DomainException('Result bound');
            }catch(\DomainException|\JsonException $e){$status='failed';$error='invalid_or_changed_evidence';$json=null;}
            $signature=$json===null?null:$this->resultSeal($r['job_id'],$r['tenant_id'],$r['request_signature'],$json);
            $this->q('UPDATE wp_research_jobs SET status=?,completed_at=?,result_json=?,result_sha=?,result_signature=?,error_code=? WHERE job_id=?',
                [$status,time(),$json,$json===null?null:hash('sha256',$json),$signature,$error,$r['job_id']]);
            if($status==='complete')$this->q('INSERT INTO wp_research_dataset(job_id) VALUES(?)',[$r['job_id']]);
            return ['job_id'=>$r['job_id'],'status'=>$status];
        });
    }
}
