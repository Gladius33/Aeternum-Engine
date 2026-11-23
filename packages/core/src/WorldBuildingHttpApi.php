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
        $isStatus=preg_match(
            '~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/settlement/status/([A-Za-z0-9][A-Za-z0-9_-]{0,63})$~D',
            $path,$match
        )===1;
        $isIndustry=preg_match(
            '~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/industrial/status/([A-Za-z0-9][A-Za-z0-9_-]{0,63})$~D',
            $path,$match
        )===1;
        $isOptions=!$isStatus && !$isIndustry && preg_match(
            '~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/construction/options$~D',
            $path,$match
        )===1;
        $isProgress=false;
        if (!$isOptions && !$isStatus && !$isIndustry) {
            $isProgress=preg_match('~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/construction/progression$~D',$path,$match)===1;
        }
        if (!$isStatus && !$isIndustry && !$isOptions && !$isProgress && !preg_match(
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
            if ($isStatus) {
                $pack=$this->worlds[$match[1]];
                if (!$pack instanceof GamePack || $pack->settlement===null) return [404,['error'=>'not_found']];
                return [200,['data'=>(new PdoSettlementStatus($this->pdo,$match[1],$pack->rules(),$pack->settlement))
                    ->forActor($actor,$match[2])]];
            }
            if ($isIndustry) {
                $pack=$this->worlds[$match[1]];
                if (!$pack instanceof GamePack || $pack->industry===null) return [404,['error'=>'not_found']];
                return [200,['data'=>(new PdoIndustryStatus($this->pdo,$match[1],$pack->rules(),$pack->industry))
                    ->forActor($actor,$match[2])]];
            }
            $service=new PdoBuildings($this->pdo,$match[1]);
            return [200,['data'=>$isOptions?$service->choicesFor($actor):($isProgress?$service->progressionFor($actor):$service->own($actor,$match[2]))]];
        } catch (\DomainException) {
            return [404,['error'=>'not_found']];
        }
    }
}
