<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** Operator-issued, optional one-use invitation tokens for controlled public beta. */
final class PdoInvitations
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock) {}

    /** @return list<string> Printable plaintext codes, never persisted. */
    public function issue(string $world,int $count,int $ttl=604800): array
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world) || $count<1 || $count>50 || $ttl<60 || $ttl>2592000)
            throw new \InvalidArgumentException('Invalid invitation batch');
        $now=$this->clock->now();
        if ($now<0 || $now>PHP_INT_MAX-$ttl) throw new \DomainException('Invalid invitation time');
        // An operator must never receive a partial batch after an insert failure.
        // Own the transaction rather than committing a caller's unrelated writes.
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested invitation issuance transaction');
        $codes=[];
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('INSERT INTO engine_signup_invitations(token_hash,world_id,created_at,expires_at) VALUES (?,?,?,?)');
            for($i=0;$i<$count;$i++) {
                $code=bin2hex(random_bytes(32));
                $s->execute([hash('sha256',$code),$world,$now,$now+$ttl]);
                $codes[]=$code;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        return $codes;
    }

    /** Called only inside the identity+onboarding creation transaction. */
    public function claim(string $code,string $world,string $actor): void
    {
        if (!$this->pdo->inTransaction()) throw new \LogicException('Invitation claim needs identity transaction');
        if (!preg_match('/^[a-f0-9]{64}$/D',$code)) throw new \InvalidArgumentException('Invalid invitation code');
        $key=hash('sha256',$code);
        $s=$this->pdo->prepare('SELECT world_id,actor_id,created_at,expires_at,revoked FROM engine_signup_invitations WHERE token_hash=? FOR UPDATE');
        $s->execute([$key]); $row=$s->fetch(PDO::FETCH_ASSOC);
        if ($row===false || $row['world_id']!==$world || $row['actor_id']!==null || (int)$row['revoked']!==0 || $this->clock->now()<(int)$row['created_at'] || (int)$row['expires_at']<=$this->clock->now())
            throw new \DomainException('Invitation unavailable');
        $s=$this->pdo->prepare('UPDATE engine_signup_invitations SET actor_id=?,used_at=? WHERE token_hash=? AND actor_id IS NULL');
        $s->execute([$actor,$this->clock->now(),$key]);
        if ($s->rowCount()!==1) throw new \DomainException('Invitation unavailable');
    }

    /** Revoke only UNUSED invitation credentials. Used claims retain their idempotent recovery. */
    public function revoke(string $code,string $world): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$code)) throw new \InvalidArgumentException('Invalid invitation code');
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world)) throw new \InvalidArgumentException('Invalid world');
        $s=$this->pdo->prepare('UPDATE engine_signup_invitations SET revoked=1 WHERE token_hash=? AND world_id=? AND actor_id IS NULL AND revoked=0');
        $s->execute([hash('sha256',$code),$world]);
        return $s->rowCount()===1;
    }

    public function belongsTo(string $code,string $world,string $actor): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$code)) return false;
        $s=$this->pdo->prepare('SELECT actor_id FROM engine_signup_invitations WHERE token_hash=? AND world_id=?');
        $s->execute([hash('sha256',$code),$world]);
        return $s->fetchColumn()===$actor;
    }
}
