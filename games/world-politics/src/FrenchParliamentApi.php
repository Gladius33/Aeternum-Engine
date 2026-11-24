<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoFrenchParliamentStore.php';

/**
 * WP28 transport-neutral parliamentary API. No public HTTP server is
 * launched; a future gateway must inject a session-verified actor.
 *
 * Player requests NEVER include group voting coefficients, forged
 * coalition membership or simulated yes-vote totals. Those are resolved
 * by the authenticated service using the pinned scenario and roll calls.
 */
final class FrenchParliamentApi
{
    public function __construct(private readonly PdoFrenchParliamentStore $store){}

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    public function handle(
        string $method,string $route,?array $body=null,array $headers=[]
    ):array {
        if (strlen($route)>512 || !preg_match(
            '~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/france/assembly(?:/(matters|ballots|roll-calls|coalitions|signatures)(?:/([a-z][a-z0-9_-]{0,63}))?)?$~D',
            $route,$m
        )) return self::error(404,'route_not_found');
        $world=$m[1];$resource=$m[2]??'';$item=$m[3]??'';
        $h=[];
        foreach($headers as $k=>$v) if (is_string($k)&&is_string($v)) {
            $h[strtolower($k)]=$v;
        }
        try {
            if ($method==='GET') {
                if ($body!==null) return self::error(400,'get_body_forbidden');
                $read=$this->store->read($world);
                if ($resource==='' && $item==='') {
                    return self::ok(200,$read['state'],$read['etag']);
                }
                if ($resource==='matters' && $item!=='') {
                    $matter=$read['state']['matters'][$item]??null;
                    if ($matter===null) return self::error(404,'matter_not_found');
                    return self::ok(200,$matter,$read['etag']);
                }
                return self::error(405,'method_not_allowed');
            }
            if ($method!=='POST' || $item!=='') {
                return self::error(405,'method_not_allowed');
            }
            $op=match($resource) {
                'matters'=>'open_matter','ballots'=>'cast_ballot',
                'roll-calls'=>'resolve','coalitions'=>'coalition',
                'signatures'=>'seal_signatures',
                default=>null
            };
            if ($op===null) return self::error(405,'method_not_allowed');
            if ($body===null || array_is_list($body)) {
                return self::error(422,'json_object_required');
            }
            $key=$h['idempotency-key']??'';
            $etag=$h['if-match']??'';
            if ($key==='' || $etag==='') return self::error(428,'precondition_required');
            $response=$this->store->execute($world,$op,$body,$key,$etag);
            return self::ok($response['status'],$response['result'],$response['etag']);
        } catch (\InvalidArgumentException|\JsonException $error) {
            return self::error(422,'invalid_parliamentary_request');
        } catch (\DomainException $error) {
            $message=$error->getMessage();
            if (in_array($message,[
                'Authenticated Assembly authority required',
                'Authenticated player does not control that deputy',
                'Parliamentary authenticated principal changed'
            ],true)) return self::error(403,'forbidden');
            if ($message==='French Assembly world absent') {
                return self::error(404,'not_found');
            }
            if ($message==='Stale parliamentary revision') {
                return self::error(412,'stale_precondition');
            }
            return self::error(409,'parliamentary_conflict');
        } catch (\Throwable $error) {
            return self::error(503,'backend_unavailable');
        }
    }

    /** @param array<string,mixed> $body */
    private static function ok(int $status,array $body,string $etag):array {
        return [
            'status'=>$status,
            'headers'=>[
                'Content-Type'=>'application/json',
                'Cache-Control'=>'no-store','ETag'=>$etag
            ],
            'body'=>$body
        ];
    }
    private static function error(int $status,string $error):array {
        return [
            'status'=>$status,
            'headers'=>[
                'Content-Type'=>'application/problem+json',
                'Cache-Control'=>'no-store'
            ],
            'body'=>['type'=>'about:blank','status'=>$status,'code'=>$error]
        ];
    }
}
