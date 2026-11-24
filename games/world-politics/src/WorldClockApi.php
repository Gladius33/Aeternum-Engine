<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/WorldClockCoordinator.php';

/**
 * WP17: REST contract for authoritative global day progression.
 *
 * This is an adapter, NOT a publicly exposed HTTP server. The gateway must
 * inject the verified, capability-bound WorldClockCoordinator.
 */
final class WorldClockApi
{
    public function __construct(private readonly WorldClockCoordinator $clock) {}

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string> $headers
     * @return array{status:int,headers:array<string,string>,body:array<string,mixed>}
     */
    public function handle(
        string $method,string $path,?array $body=null,array $headers=[]
    ):array {
        if (strlen($path)>512
            || !preg_match('~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/clock(?:/(advances))?$~D',$path,$m)) {
            return self::problem(404,'route_not_found');
        }
        $world=$m[1];$operation=$m[2]??'';
        $h=[];
        foreach ($headers as $key=>$value) {
            if (is_string($key) && is_string($value)) $h[strtolower($key)]=$value;
        }

        try {
            if ($method==='GET' && $operation==='') {
                if ($body!==null) return self::problem(400,'get_body_forbidden');
                $read=$this->clock->read($world);
                return self::ok(200,$read['state'],$read['etag']);
            }
            if ($method!=='POST' || $operation!=='advances') {
                return self::problem(405,'method_not_allowed');
            }
            if (!is_array($body) || (array_is_list($body) && $body!==[])
                || array_keys($body)!==['target_day']
                || !is_string($body['target_day'])) {
                return self::problem(422,'invalid_world_tick');
            }
            $key=$h['idempotency-key']??'';
            $precondition=$h['if-match']??'';
            if ($key==='' || $precondition==='') {
                return self::problem(428,'precondition_required');
            }
            $result=$this->clock->advance(
                $world,$body['target_day'],$key,$precondition
            );
            return self::ok($result['status'],$result['result'],$result['etag']);
        } catch (\InvalidArgumentException|\JsonException $error) {
            return self::problem(422,'invalid_request');
        } catch (\DomainException $error) {
            $reason=$error->getMessage();
            if ($reason==='No world clock') return self::problem(404,'not_found');
            if ($reason==='Trusted world clock service required'
                || $reason==='Mismatch between clock and verified domain principals') {
                // Conceal inaccessible world identifiers on read, without
                // weakening the explicit denial of privileged POST actions.
                return $method==='GET'
                    ? self::problem(404,'not_found')
                    : self::problem(403,'forbidden');
            }
            if ($reason==='Stale world clock precondition') {
                return self::problem(412,'stale_precondition');
            }
            return self::problem(409,'state_conflict');
        } catch (\Throwable $error) {
            return self::problem(503,'backend_unavailable');
        }
    }

    /** @param array<string,mixed> $body */
    private static function ok(int $status,array $body,string $etag):array
    {
        return ['status'=>$status,'headers'=>[
            'Content-Type'=>'application/json',
            'Cache-Control'=>'no-store',
            'ETag'=>$etag
        ],'body'=>$body];
    }

    private static function problem(int $status,string $reason):array
    {
        return ['status'=>$status,'headers'=>[
            'Content-Type'=>'application/problem+json',
            'Cache-Control'=>'no-store'
        ],'body'=>[
            'type'=>'about:blank','status'=>$status,'code'=>$reason
        ]];
    }
}
