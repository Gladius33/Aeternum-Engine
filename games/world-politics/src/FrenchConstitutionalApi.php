<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoFrenchConstitutionalStore.php';

/**
 * WP21 — France constitutional procedures, versioned headless REST.
 *
 * The HTTP gateway must authenticate the actor and inject the corresponding
 * trusted principal. Motion signature and ballot totals, official bill
 * category, Council minutes and actual virtual time NEVER come from JSON.
 */
final class FrenchConstitutionalApi
{
    public function __construct(
        private readonly PdoFrenchConstitutionalStore $store
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
                '~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/france/constitution(?:(?:/(engagements|motions|clock|sessions|confidence|spontaneous-motions|presidency))(?:/([a-z][a-z0-9_-]{0,63}))?(?:/(votes|engagements))?)?$~D',
                $path,$matches
            )) {
            return self::fail(404,'route_not_found');
        }
        $world=$matches[1];
        $resource=$matches[2]??'';
        $resourceId=$matches[3]??'';
        $subresource=$matches[4]??'';
        $normalized=[];
        foreach ($headers as $name=>$value) {
            if (is_string($name) && is_string($value)) {
                $normalized[strtolower($name)]=$value;
            }
        }
        try {
            if ($method==='GET') {
                if ($body!==null) return self::fail(400,'get_body_forbidden');
                if ($resource!=='' || $resourceId!=='' || $subresource!=='') {
                    return self::fail(405,'method_not_allowed');
                }
                $read=$this->store->read($world);
                return self::ok(200,$read['state'],$read['etag']);
            }
            if ($method!=='POST') return self::fail(405,'method_not_allowed');
            if (!is_array($body) || ($body!==[] && array_is_list($body))) {
                return self::fail(400,'json_object_required');
            }
            $operation=match(true) {
                $resource==='engagements' && $resourceId==='' && $subresource==='' => 'engage',
                $resource==='motions' && $resourceId==='' && $subresource==='' => 'file_motion',
                $resource==='motions' && $resourceId!=='' && $subresource==='votes' => 'vote_motion',
                $resource==='confidence' && $resourceId==='engagements' && $subresource==='' => 'engage_confidence',
                $resource==='confidence' && $resourceId!=='' && $subresource==='votes' => 'vote_confidence',
                $resource==='spontaneous-motions' && $resourceId==='' && $subresource==='' => 'file_spontaneous',
                $resource==='spontaneous-motions' && $resourceId!=='' && $subresource==='votes' => 'vote_spontaneous',
                $resource==='clock' && $resourceId==='' && $subresource==='' => 'clock',
                $resource==='sessions' && $resourceId==='' && $subresource==='' => 'session',
                $resource==='presidency' && $resourceId==='prime-minister-appointments'
                    && $subresource==='' => 'appoint_prime_minister',
                default=>null
            };
            if ($operation===null) return self::fail(405,'method_not_allowed');
            if ($operation==='vote_motion' || $operation==='vote_spontaneous'
                || $operation==='vote_confidence') {
                $field=$operation==='vote_confidence'?'statement_id':'motion_id';
                if (array_key_exists($field,$body)) {
                    return self::fail(422,'identifier_is_path_scoped');
                }
                $body=[$field=>$resourceId]+$body;
            }
            $key=$normalized['idempotency-key']??'';
            $precondition=$normalized['if-match']??'';
            if ($key==='' || $precondition==='') {
                return self::fail(428,'precondition_required');
            }
            $result=$this->store->execute(
                $world,$operation,$body,$key,$precondition
            );
            return self::ok(
                $result['status'],$result['result'],$result['etag']
            );
        } catch (\InvalidArgumentException|\JsonException $error) {
            return self::fail(422,'invalid_request');
        } catch (\DomainException $error) {
            $reason=$error->getMessage();
            if ($reason==='Constitutional permission denied'
                || $reason==='French constitutional world absent') {
                return $method==='GET'
                    ?self::fail(404,'not_found')
                    :self::fail($reason==='French constitutional world absent'?404:403,
                        $reason==='French constitutional world absent'?'not_found':'forbidden');
            }
            if ($reason==='Invalid trusted service principal'
                || $reason==='Constitutional identity changed during transaction'
                || $reason==='Unverified constitutional simulation time'
                || $reason==='Verified constitutional office required'
                || $reason==='Authenticated parliamentary clerk required') {
                return self::fail(403,'forbidden');
            }
            if ($reason==='Stale French constitutional ETag') {
                return self::fail(412,'stale_precondition');
            }
            return self::fail(409,'constitutional_conflict');
        } catch (\Throwable $error) {
            return self::fail(503,'backend_unavailable');
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

    private static function fail(int $status,string $reason):array
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
                'code'=>$reason
            ]
        ];
    }
}
