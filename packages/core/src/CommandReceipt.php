<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class CommandReceipt
{
    /** @param list<string> $scheduledIds */
    public function __construct(
        public int $effectiveAt,
        public array $state,
        public array $scheduledIds,
    ) {}
}
