<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

final class PlayerDiscoveryApi
{
    /** @param array<string,GamePack> $packs Explicit server-configured worlds. */
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock,private readonly array $packs) {}
    public function handle(string $method,string $authorization): array
    {
        if ($method!=='GET') return [405,['error'=>'method_not_allowed']];
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m)) return [401,['error'=>'unauthorized']];
        $actor=(new PdoSessions($this->pdo,$this->clock))->resolve($m[1]);
        if ($actor===null) return [401,['error'=>'unauthorized']];
        if (!(new PdoRateLimiter($this->pdo,$this->clock))->admit('discovery:'.$actor,60,60)) return [429,['error'=>'rate_limited']];
        $worlds=[];
        $query=$this->pdo->prepare('SELECT o.account_id FROM engine_account_owners o JOIN engine_memberships m ON m.world_id=o.world_id AND m.actor_id=o.actor_id WHERE o.world_id=? AND o.actor_id=? AND m.active=1 ORDER BY o.account_id LIMIT 101');
        foreach ($this->packs as $world=>$pack) {
            $query->execute([$world,$actor]);
            $accounts=$query->fetchAll(PDO::FETCH_COLUMN);
            if (!$accounts) continue;
            $worlds[]=['world'=>$world,'pack'=>$pack->id,'name'=>$pack->name,'accounts'=>array_slice($accounts,0,100),'truncated'=>count($accounts)>100,'resources'=>array_map(static fn($r)=>$r->id,array_values($pack->resources))];
        }
        return [200,['data'=>['worlds'=>$worlds]]];
    }
}
