<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Trusted upgrade definition; level is the resulting level. */
final readonly class ResourceUpgrade
{
    public function __construct(
        public string $resource,
        public int $level,
        public int $cost,
        public int $duration,
        public int $amount,
    ) {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$resource)
            || $level<1 || $level>100 || $cost<1 || $cost>ProductionState::MAX_UNITS
            || $duration<1 || $duration>ProductionState::MAX_INTERVAL
            || $amount<0 || $amount>ProductionState::MAX_UNITS) {
            throw new \InvalidArgumentException('Invalid resource upgrade');
        }
    }
}
