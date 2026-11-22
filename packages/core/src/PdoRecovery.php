<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Single-use 256-bit recovery codes. Only SHA-256 token digests are persisted.
 * This is an offline recovery-code flow, not an email ownership attestation.
 */
final class PdoRecovery
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock) {}

    /** Issue once after successful onboarding. A lost response can be recovered by rotating while signed in. */
    public function issueIfMissing(string $actor): ?string
    {
        PdoMemberships::validateId($actor);
        $code=bin2hex(random_bytes(32));
        $s=$this->pdo->prepare('INSERT IGNORE INTO engine_account_recovery(actor_id,token_hash,updated_at) VALUES (?,?,?)');
        $s->execute([$actor,hash('sha256',$code),$this->clock->now()]);
        return $s->rowCount()===1?$code:null;
    }

    /** Session identity and current password must already be verified by the caller. */
    public function rotate(string $actor,string $password): ?string
    {
        PdoMemberships::validateId($actor);
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested recovery rotation');
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT login FROM engine_identities WHERE actor_id=? FOR UPDATE');
            $s->execute([$actor]);
            $login=$s->fetchColumn();
            if ($login===false || (new PdoIdentity($this->pdo))->authenticate($login,$password)!==$actor) {
                $this->pdo->rollBack(); return null;
            }
            $code=bin2hex(random_bytes(32));
            $s=$this->pdo->prepare('INSERT INTO engine_account_recovery(actor_id,token_hash,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),updated_at=VALUES(updated_at)');
            $s->execute([$actor,hash('sha256',$code),$this->clock->now()]);
            $this->pdo->commit();
            return $code;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Return the NEXT recovery code only if the supplied code was current.
     * Password change, token rotation and global session revocation commit together.
     */
    public function reset(string $login,string $code,string $password): ?string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login)
            || !preg_match('/^[a-f0-9]{64}$/D',$code)
            || strlen($password)<12 || strlen($password)>1024) return null;
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested recovery transaction');
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT actor_id FROM engine_identities WHERE login=? FOR UPDATE');
            $s->execute([$login]); $actor=$s->fetchColumn();
            if ($actor===false) { $this->pdo->rollBack(); return null; }
            $s=$this->pdo->prepare('SELECT token_hash FROM engine_account_recovery WHERE actor_id=? FOR UPDATE');
            $s->execute([$actor]); $old=$s->fetchColumn();
            if ($old===false || !hash_equals($old,hash('sha256',$code))) {
                $this->pdo->rollBack(); return null;
            }
            $newHash=PdoIdentity::hashNewPassword($password);
            $next=bin2hex(random_bytes(32));
            $s=$this->pdo->prepare('UPDATE engine_identities SET password_hash=? WHERE actor_id=?');
            $s->execute([$newHash,$actor]);
            $s=$this->pdo->prepare('DELETE FROM engine_sessions WHERE actor_id=?');
            $s->execute([$actor]);
            $s=$this->pdo->prepare('UPDATE engine_account_recovery SET token_hash=?,updated_at=? WHERE actor_id=?');
            $s->execute([hash('sha256',$next),$this->clock->now(),$actor]);
            $this->pdo->commit();
            return $next;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
