<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;
use PDOException;

/**
 * Optional self-service mailbox ownership proof. The emailed secret and
 * password are both needed before a pending player can obtain a session.
 * Never deliver mail inside a database transaction or record a plaintext code.
 */
final class PdoEmailActivation
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock) {}
    public static function normalizeEmail(string $email): string
    {
        $email=strtolower(trim($email));
        if (strlen($email)>254 || strlen($email)<6 || str_contains($email,"\r") || str_contains($email,"\n")
            || filter_var($email,FILTER_VALIDATE_EMAIL)===false)
            throw new \InvalidArgumentException('Invalid email address');
        return $email;
    }
    /**
     * Atomically claim an identity, world and mailbox; no provisioned actor,
     * recovery credential or bearer is issued before activation.
     * @return array{email:string,code:string}|null null means duplicate/ineligible.
     */
    public function begin(string $world,string $login,string $password,string $email,?string $invite=null): ?array
    {
        PdoMemberships::validateId($world);
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login)
            || strlen($password)<12 || strlen($password)>1024) throw new \InvalidArgumentException('Invalid signup');
        $email=self::normalizeEmail($email);
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested activation registration prohibited');
        $now=$this->clock->now();
        if ($now<0 || $now>PHP_INT_MAX-86400) throw new \DomainException('Invalid activation clock');
        $code=bin2hex(random_bytes(32));
        $this->pdo->beginTransaction();
        try {
            $actor=(new PdoIdentity($this->pdo))->register($login,$password);
            if ($invite!==null) (new PdoInvitations($this->pdo,$this->clock))->claim($invite,$world,$actor);
            $s=$this->pdo->prepare("INSERT INTO engine_onboarding(actor_id,world_id,status,created_at) VALUES (?,?,'pending',?)");
            $s->execute([$actor,$world,$now]);
            $s=$this->pdo->prepare('INSERT INTO engine_email_activations(actor_id,email,token_hash,created_at,expires_at) VALUES (?,?,?,?,?)');
            $s->execute([$actor,$email,hash('sha256',$code),$now,$now+86400]);
            $this->pdo->commit();
        } catch (PDOException $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            if($e->getCode()==='23000') return null;
            throw $e;
        } catch (\Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
        return ['email'=>$email,'code'=>$code];
    }
    /** Rate-limited by the HTTP adapter. No leakage for unknown or mismatched users. */
    public function resend(string $login,string $password,string $email): ?array
    {
        $email=self::normalizeEmail($email);
        if($this->pdo->inTransaction())throw new \LogicException('Nested activation resend prohibited');
        $now=$this->clock->now();
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT i.actor_id,a.email,a.created_at,a.verified_at,o.status FROM engine_identities i JOIN engine_email_activations a ON a.actor_id=i.actor_id JOIN engine_onboarding o ON o.actor_id=i.actor_id WHERE i.login=? FOR UPDATE');
            $s->execute([$login]);$row=$s->fetch(PDO::FETCH_ASSOC);
            if($row===false || $row['email']!==$email || $row['verified_at']!==null || $row['status']!=='pending'
                || (int)$row['created_at']>$now-60
                || (new PdoIdentity($this->pdo))->authenticate($login,$password)!==$row['actor_id']) {
                $this->pdo->rollBack(); return null;
            }
            $code=bin2hex(random_bytes(32));
            $s=$this->pdo->prepare('UPDATE engine_email_activations SET token_hash=?,created_at=?,expires_at=? WHERE actor_id=?');
            $s->execute([hash('sha256',$code),$now,$now+86400,$row['actor_id']]);
            $this->pdo->commit();
            return ['email'=>$email,'code'=>$code];
        } catch(\Throwable $e){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }
    /**
     * Proof of mailbox secret AND account password. On first success mark
     * verified; retries while still pending may finish interrupted provisioning.
     * A completed activation is never replayable for a second session.
     * @return array{actor:string,world:string}|null
     */
    public function verify(string $login,string $password,string $code): ?array
    {
        if(!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login)
            || !preg_match('/^[a-f0-9]{64}$/D',$code) || strlen($password)>1024)
            return null;
        if($this->pdo->inTransaction())throw new \LogicException('Nested activation proof prohibited');
        $now=$this->clock->now();
        $this->pdo->beginTransaction();
        try {
            $s=$this->pdo->prepare('SELECT i.actor_id,a.token_hash,a.created_at,a.expires_at,a.verified_at,a.consumed_at,o.status,o.world_id FROM engine_identities i JOIN engine_email_activations a ON a.actor_id=i.actor_id JOIN engine_onboarding o ON o.actor_id=i.actor_id WHERE i.login=? FOR UPDATE');
            $s->execute([$login]);$row=$s->fetch(PDO::FETCH_ASSOC);
            if($row===false || $row['status']!=='pending' || $row['consumed_at']!==null
                || !hash_equals($row['token_hash'],hash('sha256',$code))
                || ($row['verified_at']===null && ($now<(int)$row['created_at'] || $now>=(int)$row['expires_at']))
                || (new PdoIdentity($this->pdo))->authenticate($login,$password)!==$row['actor_id']) {
                $this->pdo->rollBack(); return null;
            }
            if($row['verified_at']===null) {
                $s=$this->pdo->prepare('UPDATE engine_email_activations SET verified_at=? WHERE actor_id=?');
                $s->execute([$now,$row['actor_id']]);
            }
            $this->pdo->commit();
            return ['actor'=>$row['actor_id'],'world'=>$row['world_id']];
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }
    public function consume(string $actor): void
    {
        PdoMemberships::validateId($actor);
        $s=$this->pdo->prepare('UPDATE engine_email_activations SET consumed_at=? WHERE actor_id=? AND verified_at IS NOT NULL AND consumed_at IS NULL');
        $s->execute([$this->clock->now(),$actor]);
    }
}
