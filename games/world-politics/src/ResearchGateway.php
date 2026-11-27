<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ResearchQueue.php';

/** Separate entrypoint, credentials and database; no gameplay imports. */
final class ResearchGateway
{
    public function __construct(private readonly ResearchQueue $queue){}
    private static function response(int $status,array $body):array{return ['status'=>$status,'body'=>$body];}
    public function handle(string $method,string $path,string $raw,array $headers):array{
        try{
            if(strlen($raw)>16384||strlen($path)>256)return self::response(413,['code'=>'request_bound']);
            $h=array_change_key_case($headers,CASE_LOWER);
            if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$h['authorization']??'',$m))
                return self::response(401,['code'=>'research_authentication_required']);
            $token=$m[1];
            if(in_array($path,['/research/v1/billing/checkout','/research/v1/billing/portal','/research/v1/billing/payg'],true)){
                if($method!=='POST')return self::response(405,['code'=>'method_not_allowed']);
                if($path!=='/research/v1/billing/payg'&&getenv('WP_STRIPE_ENABLED')!=='YES')return self::response(503,['code'=>'stripe_disabled']);
                if(!preg_match('/^application\/json(?:; ?charset=utf-8)?$/iD',$h['content-type']??''))return self::response(415,['code'=>'json_required']);
                $obj=json_decode($raw,false,16,JSON_THROW_ON_ERROR);
                if(!$obj instanceof \stdClass)return self::response(400,['code'=>'invalid_json']);
                $body=(array)$obj;
                if($path==='/research/v1/billing/payg'){
                    if(array_keys($body)!==['plan']||!is_string($body['plan']))return self::response(400,['code'=>'invalid_fields']);
                    return self::response(200,$this->queue->selectPayg($token,$body['plan']));
                }
                if($path==='/research/v1/billing/portal'){
                    if($body!==[])return self::response(400,['code'=>'invalid_fields']);
                    return self::response(200,$this->queue->stripeBilling($token,'portal'));
                }
                if(array_keys($body)!==['offer']||!is_string($body['offer']))return self::response(400,['code'=>'invalid_fields']);
                if(!isset($h['idempotency-key']))return self::response(428,['code'=>'idempotency_required']);
                return self::response(201,$this->queue->stripeBilling($token,'checkout',$body['offer'],$h['idempotency-key']));
            }
            $admitted=$this->queue->admit($token,ResearchBilling::operation($method,$path));
            if($admitted===null)return self::response(401,['code'=>'research_authentication_required']);
            if(!$admitted)return self::response(429,['code'=>'research_rate_limited']);
            if($method!=='POST'&&$raw!=='')return self::response(400,['code'=>'body_forbidden']);
            if($method==='GET'&&$path==='/research/v1/usage')return self::response(200,$this->queue->usage($token));
            if($method==='GET'&&$path==='/research/v1/entitlements')return self::response(200,$this->queue->capabilities($token));
            if($method==='GET'&&$path==='/research/v1/worlds')return self::response(200,$this->queue->worlds($token));
            if($method==='POST'&&$path==='/research/v1/worlds'){
                if(!isset($h['idempotency-key']))return self::response(428,['code'=>'idempotency_required']);
                if($raw!==''&&$raw!=='{}')return self::response(400,['code'=>'body_forbidden']);
                return self::response(201,$this->queue->createWorld($token,$h['idempotency-key']));
            }
            if($method==='GET'&&preg_match('~^/research/v1/datasets/transitions(?:/([0-9]{1,15})/([0-9]{1,15}))?$~D',$path,$ds))
                return self::response(200,$this->queue->globalTransitions($token,isset($ds[1])?(int)$ds[1]:0,isset($ds[2])?(int)$ds[2]:null));
            if($method==='GET'&&preg_match('~^/research/v1/datasets/game-worlds(?:/([0-9]{1,15})/([0-9]{1,15}))?$~D',$path,$ds))
                return self::response(200,$this->queue->gameDataset($token,isset($ds[1])?(int)$ds[1]:0,isset($ds[2])?(int)$ds[2]:null));
            if($method==='GET'&&preg_match('~^/research/v1/datasets/(own|global)(?:/([0-9]{1,15})/([0-9]{1,15}))?$~D',$path,$ds)){
                if($ds[1]==='global'&&!$this->queue->capabilities($token)['global_dataset'])return self::response(403,['code'=>'research_pro_required']);
                return self::response(200,$this->queue->dataset($token,$ds[1],null,isset($ds[2])?(int)$ds[2]:0,isset($ds[3])?(int)$ds[3]:null));
            }
            if($method==='GET'&&preg_match('~^/research/v1/worlds/([a-f0-9]{32})/dataset(?:/([0-9]{1,15})/([0-9]{1,15}))?$~D',$path,$ds))
                return self::response(200,$this->queue->dataset($token,'own',$ds[1],isset($ds[2])?(int)$ds[2]:0,isset($ds[3])?(int)$ds[3]:null));
            if(preg_match('~^/research/v1/worlds/([a-f0-9]{32})/simulation(?:/(step|dataset)(?:/([0-9]{1,3}))?)?$~D',$path,$sim)){
                $op=$sim[2]??'';$after=isset($sim[3])?(int)$sim[3]:0;
                if($method==='GET'&&in_array($op,['','dataset'],true))return self::response(200,$this->queue->simulation($token,$sim[1],$op===''?'read':'dataset',[],'',$after));
                if($method==='POST'&&$op===''){
                    if($raw!==''&&$raw!=='{}')return self::response(400,['code'=>'body_forbidden']);
                    return self::response(201,$this->queue->simulation($token,$sim[1],'init'));
                }
                if($method==='POST'&&$op==='step'&&!isset($sim[3])){
                    if(!isset($h['idempotency-key']))return self::response(428,['code'=>'idempotency_required']);
                    if(!preg_match('/^application\/json(?:; ?charset=utf-8)?$/iD',$h['content-type']??''))return self::response(415,['code'=>'json_required']);
                    $body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($body))return self::response(400,['code'=>'invalid_json']);
                    return self::response(200,$this->queue->simulation($token,$sim[1],'step',$body,$h['idempotency-key']));
                }
                return self::response(405,['code'=>'method_not_allowed']);
            }
            if($method==='GET'&&$path==='/research/v1/models')return self::response(200,['models'=>ResearchExperiment::TYPES,'manifest'=>ResearchExperiment::pin()]);
            if($method==='POST'&&($path==='/research/v1/experiments'||preg_match('~^/research/v1/worlds/([a-f0-9]{32})/experiments$~D',$path,$w))){
                if(!preg_match('/^application\/json(?:; ?charset=utf-8)?$/iD',$h['content-type']??''))return self::response(415,['code'=>'json_required']);
                if(!isset($h['idempotency-key']))return self::response(428,['code'=>'idempotency_required']);
                $body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
                if(!is_array($body))return self::response(400,['code'=>'invalid_json']);
                return self::response(202,$this->queue->submit($token,$h['idempotency-key'],$body,$w[1]??null));
            }
            if(preg_match('~^/research/v1/experiments/([a-f0-9]{32})(/csv)?$~D',$path,$m)){
                $r=$this->queue->read($token,$m[1]);if($r===null)return self::response(404,['code'=>'not_found']);
                if($method==='GET'&&!isset($m[2]))return self::response(200,$r);
                if($method==='GET'&&isset($m[2])&&$r['status']==='complete')return self::response(200,['csv'=>ResearchExperiment::csv($r['result']),'result_sha256'=>$r['result']['result_sha256']]);
                if($method==='DELETE'&&!isset($m[2]))return self::response($this->queue->cancel($token,$m[1])?200:409,['job_id'=>$m[1]]);
                return self::response(409,['code'=>'invalid_job_state']);
            }
            return self::response(404,['code'=>'not_found']);
        }catch(ResearchBillingError $e){return self::response($e->status,['code'=>$e->getMessage()]);}
        catch(\JsonException $e){return self::response(400,['code'=>'invalid_json']);}
        catch(\DomainException $e){return self::response(409,['code'=>'research_request_rejected']);}
        catch(\Throwable $e){return self::response(503,['code'=>'research_unavailable']);}
    }
}
