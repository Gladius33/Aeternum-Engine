<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoPoliticalStore.php';

/**
 * Versioned, headless REST contract for an explicitly injected durable store.
 *
 * This is NOT a public front controller or production auth implementation:
 * a future trusted gateway must validate Bearer sessions, admission policies,
 * request size and rate limits, and must never derive actor grants from JSON.
 */
final class PdoWorldPoliticsApi
{
    public function __construct(private readonly PdoPoliticalStore $store) {}

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    public function handle(string $method,string $path,?array $body=null,array $headers=[]):array
    {
        if (strlen($path)>512 || !preg_match(
            '~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/countries/([A-Z]{2})/politics(?:/(person-template|people|head-of-state|nominations)(?:/([a-z][a-z0-9_-]{0,63}))?(?:/(resolution))?)?$~D',
            $path,$matches
        )) return self::problem(404,'route_not_found');
        $world=$matches[1]; $country=$matches[2];
        $resource=$matches[3]??'';
        $item=$matches[4]??'';
        $action=$matches[5]??'';
        $normalizedHeaders=[];
        foreach ($headers as $key=>$value) {
            if (is_string($key) && is_string($value)) {
                $normalizedHeaders[strtolower($key)]=$value;
            }
        }

        try {
            if ($method==='GET') {
                if ($body!==null) return self::problem(400,'get_body_forbidden');
                $snapshot=$this->store->read($world,$country);
                if ($resource==='' && $item==='' && $action==='') {
                    return self::success(200,$snapshot['state'],$snapshot['etag']);
                }
                if ($resource==='person-template' && $item==='' && $action==='') {
                    return self::success(200,PoliticalProfileTemplate::definition(),$snapshot['etag']);
                }
                if ($resource==='people' && $item!=='' && $action==='') {
                    $person=$snapshot['state']['people'][$item]??null;
                    return $person===null?self::problem(404,'not_found')
                        :self::success(200,['person'=>$person],$snapshot['etag']);
                }
                return self::problem(405,'method_not_allowed');
            }
            if ($method!=='POST') return self::problem(405,'method_not_allowed');
            $operation=match (true) {
                $resource==='people' && $item==='' && $action==='' => 'create_fictional_person',
                $resource==='head-of-state' && $item==='' && $action==='' => 'initialize_scenario',
                $resource==='nominations' && $item==='' && $action==='' => 'nominate',
                $resource==='nominations' && $item!=='' && $action==='resolution' => 'resolve_nomination',
                default=>null
            };
            if ($operation===null) return self::problem(405,'method_not_allowed');
            if (!is_array($body) || (array_is_list($body) && $body!==[])) {
                return self::problem(400,'json_object_required');
            }
            $key=$normalizedHeaders['idempotency-key']??'';
            $precondition=$normalizedHeaders['if-match']??'';
            if ($key==='' || $precondition==='') {
                return self::problem(428,'precondition_required');
            }
            if ($operation==='resolve_nomination') {
                if (array_keys($body)!==['accepted']) {
                    return self::problem(422,'invalid_resolution');
                }
                $body=['office'=>$item,'accepted'=>$body['accepted']];
            }
            $receipt=$this->store->execute(
                $world,$country,$operation,$body,$key,$precondition
            );
            return self::success($receipt['status'],$receipt['result'],$receipt['etag']);
        } catch (\InvalidArgumentException $e) {
            return self::problem(422,'invalid_request');
        } catch (\DomainException $e) {
            $message=$e->getMessage();
            if ($method==='GET' || $message==='Missing world') return self::problem(404,'not_found');
            if ($message==='Unauthorized world or action' || $message==='Actor changed during transaction') {
                return self::problem(403,'forbidden');
            }
            if ($message==='Stale state precondition') return self::problem(412,'stale_precondition');
            return self::problem(409,'state_conflict');
        } catch (\Throwable $e) {
            // Never echo SQL, session identifiers or internal exception details.
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

    private static function problem(int $status,string $error):array
    {
        return [
            'status'=>$status,
            'headers'=>[
                'Content-Type'=>'application/problem+json',
                'Cache-Control'=>'no-store'
            ],
            'body'=>[
                'type'=>'about:blank',
                'status'=>$status,
                'code'=>$error
            ]
        ];
    }
}
