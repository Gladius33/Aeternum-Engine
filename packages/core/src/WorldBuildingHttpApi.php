<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** Read-only, actor-authorized building stage and cost preview. */
final class WorldBuildingHttpApi
{
    /** @param array<string,mixed> $worlds */
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock,private readonly array $worlds) {}

    public function handle(string $method,string $path,string $authorization): array
    {
        if (strlen($path)>512) return [404,['error'=>'not_found']];
        $isOptions=preg_match(
            '~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/construction/options$~D',
            $path,$match
        )===1;
        if (!$isOptions && !preg_match(
            '~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/buildings/([A-Za-z0-9][A-Za-z0-9_-]{0,63})$~D',
            $path,$match
        )) return [404,['error'=>'not_found']];
        if ($method!=='GET') return [405,['error'=>'method_not_allowed']];
        if (!array_key_exists($match[1],$this->worlds)) return [404,['error'=>'not_found']];
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$bearer)) return [401,['error'=>'unauthorized']];
        $actor=(new PdoSessions($this->pdo,$this->clock))->resolve($bearer[1]);
        if ($actor===null) return [401,['error'=>'unauthorized']];
        if (!(new PdoRateLimiter($this->pdo,$this->clock))->admit('building-read:'.$actor,120,60)) {
            return [429,['error'=>'rate_limited']];
        }
        try {
            $service=new PdoBuildings($this->pdo,$match[1]);
            return [200,['data'=>$isOptions?$service->choicesFor($actor):$service->own($actor,$match[2])]];
        } catch (\DomainException) {
            return [404,['error'=>'not_found']];
        }
    }
}
