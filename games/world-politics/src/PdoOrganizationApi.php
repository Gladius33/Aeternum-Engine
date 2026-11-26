<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoOrganizationStore.php';

/** Internal, UI-agnostic REST contract. Does not expose an HTTP socket. */
final class PdoOrganizationApi
{
    public function __construct(private readonly PdoOrganizationStore $store) {}

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    public function handle(string $method,string $route,?array $body=null,array $headers=[]):array
    {
        if (strlen($route)>512 || !preg_match(
            '~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/organizations(?:/([a-z][a-z0-9_-]{0,63}))?(?:/(applications|votes|formalizations|withdrawals|withdrawal-intents|clock|decisions))?$~D',
            $route,$m
        )) {
            return self::problem(404,'route_not_found');
        }
        $world=$m[1];$organization=$m[2]??'';$operationPath=$m[3]??'';
        if ($organization==='clock' && $operationPath==='') {
            $organization='';
            $operationPath='clock';
        }
        $headersNormalized=[];
        foreach ($headers as $name=>$value) {
            if (is_string($name) && is_string($value)) $headersNormalized[strtolower($name)]=$value;
        }
        try {
            if ($method==='GET') {
                if ($body!==null) return self::problem(400,'get_body_forbidden');
                $state=$this->store->read($world);
                if ($organization==='' && $operationPath==='') {
                    return self::ok(200,[
                        'world_id'=>$world,
                        'day'=>$state['state']['day'],
                        'organizations'=>$state['state']['organizations'],... (isset($state['state']['eu_withdrawal'])?['eu_withdrawal'=>$state['state']['eu_withdrawal']]:[])
                    ],$state['etag']);
                }
                if ($organization!=='' && $operationPath==='') {
                    $organizationData=$state['state']['organizations'][$organization]??null;
                    if ($organizationData===null) return self::problem(404,'not_found');
                    $applications=array_values(array_filter(
                        $state['state']['applications'],
                        static fn(array $a):bool=>$a['organization']===$organization
                    ));
                    $withdrawals=array_values(array_filter(
                        $state['state']['withdrawals'],
                        static fn(array $w):bool=>$w['organization']===$organization
                    ));
                    return self::ok(200,[
                        'organization'=>$organizationData,
                        'applications'=>$applications,'withdrawals'=>$withdrawals
                    ],$state['etag']);
                }
                return self::problem(405,'method_not_allowed');
            }
            if ($method!=='POST') return self::problem(405,'method_not_allowed');
            $operation=match (true) {
                $organization!=='' && $operationPath==='applications'=>'apply',
                $organization!=='' && $operationPath==='votes'=>'vote',
                $organization!=='' && $operationPath==='formalizations'=>'formalize',
                $organization!=='' && $operationPath==='withdrawals'=>'withdraw',
                $organization==='' && $operationPath==='clock'=>'clock',
                $organization==='ai' && $operationPath==='decisions'=>'ai_vote',
                $organization==='eu' && $operationPath==='withdrawal-intents'=>'eu_intent',
                default=>null,
            };
            if ($operation===null) return self::problem(405,'method_not_allowed');
            if (!is_array($body) || (array_is_list($body) && $body!==[])) {
                return self::problem(400,'json_object_required');
            }
            $key=$headersNormalized['idempotency-key']??'';
            $etag=$headersNormalized['if-match']??'';
            if ($key==='' || $etag==='') return self::problem(428,'precondition_required');
            $payload=$body;
            if ($operation!=='clock' && $operation!=='ai_vote' && $operation!=='eu_intent') {
                if (array_key_exists('organization',$body)) {
                    return self::problem(422,'organization_is_path_scoped');
                }
                $payload=['organization'=>$organization]+$body;
            }
            $receipt=$this->store->execute($world,$operation,$payload,$key,$etag);
            return self::ok($receipt['status'],$receipt['result'],$receipt['etag']);
        } catch (\InvalidArgumentException|\JsonException $e) {
            return self::problem(422,'invalid_request');
        } catch (\DomainException $e) {
            $reason=$e->getMessage();
            if ($method==='GET' || in_array($reason,[
                'No world membership','Missing organization world'
            ],true)) return self::problem(404,'not_found');
            if (in_array($reason,[
                'Actor not authorized for this organization action',
                'Trusted treaty service required',
                'Trusted AI voting service required',
                'Authenticated principal changed during transaction'
            ],true)) return self::problem(403,'forbidden');
            if ($reason==='Stale state precondition') return self::problem(412,'stale_precondition');
            return self::problem(409,'state_conflict');
        } catch (\Throwable $e) {
            return self::problem(503,'backend_unavailable');
        }
    }

    /** @param array<string,mixed> $body */
    private static function ok(int $status,array $body,string $etag):array
    {
        return ['status'=>$status,'headers'=>[
            'Content-Type'=>'application/json','Cache-Control'=>'no-store','ETag'=>$etag
        ],'body'=>$body];
    }

    private static function problem(int $status,string $code):array
    {
        return ['status'=>$status,'headers'=>[
            'Content-Type'=>'application/problem+json','Cache-Control'=>'no-store'
        ],'body'=>['type'=>'about:blank','status'=>$status,'code'=>$code]];
    }
}
