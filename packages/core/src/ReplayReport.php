<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class ReplayReport
{
    public function __construct(
        public int $entries,
        public int $checkpointAt,
        public int $effectiveAt,
        public string $stateDigest,
    ) {}
}
