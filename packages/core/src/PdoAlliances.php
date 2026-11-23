<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * M09 alliance write model. Every mutation locks the parent alliance row,
 * serializing invitations, role changes and membership changes.
 * Caller must authenticate the actor; this class rechecks active membership.
 */
final class PdoAlliances
{
    public function __construct(private readonly PDO $pdo, private readonly string $world)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $world)) {
            throw new \InvalidArgumentException('Invalid world');
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
            throw new \InvalidArgumentException('Native MySQL PDO required');
        }
    }

    private function id(string $id): void
    {
        PdoMemberships::validateId($id);
    }

    private function query(string $sql, array $args): \PDOStatement
    {
        $q=$this->pdo->prepare($sql);
        $q->execute($args);
        return $q;
    }

    private function active(string $actor): bool
    {
        return $this->query('SELECT 1 FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',
            [$this->world,$actor])->fetchColumn()!==false;
    }

    private function lockedRole(string $alliance,string $actor): ?string
    {
        $role=$this->query('SELECT role FROM engine_alliance_members WHERE world_id=? AND alliance_id=? AND actor_id=?',
            [$this->world,$alliance,$actor])->fetchColumn();
        return $role===false?null:(string)$role;
    }

    private function transaction(callable $operation): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested social transaction');
        $this->pdo->beginTransaction();
        try {
            $result=$operation();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function lockAlliance(string $alliance): void
    {
        if ($this->query('SELECT 1 FROM engine_alliances WHERE world_id=? AND alliance_id=? FOR UPDATE',
            [$this->world,$alliance])->fetchColumn()===false) {
            throw new \DomainException('Alliance unavailable');
        }
    }

    public function create(string $actor,string $alliance,string $name): void
    {
        $this->id($actor); $this->id($alliance);
        if (trim($name)==='' || strlen($name)>100) throw new \InvalidArgumentException('Invalid alliance name');
        $this->transaction(function() use ($actor,$alliance,$name): void {
            if (!$this->active($actor)) throw new \DomainException('Membership required');
            $this->query('INSERT INTO engine_alliances(world_id,alliance_id,name,created_by) VALUES (?,?,?,?)',
                [$this->world,$alliance,$name,$actor]);
            $this->query("INSERT INTO engine_alliance_members(world_id,alliance_id,actor_id,role) VALUES (?,?,?,'owner')",
                [$this->world,$alliance,$actor]);
        });
    }

    public function invite(string $actor,string $alliance,string $target): void
    {
        $this->id($actor); $this->id($alliance); $this->id($target);
        $this->transaction(function() use ($actor,$alliance,$target): void {
            $this->lockAlliance($alliance);
            if (!$this->active($actor) || !$this->active($target)
                || !in_array($this->lockedRole($alliance,$actor),['owner','officer'],true)) {
                throw new \DomainException('Invitation denied');
            }
            if ($this->lockedRole($alliance,$target)!==null) throw new \DomainException('Already member');
            $this->query("INSERT INTO engine_alliance_invites(world_id,alliance_id,invited_actor,invited_by,status)
                VALUES (?,?,?,?,'pending') ON DUPLICATE KEY UPDATE
                invited_by=IF(status IN ('revoked','declined'),VALUES(invited_by),invited_by),
                status=IF(status IN ('revoked','declined'),'pending',status)",
                [$this->world,$alliance,$target,$actor]);
        });
    }

    public function respond(string $actor,string $alliance,bool $accept): void
    {
        $this->id($actor); $this->id($alliance);
        $this->transaction(function() use ($actor,$alliance,$accept): void {
            $this->lockAlliance($alliance);
            if (!$this->active($actor)) throw new \DomainException('Membership required');
            $invite=$this->query('SELECT status FROM engine_alliance_invites
                WHERE world_id=? AND alliance_id=? AND invited_actor=? FOR UPDATE',
                [$this->world,$alliance,$actor])->fetchColumn();
            if ($invite==='accepted' && $accept && $this->lockedRole($alliance,$actor)!==null) return;
            if ($invite!=='pending') throw new \DomainException('Invitation unavailable');
            if ($accept) {
                $this->query("INSERT INTO engine_alliance_members(world_id,alliance_id,actor_id,role)
                    VALUES (?,?,?,'member')",[$this->world,$alliance,$actor]);
            }
            $this->query('UPDATE engine_alliance_invites SET status=? WHERE world_id=? AND alliance_id=? AND invited_actor=?',
                [$accept?'accepted':'declined',$this->world,$alliance,$actor]);
        });
    }

    public function revokeInvite(string $actor,string $alliance,string $target): void
    {
        $this->id($actor); $this->id($alliance); $this->id($target);
        $this->transaction(function() use ($actor,$alliance,$target): void {
            $this->lockAlliance($alliance);
            if (!$this->active($actor) || !in_array($this->lockedRole($alliance,$actor),['owner','officer'],true))
                throw new \DomainException('Revocation denied');
            $this->query("UPDATE engine_alliance_invites SET status='revoked'
                WHERE world_id=? AND alliance_id=? AND invited_actor=? AND status='pending'",
                [$this->world,$alliance,$target]);
        });
    }

    /** Owner-only promotion/demotion; ownership cannot be reassigned here. */
    public function setRole(string $actor,string $alliance,string $target,string $role): void
    {
        $this->id($actor); $this->id($alliance); $this->id($target);
        if (!in_array($role,['officer','member'],true)) throw new \InvalidArgumentException('Invalid role');
        $this->transaction(function() use ($actor,$alliance,$target,$role): void {
            $this->lockAlliance($alliance);
            if (!$this->active($actor) || $this->lockedRole($alliance,$actor)!=='owner'
                || !$this->active($target) || !in_array($this->lockedRole($alliance,$target),['officer','member'],true)) {
                throw new \DomainException('Role change denied');
            }
            $this->query('UPDATE engine_alliance_members SET role=? WHERE world_id=? AND alliance_id=? AND actor_id=?',
                [$role,$this->world,$alliance,$target]);
        });
    }
}
