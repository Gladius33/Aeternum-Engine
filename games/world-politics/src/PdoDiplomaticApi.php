<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoDiplomaticStore.php';

/**
 * Transport-neutral RESTful facade. Not an HTTP front controller.
 * Only a separately audited gateway may supply an authenticated principal.
 */
final class PdoDiplomaticApi
{
    public function __construct(private readonly PdoDiplomaticStore $store) {}

    /**
     * @param array<string,mixed>|null $payload
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    public function handle(string $method,string $route,?array $payload=null,array $headers=[]):array
    {
        if (strlen($route)>512 || !preg_match(
            '~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/diplomacy(?:/(actors|proposals|responses|ai|clock)(?:/(decisions))?)?$~D',
            $route,$match
        )) return self::problem(404,'route_not_found');
        $world=$match[1];$resource=$match[2]??'';$sub=$match[3]??'';
        $normalized=[];
        foreach ($headers as $key=>$value) {
            if (is_string($key) && is_string($value)) $normalized[strtolower($key)]=$value;
        }
        try {
            if ($method==='GET') {
                if ($payload!==null) return self::problem(400,'get_body_forbidden');
                $read=$this->store->read($world);
                if ($resource==='' && $sub==='') {
                    return self::success(200,$read['state'],$read['etag']);
                }
                if ($resource==='actors' && $sub==='') {
                    return self::success(200,[
                        'world_id'=>$world,
                        'registry_hash'=>$this->store->registry()->hash(),
                        'actors'=>$this->store->registry()->all()
                    ],$read['etag']);
                }
                if ($resource==='proposals' && $sub==='') {
                    return self::success(200,[
                        'world_id'=>$world,
                        'proposals'=>$read['state']['proposals']
                    ],$read['etag']);
                }
                return self::problem(405,'method_not_allowed');
            }
            if ($method!=='POST') return self::problem(405,'method_not_allowed');
            $operation=match(true) {
                $resource==='proposals' && $sub==='' => 'propose',
                $resource==='responses' && $sub==='' => 'respond',
                $resource==='ai' && $sub==='decisions' => 'ai_decide',
                $resource==='clock' && $sub==='' => 'clock',
                default=>null
            };
            if ($operation===null) return self::problem(405,'method_not_allowed');
            if (!is_array($payload) || (array_is_list($payload) && $payload!==[])) {
                return self::problem(400,'json_object_required');
            }
            $idempotency=$normalized['idempotency-key']??'';
            $ifMatch=$normalized['if-match']??'';
            if ($idempotency==='' || $ifMatch==='') {
                return self::problem(428,'precondition_required');
            }
            $receipt=$this->store->execute(
                $world,$operation,$payload,$idempotency,$ifMatch
            );
            return self::success($receipt['status'],$receipt['result'],$receipt['etag']);
        } catch (\InvalidArgumentException|\JsonException $e) {
            return self::problem(422,'invalid_request');
        } catch (\DomainException $e) {
            $reason=$e->getMessage();
            if ($method==='GET' || $reason==='Unknown world' || $reason==='No verified world access') {
                return self::problem(404,'not_found');
            }
            if (in_array($reason,[
                'Trusted AI scheduler required','Trusted clock required',
                'Actor has no verified authority over this diplomatic participant',
                'Human account cannot pilot an AI-only actor',
                'Principal changed inside transaction'
            ],true)) return self::problem(403,'forbidden');
            if ($reason==='Stale state precondition') return self::problem(412,'stale_precondition');
            return self::problem(409,'state_conflict');
        } catch (\Throwable $e) {
            // Do not expose driver SQL details or service credentials.
            return self::problem(503,'backend_unavailable');
        }
    }

    /** @param array<string,mixed> $body */
    private static function success(int $status,array $body,string $etag):array
    {
        return [
            'status'=>$status,
            'headers'=>[
                'Content-Type'=>'application/json',
                'Cache-Control'=>'no-store',
                'ETag'=>$etag
            ],
            'body'=>$body
        ];
    }

    private static function problem(int $status,string $code):array
    {
        return [
            'status'=>$status,
            'headers'=>[
                'Content-Type'=>'application/problem+json',
                'Cache-Control'=>'no-store'
            ],
            'body'=>['type'=>'about:blank','status'=>$status,'code'=>$code]
        ];
    }
}
