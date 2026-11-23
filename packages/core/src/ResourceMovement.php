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
            || !in_array($reason,['opening','production','transfer','upgrade_cost','market_reserve','market_payment','market_delivery','market_release','market_claim','construction_cost','construction_refund','construction_claim','settlement_food','settlement_work','industry_fuel','industry_generation','industry_feedstock','industry_power','industry_manufacture','industry_freight','industry_transport'],true)
            || (in_array($reason,['opening','production','market_delivery','market_release','market_claim','construction_refund','construction_claim','settlement_work','industry_generation','industry_manufacture'],true) && $delta < 0)
            || (in_array($reason,['upgrade_cost','market_reserve','construction_cost','settlement_food','industry_fuel','industry_feedstock','industry_power','industry_freight','industry_transport'],true) && $delta > 0)) {
            throw new \InvalidArgumentException('Invalid ledger movement');
        }
    }

    public function row(): array
    {
        return [$this->account,$this->resource,$this->delta,$this->balanceAfter,$this->reason];
    }
}
