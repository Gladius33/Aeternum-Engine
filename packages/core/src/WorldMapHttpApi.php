<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** Authenticated, read-only M04 map routes; no operator writes on HTTP. */
final class WorldMapHttpApi
{
    /** @param array<string,mixed> $approvedWorlds */
    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
        private readonly array $approvedWorlds
    ) {}

    public function handle(string $method,string $path,string $authorization): array
    {
        if (strlen($path)>512 || !preg_match(
            '~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/map/(?:territories(?:/([A-Za-z0-9][A-Za-z0-9_-]{0,63}))?|locations/([A-Za-z0-9][A-Za-z0-9_-]{0,63})/links(?:/([A-Za-z0-9][A-Za-z0-9_-]{0,63}))?)$~D',
            $path,$parts
        )) return [404,['error'=>'not_found']];
        if ($method!=='GET') return [405,['error'=>'method_not_allowed']];
        if (!array_key_exists($parts[1],$this->approvedWorlds)) return [404,['error'=>'not_found']];
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$token)) return [401,['error'=>'unauthorized']];
        $actor=(new PdoSessions($this->pdo,$this->clock))->resolve($token[1]);
        if ($actor===null) return [401,['error'=>'unauthorized']];
        if (!(new PdoRateLimiter($this->pdo,$this->clock))->admit('map:'.$actor,120,60)) {
            return [429,['error'=>'rate_limited']];
        }
        try {
            if (isset($parts[3]) && $parts[3]!=='') {
                $neighbors=(new PdoTopology($this->pdo,$parts[1]))->neighbors($actor,$parts[3],51,$parts[4]??null);
                $more=count($neighbors)>50;
                if ($more) array_pop($neighbors);
                return [200,['data'=>[
                    'neighbors'=>$neighbors,
                    'nextAfter'=>$more?$neighbors[count($neighbors)-1]['location']:null
                ]]];
            }
            return [200,['data'=>(new PdoTerritories($this->pdo,$parts[1]))->pageFor($actor,50,$parts[2]??null)]];
        } catch (\DomainException) {
            return [404,['error'=>'not_found']];
        }
    }
}
