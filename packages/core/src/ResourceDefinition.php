<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class ResourceDefinition
{
    public function __construct(
        public string $id,
        public int $initialStock,
        public int $capacity,
        public int $amount,
        public int $interval,
    ) {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$id)) throw new \InvalidArgumentException('Invalid resource ID');
        new ProductionState($initialStock,$capacity,$amount,$interval,0);
    }

    public function open(int $at): ProductionState
    {
        return new ProductionState($this->initialStock,$this->capacity,$this->amount,$this->interval,$at);
    }
}
