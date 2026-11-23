<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Opt-in data-only colony rules; immutable within a world-pinned pack. */
final readonly class SettlementRules
{
    public function __construct(
        public string $foodResource,
        public string $workResource,
        public string $farmType,
        public string $lumberType,
        public string $housingType,
        public int $populationStart,
        public int $populationPerFarm,
        public int $populationPerHousing,
        public int $foodPerPerson,
        public int $workFoodCost,
        public int $workGainPerLumber,
        public int $workCooldownSeconds,
        public array $victoryLevels,
        public int $victoryPopulation
    ) {}

    public static function load(\stdClass $raw,array $resources,array $buildings): self
    {
        $input=get_object_vars($raw);
        $keys=['food_resource','work_resource','farm_type','lumber_type','housing_type',
            'population_start','population_per_farm','population_per_housing','food_per_person',
            'work_food_cost','work_gain_per_lumber','work_cooldown_seconds','victory_levels','victory_population'];
        $actual=array_keys($input);sort($actual,SORT_STRING);sort($keys,SORT_STRING);
        if ($keys!==$actual || !$input['victory_levels'] instanceof \stdClass) {
            throw new \InvalidArgumentException('Invalid settlement fields');
        }
        foreach (['food_resource','work_resource','farm_type','lumber_type','housing_type'] as $key) {
            if (!is_string($input[$key])) throw new \InvalidArgumentException('Invalid settlement identifier');
            PdoMemberships::validateId($input[$key]);
        }
        if ($input['food_resource']===$input['work_resource']) {
            throw new \InvalidArgumentException('Settlement work and food must differ');
        }
        $resourceDefs=[];
        foreach ($resources as $resource) $resourceDefs[$resource->id]=$resource;
        foreach (['food_resource','work_resource'] as $key) {
            if (!isset($resourceDefs[$input[$key]])) throw new \InvalidArgumentException('Unknown settlement resource');
        }
        foreach (['farm_type','lumber_type','housing_type'] as $key) {
            if (!isset($buildings[$input[$key]])) throw new \InvalidArgumentException('Unknown settlement building');
        }
        foreach (['population_start','population_per_farm','population_per_housing','food_per_person',
            'work_food_cost','work_gain_per_lumber','work_cooldown_seconds','victory_population'] as $key) {
            if (!is_int($input[$key]) || $input[$key]<1 || $input[$key]>1000) {
                throw new \InvalidArgumentException('Settlement integer out of bounds');
            }
        }
        if ($input['food_per_person']>$resourceDefs[$input['food_resource']]->capacity
            || $input['work_food_cost']>$resourceDefs[$input['food_resource']]->capacity
            || $input['work_gain_per_lumber']*count($buildings[$input['lumber_type']])
                >$resourceDefs[$input['work_resource']]->capacity) {
            throw new \InvalidArgumentException('Settlement costs exceed physical capacity');
        }
        $goals=get_object_vars($input['victory_levels']);
        if ($goals===[] || count($goals)>16) throw new \InvalidArgumentException('Missing victory goals');
        foreach ($goals as $type=>$level) {
            PdoMemberships::validateId($type);
            if (!isset($buildings[$type]) || !is_int($level) || $level<1 || $level>count($buildings[$type])) {
                throw new \InvalidArgumentException('Invalid victory level');
            }
        }
        ksort($goals,SORT_STRING);
        $cap=$input['population_start']
            +count($buildings[$input['farm_type']])*$input['population_per_farm']
            +count($buildings[$input['housing_type']])*$input['population_per_housing'];
        if ($input['victory_population']>$cap) throw new \InvalidArgumentException('Unreachable population objective');
        return new self($input['food_resource'],$input['work_resource'],$input['farm_type'],
            $input['lumber_type'],$input['housing_type'],$input['population_start'],
            $input['population_per_farm'],$input['population_per_housing'],$input['food_per_person'],
            $input['work_food_cost'],$input['work_gain_per_lumber'],$input['work_cooldown_seconds'],
            $goals,$input['victory_population']);
    }

    public function level(array $state,string $account,string $type): int
    {
        $level=$state['build:max:'.$account.':'.$type]??0;
        if (!is_int($level) || $level<0 || $level>16) throw new \UnexpectedValueException('Invalid settlement building level');
        return $level;
    }

    public function capacity(array $state,string $account): int
    {
        return $this->populationStart
            +$this->level($state,$account,$this->farmType)*$this->populationPerFarm
            +$this->level($state,$account,$this->housingType)*$this->populationPerHousing;
    }

    public function population(array $state,string $account): int
    {
        $count=$state['settlement:'.$account.':population']??$this->populationStart;
        if (!is_int($count) || $count<$this->populationStart || $count>$this->capacity($state,$account)) {
            throw new \UnexpectedValueException('Invalid settlement population');
        }
        return $count;
    }

    public function won(array $state,string $account): bool
    {
        if ($this->population($state,$account)<$this->victoryPopulation) return false;
        foreach ($this->victoryLevels as $type=>$level) {
            if ($this->level($state,$account,$type)<$level) return false;
        }
        return true;
    }
}
