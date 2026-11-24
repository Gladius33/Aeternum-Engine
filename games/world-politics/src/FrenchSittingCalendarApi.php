<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoFrenchSittingCalendarStore.php';

/**
 * Versioned read-only API contract for future web/mobile/desktop clients.
 * Scheduling remains an authenticated internal parliamentary-clerk action,
 * not a client-supplied REST command.
 */
final class FrenchSittingCalendarApi
{
    public function __construct(
        private readonly PdoFrenchSittingCalendarStore $calendar
    ) {}

    /** @return array{status:int,headers:array<string,string>,body:array<string,mixed>} */
    public function handle(
        string $method,string $path,?array $body=null
    ):array {
        if (strlen($path)>512 || !preg_match(
            '~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})/france/calendar(?:/motions/([a-z][a-z0-9_-]{0,63}))?$~D',
            $path,$matches
        )) return self::problem(404,'route_not_found');
        if ($method!=='GET') return self::problem(405,'method_not_allowed');
        if ($body!==null) return self::problem(400,'get_body_forbidden');
        try {
            $read=$this->calendar->read($matches[1]);
            if (isset($matches[2])) {
                $motion=$read['state']['motions'][$matches[2]]??null;
                if ($motion===null) return self::problem(404,'motion_not_scheduled');
                return self::ok($motion,$read['etag']);
            }
            return self::ok($read['state'],$read['etag']);
        } catch (\DomainException $error) {
            if (in_array($error->getMessage(),[
                'Verified parliamentary sitting authority required',
                'No reviewed parliamentary sitting calendar',
                'No pinned parliamentary calendar'
            ],true)) return self::problem(404,'not_found');
            return self::problem(409,'calendar_conflict');
        } catch (\Throwable $error) {
            return self::problem(503,'backend_unavailable');
        }
    }

    /** @param array<string,mixed> $body */
    private static function ok(array $body,string $etag):array
    {
        return [
            'status'=>200,
            'headers'=>[
                'Content-Type'=>'application/json',
                'Cache-Control'=>'no-store',
                'ETag'=>$etag
            ],
            'body'=>$body
        ];
    }

    private static function problem(int $code,string $reason):array
    {
        return [
            'status'=>$code,
            'headers'=>[
                'Content-Type'=>'application/problem+json',
                'Cache-Control'=>'no-store'
            ],
            'body'=>[
                'type'=>'about:blank','status'=>$code,'code'=>$reason
            ]
        ];
    }
}
