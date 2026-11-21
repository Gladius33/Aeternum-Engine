<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Player-facing receipt intentionally excludes private world state. */
final readonly class ActorReceipt
{
    /** @param list<string> $scheduledIds */
    public function __construct(
        public string $requestId,
        public int $effectiveAt,
        public array $scheduledIds,
    ) {}
}
