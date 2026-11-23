<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Player-safe read-only projection. No premium mutation, payment method,
 * checkout, role management or admin API exists on the public router.
 */
final class PremiumHttpApi
{
    public function __construct(
        private readonly PDO $pdo,private readonly Clock $clock,private readonly array $approvedWorlds
    ) {}
    public function handle(string $method,string $path,string $auth): array
    {
        if(!preg_match('~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/premium/me$~D',
            $path,$m))return [404,['error'=>'not_found']];
        if($method!=='GET')return [405,['error'=>'method_not_allowed']];
        $world=$m[1];
        if(!array_key_exists($world,$this->approvedWorlds))
            return [404,['error'=>'not_found']];
        if(!preg_match('/^Bearer ([a-f0-9]{64})$/D',$auth,$token))
            return [401,['error'=>'unauthorized']];
        $actor=(new PdoSessions($this->pdo,$this->clock))->resolve($token[1]);
        if($actor===null)return [401,['error'=>'unauthorized']];
        if(!(new PdoRateLimiter($this->pdo,$this->clock))->admit('premium:read:'.$actor,60,60))
            return [429,['error'=>'rate_limited']];
        $q=$this->pdo->prepare('SELECT 1 FROM engine_memberships
            WHERE world_id=? AND actor_id=? AND active=1');
        $q->execute([$world,$actor]);
        if($q->fetchColumn()===false)return [404,['error'=>'not_found']];
        return [200,['data'=>[
            'world'=>$world,'features'=>PdoAdminConsole::cosmeticFor($this->pdo,$world,$actor),
            'testModeOnly'=>true
        ]]];
    }
}
