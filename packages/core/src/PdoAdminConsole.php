<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * M10 trusted-console domain service. Not HTTP-routed: callers must already
 * be authenticated as OS operators and possess an explicitly scoped DB grant.
 * No operation modifies a game-pack manifest, pinned rules or economy ledger.
 */
final class PdoAdminConsole
{
    public const FEATURES=['profile_badge','ui_theme']; // Visual-only, zero gameplay effect.

    public function __construct(
        private readonly PDO $pdo,private readonly string $world,private readonly Clock $clock
    ) {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world))
            throw new \InvalidArgumentException('Invalid world identifier');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES))
            throw new \InvalidArgumentException('Native PDO MySQL required');
    }

    private function query(string $sql,array $params=[]): \PDOStatement
    {
        $s=$this->pdo->prepare($sql);$s->execute($params);return $s;
    }

    private function tx(callable $operation): mixed
    {
        if($this->pdo->inTransaction()) throw new \LogicException('Nested admin transaction');
        $this->pdo->beginTransaction();
        try {
            if($this->query('SELECT world_id FROM engine_worlds WHERE world_id=? FOR UPDATE',
                [$this->world])->fetchColumn()===false)
                throw new \DomainException('Unknown world');
            $result=$operation();
            $this->pdo->commit();return $result;
        } catch(\Throwable $e) {
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }

    private function allowed(string $operator,bool $write): void
    {
        PdoMemberships::validateId($operator);
        $role=$this->query('SELECT role FROM engine_admin_scopes
            WHERE world_id=? AND operator_id=? AND active=1 LOCK IN SHARE MODE',
            [$this->world,$operator])->fetchColumn();
        if($role===false || ($write && $role!=='manager'))
            throw new \DomainException('Operator not authorized for this world');
    }

    private function time(): int
    {
        $now=$this->clock->now();
        if($now<0)throw new \DomainException('Invalid operator clock');
        return $now;
    }

    private static function digest(array $values): string
    {
        return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
    }

    private function record(string $operator,string $request,string $action,?string $target,string $digest): bool
    {
        $row=$this->query('SELECT operator_id,action_code,target_actor,payload_digest
            FROM engine_admin_audit WHERE world_id=? AND request_id=? FOR UPDATE',
            [$this->world,$request])->fetch(PDO::FETCH_ASSOC);
        if($row!==false) {
            if($row['operator_id']!==$operator || $row['action_code']!==$action
                || $row['target_actor']!==$target || $row['payload_digest']!==$digest)
                throw new \DomainException('Conflicting operator request replay');
            return false;
        }
        $this->query('INSERT INTO engine_admin_audit
            (world_id,request_id,operator_id,action_code,target_actor,payload_digest,created_at)
            VALUES (?,?,?,?,?,?,?)',
            [$this->world,$request,$operator,$action,$target,$digest,$this->time()]);
        return true;
    }

    /** Scoped, read-only world/rules/status inspection. */
    public function describe(string $operator): array
    {
        return $this->tx(function()use($operator):array {
            $this->allowed($operator,false);
            $rules=$this->query('SELECT rules_key,watermark FROM engine_worlds WHERE world_id=?',
                [$this->world])->fetch(PDO::FETCH_ASSOC);
            $count=$this->query('SELECT COUNT(*) FROM engine_memberships
                WHERE world_id=? AND active=1',[$this->world])->fetchColumn();
            return ['world'=>$this->world,'rulesKey'=>$rules['rules_key'],
                'watermark'=>(int)$rules['watermark'],'activeMembers'=>(int)$count];
        });
    }

    /**
     * Authenticated console inventory. Legacy world members without an
     * application identity remain visible with login=null, never inferred.
     * Cursor is a stable actor ID and never an offset into a mutable list.
     */
    public function members(string $operator,?string $after=null): array
    {
        if($after!==null)PdoMemberships::validateId($after);
        return $this->tx(function()use($operator,$after):array {
            $this->allowed($operator,false);
            $rows=$this->query('SELECT m.actor_id,i.login,m.active
                FROM engine_memberships m
                LEFT JOIN engine_identities i ON i.actor_id=m.actor_id
                WHERE m.world_id=? AND m.actor_id>?
                ORDER BY m.actor_id LIMIT 51',
                [$this->world,$after??''])->fetchAll(PDO::FETCH_ASSOC);
            $more=count($rows)>50;if($more)array_pop($rows);
            return ['members'=>array_map(static fn(array $row):array=>[
                'actor'=>$row['actor_id'],'login'=>$row['login'],
                'active'=>(int)$row['active']===1
            ],$rows),'nextAfter'=>$more?$rows[count($rows)-1]['actor_id']:null];
        });
    }

    public function identity(string $operator,string $actor): array
    {
        PdoMemberships::validateId($actor);
        return $this->tx(function()use($operator,$actor):array {
            $this->allowed($operator,false);
            $row=$this->query('SELECT m.actor_id,i.login,m.active FROM engine_memberships m
                LEFT JOIN engine_identities i ON i.actor_id=m.actor_id
                WHERE m.world_id=? AND m.actor_id=?',
                [$this->world,$actor])->fetch(PDO::FETCH_ASSOC);
            if($row===false)throw new \DomainException('Identity unavailable in this world');
            $accounts=$this->query('SELECT account_id FROM engine_account_owners
                WHERE world_id=? AND actor_id=? ORDER BY account_id LIMIT 101',
                [$this->world,$actor])->fetchAll(PDO::FETCH_COLUMN);
            return ['world'=>$this->world,'actor'=>$row['actor_id'],
                'login'=>$row['login'],'active'=>(int)$row['active']===1,
                'accounts'=>array_slice($accounts,0,100),
                'accountsTruncated'=>count($accounts)>100];
        });
    }

    /**
     * The identity must already exist. World membership is the only mutable
     * player data here; existing ownership, economy, sessions are preserved.
     */
    public function membership(string $operator,string $request,string $actor,bool $active): bool
    {
        PdoMemberships::validateId($request);PdoMemberships::validateId($actor);
        return $this->tx(function()use($operator,$request,$actor,$active):bool {
            $this->allowed($operator,true);
            $action=$active?'member_grant':'member_revoke';
            $digest=self::digest([$action,$actor]);
            if(!$this->record($operator,$request,$action,$actor,$digest))return false;
            if($this->query('SELECT actor_id FROM engine_identities WHERE actor_id=?',
                [$actor])->fetchColumn()===false)
                throw new \DomainException('Identity unavailable');
            if($active) {
                $this->query('INSERT INTO engine_memberships (world_id,actor_id,active)
                    VALUES (?,?,1) ON DUPLICATE KEY UPDATE active=1',[$this->world,$actor]);
            } else {
                $row=$this->query('UPDATE engine_memberships SET active=0
                    WHERE world_id=? AND actor_id=? AND active=1',
                    [$this->world,$actor]);
                if($row->rowCount()!==1)throw new \DomainException('No active membership to revoke');
            }
            return true;
        });
    }

    /**
     * This is a SIMULATED payment notification, never a purchase or a PSP
     * webhook. It cannot mint resources, game bonuses or real receipts.
     * The unique event ID and digest guarantee idempotent effect and audit.
     */
    public function testPayment(string $operator,string $event,string $actor,string $feature,string $transition): bool
    {
        PdoMemberships::validateId($event);PdoMemberships::validateId($actor);
        if(!in_array($feature,self::FEATURES,true)
            || !in_array($transition,['grant','revoke'],true))
            throw new \InvalidArgumentException('Unsupported test entitlement event');
        return $this->tx(function()use($operator,$event,$actor,$feature,$transition):bool {
            $this->allowed($operator,true);
            $digest=self::digest(['test_payment_v1',$actor,$feature,$transition]);
            $previous=$this->query('SELECT actor_id,feature,transition,operator_id,payload_digest
                FROM engine_test_payment_events WHERE world_id=? AND event_id=? FOR UPDATE',
                [$this->world,$event])->fetch(PDO::FETCH_ASSOC);
            if($previous!==false) {
                if($previous['actor_id']!==$actor || $previous['feature']!==$feature
                    || $previous['transition']!==$transition || $previous['operator_id']!==$operator
                    || $previous['payload_digest']!==$digest)
                    throw new \DomainException('Conflicting test payment replay');
                return false;
            }
            $member=$this->query('SELECT active FROM engine_memberships
                WHERE world_id=? AND actor_id=? FOR UPDATE',[$this->world,$actor])->fetchColumn();
            if($member===false || (int)$member!==1)
                throw new \DomainException('Recipient is not an active world member');
            $action='test_'.$transition;
            if(!$this->record($operator,$event,$action,$actor,$digest))
                throw new \DomainException('Event collides with an existing operator action');
            $now=$this->time();
            $this->query('INSERT INTO engine_test_payment_events
                (world_id,event_id,actor_id,feature,transition,operator_id,payload_digest,created_at)
                VALUES (?,?,?,?,?,?,?,?)',
                [$this->world,$event,$actor,$feature,$transition,$operator,$digest,$now]);
            $enabled=$transition==='grant'?1:0;
            $this->query('INSERT INTO engine_premium_entitlements
                (world_id,actor_id,feature,enabled,last_event_id,changed_at)
                VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
                enabled=VALUES(enabled),last_event_id=VALUES(last_event_id),
                changed_at=VALUES(changed_at)',
                [$this->world,$actor,$feature,$enabled,$event,$now]);
            return true;
        });
    }

    /** Operator audit is private, world-scoped, bounded and cursor-based. */
    public function audit(string $operator,?string $after=null): array
    {
        if($after!==null)PdoMemberships::validateId($after);
        return $this->tx(function()use($operator,$after):array {
            $this->allowed($operator,false);
            $rows=$this->query('SELECT request_id,operator_id,action_code,target_actor,created_at
                FROM engine_admin_audit WHERE world_id=? AND request_id>?
                ORDER BY request_id LIMIT 51',[$this->world,$after??''])
                ->fetchAll(PDO::FETCH_ASSOC);
            $more=count($rows)>50;if($more)array_pop($rows);
            return ['entries'=>$rows,'nextAfter'=>$more?$rows[count($rows)-1]['request_id']:null];
        });
    }

    public static function cosmeticFor(PDO $pdo,string $world,string $actor): array
    {
        PdoMemberships::validateId($actor);
        $s=$pdo->prepare('SELECT feature FROM engine_premium_entitlements
            WHERE world_id=? AND actor_id=? AND enabled=1
            ORDER BY feature');
        $s->execute([$world,$actor]);
        return $s->fetchAll(PDO::FETCH_COLUMN);
    }
}
