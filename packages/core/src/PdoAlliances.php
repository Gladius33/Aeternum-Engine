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
    public function __construct(private readonly PDO $pdo, private readonly string $world, private readonly ?Clock $clock=null)
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
        return $this->query('SELECT 1 FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1 FOR UPDATE',
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

    /** Bidirectional blocking also prevents alliance invitations. */
    private function blocked(string $sender,string $target): bool
    {
        return $this->query('SELECT 1 FROM engine_social_blocks WHERE world_id=?
            AND ((actor_id=? AND blocked_actor=?) OR (actor_id=? AND blocked_actor=?))
            LIMIT 1',[$this->world,$sender,$target,$target,$sender])->fetchColumn()!==false;
    }

    /** Notification is part of the same membership/invitation transaction. */
    private function notify(string $recipient,string $kind,string $subject): void
    {
        $now=($this->clock??new SystemClock())->now();
        if ($now<0) throw new \DomainException('Invalid clock');
        $this->query('INSERT INTO engine_social_notifications
            (world_id,notification_id,actor_id,kind,subject_id,created_at)
            VALUES (?,?,?,?,?,?)',[$this->world,bin2hex(random_bytes(32)),$recipient,$kind,$subject,$now]);
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
            // No alliance row exists yet. Serialize creation on the established
            // world row, using the same world-before-membership lock order as
            // administrative grant/revoke and player provisioning.
            if ($this->query('SELECT world_id FROM engine_worlds WHERE world_id=? FOR UPDATE',
                [$this->world])->fetchColumn()===false)
                throw new \DomainException('World unavailable');
            if (!$this->active($actor)) throw new \DomainException('Membership required');
            $old=$this->query('SELECT name,created_by FROM engine_alliances WHERE world_id=? AND alliance_id=? FOR UPDATE',
                [$this->world,$alliance])->fetch(PDO::FETCH_ASSOC);
            if ($old!==false) {
                if ($old['name']!==$name || $old['created_by']!==$actor) throw new \DomainException('Alliance ID already used');
                return; // Identical retry must not create a second alliance.
            }
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
            if ($this->blocked($actor,$target)) throw new \DomainException('Contact blocked');
            $previous=$this->query('SELECT status FROM engine_alliance_invites
                WHERE world_id=? AND alliance_id=? AND invited_actor=? FOR UPDATE',
                [$this->world,$alliance,$target])->fetchColumn();
            if ($previous==='pending') return; // A retry does not emit another notification.
            $this->query("INSERT INTO engine_alliance_invites(world_id,alliance_id,invited_actor,invited_by,status)
                VALUES (?,?,?,?,'pending') ON DUPLICATE KEY UPDATE
                invited_by=IF(status IN ('revoked','declined','accepted'),VALUES(invited_by),invited_by),
                status=IF(status IN ('revoked','declined','accepted'),'pending',status)",
                [$this->world,$alliance,$target,$actor]);
            $this->notify($target,'invitation',$alliance);
        });
    }

    public function respond(string $actor,string $alliance,bool $accept): void
    {
        $this->id($actor); $this->id($alliance);
        $this->transaction(function() use ($actor,$alliance,$accept): void {
            $this->lockAlliance($alliance);
            if (!$this->active($actor)) throw new \DomainException('Membership required');
            $invite=$this->query('SELECT status,invited_by FROM engine_alliance_invites
                WHERE world_id=? AND alliance_id=? AND invited_actor=? FOR UPDATE',
                [$this->world,$alliance,$actor])->fetch(PDO::FETCH_ASSOC);
            if ($invite!==false && $invite['status']==='accepted' && $accept
                && $this->lockedRole($alliance,$actor)!==null) return;
            if ($invite===false || $invite['status']!=='pending')
                throw new \DomainException('Invitation unavailable');
            if ($accept && $this->blocked($actor,$invite['invited_by']))
                throw new \DomainException('Contact blocked');
            if ($accept) {
                $this->query("INSERT INTO engine_alliance_members(world_id,alliance_id,actor_id,role)
                    VALUES (?,?,?,'member')",[$this->world,$alliance,$actor]);
            }
            $this->query('UPDATE engine_alliance_invites SET status=? WHERE world_id=? AND alliance_id=? AND invited_actor=?',
                [$accept?'accepted':'declined',$this->world,$alliance,$actor]);
            if ($accept) $this->notify($invite['invited_by'],'invitation_accepted',$alliance);
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
    /** Bounded, world-scoped alliance directory for an active world member. */
    public function listFor(string $actor, int $limit=50, ?string $after=null): array
    {
        $this->id($actor);
        if ($after!==null) $this->id($after);
        if ($limit<1 || $limit>100) throw new \InvalidArgumentException('Invalid page size');
        return $this->transaction(function() use ($actor,$limit,$after): array {
            if (!$this->active($actor)) throw new \DomainException('Membership required');
            $rows=$this->query('SELECT a.alliance_id,a.name,COUNT(wm.actor_id) AS member_count
                FROM engine_alliances a LEFT JOIN engine_alliance_members m
                ON m.world_id=a.world_id AND m.alliance_id=a.alliance_id
                LEFT JOIN engine_memberships wm ON wm.world_id=m.world_id
                    AND wm.actor_id=m.actor_id AND wm.active=1
                WHERE a.world_id=? AND a.alliance_id>?
                GROUP BY a.world_id,a.alliance_id,a.name
                ORDER BY a.alliance_id LIMIT '.($limit+1),[$this->world,$after??''])
                ->fetchAll(PDO::FETCH_ASSOC);
            $hasMore=count($rows)>$limit;
            if ($hasMore) array_pop($rows);
            $list=array_map(static fn(array $row):array=>[
                'id'=>$row['alliance_id'],'name'=>$row['name'],'members'=>(int)$row['member_count']
            ],$rows);
            return ['alliances'=>$list,'nextAfter'=>$hasMore?$list[count($list)-1]['id']:null];
        });
    }

    /** Paged private roster: active members only, stable actor-ID ordering. */
    public function detailsFor(string $actor,string $alliance,?string $after=null): array
    {
        $this->id($actor); $this->id($alliance);
        if ($after!==null) $this->id($after);
        return $this->transaction(function() use($actor,$alliance,$after):array {
            $this->lockAlliance($alliance);
            if (!$this->active($actor) || $this->lockedRole($alliance,$actor)===null)
                throw new \DomainException('Alliance membership required');
            $name=$this->query('SELECT name FROM engine_alliances WHERE world_id=? AND alliance_id=?',
                [$this->world,$alliance])->fetchColumn();
            $rows=$this->query('SELECT m.actor_id,m.role FROM engine_alliance_members m
                JOIN engine_memberships worldmember ON worldmember.world_id=m.world_id
                  AND worldmember.actor_id=m.actor_id AND worldmember.active=1
                WHERE m.world_id=? AND m.alliance_id=? AND m.actor_id>?
                ORDER BY m.actor_id LIMIT 101',
                [$this->world,$alliance,$after??''])->fetchAll(PDO::FETCH_ASSOC);
            $more=count($rows)>100;
            if ($more) array_pop($rows);
            $last=$more?$rows[count($rows)-1]['actor_id']:null;
            return ['id'=>$alliance,'name'=>$name,'role'=>$this->lockedRole($alliance,$actor),
                'members'=>array_map(static fn(array $m):array=>['actor'=>$m['actor_id'],'role'=>$m['role']],$rows),
                'nextAfter'=>$last];
        });
    }

    /** Legacy private-inbox model API; the HTTP adapter uses the page object. */
    public function invitationsFor(string $actor): array
    {
        return $this->invitationsPageFor($actor)['invitations'];
    }

    /** Non-blocked pending invites, keyset by alliance ID. */
    public function invitationsPageFor(string $actor,?string $after=null): array
    {
        $this->id($actor);
        if ($after!==null) $this->id($after);
        return $this->transaction(function() use($actor,$after):array {
            if (!$this->active($actor)) throw new \DomainException('Membership required');
            $rows=$this->query("SELECT i.alliance_id,a.name,i.invited_by FROM engine_alliance_invites i
                JOIN engine_alliances a ON a.world_id=i.world_id AND a.alliance_id=i.alliance_id
                WHERE i.world_id=? AND i.invited_actor=? AND i.status='pending'
                  AND i.alliance_id>?
                  AND NOT EXISTS (
                    SELECT 1 FROM engine_social_blocks b WHERE b.world_id=i.world_id
                      AND ((b.actor_id=i.invited_actor AND b.blocked_actor=i.invited_by)
                        OR (b.actor_id=i.invited_by AND b.blocked_actor=i.invited_actor))
                  )
                ORDER BY i.alliance_id LIMIT 101",[$this->world,$actor,$after??''])
                ->fetchAll(PDO::FETCH_ASSOC);
            $more=count($rows)>100;
            if ($more) array_pop($rows);
            $last=$more?$rows[count($rows)-1]['alliance_id']:null;
            return ['invitations'=>array_map(static fn(array $i):array=>[
                'alliance'=>$i['alliance_id'],'name'=>$i['name'],'invitedBy'=>$i['invited_by']
            ],$rows),'nextAfter'=>$last];
        });
    }

    /** Member can leave; owner must transfer ownership before leaving. */
    public function leave(string $actor,string $alliance): void
    {
        $this->id($actor); $this->id($alliance);
        $this->transaction(function() use($actor,$alliance):void {
            $this->lockAlliance($alliance);
            if (!$this->active($actor)) throw new \DomainException('Membership required');
            $role=$this->lockedRole($alliance,$actor);
            if ($role==='owner') throw new \DomainException('Owner must transfer first');
            if ($role===null) return; // Idempotent repeated leave.
            $this->query('DELETE FROM engine_alliance_members WHERE world_id=? AND alliance_id=? AND actor_id=?',
                [$this->world,$alliance,$actor]);
        });
    }

    /** The owner can remove anyone except themselves; officers may remove members. */
    public function remove(string $actor,string $alliance,string $target): void
    {
        $this->id($actor); $this->id($alliance); $this->id($target);
        $this->transaction(function() use($actor,$alliance,$target):void {
            $this->lockAlliance($alliance);
            if (!$this->active($actor)) throw new \DomainException('Membership required');
            $role=$this->lockedRole($alliance,$actor);
            $targetRole=$this->lockedRole($alliance,$target);
            if (!($role==='owner' && in_array($targetRole,['member','officer'],true))
                && !($role==='officer' && $targetRole==='member')) {
                throw new \DomainException('Removal denied');
            }
            $this->query('DELETE FROM engine_alliance_members WHERE world_id=? AND alliance_id=? AND actor_id=?',
                [$this->world,$alliance,$target]);
        });
    }

    /** Atomic, serialized ownership handover. No alliance may have two owners. */
    public function transferOwner(string $actor,string $alliance,string $target): void
    {
        $this->id($actor); $this->id($alliance); $this->id($target);
        $this->transaction(function() use($actor,$alliance,$target):void {
            $this->lockAlliance($alliance);
            if (!$this->active($actor) || !$this->active($target)
                || $this->lockedRole($alliance,$actor)!=='owner'
                || !in_array($this->lockedRole($alliance,$target),['officer','member'],true))
                throw new \DomainException('Ownership transfer denied');
            $this->query("UPDATE engine_alliance_members SET role='member'
                WHERE world_id=? AND alliance_id=? AND actor_id=?",[$this->world,$alliance,$actor]);
            $this->query("UPDATE engine_alliance_members SET role='owner'
                WHERE world_id=? AND alliance_id=? AND actor_id=?",[$this->world,$alliance,$target]);
        });
    }

    /**
     * Deterministic active-member rankings, independent of the economy journal.
     * Each request observes one transactional snapshot. The world boundary and
     * active requester admission are always rechecked.
     */
    public function leaderboardFor(string $actor,?int $afterCount=null,?string $afterId=null): array
    {
        $this->id($actor);
        if (($afterCount===null)!==($afterId===null) || ($afterCount!==null && $afterCount<0))
            throw new \InvalidArgumentException('Invalid leaderboard cursor');
        if ($afterId!==null) $this->id($afterId);
        return $this->transaction(function() use($actor,$afterCount,$afterId):array {
            if (!$this->active($actor)) throw new \DomainException('Membership required');
            // Compute the ordinal rank across ALL world-scoped alliances before
            // filtering to the next keyset page; never trust a client rank offset.
            $query='SELECT ranked.alliance_id,ranked.name,ranked.active_members,ranked.rank_num
                FROM (
                    SELECT a.alliance_id,a.name,COUNT(wm.actor_id) AS active_members,
                        ROW_NUMBER() OVER (ORDER BY COUNT(wm.actor_id) DESC,a.alliance_id ASC) AS rank_num
                    FROM engine_alliances a
                    LEFT JOIN engine_alliance_members m ON m.world_id=a.world_id
                        AND m.alliance_id=a.alliance_id
                    LEFT JOIN engine_memberships wm ON wm.world_id=m.world_id
                        AND wm.actor_id=m.actor_id AND wm.active=1
                    WHERE a.world_id=?
                    GROUP BY a.world_id,a.alliance_id,a.name
                ) ranked';
            $params=[$this->world];
            if ($afterCount!==null) {
                $query.=' WHERE (ranked.active_members < ? OR
                    (ranked.active_members = ? AND ranked.alliance_id > ?))';
                array_push($params,$afterCount,$afterCount,$afterId);
            }
            $query.=' ORDER BY ranked.active_members DESC,ranked.alliance_id ASC LIMIT 51';
            $rows=$this->query($query,$params)->fetchAll(PDO::FETCH_ASSOC);
            $more=count($rows)>50;
            if ($more) array_pop($rows);
            $last=$more?$rows[count($rows)-1]:null;
            $ranks=[];
            foreach ($rows as $row) {
                $ranks[]=['rank'=>(int)$row['rank_num'],'id'=>$row['alliance_id'],
                    'name'=>$row['name'],'activeMembers'=>(int)$row['active_members']];
            }
            return ['alliances'=>$ranks,'truncated'=>$more,
                'nextAfter'=>$last===null?null:[
                    'activeMembers'=>(int)$last['active_members'],'id'=>$last['alliance_id']]];
        });
    }

}
