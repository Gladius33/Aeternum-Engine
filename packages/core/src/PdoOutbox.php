<?php
declare(strict_types=1);
namespace Aeternum\Core;

use PDO;

/** At-least-once handoff; delivery itself must occur outside the DB transaction. */
final class PdoOutbox
{
    public function __construct(private readonly PDO $pdo, private readonly string $worldId, private readonly Clock $deliveryClock)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$worldId)) throw new \InvalidArgumentException('Invalid world identifier');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
            throw new \InvalidArgumentException('Require mysql PDO with exceptions and native prepares');
        }
    }

    private function query(string $sql,array $params): \PDOStatement
    {
        $s = $this->pdo->prepare($sql);
        $s->execute($params);
        return $s;
    }

    private function transaction(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction prohibited');
        $this->pdo->beginTransaction();
        try {
            if ($this->query('SELECT world_id FROM engine_worlds WHERE world_id = ? FOR UPDATE',[$this->worldId])->fetchColumn() === false) {
                throw new \DomainException('World not initialized');
            }
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function now(int $offset = 0): int
    {
        $now = $this->deliveryClock->now();
        if ($now < 0 || $now > PHP_INT_MAX - $offset) throw new \OverflowException('Invalid delivery clock');
        return $now;
    }

    /** Claims a single ready envelope. Workers must use an operational UTC clock, not logical event time. */
    public function claim(int $leaseSeconds = 60): ?OutboxDelivery
    {
        if ($leaseSeconds < 1 || $leaseSeconds > 3600) throw new \InvalidArgumentException('Lease must be 1–3600 seconds');
        return $this->transaction(function () use ($leaseSeconds): ?OutboxDelivery {
            $now = $this->now($leaseSeconds);
            $row = $this->query('SELECT journal_no,envelope_json,attempts FROM engine_outbox WHERE world_id = ? AND delivered_at IS NULL AND available_at <= ? AND lease_until <= ? ORDER BY journal_no LIMIT 1 FOR UPDATE',[$this->worldId,$now,$now])->fetch(PDO::FETCH_ASSOC);
            if ($row === false) return null;
            $attempts = (int)$row['attempts'];
            if ($attempts === PHP_INT_MAX) throw new \OverflowException('Delivery attempts exhausted');
            $token = bin2hex(random_bytes(32));
            $until = $now+$leaseSeconds;
            $sequence = (int)$row['journal_no'];
            $this->query('UPDATE engine_outbox SET lease_token = ?, lease_until = ?, attempts = ? WHERE world_id = ? AND journal_no = ?',[$token,$until,$attempts+1,$this->worldId,$sequence]);
            return new OutboxDelivery($this->worldId,$sequence,ScalarMap::decode($row['envelope_json']),$token,$until,$attempts+1);
        });
    }

    /** Only the current, unexpired lease may acknowledge an envelope. */
    public function acknowledge(OutboxDelivery $delivery): bool
    {
        $this->validate($delivery);
        return $this->transaction(function () use ($delivery): bool {
            $now = $this->now();
            return $this->query('UPDATE engine_outbox SET delivered_at = ?, lease_token = NULL, lease_until = 0 WHERE world_id = ? AND journal_no = ? AND lease_token = ? AND lease_until > ? AND delivered_at IS NULL',[$now,$this->worldId,$delivery->journalNo,$delivery->leaseToken,$now])->rowCount() === 1;
        });
    }

    public function release(OutboxDelivery $delivery,int $delaySeconds = 0): bool
    {
        $this->validate($delivery);
        if ($delaySeconds < 0 || $delaySeconds > 86400) throw new \InvalidArgumentException('Retry delay out of bounds');
        return $this->transaction(function () use ($delivery,$delaySeconds): bool {
            $now = $this->now($delaySeconds);
            return $this->query('UPDATE engine_outbox SET available_at = ?, lease_token = NULL, lease_until = 0 WHERE world_id = ? AND journal_no = ? AND lease_token = ? AND lease_until > ? AND delivered_at IS NULL',[$now+$delaySeconds,$this->worldId,$delivery->journalNo,$delivery->leaseToken,$now])->rowCount() === 1;
        });
    }

    private function validate(OutboxDelivery $delivery): void
    {
        if ($delivery->worldId !== $this->worldId || $delivery->journalNo < 1 || !preg_match('/^[0-9a-f]{64}$/D',$delivery->leaseToken)) {
            throw new \InvalidArgumentException('Invalid scoped delivery');
        }
    }

    public function pendingCount(): int
    {
        return (int)$this->query('SELECT COUNT(*) FROM engine_outbox WHERE world_id = ? AND delivered_at IS NULL',[$this->worldId])->fetchColumn();
    }
}
