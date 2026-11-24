<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoFrenchSocialStore.php';

/**
 * WP24: headless social scenario REST adapter.
 *
 * Requests contain a previously curated bill ID only. No client-supplied
 * social grievance coefficients, law proofs, public-order scores or
 * economic-loss claims can override the server's pinned scenario data.
 */
final class FrenchSocialApi
{
    public function __construct(
        private readonly PdoFrenchSocialStore $store
    ) {}

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    public function handle(
        string $method,string $path,?array $body=null,array $headers=[]
    ):array {
        if (strlen($path)>512
            || !preg_match(
                '~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/france/social(?:/(assessments)(?:/([a-z][a-z0-9_-]{0,63}))?)?$~D',
                $path,$m
            )) return self::error(404,'route_not_found');
        $world=$m[1];$resource=$m[2]??'';$bill=$m[3]??'';
        $h=[];
        foreach ($headers as $key=>$value) {
            if (is_string($key) && is_string($value)) {
                $h[strtolower($key)]=$value;
            }
        }

        try {
            if ($method==='GET') {
                if ($body!==null) return self::error(400,'get_body_forbidden');
                $read=$this->store->read($world);
                if ($resource==='' && $bill==='') {
                    return self::ok(200,$read['state'],$read['etag']);
                }
                if ($resource==='assessments' && $bill!=='') {
                    $report=$read['state']['history'][$bill]??null;
                    if ($report===null) return self::error(404,'assessment_not_found');
                    return self::ok(200,$report,$read['etag']);
                }
                return self::error(405,'method_not_allowed');
            }
            if ($method!=='POST' || $resource!=='assessments' || $bill!=='') {
                return self::error(405,'method_not_allowed');
            }
            if (!is_array($body)
                || array_keys($body)!==['bill_id']
                || !is_string($body['bill_id'])) {
                return self::error(422,'only_curated_bill_id_allowed');
            }
            $key=$h['idempotency-key']??'';
            $etag=$h['if-match']??'';
            if ($key==='' || $etag==='') return self::error(428,'precondition_required');
            $result=$this->store->assess(
                $world,$body['bill_id'],$key,$etag
            );
            return self::ok($result['status'],$result['result'],$result['etag']);
        } catch (\InvalidArgumentException|\JsonException $error) {
            return self::error(422,'invalid_request');
        } catch (\DomainException $error) {
            $message=$error->getMessage();
            if ($message==='No verified social permission'
                || $message==='French social world absent') {
                return $method==='GET'
                    ?self::error(404,'not_found')
                    :self::error(
                        $message==='French social world absent'?404:403,
                        $message==='French social world absent'?'not_found':'forbidden'
                    );
            }
            if ($message==='Social worker cannot borrow another principal legal rights'
                || $message==='Social principal identity changed during transaction') {
                return self::error(403,'forbidden');
            }
            if ($message==='Stale social assessment ETag') {
                return self::error(412,'stale_precondition');
            }
            return self::error(409,'scenario_conflict');
        } catch (\Throwable $error) {
            return self::error(503,'backend_unavailable');
        }
    }

    /** @param array<string,mixed> $body */
    private static function ok(int $status,array $body,string $etag):array
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

    private static function error(int $status,string $code):array
    {
        return [
            'status'=>$status,
            'headers'=>[
                'Content-Type'=>'application/problem+json',
                'Cache-Control'=>'no-store'
            ],
            'body'=>[
                'type'=>'about:blank','status'=>$status,'code'=>$code
            ]
        ];
    }
}
