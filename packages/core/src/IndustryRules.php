<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * An opt-in, immutable industrial ruleset shared by every configured world.
 * No industrial counters or resource movements exist outside the normal
 * journalled world state; the engine, not the client, derives network capacity.
 */
final readonly class IndustryRules
{
    public function __construct(
        public string $rawResource,
        public string $energyResource,
        public string $productResource,
        public string $generatorType,
        public string $factoryType,
        public string $warehouseType,
        public string $terminalType,
        public int $fuelCost,
        public int $generationPerLevel,
        public int $rawPerBatch,
        public int $energyPerBatch,
        public int $productPerBatch,
        public int $warehousePerLevel,
        public int $shipmentPerLevel,
        public int $energyPerShipment,
        public int $powerPerLevel,
        public int $factoryPowerDraw,
        public int $terminalPowerDraw,
        public int $cooldownSeconds,
        public int $deliveryGoal,
        public array $victoryLevels
    ) {}

    public static function load(\stdClass $raw,array $resources,array $buildings): self
    {
        $fields=get_object_vars($raw);
        $ids=['raw_resource','energy_resource','product_resource','generator_type',
            'factory_type','warehouse_type','terminal_type'];
        $numbers=['fuel_cost','generation_per_level','raw_per_batch','energy_per_batch',
            'product_per_batch','warehouse_per_level','shipment_per_level',
            'energy_per_shipment','power_per_level','factory_power_draw',
            'terminal_power_draw','cooldown_seconds','delivery_goal'];
        $expected=array_merge($ids,$numbers,['victory_levels']);
        $given=array_keys($fields);sort($given,SORT_STRING);sort($expected,SORT_STRING);
        if ($given!==$expected || !$fields['victory_levels'] instanceof \stdClass) {
            throw new \InvalidArgumentException('Invalid industry fields');
        }
        foreach ($ids as $key) {
            if (!is_string($fields[$key])) throw new \InvalidArgumentException('Invalid industry identifier');
            PdoMemberships::validateId($fields[$key]);
        }
        if (count(array_unique(array_map(fn(string $key)=>$fields[$key],
            ['raw_resource','energy_resource','product_resource'])))!==3
            || count(array_unique(array_map(fn(string $key)=>$fields[$key],
                ['generator_type','factory_type','warehouse_type','terminal_type'])))!==4) {
            throw new \InvalidArgumentException('Industry roles must be distinct');
        }
        $defs=[];
        foreach ($resources as $definition) $defs[$definition->id]=$definition;
        foreach (['raw_resource','energy_resource','product_resource'] as $key) {
            if (!isset($defs[$fields[$key]])) throw new \InvalidArgumentException('Unknown industrial resource');
        }
        foreach (['generator_type','factory_type','warehouse_type','terminal_type'] as $key) {
            if (!isset($buildings[$fields[$key]])) throw new \InvalidArgumentException('Unknown industrial building');
        }
        foreach ($numbers as $key) {
            if (!is_int($fields[$key]) || $fields[$key]<1 || $fields[$key]>1000000) {
                throw new \InvalidArgumentException('Industrial quantity out of bounds');
            }
        }
        if ($fields['cooldown_seconds']>ProductionState::MAX_INTERVAL
            || $fields['fuel_cost']>$defs[$fields['raw_resource']]->capacity
            || $fields['raw_per_batch']>$defs[$fields['raw_resource']]->capacity
            || max($fields['energy_per_batch'],$fields['energy_per_shipment'])>$defs[$fields['energy_resource']]->capacity
            || $fields['generation_per_level']*count($buildings[$fields['generator_type']])
                >$defs[$fields['energy_resource']]->capacity
            || $fields['product_per_batch']*count($buildings[$fields['factory_type']])
                >$defs[$fields['product_resource']]->capacity
            || $fields['warehouse_per_level']*count($buildings[$fields['warehouse_type']])
                >$defs[$fields['product_resource']]->capacity) {
            throw new \InvalidArgumentException('Industrial quantities exceed physical capacity');
        }
        $victory=get_object_vars($fields['victory_levels']);
        if ($victory===[] || count($victory)>16) throw new \InvalidArgumentException('Missing industrial objectives');
        foreach ($victory as $type=>$level) {
            PdoMemberships::validateId($type);
            if (!isset($buildings[$type]) || !is_int($level)
                || $level<1 || $level>count($buildings[$type])) {
                throw new \InvalidArgumentException('Unreachable industrial objective');
            }
        }
        ksort($victory,SORT_STRING);
        return new self(...array_merge(array_map(fn(string $key)=>$fields[$key],$ids),
            array_map(fn(string $key)=>$fields[$key],$numbers),[$victory]));
    }

    public function level(array $state,string $account,string $type): int
    {
        $level=$state['build:max:'.$account.':'.$type]??0;
        if (!is_int($level) || $level<0 || $level>16) {
            throw new \UnexpectedValueException('Invalid industrial building level');
        }
        return $level;
    }

    public function grid(array $state,string $account): array
    {
        $plant=$this->level($state,$account,$this->generatorType);
        $factory=$this->level($state,$account,$this->factoryType);
        $terminal=$this->level($state,$account,$this->terminalType);
        $supply=$plant*$this->powerPerLevel;
        $load=$factory*$this->factoryPowerDraw+$terminal*$this->terminalPowerDraw;
        return ['supply'=>$supply,'load'=>$load,'surplus'=>$supply-$load];
    }

    public function warehouseCapacity(array $state,string $account): int
    {
        return $this->warehousePerLevel*$this->level($state,$account,$this->warehouseType);
    }

    public function delivered(array $state,string $account): int
    {
        $key='industry:'.$account.':delivered';
        $quantity=$state[$key]??0;
        if (!is_int($quantity) || $quantity<0 || $quantity>ProductionState::MAX_UNITS) {
            throw new \UnexpectedValueException('Invalid delivered quantity');
        }
        return $quantity;
    }

    public function won(array $state,string $account): bool
    {
        if ($this->delivered($state,$account)<$this->deliveryGoal) return false;
        foreach ($this->victoryLevels as $type=>$level) {
            if ($this->level($state,$account,$type)<$level) return false;
        }
        return true;
    }
}
