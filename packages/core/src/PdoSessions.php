<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;
final class PdoSessions
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Require native PDO MySQL exceptions');
    }
    public function login(string $login,string $password,int $ttl=3600): ?string
    {
        if ($ttl<60 || $ttl>86400) throw new \InvalidArgumentException('Invalid session TTL');
        $actor=(new PdoIdentity($this->pdo))->authenticate($login,$password);
        if ($actor===null) return null;
        $now=$this->clock->now();
        if ($now<0 || $now>PHP_INT_MAX-$ttl) throw new \DomainException('Invalid clock');
        $token=bin2hex(random_bytes(32));
        $s=$this->pdo->prepare('INSERT INTO engine_sessions(token_hash,actor_id,expires_at) VALUES (?,?,?)');
        $s->execute([hash('sha256',$token),$actor,$now+$ttl]);
        return $token;
    }
    public function resolve(string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) return null;
        $s=$this->pdo->prepare('SELECT actor_id FROM engine_sessions WHERE token_hash=? AND revoked=0 AND expires_at>?');
        $s->execute([hash('sha256',$token),$this->clock->now()]);
        $actor=$s->fetchColumn();
        return $actor===false ? null : $actor;
    }
    public function revoke(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) return;
        $s=$this->pdo->prepare('UPDATE engine_sessions SET revoked=1 WHERE token_hash=?');
        $s->execute([hash('sha256',$token)]);
    }
}
