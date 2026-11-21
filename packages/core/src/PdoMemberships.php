<?php
declare(strict_types=1);
namespace Aeternum\Core;

use PDO;

/** Trusted administrative service, not a player endpoint. All writes lock the world first. */
final class PdoMemberships
{
    public function __construct(private readonly PDO $pdo,private readonly string $worldId)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$worldId)) throw new \InvalidArgumentException('Invalid world');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Require native PDO MySQL exceptions');
    }

    public static function validateId(string $id): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$id)) throw new \InvalidArgumentException('Invalid actor/account ID');
    }

    private function query(string $sql,array $params): \PDOStatement
    {
        $s=$this->pdo->prepare($sql); $s->execute($params); return $s;
    }

    private function transaction(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction prohibited');
        $this->pdo->beginTransaction();
        try {
            if ($this->query('SELECT world_id FROM engine_worlds WHERE world_id=? FOR UPDATE',[$this->worldId])->fetchColumn()===false) throw new \DomainException('World not initialized');
            $result=$work();
            $this->pdo->commit(); return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function grant(string $actor): void
    {
        self::validateId($actor);
        $this->transaction(fn()=> $this->query('INSERT INTO engine_memberships (world_id,actor_id,active) VALUES (?,?,1) ON DUPLICATE KEY UPDATE active=1',[$this->worldId,$actor]));
    }

    public function revoke(string $actor): void
    {
        self::validateId($actor);
        $this->transaction(function () use ($actor): void {
            if ($this->query('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=?',[$this->worldId,$actor])->fetchColumn()===false) throw new \DomainException('Unknown actor');
            $this->query('UPDATE engine_memberships SET active=0 WHERE world_id=? AND actor_id=?',[$this->worldId,$actor]);
        });
    }

    public function assign(string $account,string $actor): void
    {
        self::validateId($actor); self::validateId($account);
        $this->transaction(function () use ($account,$actor): void {
            if ($this->query('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',[$this->worldId,$actor])->fetchColumn()===false) throw new \DomainException('Actor not active');
            $previous=$this->query('SELECT actor_id FROM engine_account_owners WHERE world_id=? AND account_id=?',[$this->worldId,$account])->fetchColumn();
            if ($previous!==false) {
                if ($previous!==$actor) throw new \DomainException('Account ownership immutable');
                return;
            }
            $this->query('INSERT INTO engine_account_owners (world_id,account_id,actor_id) VALUES (?,?,?)',[$this->worldId,$account,$actor]);
        });
    }
}
