<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Compatibility module for immutable existing game packs. */
final readonly class ReferenceIndustryCommand implements CommandHandler
{
    public function __construct(private EconomyGameplayPort $economy,private IndustryRules $rules) {}

    /**
     * Three industrial operations run entirely inside the standard command
     * scheduler transaction. Every debit, credit, counter and cooldown shares
     * the ordinary replayable world state and the durable resource ledger.
     */
    public function apply(Command $command,array $state,int $at): CommandResult
    {
        $rules=$this->rules;
        $payload=$this->economy->payload($command,['account']);
        $account=$this->economy->id($payload['account']);
        $prefix='industry:'.$account.':';
        $lastKey=$prefix.'last:'.$command->type;
        $last=$state[$lastKey]??null;
        if ($last!==null && (!is_int($last) || $last<0 || $last>$at
            || $at-$last<$rules->cooldownSeconds)) {
            throw new \DomainException('Industrial operation on cooldown');
        }
        $ledger=[];
        if ($command->type==='industry.generate') {
            $plants=$rules->level($state,$account,$rules->generatorType);
            if ($plants<1) throw new \DomainException('Power plant required');
            $fuel=$this->economy->settle($state,$ledger,$account,$rules->rawResource,$at)
                ->debit($rules->fuelCost);
            $energy=$this->economy->settle($state,$ledger,$account,$rules->energyResource,$at)
                ->credit($rules->generationPerLevel*$plants);
            $this->economy->write($state,$account,$rules->rawResource,$fuel);
            $this->economy->write($state,$account,$rules->energyResource,$energy);
            $ledger[]=new ResourceMovement($account,$rules->rawResource,
                -$rules->fuelCost,$fuel->stock,'industry_fuel');
            $ledger[]=new ResourceMovement($account,$rules->energyResource,
                $rules->generationPerLevel*$plants,$energy->stock,'industry_generation');
        } elseif ($command->type==='industry.refine') {
            $factory=$rules->level($state,$account,$rules->factoryType);
            if ($factory<1 || $rules->level($state,$account,$rules->warehouseType)<1) {
                throw new \DomainException('Factory and warehouse required');
            }
            $grid=$rules->grid($state,$account);
            if ($grid['supply']<$grid['load']) throw new \DomainException('Insufficient grid capacity');
            $rawQty=$rules->rawPerBatch*$factory;
            $energyQty=$rules->energyPerBatch*$factory;
            $made=$rules->productPerBatch*$factory;
            $raw=$this->economy->settle($state,$ledger,$account,$rules->rawResource,$at)->debit($rawQty);
            $energy=$this->economy->settle($state,$ledger,$account,$rules->energyResource,$at)->debit($energyQty);
            $product=$this->economy->settle($state,$ledger,$account,$rules->productResource,$at);
            if ($product->stock+$made>$rules->warehouseCapacity($state,$account)) {
                throw new \DomainException('Insufficient warehouse capacity');
            }
            $product=$product->credit($made);
            foreach ([
                [$rules->rawResource,$raw,-$rawQty,'industry_feedstock'],
                [$rules->energyResource,$energy,-$energyQty,'industry_power'],
                [$rules->productResource,$product,$made,'industry_manufacture']
            ] as [$resource,$balance,$delta,$reason]) {
                $this->economy->write($state,$account,$resource,$balance);
                $ledger[]=new ResourceMovement($account,$resource,$delta,$balance->stock,$reason);
            }
        } elseif ($command->type==='industry.ship') {
            $terminal=$rules->level($state,$account,$rules->terminalType);
            if ($terminal<1 || $rules->level($state,$account,$rules->warehouseType)<1) {
                throw new \DomainException('Freight terminal and warehouse required');
            }
            $grid=$rules->grid($state,$account);
            if ($grid['supply']<$grid['load']) throw new \DomainException('Insufficient grid capacity');
            $units=$terminal*$rules->shipmentPerLevel;
            $product=$this->economy->settle($state,$ledger,$account,$rules->productResource,$at)->debit($units);
            $energy=$this->economy->settle($state,$ledger,$account,$rules->energyResource,$at)
                ->debit($rules->energyPerShipment*$terminal);
            $delivered=$rules->delivered($state,$account);
            if ($delivered>ProductionState::MAX_UNITS-$units) {
                throw new \DomainException('Delivery counter exhausted');
            }
            $this->economy->write($state,$account,$rules->productResource,$product);
            $this->economy->write($state,$account,$rules->energyResource,$energy);
            $ledger[]=new ResourceMovement($account,$rules->productResource,
                -$units,$product->stock,'industry_freight');
            $ledger[]=new ResourceMovement($account,$rules->energyResource,
                -$rules->energyPerShipment*$terminal,$energy->stock,'industry_transport');
            $state[$prefix.'delivered']=$delivered+$units;
        } else {
            throw new \DomainException('Unknown industrial command');
        }
        $state[$lastKey]=$at;
        return new CommandResult($state,[],$ledger);
    }

}
