<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class OutboxDelivery
{
    public function __construct(
        public string $worldId,
        public int $journalNo,
        public array $envelope,
        public string $leaseToken,
        public int $leaseUntil,
        public int $attempts,
    ) {}

    /** Stable across retries; use with a consumer-specific deduplication store. */
    public function idempotencyKey(): string
    {
        return $this->worldId.':'.$this->journalNo;
    }
}
