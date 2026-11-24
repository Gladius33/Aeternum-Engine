<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoInstitutionalStore.php';

/**
 * REST-shaped, headless institutional adapter. NOT mounted on public HTTP.
 *
 * Actor identity, role and abilities are supplied by a separate trusted
 * server-side authorization bridge. No request header or body may supply them.
 */
final class PdoInstitutionalApi
{
    public function __construct(private readonly PdoInstitutionalStore $store) {}

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    public function handle(string $method,string $path,?array $body=null,array $headers=[]):array
    {
        if (strlen($path)>512 || !preg_match(
            '~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/countries/([A-Z]{2})/institutions(?:/(decisions|clock|rulebook))?$~D',
            $path,$parts
        )) {
            return self::problem(404,'route_not_found');
        }
        $world=$parts[1];
        $country=$parts[2];
        $resource=$parts[3]??'';
        $normalized=[];
        foreach ($headers as $header=>$value) {
            if (is_string($header) && is_string($value)) {
                $normalized[strtolower($header)]=$value;
            }
        }

        try {
            if ($method==='GET') {
                if ($body!==null) return self::problem(400,'get_body_forbidden');
                $snapshot=$this->store->read($world,$country);
                if ($resource==='') return self::ok(200,$snapshot['state'],$snapshot['etag']);
                if ($resource==='rulebook') {
                    return self::ok(200,InstitutionalRulebook::forCountry($country),$snapshot['etag']);
                }
                return self::problem(405,'method_not_allowed');
            }
            if ($method!=='POST' || !in_array($resource,['decisions','clock'],true)) {
                return self::problem(405,'method_not_allowed');
            }
            if (!is_array($body) || (array_is_list($body) && $body!==[])) {
                return self::problem(400,'json_object_required');
            }
            $key=$normalized['idempotency-key']??'';
            $etag=$normalized['if-match']??'';
            if ($key==='' || $etag==='') return self::problem(428,'precondition_required');
            if ($resource==='decisions') {
                self::fields($body,['action','payload']);
                if (!is_string($body['action']) || !is_array($body['payload'])
                    || (array_is_list($body['payload']) && $body['payload']!==[])) {
                    return self::problem(422,'invalid_decision');
                }
                $receipt=$this->store->decide(
                    $world,$country,$body['action'],$body['payload'],$key,$etag
                );
            } else {
                self::fields($body,['target_day']);
                if (!is_string($body['target_day'])) return self::problem(422,'invalid_clock');
                $receipt=$this->store->advance($world,$country,$body['target_day'],$key,$etag);
            }
            return self::ok($receipt['status'],$receipt['result'],$receipt['etag']);
        } catch(\InvalidArgumentException $e) {
            return self::problem(422,'invalid_request');
        } catch(\DomainException $e) {
            $message=$e->getMessage();
            if ($method==='GET' || $message==='Institutional state missing') {
                return self::problem(404,'not_found');
            }
            if (in_array($message,[
                'Institutional command forbidden','Actor or institutional office revoked'
            ],true)) return self::problem(403,'forbidden');
            if ($message==='Stale institutional precondition') {
                return self::problem(412,'stale_precondition');
            }
            return self::problem(409,'institutional_state_conflict');
        } catch (\Throwable $e) {
            // Never expose SQL, token information or server session details.
            return self::problem(503,'backend_unavailable');
        }
    }

    /** @param array<string,mixed> $provided @param list<string> $keys */
    private static function fields(array $provided,array $keys):void
    {
        $actual=array_keys($provided);
        sort($actual,SORT_STRING);
        sort($keys,SORT_STRING);
        if ($actual!==$keys) throw new \InvalidArgumentException('Unapproved client field');
    }

    /** @param array<string,mixed> $body */
    private static function ok(int $status,array $body,string $etag):array
    {
        return [
            'status'=>$status,
            'headers'=>[
                'Content-Type'=>'application/json',
                'Cache-Control'=>'no-store',
                'ETag'=>$etag,
            ],
            'body'=>$body,
        ];
    }
    private static function problem(int $status,string $code):array
    {
        return [
            'status'=>$status,
            'headers'=>[
                'Content-Type'=>'application/problem+json',
                'Cache-Control'=>'no-store',
            ],
            'body'=>[
                'type'=>'about:blank','status'=>$status,'code'=>$code,
            ],
        ];
    }
}
