<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;
use PDOException;

/**
 * Public registration claims are bound immutably to one approved world.
 * Recovery requires the SAME password, world and existing public claim.
 * An operator-provisioned identity cannot silently self-enroll elsewhere.
 */
final class PdoOnboarding
{
    public function __construct(private readonly PDO $pdo, private readonly Clock $clock) {}

    /**
     * @return array{actor:string,created:bool,recoveryCode:?string}
     */
    public function enroll(string $world,GamePack $pack,string $login,string $password): array
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world)
            || !preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login)
            || strlen($password)<12 || strlen($password)>1024) {
            throw new \InvalidArgumentException('Invalid registration');
        }
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested onboarding transaction');
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid clock');
        $identity=new PdoIdentity($this->pdo);
        $created=false;
        // No orphaned identity when creating a new pending onboarding claim.
        $this->pdo->beginTransaction();
        try {
            $actor=$identity->register($login,$password);
            $s=$this->pdo->prepare('INSERT INTO engine_onboarding(actor_id,world_id,status,created_at) VALUES (?,?,?,?)');
            $s->execute([$actor,$world,'pending',$now]);
            $this->pdo->commit();
            $created=true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e->getCode()!=='23000') throw $e;
            // Uniqueness may mean an interrupted signup or an unrelated CLI identity.
            // Authenticate before consulting its private onboarding claim.
            $actor=$identity->authenticate($login,$password);
            if ($actor===null) throw new \DomainException('Registration unavailable');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
        $s=$this->pdo->prepare('SELECT world_id,status FROM engine_onboarding WHERE actor_id=?');
        $s->execute([$actor]);
        $claim=$s->fetch(PDO::FETCH_ASSOC);
        if ($claim===false || $claim['world_id']!==$world) throw new \DomainException('Registration unavailable');
        if ($claim['status']==='ready') return ['actor'=>$actor,'created'=>false,'recoveryCode'=>(new PdoRecovery($this->pdo,$this->clock))->issueIfMissing($actor)];
        if ($claim['status']!=='pending') throw new \DomainException('Invalid registration state');

        // Existing provisioner has world locks, stable account/resource IDs and
        // durable idempotency keys. Crashes leave a retriable pending claim.
        (new PlayerProvisioner($this->pdo,$this->clock))->provision($world,$actor,$pack);
        $s=$this->pdo->prepare("UPDATE engine_onboarding SET status='ready' WHERE actor_id=? AND world_id=? AND status='pending'");
        $s->execute([$actor,$world]);
        return ['actor'=>$actor,'created'=>$created,'recoveryCode'=>(new PdoRecovery($this->pdo,$this->clock))->issueIfMissing($actor)];
    }
}
