<?php
declare(strict_types=1);
namespace Aeternum\Core;

use PDO;

/** MariaDB adapter. Owns its PDO transactions; one committed event per transaction. */
final class PdoScheduler
{
    private bool $processing = false;

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

    private function lockedWorld(): array
    {
        $row = $this->query('SELECT next_sequence, watermark, state_json, journal_sequence, checkpoint_json, checkpoint_at, rules_key FROM engine_worlds WHERE world_id = ? FOR UPDATE', [$this->worldId])->fetch(PDO::FETCH_ASSOC);
        if ($row === false) throw new \DomainException('World not initialized');
        if ($row['rules_key'] !== $this->rulesKey()) throw new \DomainException('World rules mismatch');
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
                $id = $this->transaction(function () use ($handler, $cutoff): ?string {
                    $world = $this->lockedWorld();
                    if ($cutoff < (int)$world['watermark']) throw new \DomainException('Clock precedes committed logical time');
                    $row = $this->query('SELECT event_id,event_type,due_at,payload_json FROM engine_events WHERE world_id = ? AND completed = 0 AND due_at <= ? ORDER BY due_at,sequence_no LIMIT 1 FOR UPDATE', [$this->worldId,$cutoff])->fetch(PDO::FETCH_ASSOC);
                    if ($row === false) return null;
                    $event = $this->event($row);
                    $eventResult = $this->resolveEvent($handler,$event,ScalarMap::decode($world['state_json']));
                    $newState = ScalarMap::encode($eventResult->state);
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

    /** Transactional command intake with durable receipts and atomic follow-up scheduling. */
    public function execute(Command $command, CommandHandler $handler): CommandReceipt
    {
        $this->assertHandlers(commands: $handler);
        if ($this->processing) throw new \LogicException('Reentrant mutation prohibited');
        if ($command->worldId !== $this->worldId) throw new \InvalidArgumentException('Wrong world');
        $this->processing = true;
        try {
            return $this->transaction(function () use ($command,$handler): CommandReceipt {
                $world = $this->lockedWorld();
                $payload = ScalarMap::encode($command->payload);
                $existing = $this->query('SELECT command_type,payload_json,effective_at,result_state_json,scheduled_ids_json FROM engine_commands WHERE world_id = ? AND command_id = ?', [$this->worldId,$command->id])->fetch(PDO::FETCH_ASSOC);
                if ($existing !== false) {
                    if ($existing['command_type'] !== $command->type || ScalarMap::encode(ScalarMap::decode($existing['payload_json'])) !== $payload) {
                        throw new \DomainException('Conflicting command ID');
                    }
                    return new CommandReceipt((int)$existing['effective_at'],ScalarMap::decode($existing['result_state_json']),json_decode($existing['scheduled_ids_json'],true,512,JSON_THROW_ON_ERROR));
                }
                $at = $this->clock->now();
                if ($at < 0 || $at < (int)$world['watermark']) throw new \DomainException('Clock precedes committed logical time');
                $due = $this->query('SELECT event_id FROM engine_events WHERE world_id = ? AND completed = 0 AND due_at <= ? LIMIT 1 FOR UPDATE', [$this->worldId,$at])->fetchColumn();
                if ($due !== false) throw new \DomainException('Due events must be processed before new commands');
                $result = $handler->apply($command,ScalarMap::decode($world['state_json']),$at);
                $stateJson = ScalarMap::encode($result->state);
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
                $this->query('INSERT INTO engine_commands (world_id,command_id,command_type,payload_json,effective_at,result_state_json,scheduled_ids_json) VALUES (?,?,?,?,?,?,?)',[$this->worldId,$command->id,$command->type,$payload,$at,$stateJson,json_encode($ids,JSON_THROW_ON_ERROR)]);
                $this->journal($world,'command',$command->id,$command->type,$at,$payload,$stateJson,$result->events,$result->movements);
                return new CommandReceipt($at,ScalarMap::decode($stateJson),$ids);
            });
        } finally {
            $this->processing = false;
        }
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
