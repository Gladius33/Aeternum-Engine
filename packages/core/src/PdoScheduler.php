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
        $this->transaction(fn()=> $this->query(
            'INSERT INTO engine_worlds (world_id, state_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE world_id = world_id',
            [$this->worldId, '{}']
        ));
    }

    private function lockedWorld(): array
    {
        $row = $this->query('SELECT next_sequence, watermark, state_json FROM engine_worlds WHERE world_id = ? FOR UPDATE', [$this->worldId])->fetch(PDO::FETCH_ASSOC);
        if ($row === false) throw new \DomainException('World not initialized');
        return $row;
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
                    $newState = ScalarMap::encode($handler->apply($event, ScalarMap::decode($world['state_json'])));
                    $this->query('UPDATE engine_worlds SET state_json = ?, watermark = ? WHERE world_id = ?', [$newState,$event->dueAt,$this->worldId]);
                    $changed = $this->query('UPDATE engine_events SET completed = 1 WHERE world_id = ? AND event_id = ? AND completed = 0', [$this->worldId,$event->id])->rowCount();
                    if ($changed !== 1) throw new \LogicException('Event completion invariant violated');
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

    /** Transactional command intake with durable receipts and atomic follow-up scheduling. */
    public function execute(Command $command, CommandHandler $handler): CommandReceipt
    {
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
                return new CommandReceipt($at,ScalarMap::decode($stateJson),$ids);
            });
        } finally {
            $this->processing = false;
        }
    }

    public function state(): array
    {
        $json = $this->query('SELECT state_json FROM engine_worlds WHERE world_id = ?', [$this->worldId])->fetchColumn();
        if ($json === false) throw new \DomainException('World not initialized');
        return ScalarMap::decode($json);
    }

    public function pendingCount(): int
    {
        return (int)$this->query('SELECT COUNT(*) FROM engine_events WHERE world_id = ? AND completed = 0', [$this->worldId])->fetchColumn();
    }
}
