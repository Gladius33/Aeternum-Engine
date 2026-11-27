<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ResearchExperiment.php';

final class ResearchQueue
{
    private readonly string $evidenceKey;
    public function __construct(private readonly \PDO $db){
        $key=getenv('WP_RESEARCH_EVIDENCE_KEY');
        if(!is_string($key)||strlen($key)<32||$key===getenv('WP_CAREER_EVIDENCE_KEY'))throw new \DomainException('Independent research evidence key required');
        $this->evidenceKey=$key;
    }
    private function seal(array $p):string{return hash_hmac('sha256',ResearchExperiment::json($p),$this->evidenceKey);}
    private function requestSeal(string $id,string $tenant,string $request,string $manifest):string{
        return $this->seal(['kind'=>'research-request-v1','job'=>$id,'tenant'=>$tenant,'request'=>$request,'manifest'=>$manifest]);
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
    public function createTenant():array{
        return $this->tx(function(){
            $id=bin2hex(random_bytes(16));$this->q('INSERT INTO wp_research_tenants(tenant_id,created_at) VALUES(?,?)',[$id,time()]);
            return ['tenant_id'=>$id,'token'=>$this->issueLocked($id)];
        });
    }
    private function issueLocked(string $tenant):string{
        $token=bin2hex(random_bytes(32));$this->q('INSERT INTO wp_research_keys(key_sha,tenant_id,expires_at) VALUES(?,?,?)',[hash('sha256',$token),$tenant,time()+86400*90]);
        return $token;
    }
    public function rotate(string $tenant):array{
        self::id($tenant);
        return $this->tx(function()use($tenant){
            if((int)$this->q('SELECT enabled FROM wp_research_tenants WHERE tenant_id=? FOR UPDATE',[$tenant])->fetchColumn()!==1)throw new \DomainException('Inactive tenant');
            $this->q('UPDATE wp_research_keys SET revoked=1 WHERE tenant_id=?',[$tenant]);
            return ['tenant_id'=>$tenant,'token'=>$this->issueLocked($tenant)];
        });
    }
    public function authenticate(string $token,bool $lock=false):?string{
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
        $r=$this->q('SELECT k.tenant_id FROM wp_research_keys k JOIN wp_research_tenants t USING(tenant_id) WHERE k.key_sha=? AND k.expires_at>? AND k.revoked=0 AND t.enabled=1'.($lock?' FOR UPDATE':''),[hash('sha256',$token),time()])->fetchColumn();
        return is_string($r)?$r:null;
    }
    /** Authenticated request admission, serialized with tenant/key changes. */
    public function admit(string $token):?bool{
        return $this->tx(function()use($token){
            $tenant=$this->authenticate($token,true);if($tenant===null)return null;
            $minute=intdiv(time(),60);
            $r=$this->q('SELECT minute_no,requests FROM wp_research_rate WHERE tenant_id=? FOR UPDATE',[$tenant])->fetch(\PDO::FETCH_ASSOC);
            $count=$r!==false&&(int)$r['minute_no']===$minute?(int)$r['requests']:0;
            if($count>=120)return false;
            $this->q('INSERT INTO wp_research_rate(tenant_id,minute_no,requests) VALUES(?,?,?) ON DUPLICATE KEY UPDATE minute_no=VALUES(minute_no),requests=VALUES(requests)',
                [$tenant,$minute,$count+1]);
            return true;
        });
    }
    private function authorized(string $token):string{
        $tenant=$this->authenticate($token,true);if($tenant===null)throw new \DomainException('Invalid research credential');return $tenant;
    }
    public function submit(string $token,string $key,array $request):array{
        if(!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key))throw new \DomainException('Invalid idempotency key');
        $request=ResearchExperiment::normalize($request);$sha=ResearchExperiment::hash($request);
        return $this->tx(function()use($token,$key,$request,$sha){
            $tenant=$this->authorized($token);$key=hash('sha256',$key);
            $old=$this->q('SELECT job_id,request_sha FROM wp_research_jobs WHERE tenant_id=? AND request_key=?',[$tenant,$key])->fetch(\PDO::FETCH_ASSOC);
            if($old!==false){if(!hash_equals($old['request_sha'],$sha))throw new \DomainException('Idempotency conflict');return ['job_id'=>$old['job_id']];}
            $n=(int)$this->q('SELECT COUNT(*) FROM wp_research_jobs WHERE tenant_id=? AND created_at>=?',[$tenant,time()-86400])->fetchColumn();
            $all=(int)$this->q('SELECT COUNT(*) FROM wp_research_jobs WHERE tenant_id=?',[$tenant])->fetchColumn();
            if($n>=20||$all>=1000)throw new \DomainException('Tenant quota');
            // Validate the bounded deterministic contract before admitting a job.
            $pin=ResearchExperiment::pin();ResearchExperiment::run($request,$pin);
            $id=bin2hex(random_bytes(16));
            $requestJson=ResearchExperiment::json($request);$manifestJson=ResearchExperiment::json($pin);
            $this->q('INSERT INTO wp_research_jobs(job_id,tenant_id,request_key,request_sha,request_json,manifest_json,request_signature,created_at) VALUES(?,?,?,?,?,?,?,?)',
                [$id,$tenant,$key,$sha,$requestJson,$manifestJson,$this->requestSeal($id,$tenant,$requestJson,$manifestJson),time()]);
            return ['job_id'=>$id];
        });
    }
    public function read(string $token,string $id):?array{
        self::id($id);
        return $this->tx(function()use($token,$id){
            $tenant=$this->authorized($token);
            $r=$this->q('SELECT job_id,status,created_at,completed_at,result_json,result_sha,result_signature,request_json,manifest_json,request_signature,error_code FROM wp_research_jobs WHERE job_id=? AND tenant_id=?',[$id,$tenant])->fetch(\PDO::FETCH_ASSOC);
            if($r===false)return null;
            if(!hash_equals($r['request_signature'],$this->requestSeal($id,$tenant,$r['request_json'],$r['manifest_json'])))throw new \DomainException('Request seal mismatch');
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
    /** One bounded job per worker transaction; interrupted work rolls back
     * to queued. No untrusted code or unbounded scenarios are executed. */
    public function work():?array{
        return $this->tx(function(){
            $r=$this->q("SELECT * FROM wp_research_jobs WHERE status='queued' ORDER BY created_at,job_id LIMIT 1 FOR UPDATE")->fetch(\PDO::FETCH_ASSOC);
            if($r===false)return null;
            $status='complete';$json=null;$error=null;
            try{
                if(!hash_equals($r['request_signature'],$this->requestSeal($r['job_id'],$r['tenant_id'],$r['request_json'],$r['manifest_json'])))
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
            return ['job_id'=>$r['job_id'],'status'=>$status];
        });
    }
}
