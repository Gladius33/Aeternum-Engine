<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Opt-in, own-account-only score endpoint.
 * No leaderboard exposing other accounts, no client-provided point mutation.
 */
final readonly class ProgressionHttpApi
{
    /** @param array<string,GamePack> $packs */
    public function __construct(private PDO $pdo,private Clock $clock,private array $packs) {}

    public function handle(string $method,string $path,string $authorization): array
    {
        if (!preg_match(
            '~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/progression/([A-Za-z0-9][A-Za-z0-9_-]{0,63})$~D',
            $path,$m
        )) return [404,['error'=>'not_found']];
        if ($method!=='GET') return [405,['error'=>'method_not_allowed']];
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$token)) {
            return [401,['error'=>'unauthorized']];
        }
        $actor=(new PdoSessions($this->pdo,$this->clock))->resolve($token[1]);
        if ($actor===null) return [401,['error'=>'unauthorized']];
        if (!(new PdoRateLimiter($this->pdo,$this->clock))->admit('progression-read:'.$actor,60,60)) {
            return [429,['error'=>'rate_limited']];
        }
        $pack=$this->packs[$m[1]]??null;
        if (!$pack instanceof GamePack || $pack->progression===null) {
            return [404,['error'=>'not_found']];
        }
        try {
            $rules=$pack->rules();
            $scores=(new PdoScheduler($this->pdo,$m[1],$this->clock,$rules))
                ->scoreForActor($actor,$m[2],$pack->progression);
            return [200,['data'=>['world'=>$m[1],'account'=>$m[2],
                'development'=>$scores['development'],
                'military'=>$scores['military'],
                'global'=>$scores['global']]]];
        } catch (\DomainException|\InvalidArgumentException) {
            // No enumeration of actors, world state or foreign account scores.
            return [404,['error'=>'not_found']];
        }
    }
}
