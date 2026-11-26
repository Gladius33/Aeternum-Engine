<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PdoDomesticPolicyStore.php';

/** In-process REST contract only. No publication, clock or coefficient route. */
final class DomesticPolicyApi
{
    public function __construct(private readonly PdoDomesticPolicyStore $store) {}
    public function handle(string $method,string $route,?array $body=null,array $headers=[]):array
    {
        if (!preg_match('~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/domestic(?:/(proposals|enactments|summary|crisis))?$~D',$route,$m))
            return self::problem(404,'not_found');
        $world=$m[1];$resource=$m[2]??'';
        try {
            if ($method==='GET' && in_array($resource,['','summary'],true)) {
                if ($body!==null) return self::problem(400,'get_body_forbidden');
                $r=$this->store->read($world);
                $result=$resource===''?$r['state']:self::summary($r['state']);
                return self::ok(200,$result,$r['etag']);
            }
            if ($method!=='POST' || !in_array($resource,['proposals','enactments','crisis'],true)) return self::problem(405,'method_not_allowed');
            $h=array_change_key_case($headers,CASE_LOWER);
            if (!is_string($h['if-match']??null) || !is_string($h['idempotency-key']??null)) return self::problem(428,'precondition_required');
            if ($body===null) return self::problem(400,'body_required');
            $r=$this->store->execute($world,match($resource){'proposals'=>'propose','crisis'=>'crisis',default=>'enact'},$body,$h['idempotency-key'],$h['if-match']);
            return self::ok($r['status'],$r['result'],$r['etag']);
        } catch (\InvalidArgumentException $e) {return self::problem(422,'invalid_request');}
        catch (\DomainException $e) {
            if ($method==='GET') return self::problem(404,'not_found');
            return match($e->getMessage()) {
                'Unverified domestic permission','Domestic principal changed'=>self::problem(403,'forbidden'),
                'Stale domestic state'=>self::problem(412,'stale_precondition'),
                default=>self::problem(409,'state_conflict')
            };
        } catch (\Throwable $e) {return self::problem(503,'backend_unavailable');}
    }

    private static function summary(array $s):array
    {
        $revenue=0;$expense=0;$cost=0;
        foreach ($s['days'] as $d) {
            $revenue+=$d['revenue'];$expense+=$d['expense'];$cost+=$d['economic']['concession_cost']??0;
        }
        $last=$s['days']===[]?null:$s['days'][array_key_last($s['days'])];
        return ['classification'=>'fictional_scenario','world_id'=>$s['world_id'],'day'=>$s['day'],
            'currency'=>$last['currency']??null,'days_observed'=>count($s['days']),
            'total_revenue'=>$revenue,'total_expense'=>$expense,'net_flow'=>$revenue-$expense,
            'concession_cost'=>$cost,'balance'=>$s['balance'],'pressure'=>$s['pressure'],
            'latest_economic_indices'=>$last['economic']??null];
    }

    private static function ok(int $status,array $body,string $etag):array {
        return ['status'=>$status,'headers'=>['Content-Type'=>'application/json','Cache-Control'=>'no-store','ETag'=>$etag],'body'=>$body];
    }
    private static function problem(int $status,string $code):array {
        return ['status'=>$status,'headers'=>['Content-Type'=>'application/problem+json','Cache-Control'=>'no-store'],'body'=>['status'=>$status,'code'=>$code]];
    }
}
