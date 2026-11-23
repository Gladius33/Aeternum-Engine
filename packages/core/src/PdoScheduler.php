<?php
declare(strict_types=1);
namespace Aeternum\Core;

use PDO;

/** MariaDB adapter. Owns its PDO transactions; one committed event per transaction. */
final class PdoScheduler
{
    private bool $processing = false;
    private ?bool $projectionInstalled = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $worldId,
        private readonly Clock $clock,
        private readonly ?RuleSet $rules = null,
    ) {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $worldId)) {
            throw new \InvalidArgumentException('Invalid world identifier');
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
            throw new \InvalidArgumentException('Require mysql PDO with exceptions and native prepares');
        }
    }

    private function query(string $sql, array $params): \PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    private function transaction(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction prohibited');
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * M03: write only the touched relational rows before acquiring the world
     * row's exclusive lock. The final compare-and-swap atomically serializes
     * canonical JSON, logical time and journal sequence. Failed CAS, deadlocks
     * and lock timeouts roll back every tentative SQL projection and fall back
     * to the original serialized command path.
     *
     * This is still a sequential global journal; no two commits can publish
     * the same world version, receipt or escrow transfer.
     */
    private function tryOptimisticEconomyCommit(Command $command,string $actor,?string $clientRequest,array $candidate): ?CommandReceipt
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction prohibited');
        $result=$candidate['result'];
        $at=$candidate['at'];
        $seq=$candidate['next_sequence'];
        if ($candidate['journal_sequence']===PHP_INT_MAX || $this->clock->now()!==$at) return null;
        $payload=ScalarMap::encode($command->payload);
        $stateJson=ScalarMap::encode($result->state);
        $ids=[];
        foreach ($result->events as $event) {
            if ($event->worldId!==$this->worldId || $event->dueAt<$at) {
                throw new \DomainException('Invalid follow-up event world or time');
            }
            if ($seq===PHP_INT_MAX) throw new \OverflowException('Event sequence exhausted');
            $seq++;
            $ids[]=$event->id;
        }
        $this->pdo->beginTransaction();
        try {
            // Upserts lock only affected balance/offer/refund rows. None are
            // visible until this transaction and the world CAS both commit.
            $this->syncProjection($candidate['before'],$result->state,$candidate['changed_keys']);
            $changed=$this->query('UPDATE engine_worlds
                SET next_sequence=?,watermark=?,state_json=?,journal_sequence=journal_sequence+1
                WHERE world_id=? AND next_sequence=? AND journal_sequence=? AND watermark=?
                  AND state_json=? AND rules_key=? AND economy_projection_version=1',
                [$seq,$at,$stateJson,$this->worldId,$candidate['next_sequence'],
                    $candidate['journal_sequence'],$candidate['watermark'],
                    $candidate['state_json'],$this->rulesKey()])->rowCount();
            if ($changed!==1 || $this->clock->now()!==$at) {
                $this->pdo->rollBack();
                return null;
            }
            // The CAS now owns the world write lock, just like the legacy
            // path: recheck authorization and every barrier after any admin
            // or event scheduler transactions that committed meanwhile.
            $this->authorizeActor($actor,$command);
            $old=$this->query('SELECT command_id FROM engine_commands WHERE world_id=? AND command_id=?',
                [$this->worldId,$command->id])->fetchColumn();
            if ($old!==false) {
                $this->pdo->rollBack();
                return null; // slow path validates immutable replay payload
            }
            $due=$this->query('SELECT event_id FROM engine_events
                WHERE world_id=? AND completed=0 AND due_at<=? LIMIT 1 FOR UPDATE',
                [$this->worldId,$at])->fetchColumn();
            if ($due!==false) throw new \DomainException('Due events must be processed before new commands');
            $eventSeq=$candidate['next_sequence'];
            foreach ($result->events as $event) {
                if ($this->query('SELECT event_id FROM engine_events WHERE world_id=? AND event_id=?',
                    [$this->worldId,$event->id])->fetchColumn()!==false) {
                    throw new \DomainException('Follow-up event ID already exists');
                }
                $eventSeq++;
                $this->query('INSERT INTO engine_events
                    (world_id,event_id,event_type,due_at,sequence_no,payload_json)
                    VALUES (?,?,?,?,?,?)',[$this->worldId,$event->id,$event->type,
                        $event->dueAt,$eventSeq,ScalarMap::encode($event->payload)]);
            }
            $this->query('INSERT INTO engine_commands
                (world_id,command_id,command_type,payload_json,effective_at,result_state_json,
                 scheduled_ids_json,actor_id,client_request_id)
                VALUES (?,?,?,?,?,?,?,?,?)',
                [$this->worldId,$command->id,$command->type,$payload,$at,'{}',
                    json_encode($ids,JSON_THROW_ON_ERROR),$actor,$clientRequest]);
            $this->journal(['journal_sequence'=>$candidate['journal_sequence']],
                'command',$command->id,$command->type,$at,$payload,$stateJson,
                $result->events,$result->movements);
            $this->pdo->commit();
            return new CommandReceipt($at,$result->state,$ids);
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            // A competing scheduler can hold the world row first and then
            // request a projection row; InnoDB resolves such lock inversions.
            // Re-enter the canonical world-first path after rollback.
            if (in_array((int)($e->errorInfo[1]??0),[1205,1213],true)) return null;
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Explicit provisioning; repeated initialization never resets existing state. */
    public function initialize(): void
    {
        if ($this->processing) throw new \LogicException('Reentrant mutation prohibited');
        $this->transaction(function (): void {
            $this->query(
                'INSERT INTO engine_worlds (world_id,state_json,checkpoint_json,rules_key) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE world_id = world_id',
                [$this->worldId,'{}','{}',$this->rulesKey()]
            );
            $this->lockedWorld();
        });
    }

    /** Legacy migrations 001..019 are exercised in CI before 020 is applied. */
    private function hasProjection(): bool
    {
        if ($this->projectionInstalled===null) {
            $q=$this->pdo->query("SHOW COLUMNS FROM engine_worlds LIKE 'economy_projection_version'");
            $this->projectionInstalled=$q->fetch(PDO::FETCH_ASSOC)!==false;
        }
        return $this->projectionInstalled;
    }

    private function syncProjection(array $before,array $after,?array $changedKeys=null): void
    {
        if ($this->hasProjection()) (new PdoEconomyProjection($this->pdo,$this->worldId))->sync($before,$after,$changedKeys);
    }

    /**
     * Prepare a pure actor economy transition before acquiring the world
     * write lock. A changed snapshot forces re-evaluation inside the lock.
     * This does not bypass membership, due events, journal order or receipts.
     */
    private function prepareEconomyTransition(Command $command,CommandHandler $handler,?string $actor): ?array
    {
        // A new building requires an InnoDB row in the same world-locked
        // transaction as its journal/state. Never route placement via CAS.
        if ($command->type==='building.place' || str_starts_with($command->type,'pack.')) return null;
        if ($actor===null || !$handler instanceof EconomyCommandHandler || !$this->hasProjection()) return null;
        try {
            // Speculative authorization is only a precheck; locked commit rechecks.
            $this->authorizeActor($actor,$command);
            $snapshot=$this->query('SELECT state_json,journal_sequence,next_sequence,watermark,rules_key,economy_projection_version
                FROM engine_worlds WHERE world_id=?',[$this->worldId])->fetch(PDO::FETCH_ASSOC);
            if ($snapshot===false || $snapshot['rules_key']!==$this->rulesKey()
                || (int)$snapshot['economy_projection_version']!==1) return null;
            $at=$this->clock->now();
            if ($at<0 || $at<(int)$snapshot['watermark']) return null;
            $before=ScalarMap::decode($snapshot['state_json']);
            $result=$handler->apply($command,$before,$at);
            return ['state_json'=>$snapshot['state_json'],
                'journal_sequence'=>(int)$snapshot['journal_sequence'],
                'next_sequence'=>(int)$snapshot['next_sequence'],
                'watermark'=>(int)$snapshot['watermark'],
                'at'=>$at,'before'=>$before,'result'=>$result,
                'changed_keys'=>PdoEconomyProjection::deltaKeys($before,$result->state)];
        } catch (\Throwable) {
            // The locked canonical path decides every visible error. A stale
            // speculative read must never reject or commit a request.
            return null;
        }
    }

    /** All projection bootstrap runs under the existing InnoDB world lock. */
    private function lockedWorld(): array
    {
        $row = $this->query('SELECT next_sequence, watermark, state_json, journal_sequence, checkpoint_json, checkpoint_at, rules_key FROM engine_worlds WHERE world_id = ? FOR UPDATE', [$this->worldId])->fetch(PDO::FETCH_ASSOC);
        if ($row === false) throw new \DomainException('World not initialized');
        if ($row['rules_key'] !== $this->rulesKey()) throw new \DomainException('World rules mismatch');
        if ($this->hasProjection()) {
            $version=(int)$this->query('SELECT economy_projection_version FROM engine_worlds WHERE world_id=?',[$this->worldId])->fetchColumn();
            if ($version===0) {
                (new PdoEconomyProjection($this->pdo,$this->worldId))->bootstrap(ScalarMap::decode($row['state_json']));
                $this->query('UPDATE engine_worlds SET economy_projection_version=1 WHERE world_id=?',[$this->worldId]);
            } elseif ($version!==1) throw new \UnexpectedValueException('Invalid economy projection version');
        }
        return $row;
    }

    private function rulesKey(): string { return $this->rules?->key ?? 'legacy'; }

    private function assertHandlers(?CommandHandler $commands = null, ?EventHandler $events = null): void
    {
        if ($this->rules !== null && (($commands !== null && $commands !== $this->rules->commands)
            || ($events !== null && $events !== $this->rules->events))) {
            throw new \DomainException('Handler is not part of the pinned rule set');
        }
    }

    private function event(array $row): ScheduledEvent
    {
        return new ScheduledEvent($this->worldId, $row['event_id'], $row['event_type'], (int)$row['due_at'], ScalarMap::decode($row['payload_json']));
    }

    public function schedule(ScheduledEvent $event): bool
    {
        if ($this->processing) throw new \LogicException('Reentrant mutation prohibited');
        if ($event->worldId !== $this->worldId) throw new \InvalidArgumentException('Wrong world');
        return $this->transaction(function () use ($event): bool {
            $world = $this->lockedWorld();
            $existing = $this->query('SELECT event_id, event_type, due_at, payload_json FROM engine_events WHERE world_id = ? AND event_id = ?', [$this->worldId, $event->id])->fetch(PDO::FETCH_ASSOC);
            if ($existing !== false) {
                if (!$this->event($existing)->equals($event)) throw new \DomainException('Conflicting event ID');
                return false;
            }
            if ($event->dueAt < (int)$world['watermark']) throw new \DomainException('Event precedes committed logical time');
            $seq = (int)$world['next_sequence'];
            if ($seq === PHP_INT_MAX) throw new \OverflowException('Event sequence exhausted');
            $seq++;
            $this->query('INSERT INTO engine_events (world_id,event_id,event_type,due_at,sequence_no,payload_json) VALUES (?,?,?,?,?,?)',
                [$this->worldId,$event->id,$event->type,$event->dueAt,$seq,ScalarMap::encode($event->payload)]);
            $this->query('UPDATE engine_worlds SET next_sequence = ? WHERE world_id = ?', [$seq,$this->worldId]);
            return true;
        });
    }

    /** @return list<string> events committed by this invocation; already committed events survive later failure. */
    public function processDue(EventHandler $handler, int $limit = 100, int $budgetMs = 15000): array
    {
        $this->assertHandlers(events: $handler);
        if ($limit < 1 || $budgetMs < 1) throw new \InvalidArgumentException('Positive batch limits required');
        if ($this->processing) throw new \LogicException('Reentrant mutation prohibited');
        $cutoff = $this->clock->now();
        $started = hrtime(true);
        $committed = [];
        $this->processing = true;
        try {
            while (count($committed) < $limit && (hrtime(true) - $started) / 1000000 < $budgetMs) {
                $prepared=$this->prepareEconomyEvent($handler,$cutoff);
                $id = $this->transaction(function () use ($handler, $cutoff,$prepared): ?string {
                    $world = $this->lockedWorld();
                    if ($cutoff < (int)$world['watermark']) throw new \DomainException('Clock precedes committed logical time');
                    $row = $this->query('SELECT event_id,event_type,due_at,payload_json FROM engine_events WHERE world_id = ? AND completed = 0 AND due_at <= ? ORDER BY due_at,sequence_no LIMIT 1 FOR UPDATE', [$this->worldId,$cutoff])->fetch(PDO::FETCH_ASSOC);
                    if ($row === false) return null;
                    $event = $this->event($row);
                    $usable=$prepared!==null
                        && $prepared['journal_sequence']===(int)$world['journal_sequence']
                        && $prepared['state_json']===$world['state_json']
                        && $prepared['next_sequence']===(int)$world['next_sequence']
                        && $prepared['event_id']===$event->id
                        && $prepared['event_type']===$event->type
                        && $prepared['due_at']===$event->dueAt
                        && $prepared['payload_json']===$row['payload_json'];
                    if ($usable) {
                        $before=$prepared['before'];
                        $eventResult=$prepared['result'];
                        $changedKeys=$prepared['changed_keys'];
                    } else {
                        $before=ScalarMap::decode($world['state_json']);
                        $eventResult=$this->resolveEvent($handler,$event,$before);
                        $changedKeys=null;
                    }
                    $newState = ScalarMap::encode($eventResult->state);
                    $this->syncProjection($before,$eventResult->state,$changedKeys);
                    $this->query('UPDATE engine_worlds SET state_json = ?, watermark = ? WHERE world_id = ?', [$newState,$event->dueAt,$this->worldId]);
                    $changed = $this->query('UPDATE engine_events SET completed = 1 WHERE world_id = ? AND event_id = ? AND completed = 0', [$this->worldId,$event->id])->rowCount();
                    if ($changed !== 1) throw new \LogicException('Event completion invariant violated');
                    $this->journal($world,'event',$event->id,$event->type,$event->dueAt,ScalarMap::encode($event->payload),$newState,[],$eventResult->movements);
                    return $event->id;
                });
                if ($id === null) break;
                $committed[] = $id;
            }
        } finally {
            $this->processing = false;
        }
        return $committed;
    }

    /**
     * Speculative computation for pinned, pure economy events. Read world and
     * due event outside any writer lock; processDue rechecks the full
     * version, sequence and exact due event under world FOR UPDATE before use.
     */
    private function prepareEconomyEvent(EventHandler $handler,int $cutoff): ?array
    {
        if (!$handler instanceof EconomyEventHandler || !$this->hasProjection()) return null;
        try {
            $world=$this->query('SELECT state_json,journal_sequence,next_sequence,watermark,rules_key,economy_projection_version
                FROM engine_worlds WHERE world_id=?',[$this->worldId])->fetch(PDO::FETCH_ASSOC);
            if ($world===false || $world['rules_key']!==$this->rulesKey()
                || (int)$world['economy_projection_version']!==1
                || $cutoff<(int)$world['watermark']) return null;
            $row=$this->query('SELECT event_id,event_type,due_at,payload_json,sequence_no FROM engine_events
                WHERE world_id=? AND completed=0 AND due_at<=?
                ORDER BY due_at,sequence_no LIMIT 1',[$this->worldId,$cutoff])->fetch(PDO::FETCH_ASSOC);
            if ($row===false) return null;
            $event=$this->event($row);
            $before=ScalarMap::decode($world['state_json']);
            $result=$this->resolveEvent($handler,$event,$before);
            return ['state_json'=>$world['state_json'],
                'journal_sequence'=>(int)$world['journal_sequence'],
                'next_sequence'=>(int)$world['next_sequence'],
                'event_id'=>$event->id,'event_type'=>$event->type,
                'due_at'=>$event->dueAt,'payload_json'=>$row['payload_json'],
                'before'=>$before,'result'=>$result,
                'changed_keys'=>PdoEconomyProjection::deltaKeys($before,$result->state)];
        } catch (\Throwable) {
            // A bad speculative snapshot is never a result. The locked
            // canonical event processor reports the actual domain error.
            return null;
        }
    }

    private function resolveEvent(EventHandler $handler,ScheduledEvent $event,array $state): EventResult
    {
        return $handler instanceof EventEffectsHandler
            ? $handler->resolve($event,$state)
            : new EventResult($handler->apply($event,$state));
    }

    /** Canonical digest includes complete planned event definitions in order. */
    private function followupDigest(array $events): string
    {
        $rows = [];
        foreach ($events as $event) {
            $rows[] = [$event->worldId,$event->id,$event->type,$event->dueAt,ScalarMap::encode($event->payload)];
        }
        return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
    }

    private function journal(array $world, string $kind, string $id, string $type, int $at, string $payload, string $state, array $events, array $movements = []): void
    {
        $seq = (int)$world['journal_sequence'];
        if ($seq === PHP_INT_MAX) throw new \OverflowException('Journal exhausted');
        $this->query('INSERT INTO engine_journal (world_id,journal_no,entry_kind,entry_id,entry_type,effective_at,payload_json,state_digest,followup_digest) VALUES (?,?,?,?,?,?,?,?,?)',[
            $this->worldId,$seq+1,$kind,$id,$type,$at,$payload,hash('sha256',$state),$this->followupDigest($events),
        ]);
        $this->query('UPDATE engine_worlds SET journal_sequence = ? WHERE world_id = ?',[$seq+1,$this->worldId]);
        $envelope = ScalarMap::encode(['schema'=>1,'kind'=>$kind,'id'=>$id,'type'=>$type,'effective_at'=>$at]);
        $this->query('INSERT INTO engine_outbox (world_id,journal_no,envelope_json) VALUES (?,?,?)',[$this->worldId,$seq+1,$envelope]);
        foreach ($movements as $index=>$movement) {
            $this->query('INSERT INTO engine_resource_ledger (world_id,journal_no,line_no,account_id,resource_id,delta_units,balance_after,reason) VALUES (?,?,?,?,?,?,?,?)',
                [$this->worldId,$seq+1,$index+1,...$movement->row()]);
        }
    }

    /** Re-evaluate a bounded journal using the original trusted handlers.
     * Diagnostic operation, never called on the normal request path.
     * Existing worlds replay only from their migration checkpoint.
     */
    public function verifyReplay(CommandHandler $commands, EventHandler $events, int $maxEntries = 10000): ReplayReport
    {
        $this->assertHandlers($commands,$events);
        if ($maxEntries < 1 || $maxEntries > 100000) throw new \InvalidArgumentException('Invalid replay entry limit');
        if ($this->processing) throw new \LogicException('Reentrant mutation prohibited');
        $this->processing = true;
        try {
            return $this->transaction(function () use ($commands,$events,$maxEntries): ReplayReport {
                $world = $this->lockedWorld();
                $count = (int)$world['journal_sequence'];
                if ($count > $maxEntries) throw new \LengthException('Journal exceeds replay limit');
                $rows = $this->query('SELECT journal_no,entry_kind,entry_id,entry_type,effective_at,payload_json,state_digest,followup_digest FROM engine_journal WHERE world_id = ? ORDER BY journal_no LIMIT '.($maxEntries+1),[$this->worldId]);
                $state = ScalarMap::decode($world['checkpoint_json']);
                $at = (int)$world['checkpoint_at'];
                $index = 0;
                while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                    $index++;
                    $nextAt = (int)$row['effective_at'];
                    if ($index > $maxEntries || (int)$row['journal_no'] !== $index || $nextAt < $at) throw new \UnexpectedValueException('Journal order mismatch');
                    $payload = ScalarMap::decode($row['payload_json']);
                    $followups = [];
                    $movements = [];
                    if ($row['entry_kind'] === 'command') {
                        $result = $commands->apply(new Command($this->worldId,$row['entry_id'],$row['entry_type'],$payload),$state,$nextAt);
                        $state = ScalarMap::validate($result->state);
                        $followups = $result->events;
                        $movements = $result->movements;
                    } elseif ($row['entry_kind'] === 'event') {
                        $result = $this->resolveEvent($events,new ScheduledEvent($this->worldId,$row['entry_id'],$row['entry_type'],$nextAt,$payload),$state);
                        $state = ScalarMap::validate($result->state);
                        $movements = $result->movements;
                    } else {
                        throw new \UnexpectedValueException('Unknown journal entry');
                    }
                    if (!hash_equals($row['state_digest'],hash('sha256',ScalarMap::encode($state))) || !hash_equals($row['followup_digest'],$this->followupDigest($followups))) {
                        throw new \UnexpectedValueException('Replay divergence at journal entry '.$index);
                    }
                    $ledgerRows = $this->query('SELECT line_no,account_id,resource_id,delta_units,balance_after,reason FROM engine_resource_ledger WHERE world_id = ? AND journal_no = ? ORDER BY line_no',[$this->worldId,$index])->fetchAll(PDO::FETCH_NUM);
                    $expectedRows = [];
                    foreach ($movements as $line=>$movement) $expectedRows[] = [$line+1,...$movement->row()];
                    $actualRows = array_map(static fn(array $r): array => [(int)$r[0],$r[1],$r[2],(int)$r[3],(int)$r[4],$r[5]],$ledgerRows);
                    if ($actualRows !== $expectedRows) throw new \UnexpectedValueException('Ledger divergence at journal entry '.$index);
                    $at = $nextAt;
                }
                $stateJson = ScalarMap::encode($state);
                if ($index !== $count || $at !== (int)$world['watermark'] || $stateJson !== ScalarMap::encode(ScalarMap::decode($world['state_json']))) {
                    throw new \UnexpectedValueException('Replay differs from committed world');
                }
                return new ReplayReport($index,(int)$world['checkpoint_at'],$at,hash('sha256',$stateJson));
            });
        } finally {
            $this->processing = false;
        }
    }

    /** Trusted system command intake. Never expose directly to players. */
    public function execute(Command $command, CommandHandler $handler): CommandReceipt
    {
        if (str_starts_with($command->id,'actor:')) throw new \InvalidArgumentException('Reserved command namespace');
        return $this->executeInternal($command,$handler,null,null);
    }

    /** Identity is supplied by a trusted authenticated application, never by request payload. */
    public function executeForActor(string $actor,Command $command,EconomyCommandHandler $handler): ActorReceipt
    {
        PdoMemberships::validateId($actor);
        if ($this->rules===null) throw new \DomainException('Actor intake requires pinned rules');
        if ($command->worldId!==$this->worldId) throw new \InvalidArgumentException('Wrong world');
        $scoped='actor:'.hash('sha256',json_encode([$actor,$command->id],JSON_THROW_ON_ERROR));
        $internal=new Command($this->worldId,$scoped,$command->type,$command->payload);
        $receipt=$this->executeInternal($internal,$handler,$actor,$command->id);
        return new ActorReceipt($command->id,$receipt->effectiveAt,$receipt->scheduledIds);
    }

    /** Caller holds world lock; admin membership changes use the same lock. */
    private function assertActiveActor(string $actor): void
    {
        if ($this->query('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',[$this->worldId,$actor])->fetchColumn()===false) {
            throw new \DomainException('Actor access denied');
        }
    }

    private function assertAccountOwner(mixed $account,?string $actor): void
    {
        if (!is_string($account)) throw new \InvalidArgumentException('Invalid account');
        PdoMemberships::validateId($account);
        $owner=$this->query('SELECT o.actor_id FROM engine_account_owners o JOIN engine_memberships m ON m.world_id=o.world_id AND m.actor_id=o.actor_id WHERE o.world_id=? AND o.account_id=? AND m.active=1',[$this->worldId,$account])->fetchColumn();
        if ($owner===false || ($actor!==null && $owner!==$actor)) throw new \DomainException('Account access denied');
    }

    private function authorizeActor(string $actor,Command $command,?array $lockedWorld=null): void
    {
        $this->assertActiveActor($actor);
        switch ($command->type) {
            case 'economy.open':
            case 'economy.settle':
            case 'economy.upgrade':
            case 'market.claim':
            case 'building.claim':
            case 'settlement.feed':
            case 'settlement.work':
            case 'industry.generate':
            case 'industry.refine':
            case 'industry.ship':
                $this->assertAccountOwner($command->payload['account']??null,$actor);
                break;
            case 'economy.transfer':
                $this->assertAccountOwner($command->payload['from']??null,$actor);
                $this->assertAccountOwner($command->payload['to']??null,null);
                break;
            case 'building.place':
                $this->assertAccountOwner($command->payload['account']??null,$actor);
                $p=$command->payload;
                foreach (['building','territory','entity','type'] as $key) {
                    if (!is_string($p[$key]??null)) throw new \InvalidArgumentException('Missing foundation identity');
                    PdoMemberships::validateId($p[$key]);
                }
                $stages=$this->rules?->commands instanceof EconomyCommandHandler
                    ? $this->rules->commands->buildingStages($p['type']):null;
                if ($stages===null) throw new \DomainException('Unapproved building type');
                $typeRecord=$this->query('SELECT rules_key,stages_json FROM engine_building_types
                    WHERE world_id=? AND building_type=?',[$this->worldId,$p['type']])->fetch(PDO::FETCH_ASSOC);
                $entityRecord=$this->query('SELECT actor_id,location_id FROM engine_entities WHERE world_id=?
                    AND entity_id=?',[$this->worldId,$p['entity']])->fetch(PDO::FETCH_ASSOC);
                $territoryRecord=$this->query('SELECT owner_actor_id,location_id FROM engine_territories
                    WHERE world_id=? AND territory_id=?',[$this->worldId,$p['territory']])->fetch(PDO::FETCH_ASSOC);
                if ($typeRecord===false || $typeRecord['rules_key']!==$this->rulesKey()
                    || $typeRecord['stages_json']!==json_encode($stages,JSON_THROW_ON_ERROR)
                    || $entityRecord===false || $territoryRecord===false
                    || $entityRecord['actor_id']!==$actor
                    || $territoryRecord['owner_actor_id']!==$actor
                    || $entityRecord['location_id']!==$territoryRecord['location_id']) {
                    throw new \DomainException('Invalid or foreign foundation site');
                }
                break;
            case 'building.cancel':
            case 'building.start':
                $this->assertAccountOwner($command->payload['account']??null,$actor);
                $building=$command->payload['building']??null;
                $type=$command->payload['type']??null;
                if (!is_string($building) || !is_string($type)) {
                    throw new \InvalidArgumentException('Invalid building identity');
                }
                PdoMemberships::validateId($building);
                PdoMemberships::validateId($type);
                $pinned=$this->rules?->commands instanceof EconomyCommandHandler
                    ? $this->rules->commands->buildingStages($type) : null;
                if ($pinned===null) throw new \DomainException('Building type not in pinned rules');
                $row=$this->query('SELECT b.actor_id,b.building_type,t.rules_key,t.stages_json
                    FROM engine_buildings b JOIN engine_building_types t
                    ON t.world_id=b.world_id AND t.building_type=b.building_type
                    WHERE b.world_id=? AND b.building_id=?',
                    [$this->worldId,$building])->fetch(PDO::FETCH_ASSOC);
                if ($row===false || $row['actor_id']!==$actor
                    || $row['building_type']!==$type || $row['rules_key']!==$this->rulesKey()
                    || $row['stages_json']!==json_encode($pinned,JSON_THROW_ON_ERROR)) {
                    throw new \DomainException('Building not owned or incompatible with pinned rules');
                }
                break;
            case 'market.create':
            case 'market.cancel':
                $this->assertAccountOwner($command->payload['seller']??null,$actor);
                break;
            case 'market.accept':
                $this->assertAccountOwner($command->payload['buyer']??null,$actor);
                $this->assertAccountOwner($command->payload['seller']??null,null);
                break;
            default:
                // Pack extensions are opt-in and verified ONLY inside the
                // locked command transaction. No speculative execution,
                // cross-world access, or actor-controlled handler selection.
                $owned=$this->rules?->commands instanceof EconomyCommandHandler
                    ? $this->rules->commands->actorPackAccounts($command):null;
                if ($owned===null) throw new \DomainException('Actor command not allowed');
                foreach ($owned as $account) $this->assertAccountOwner($account,$actor);
                $diplomacyTarget=$this->rules->commands->diplomacyTarget($command);
                if ($diplomacyTarget!==null) {
                    if ($lockedWorld===null || count($owned)!==1
                        || $owned[0]===$diplomacyTarget) {
                        throw new \DomainException('Diplomacy requires distinct world-locked accounts');
                    }
                    $this->assertAccountOwner($diplomacyTarget,null);
                    $diplomacyOwner=$this->query('SELECT actor_id FROM engine_account_owners
                        WHERE world_id=? AND account_id=?',
                        [$this->worldId,$diplomacyTarget])->fetchColumn();
                    if ($diplomacyOwner===false || $diplomacyOwner===$actor) {
                        throw new \DomainException('Diplomacy denied');
                    }
                }
                $target=$this->rules->commands->engagementTarget($command);
                if ($target!==null) {
                    if ($lockedWorld===null || count($owned)!==1) {
                        throw new \DomainException('Engagement requires locked world and single source account');
                    }
                    $this->assertAccountOwner($target,null);
                    $targetOwner=$this->query('SELECT actor_id FROM engine_account_owners
                        WHERE world_id=? AND account_id=?',[$this->worldId,$target])->fetchColumn();
                    if ($targetOwner===false || $targetOwner===$actor) {
                        throw new \DomainException('Engagement denied');
                    }
                    $this->rules->commands->assertEngagement($command,
                        ScalarMap::decode($lockedWorld['state_json']),$owned[0],$target,$this->clock->now());
                }
                break;
        }
    }

    /** Read committed market terms only, never private world state. */
    public function marketForActor(string $actor,?string $after=null,int $limit=50,?string $give=null,?string $want=null): array
    {
        PdoMemberships::validateId($actor);
        MarketProjection::validatePage($after,$limit);
        MarketProjection::validateResourceFilter($give);
        MarketProjection::validateResourceFilter($want);
        if ($this->rules===null || !$this->rules->commands instanceof EconomyCommandHandler) throw new \DomainException('Market requires pinned economy rules');
        if ($this->processing) throw new \LogicException('Reentrant mutation prohibited');
        return $this->transaction(function () use ($actor,$after,$limit,$give,$want): array {
            if ($this->hasProjection()) {
                // Normal SQL listing is an MVCC read; it does not lock the
                // entire world or enumerate full JSON state. Only the initial
                // controlled legacy backfill needs an exclusive world lock.
                $row=$this->query('SELECT rules_key,economy_projection_version,watermark FROM engine_worlds WHERE world_id=?',
                    [$this->worldId])->fetch(PDO::FETCH_ASSOC);
                if ($row===false || $row['rules_key']!==$this->rulesKey()) {
                    throw new \DomainException('World not initialized or rules mismatch');
                }
                if ((int)$row['economy_projection_version']===0) {
                    // Never let an unauthenticated/nonmember read force a large
                    // first-use backfill. Actor is rechecked in the snapshot.
                    $this->assertActiveActor($actor);
                    $this->lockedWorld();
                }
                elseif ((int)$row['economy_projection_version']!==1) throw new \UnexpectedValueException('Invalid projection version');
                $this->assertActiveActor($actor);
                $now=$this->clock->now();
                if ($now<0 || $now<(int)$row['watermark']) {
                    throw new \DomainException('Clock precedes committed logical time');
                }
                return (new PdoEconomyProjection($this->pdo,$this->worldId))->marketPage($after,$limit,$now,$give,$want);
            }
            $world=$this->lockedWorld();
            $this->assertActiveActor($actor);
            $offers=MarketProjection::openOffers(ScalarMap::decode($world['state_json']));
            $accounts=array_values(array_unique(array_column($offers,'seller'),SORT_STRING));
            if ($accounts===[]) return ['offers'=>[],'nextAfter'=>null];
            $slots=implode(',',array_fill(0,count($accounts),'?'));
            $active=$this->query('SELECT o.account_id FROM engine_account_owners o JOIN engine_memberships m ON m.world_id=o.world_id AND m.actor_id=o.actor_id WHERE o.world_id=? AND m.active=1 AND o.account_id IN ('.$slots.')',[$this->worldId,...$accounts])->fetchAll(PDO::FETCH_COLUMN);
            return MarketProjection::page($offers,$active,$after,$limit,$give,$want);
        });
    }

    /** Own-account, read-only terminal history. Does not alter replay state. */
    public function marketHistoryForActor(string $actor,string $account,?string $after=null,int $limit=50): array
    {
        PdoMemberships::validateId($actor);
        PdoMemberships::validateId($account);
        MarketProjection::validatePage($after,$limit);
        if ($this->rules===null || !$this->rules->commands instanceof EconomyCommandHandler) {
            throw new \DomainException('Market requires pinned economy rules');
        }
        if ($this->processing) throw new \LogicException('Reentrant history prohibited');
        return $this->transaction(function () use ($actor,$account,$after,$limit): array {
            if ($this->hasProjection()) {
                $row=$this->query('SELECT rules_key,economy_projection_version,watermark
                    FROM engine_worlds WHERE world_id=?',[$this->worldId])->fetch(PDO::FETCH_ASSOC);
                if ($row===false || $row['rules_key']!==$this->rulesKey()) {
                    throw new \DomainException('World not initialized or rules mismatch');
                }
                $this->assertActiveActor($actor);
                $this->assertAccountOwner($account,$actor);
                if ((int)$row['economy_projection_version']===0) {
                    $this->lockedWorld();
                } elseif ((int)$row['economy_projection_version']!==1) {
                    throw new \UnexpectedValueException('Invalid projection version');
                }
                if ($this->clock->now()<(int)$row['watermark']) {
                    throw new \DomainException('Clock precedes committed logical time');
                }
                return (new PdoEconomyProjection($this->pdo,$this->worldId))->historyPage($account,$after,$limit);
            }
            $world=$this->lockedWorld();
            $this->assertActiveActor($actor);
            $this->assertAccountOwner($account,$actor);
            return MarketProjection::history(ScalarMap::decode($world['state_json']),$account,$after,$limit);
        });
    }

    /** Own-account projection, guarded by the due-event barrier. */
    public function balanceForActor(string $actor,string $account,string $resource,EconomyCommandHandler $handler): ProductionState
    {
        if (!$this->hasProjection()) return $this->actorEconomyProjection($actor,$account,$resource,$handler,false);
        PdoMemberships::validateId($actor);
        if ($this->rules===null) throw new \DomainException('Actor intake requires pinned rules');
        $this->assertHandlers(commands:$handler);
        if ($this->processing) throw new \LogicException('Reentrant projection prohibited');
        $marker=$this->query('SELECT economy_projection_version FROM engine_worlds WHERE world_id=?',[$this->worldId])->fetchColumn();
        if ($marker===false) throw new \DomainException('World not initialized');
        if ((int)$marker===0) {
            $this->assertActiveActor($actor);
            $this->materialize();
        }
        return $this->transaction(function () use ($actor,$account,$resource,$handler): ProductionState {
            $world=$this->query('SELECT rules_key,watermark FROM engine_worlds WHERE world_id=?',
                [$this->worldId])->fetch(PDO::FETCH_ASSOC);
            if ($world===false || $world['rules_key']!==$this->rulesKey()) throw new \DomainException('World rules mismatch');
            $this->assertActiveActor($actor);
            $this->assertAccountOwner($account,$actor);
            $at=$this->clock->now();
            if ($at<0 || $at<(int)$world['watermark']) throw new \DomainException('Clock precedes committed logical time');
            if ($this->query('SELECT event_id FROM engine_events WHERE world_id=? AND completed=0 AND due_at<=? LIMIT 1',
                [$this->worldId,$at])->fetchColumn()!==false) {
                throw new \DomainException('Due events must be processed before projection');
            }
            $row=$this->query('SELECT stock_units,settled_at,remainder,level_no
                FROM engine_economy_balances WHERE world_id=? AND account_id=? AND resource_id=?',
                [$this->worldId,$account,$resource])->fetch(PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('Unknown resource account');
            return $handler->balanceAtRow($row,$resource,$at);
        });
    }

    /** Atomic stock and progression snapshot; no private event identifiers are exposed. */
    public function overviewForActor(string $actor,string $account,string $resource,EconomyCommandHandler $handler): array
    {
        if (!$this->hasProjection()) return $this->actorEconomyProjection($actor,$account,$resource,$handler,true);
        PdoMemberships::validateId($actor);
        if ($this->rules===null) throw new \DomainException('Actor intake requires pinned rules');
        $this->assertHandlers(commands:$handler);
        if ($this->processing) throw new \LogicException('Reentrant projection prohibited');
        $marker=$this->query('SELECT economy_projection_version FROM engine_worlds WHERE world_id=?',[$this->worldId])->fetchColumn();
        if ($marker===false) throw new \DomainException('World not initialized');
        if ((int)$marker===0) {
            $this->assertActiveActor($actor);
            $this->materialize();
        }
        return $this->transaction(function () use ($actor,$account,$resource,$handler): array {
            $world=$this->query('SELECT rules_key,watermark FROM engine_worlds WHERE world_id=?',
                [$this->worldId])->fetch(PDO::FETCH_ASSOC);
            if ($world===false || $world['rules_key']!==$this->rulesKey()) throw new \DomainException('World rules mismatch');
            $this->assertActiveActor($actor);
            $this->assertAccountOwner($account,$actor);
            $at=$this->clock->now();
            if ($at<0 || $at<(int)$world['watermark']) throw new \DomainException('Clock precedes committed logical time');
            if ($this->query('SELECT event_id FROM engine_events WHERE world_id=? AND completed=0 AND due_at<=? LIMIT 1',
                [$this->worldId,$at])->fetchColumn()!==false) {
                throw new \DomainException('Due events must be processed before projection');
            }
            $row=$this->query('SELECT stock_units,settled_at,remainder,level_no,pending_event_id
                FROM engine_economy_balances WHERE world_id=? AND account_id=? AND resource_id=?',
                [$this->worldId,$account,$resource])->fetch(PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('Unknown resource account');
            $balance=$handler->balanceAtRow($row,$resource,$at);
            $progress=$handler->upgradeProjectionRow($row,$resource);
            $pending=null;$next=$progress['next'];
            if ($progress['pendingId']!==null) {
                $scheduled=$this->query('SELECT event_id,event_type,due_at,payload_json FROM engine_events
                    WHERE world_id=? AND event_id=? AND completed=0',
                    [$this->worldId,$progress['pendingId']])->fetch(PDO::FETCH_ASSOC);
                if ($scheduled===false) throw new \LogicException('Missing pending upgrade event');
                $event=$this->event($scheduled);
                if ($event->type!=='economy.upgrade.complete'
                    || ($event->payload['account']??null)!==$account
                    || ($event->payload['resource']??null)!==$resource
                    || ($event->payload['level']??null)!==$next->level || $event->dueAt<=$at) {
                    throw new \LogicException('Inconsistent pending upgrade event');
                }
                $pending=['level'=>$next->level,'dueAt'=>$event->dueAt,'amount'=>$next->amount];
                $next=null;
            }
            $r=$this->query('SELECT claimable_units FROM engine_market_refunds
                WHERE world_id=? AND account_id=? AND resource_id=?',
                [$this->worldId,$account,$resource])->fetchColumn();
            $claimable=$r===false?0:(int)$r;
            return get_object_vars($balance)+['refundPending'=>$claimable,
                'level'=>$progress['level'],'pending'=>$pending,
                'next'=>$next===null?null:['level'=>$next->level,'cost'=>$next->cost,
                    'duration'=>$next->duration,'amount'=>$next->amount]];
        });
    }

    private function actorEconomyProjection(string $actor,string $account,string $resource,EconomyCommandHandler $handler,bool $overview): ProductionState|array
    {
        PdoMemberships::validateId($actor);
        if ($this->rules===null) throw new \DomainException('Actor intake requires pinned rules');
        $this->assertHandlers(commands:$handler);
        if ($this->processing) throw new \LogicException('Reentrant mutation prohibited');
        return $this->transaction(function () use ($actor,$account,$resource,$handler,$overview): ProductionState|array {
            $world=$this->lockedWorld();
            $this->assertActiveActor($actor);
            $this->assertAccountOwner($account,$actor);
            $at=$this->clock->now();
            if ($at<0 || $at<(int)$world['watermark']) throw new \DomainException('Clock precedes committed logical time');
            if ($this->query('SELECT event_id FROM engine_events WHERE world_id=? AND completed=0 AND due_at<=? LIMIT 1 FOR UPDATE',[$this->worldId,$at])->fetchColumn()!==false) {
                throw new \DomainException('Due events must be processed before projection');
            }
            $state=ScalarMap::decode($world['state_json']);
            $balance=$handler->balanceAt($state,$account,$resource,$at);
            if (!$overview) return $balance;
            $progress=$handler->upgradeProjection($state,$account,$resource);
            $pending=null;
            $next=$progress['next'];
            if ($progress['pendingId']!==null) {
                $row=$this->query('SELECT event_id,event_type,due_at,payload_json FROM engine_events WHERE world_id=? AND event_id=? AND completed=0 FOR UPDATE',[$this->worldId,$progress['pendingId']])->fetch(PDO::FETCH_ASSOC);
                if ($row===false) throw new \LogicException('Missing pending upgrade event');
                $event=$this->event($row);
                if ($event->type!=='economy.upgrade.complete' || ($event->payload['account']??null)!==$account || ($event->payload['resource']??null)!==$resource || ($event->payload['level']??null)!==$next->level || $event->dueAt<=$at) {
                    throw new \LogicException('Inconsistent pending upgrade event');
                }
                $pending=['level'=>$next->level,'dueAt'=>$event->dueAt,'amount'=>$next->amount];
                $next=null;
            }
            $claimable=$state['market:refund:'.$account.':'.$resource]??0;
            if (!is_int($claimable) || $claimable<0) throw new \LogicException('Invalid pending escrow refund');
            return get_object_vars($balance)+['refundPending'=>$claimable,'level'=>$progress['level'],'pending'=>$pending,'next'=>$next===null?null:['level'=>$next->level,'cost'=>$next->cost,'duration'=>$next->duration,'amount'=>$next->amount]];
        });
    }

    /** Transactional command intake with durable receipts and atomic follow-up scheduling. */
    private function executeInternal(Command $command,CommandHandler $handler,?string $actor,?string $clientRequest): CommandReceipt
    {
        $this->assertHandlers(commands: $handler);
        if ($this->processing) throw new \LogicException('Reentrant mutation prohibited');
        if ($command->worldId !== $this->worldId) throw new \InvalidArgumentException('Wrong world');
        $this->processing = true;
        try {
            $prepared=$this->prepareEconomyTransition($command,$handler,$actor);
            if ($prepared!==null && $actor!==null) {
                $committed=$this->tryOptimisticEconomyCommit($command,$actor,$clientRequest,$prepared);
                if ($committed!==null) return $committed;
            }
            return $this->transaction(function () use ($command,$handler,$actor,$clientRequest,$prepared): CommandReceipt {
                $world = $this->lockedWorld();
                if ($actor!==null) $this->authorizeActor($actor,$command,$world);
                $payload = ScalarMap::encode($command->payload);
                $existing = $this->query('SELECT command_type,payload_json,effective_at,result_state_json,scheduled_ids_json,actor_id,client_request_id FROM engine_commands WHERE world_id = ? AND command_id = ?', [$this->worldId,$command->id])->fetch(PDO::FETCH_ASSOC);
                if ($existing !== false) {
                    if ($existing['actor_id']!==$actor || $existing['client_request_id']!==$clientRequest || $existing['command_type'] !== $command->type || ScalarMap::encode(ScalarMap::decode($existing['payload_json'])) !== $payload) {
                        throw new \DomainException('Conflicting command ID');
                    }
                    return new CommandReceipt((int)$existing['effective_at'],ScalarMap::decode($existing['result_state_json']),json_decode($existing['scheduled_ids_json'],true,512,JSON_THROW_ON_ERROR));
                }
                $at = $this->clock->now();
                if ($at < 0 || $at < (int)$world['watermark']) throw new \DomainException('Clock precedes committed logical time');
                $due = $this->query('SELECT event_id FROM engine_events WHERE world_id = ? AND completed = 0 AND due_at <= ? LIMIT 1 FOR UPDATE', [$this->worldId,$at])->fetchColumn();
                if ($due !== false) throw new \DomainException('Due events must be processed before new commands');
                $usable=$prepared!==null
                    && $prepared['journal_sequence']===(int)$world['journal_sequence']
                    && $prepared['state_json']===$world['state_json']
                    && $prepared['at']===$at;
                if ($usable) {
                    $before=$prepared['before'];
                    $result=$prepared['result'];
                    $changedKeys=$prepared['changed_keys'];
                } else {
                    $before=ScalarMap::decode($world['state_json']);
                    $result=$handler->apply($command,$before,$at);
                    $changedKeys=null;
                }
                if ($command->type==='building.place') {
                    if ($actor===null) throw new \DomainException('Actor required for player foundation');
                    $p=$command->payload;
                    $count=(int)$this->query('SELECT COUNT(*) FROM engine_buildings WHERE world_id=? AND actor_id=?',
                        [$this->worldId,$actor])->fetchColumn();
                    if ($count>=64) throw new \DomainException('Actor building count exceeded');
                    // A second request with a different ID cannot mutate an
                    // existing foundation. A replay with the same request ID
                    // returns above before arriving here.
                    if ($this->query('SELECT building_id FROM engine_buildings WHERE world_id=? AND building_id=?',
                        [$this->worldId,$p['building']])->fetchColumn()!==false) {
                        throw new \DomainException('Building identifier already registered');
                    }
                    $this->query('INSERT INTO engine_buildings
                        (world_id,building_id,entity_id,territory_id,actor_id,building_type)
                        VALUES (?,?,?,?,?,?)',[
                        $this->worldId,$p['building'],$p['entity'],$p['territory'],$actor,$p['type']
                    ]);
                }
                $stateJson = ScalarMap::encode($result->state);
                $this->syncProjection($before,$result->state,$changedKeys);
                $seq = (int)$world['next_sequence'];
                $ids = [];
                foreach ($result->events as $event) {
                    if ($event->worldId !== $this->worldId || $event->dueAt < $at) throw new \DomainException('Invalid follow-up event world or time');
                    if ($this->query('SELECT event_id FROM engine_events WHERE world_id = ? AND event_id = ?', [$this->worldId,$event->id])->fetchColumn() !== false) {
                        throw new \DomainException('Follow-up event ID already exists');
                    }
                    if ($seq === PHP_INT_MAX) throw new \OverflowException('Event sequence exhausted');
                    $seq++;
                    $this->query('INSERT INTO engine_events (world_id,event_id,event_type,due_at,sequence_no,payload_json) VALUES (?,?,?,?,?,?)',[$this->worldId,$event->id,$event->type,$event->dueAt,$seq,ScalarMap::encode($event->payload)]);
                    $ids[] = $event->id;
                }
                $this->query('UPDATE engine_worlds SET next_sequence = ?, watermark = ?, state_json = ? WHERE world_id = ?',[$seq,$at,$stateJson,$this->worldId]);
                // Actor API receipts expose only id/time/scheduled IDs, never
                // the full world snapshot. On schema 020, store '{}' for such
                // receipts to avoid quadratic database growth. Existing rows
                // and trusted-system receipts retain their original format.
                $receiptState=$actor!==null && $this->hasProjection() ? '{}' : $stateJson;
                $this->query('INSERT INTO engine_commands (world_id,command_id,command_type,payload_json,effective_at,result_state_json,scheduled_ids_json,actor_id,client_request_id) VALUES (?,?,?,?,?,?,?,?,?)',[$this->worldId,$command->id,$command->type,$payload,$at,$receiptState,json_encode($ids,JSON_THROW_ON_ERROR),$actor,$clientRequest]);
                $this->journal($world,'command',$command->id,$command->type,$at,$payload,$stateJson,$result->events,$result->movements);
                return new CommandReceipt($at,ScalarMap::decode($stateJson),$ids);
            });
        } finally {
            $this->processing = false;
        }
    }

    /** Trusted operator CLI: bootstrap legacy rows after a verified backup. */
    public function materialize(): array
    {
        if ($this->processing) throw new \LogicException('Reentrant projection migration');
        if (!$this->hasProjection()) throw new \DomainException('Migration 020 not installed');
        return $this->transaction(function ():array {
            $world=$this->lockedWorld();
            $balances=(int)$this->query('SELECT COUNT(*) FROM engine_economy_balances WHERE world_id=?',[$this->worldId])->fetchColumn();
            $offers=(int)$this->query('SELECT COUNT(*) FROM engine_market_offers WHERE world_id=?',[$this->worldId])->fetchColumn();
            $refunds=(int)$this->query('SELECT COUNT(*) FROM engine_market_refunds WHERE world_id=?',[$this->worldId])->fetchColumn();
            return ['world'=>$this->worldId,'journal'=>(int)$world['journal_sequence'],
                'balances'=>$balances,'offers'=>$offers,'deferredRefunds'=>$refunds,
                'projectionVersion'=>1];
        });
    }


    /**
     * Private aggregate-only rank; ties share position, opponent identities
     * and individual scores never leave the SQL transaction.
     *
     * @return array{position:int,participants:int}
     */
    public function rankForActor(string $actor,string $account,ScoreRules $policy): array
    {
        PdoMemberships::validateId($actor);
        PdoMemberships::validateId($account);
        if ($this->rules===null || $this->processing) {
            throw new \DomainException('Ranking requires pinned rules');
        }
        return $this->transaction(function () use($actor,$account,$policy): array {
            $world=$this->lockedWorld();
            $this->assertActiveActor($actor);
            $this->assertAccountOwner($account,$actor);
            $now=$this->clock->now();
            if ($now<0 || $now<(int)$world['watermark']
                || $this->query('SELECT event_id FROM engine_events
                    WHERE world_id=? AND completed=0 AND due_at<=? LIMIT 1 FOR UPDATE',
                    [$this->worldId,$now])->fetchColumn()!==false) {
                throw new \DomainException('Ranking requires completed due events');
            }
            $accounts=$this->query('SELECT o.account_id FROM engine_account_owners o
                JOIN engine_memberships m ON m.world_id=o.world_id
                    AND m.actor_id=o.actor_id AND m.active=1
                WHERE o.world_id=? ORDER BY o.account_id LIMIT 129',
                [$this->worldId])->fetchAll(PDO::FETCH_COLUMN);
            return (new ScoreRanker($policy))->rank(
                ScalarMap::decode($world['state_json']),$accounts,$account);
        });
    }

    /**
     * Own-account, read-only progression projection, calculated from the
     * world-locked canonical state. No score value is accepted from clients.
     * The same due-event barrier used by other authoritative projections
     * prevents reading a state whose due events must first be processed.
     *
     * @return array{development:int,military:int,global:int}
     */
    public function scoreForActor(string $actor,string $account,ScoreRules $policy): array
    {
        PdoMemberships::validateId($actor);
        PdoMemberships::validateId($account);
        if ($this->rules===null) throw new \DomainException('Progression requires pinned world rules');
        if ($this->processing) throw new \LogicException('Reentrant progression read denied');
        return $this->transaction(function () use($actor,$account,$policy): array {
            $world=$this->lockedWorld();
            $this->assertActiveActor($actor);
            $this->assertAccountOwner($account,$actor);
            $now=$this->clock->now();
            if ($now<0 || $now<(int)$world['watermark']) {
                throw new \DomainException('Progression clock behind world history');
            }
            if ($this->query('SELECT event_id FROM engine_events
                    WHERE world_id=? AND completed=0 AND due_at<=? LIMIT 1 FOR UPDATE',
                    [$this->worldId,$now])->fetchColumn()!==false) {
                throw new \DomainException('Progression requires processing due events');
            }
            return (new ScoreProjector($policy))->forAccount(
                ScalarMap::decode($world['state_json']),$account);
        });
    }


    /**
     * Durable bot command receipt, scoped to an authenticated actor, their
     * account and resource. World locking serializes it with command inserts.
     * No private world snapshot or another actor's receipt is ever returned.
     */
    public function botReceiptForActor(
        string $actor,string $account,string $resource,string $requestId
    ): ?ActorReceipt {
        PdoMemberships::validateId($actor);
        PdoMemberships::validateId($account);
        PdoMemberships::validateId($resource);
        if (!preg_match('/^bot-[a-f0-9]{64}$/D',$requestId)) {
            throw new \InvalidArgumentException('Invalid bot receipt identifier');
        }
        if ($this->rules===null || $this->processing) {
            throw new \DomainException('Bot receipt read requires pinned rules');
        }
        return $this->transaction(function () use (
            $actor,$account,$resource,$requestId
        ): ?ActorReceipt {
            $this->lockedWorld();
            $this->assertActiveActor($actor);
            $this->assertAccountOwner($account,$actor);
            $scoped='actor:'.hash('sha256',
                json_encode([$actor,$requestId],JSON_THROW_ON_ERROR));
            $row=$this->query('SELECT command_type,payload_json,effective_at,scheduled_ids_json
                FROM engine_commands WHERE world_id=? AND command_id=?
                    AND actor_id=? AND client_request_id=?',
                [$this->worldId,$scoped,$actor,$requestId])->fetch(PDO::FETCH_ASSOC);
            if ($row===false) return null;
            $payload=ScalarMap::decode($row['payload_json']);
            if (!in_array($row['command_type'],['economy.settle','economy.upgrade'],true)
                || count($payload)!==2
                || ($payload['account']??null)!==$account
                || ($payload['resource']??null)!==$resource) {
                return null;
            }
            return new ActorReceipt($requestId,(int)$row['effective_at'],
                json_decode($row['scheduled_ids_json'],true,512,JSON_THROW_ON_ERROR));
        });
    }

    public function state(): array
    {
        $json = $this->query('SELECT state_json FROM engine_worlds WHERE world_id = ? AND rules_key = ?', [$this->worldId,$this->rulesKey()])->fetchColumn();
        if ($json === false) throw new \DomainException('World not initialized');
        return ScalarMap::decode($json);
    }

    public function pendingCount(): int
    {
        $count = $this->query('SELECT COUNT(e.event_id) FROM engine_worlds w LEFT JOIN engine_events e ON e.world_id = w.world_id AND e.completed = 0 WHERE w.world_id = ? AND w.rules_key = ? GROUP BY w.world_id',[$this->worldId,$this->rulesKey()])->fetchColumn();
        if ($count === false) throw new \DomainException('World not initialized or rules mismatch');
        return (int)$count;
    }
}
