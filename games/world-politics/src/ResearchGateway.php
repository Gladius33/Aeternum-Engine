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
            $token=$m[1];$admitted=$this->queue->admit($token);
            if($admitted===null)return self::response(401,['code'=>'research_authentication_required']);
            if(!$admitted)return self::response(429,['code'=>'research_rate_limited']);
            if($method!=='POST'&&$raw!=='')return self::response(400,['code'=>'body_forbidden']);
            if($method==='GET'&&$path==='/research/v1/models')return self::response(200,['models'=>ResearchExperiment::TYPES,'manifest'=>ResearchExperiment::pin()]);
            if($method==='POST'&&$path==='/research/v1/experiments'){
                if(!preg_match('/^application\/json(?:; ?charset=utf-8)?$/iD',$h['content-type']??''))return self::response(415,['code'=>'json_required']);
                if(!isset($h['idempotency-key']))return self::response(428,['code'=>'idempotency_required']);
                $body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
                if(!is_array($body))return self::response(400,['code'=>'invalid_json']);
                return self::response(202,$this->queue->submit($token,$h['idempotency-key'],$body));
            }
            if(preg_match('~^/research/v1/experiments/([a-f0-9]{32})(/csv)?$~D',$path,$m)){
                $r=$this->queue->read($token,$m[1]);if($r===null)return self::response(404,['code'=>'not_found']);
                if($method==='GET'&&!isset($m[2]))return self::response(200,$r);
                if($method==='GET'&&isset($m[2])&&$r['status']==='complete')return self::response(200,['csv'=>ResearchExperiment::csv($r['result']),'result_sha256'=>$r['result']['result_sha256']]);
                if($method==='DELETE'&&!isset($m[2]))return self::response($this->queue->cancel($token,$m[1])?200:409,['job_id'=>$m[1]]);
                return self::response(409,['code'=>'invalid_job_state']);
            }
            return self::response(404,['code'=>'not_found']);
        }catch(\JsonException $e){return self::response(400,['code'=>'invalid_json']);}
        catch(\DomainException $e){return self::response(409,['code'=>'research_request_rejected']);}
        catch(\Throwable $e){return self::response(503,['code'=>'research_unavailable']);}
    }
}
