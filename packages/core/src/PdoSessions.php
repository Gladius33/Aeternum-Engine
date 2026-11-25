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
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested login transaction prohibited');
        $now=$this->clock->now();
        if ($now<0 || $now>PHP_INT_MAX-$ttl) throw new \DomainException('Invalid clock');
        // A shared identity lock is kept through token issuance. Password
        // recovery takes an exclusive identity lock before invalidating
        // sessions, so no login with the OLD password can race past a reset.
        $this->pdo->beginTransaction();
        try {
            if (preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login)) {
                $s=$this->pdo->prepare('SELECT actor_id FROM engine_identities WHERE login=? LOCK IN SHARE MODE');
                $s->execute([$login]);
                $s->fetchColumn();
            }
            $actor=(new PdoIdentity($this->pdo))->authenticate($login,$password);
            if ($actor===null) { $this->pdo->commit(); return null; }
            // Identity creation precedes world enrollment in a resumable
            // workflow. Never issue a bearer for a partially provisioned
            // public identity; operator-only identities have no such claim.
            $s=$this->pdo->prepare('SELECT status FROM engine_onboarding WHERE actor_id=?');
            $s->execute([$actor]);
            $claim=$s->fetchColumn();
            if ($claim!==false && ($claim!=='ready' || !PdoEmailActivation::permitsEnrollment($this->pdo,$actor))) { $this->pdo->commit(); return null; }
            $token=bin2hex(random_bytes(32));
            $s=$this->pdo->prepare('INSERT INTO engine_sessions(token_hash,actor_id,expires_at) VALUES (?,?,?)');
            $s->execute([hash('sha256',$token),$actor,$now+$ttl]);
            $this->pdo->commit();
            return $token;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
    public function resolve(string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) return null;
        // A stale token from an interrupted or incorrectly enrolled public
        // claim must not confer access. One statement avoids a split snapshot.
        $s=$this->pdo->prepare("SELECT s.actor_id FROM engine_sessions s
            LEFT JOIN engine_onboarding o ON o.actor_id=s.actor_id
            LEFT JOIN engine_email_activations a ON a.actor_id=s.actor_id
            WHERE s.token_hash=? AND s.revoked=0 AND s.expires_at>?
            AND (o.actor_id IS NULL OR o.status='ready')
            AND (a.actor_id IS NULL OR a.verified_at IS NOT NULL)");
        $s->execute([hash('sha256',$token),$this->clock->now()]);
        $actor=$s->fetchColumn();
        return $actor===false?null:$actor;
    }
    public function revoke(string $token): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) return;
        $s=$this->pdo->prepare('UPDATE engine_sessions SET revoked=1 WHERE token_hash=?');
        $s->execute([hash('sha256',$token)]);
    }
    /**
     * Sign out every device for one verified actor. Identity locking serializes
     * this operation with login token issuance and password/recovery changes.
     * Callers at the HTTP boundary must resolve an active bearer first.
     */
    public function revokeAllForActor(string $actor): int
    {
        PdoMemberships::validateId($actor);
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested global logout prohibited');
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT actor_id FROM engine_identities WHERE actor_id=? FOR UPDATE');
            $s->execute([$actor]);
            if ($s->fetchColumn()===false) { $this->pdo->rollBack(); return 0; }
            $s=$this->pdo->prepare('DELETE FROM engine_sessions WHERE actor_id=?');
            $s->execute([$actor]);
            $count=$s->rowCount();
            $this->pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
    /** Delete at most one bounded batch of expired sessions, never live sessions. */
    public function purgeExpired(int $limit=500): int
    {
        if ($limit<1 || $limit>1000) throw new \InvalidArgumentException('Invalid session purge limit');
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction prohibited');
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid clock');
        $s=$this->pdo->prepare('DELETE FROM engine_sessions WHERE expires_at<=? ORDER BY expires_at,token_hash LIMIT '.$limit);
        $s->execute([$now]);
        return $s->rowCount();
    }
}
