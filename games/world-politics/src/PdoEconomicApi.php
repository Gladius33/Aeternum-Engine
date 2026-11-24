<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoEconomicStore.php';

/**
 * Headless economic REST adapter. The real HTTP gateway must separately
 * authenticate, authorize, rate-limit and format requests.
 */
final class PdoEconomicApi
{
    public function __construct(private readonly PdoEconomicStore $store) {}

    /**
     * @param array<string,mixed>|null $payload
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    public function handle(
        string $method,string $route,?array $payload=null,array $headers=[]
    ):array {
        if (strlen($route)>512 || !preg_match(
            '~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/economy(?:/(indicators|policies|clock)(?:/([a-z][a-z0-9_-]{0,63}))?(?:/(authorization))?)?$~D',
            $route,$match
        )) return self::error(404,'route_not_found');
        $world=$match[1];$resource=$match[2]??'';$id=$match[3]??'';$action=$match[4]??'';
        if ($resource==='clock' && $id==='' && $action==='') {
            // Unambiguous single world-wide clock command.
        }
        $normalized=[];
        foreach ($headers as $name=>$value) {
            if (is_string($name) && is_string($value)) {
                $normalized[strtolower($name)]=$value;
            }
        }

        try {
            if ($method==='GET') {
                if ($payload!==null) return self::error(400,'get_body_forbidden');
                $state=$this->store->read($world);
                if ($resource==='' && $id==='' && $action==='') {
                    return self::success(200,$state['state'],$state['etag']);
                }
                if ($resource==='indicators' && $id==='' && $action==='') {
                    return self::success(200,$this->store->indicators($world),$state['etag']);
                }
                if ($resource==='policies' && $id!=='' && $action==='') {
                    $policy=$state['state']['policies'][$id]??null;
                    if ($policy===null) return self::error(404,'not_found');
                    return self::success(200,[
                        'policy'=>$policy,
                        'projection'=>$state['state']['projections'][$id]??null
                    ],$state['etag']);
                }
                return self::error(405,'method_not_allowed');
            }
            if ($method!=='POST') return self::error(405,'method_not_allowed');
            $operation=match(true) {
                $resource==='policies' && $id==='' && $action==='' => 'propose',
                $resource==='policies' && $id!=='' && $action==='authorization' => 'authorize',
                $resource==='clock' && $id==='' && $action==='' => 'clock',
                default=>null
            };
            if ($operation===null) return self::error(405,'method_not_allowed');
            if (!is_array($payload) || (array_is_list($payload) && $payload!==[])) {
                return self::error(400,'json_object_required');
            }
            $key=$normalized['idempotency-key']??'';
            $precondition=$normalized['if-match']??'';
            if ($key==='' || $precondition==='') {
                return self::error(428,'precondition_required');
            }
            if ($operation==='authorize') {
                if ($payload!==[]) return self::error(422,'unexpected_authorization_fields');
                $payload=['policy_id'=>$id];
            }
            $response=$this->store->execute(
                $world,$operation,$payload,$key,$precondition
            );
            return self::success(
                $response['status'],$response['result'],$response['etag']
            );
        } catch (\InvalidArgumentException|\JsonException $error) {
            return self::error(422,'invalid_request');
        } catch (\DomainException $error) {
            $message=$error->getMessage();
            if ($method==='GET' || in_array($message,[
                'No world read permission','No economic world'
            ],true)) return self::error(404,'not_found');
            if (in_array($message,[
                'Unverified economic policy authority',
                'Country legal procedure not satisfied',
                'Trusted simulation scheduler required',
                'Principal changed during command'
            ],true)) return self::error(403,'forbidden');
            if ($message==='Stale economic state') return self::error(412,'stale_precondition');
            return self::error(409,'state_conflict');
        } catch (\Throwable $error) {
            return self::error(503,'backend_unavailable');
        }
    }

    /** @param array<string,mixed> $body */
    private static function success(int $status,array $body,string $etag):array
    {
        return ['status'=>$status,'headers'=>[
            'Content-Type'=>'application/json',
            'Cache-Control'=>'no-store','ETag'=>$etag
        ],'body'=>$body];
    }

    private static function error(int $status,string $code):array
    {
        return ['status'=>$status,'headers'=>[
            'Content-Type'=>'application/problem+json','Cache-Control'=>'no-store'
        ],'body'=>['type'=>'about:blank','status'=>$status,'code'=>$code]];
    }
}
