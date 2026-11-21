<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class ResourceMovement
{
    public function __construct(
        public string $account,
        public string $resource,
        public int $delta,
        public int $balanceAfter,
        public string $reason,
    ) {
        foreach ([$account,$resource] as $id) {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$id)) throw new \InvalidArgumentException('Invalid ledger identifier');
        }
        if ($delta === 0 || $delta < -ProductionState::MAX_UNITS || $delta > ProductionState::MAX_UNITS
            || $balanceAfter < 0 || $balanceAfter > ProductionState::MAX_UNITS
            || $balanceAfter-$delta < 0 || $balanceAfter-$delta > ProductionState::MAX_UNITS
            || !in_array($reason,['opening','production','transfer'],true)
            || ($reason !== 'transfer' && $delta < 0)) {
            throw new \InvalidArgumentException('Invalid ledger movement');
        }
    }

    public function row(): array
    {
        return [$this->account,$this->resource,$this->delta,$this->balanceAfter,$this->reason];
    }
}
