<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Reference economy for trusted commands. Authentication/actor ownership belongs to the application adapter. */
final class EconomyCommandHandler implements CommandHandler
{
    // No lifetime counter cap: world-scale capacity is enforced by storage and quotas.
    private array $definitions = [];
    private array $upgrades = [];
    private array $buildings = [];

    /** @param list<ResourceDefinition> $definitions */
    public function __construct(array $definitions,array $upgrades = [],array $buildings = [],private readonly ?SettlementRules $settlement=null,private readonly ?IndustryRules $industry=null,private readonly ?TrustedPackCommands $trustedPackCommands=null)
    {
        if ($definitions === [] || count($definitions)>128 || !array_is_list($definitions)) throw new \InvalidArgumentException('Invalid resource definitions');
        foreach ($definitions as $definition) {
            if (!$definition instanceof ResourceDefinition || isset($this->definitions['r:'.$definition->id])) {
                throw new \InvalidArgumentException('Duplicate or invalid resource definition');
            }
            $this->definitions['r:'.$definition->id] = $definition;
        }
        if (!array_is_list($upgrades) || count($upgrades)>12800) throw new \InvalidArgumentException('Invalid upgrade list');
        foreach ($upgrades as $upgrade) {
            if (!$upgrade instanceof ResourceUpgrade || !isset($this->definitions['r:'.$upgrade->resource])) throw new \InvalidArgumentException('Unknown upgrade resource');
            $key = 'r:'.$upgrade->resource;
            if (isset($this->upgrades[$key][$upgrade->level]) || $upgrade->cost>$this->definitions[$key]->capacity) throw new \InvalidArgumentException('Duplicate or infeasible upgrade');
            $this->upgrades[$key][$upgrade->level] = $upgrade;
        }
        foreach ($this->upgrades as $key=>&$levels) {
            ksort($levels,SORT_NUMERIC);
            $expected = 1; $previousAmount = $this->definitions[$key]->amount;
            foreach ($levels as $level=>$upgrade) {
                if ($level!==$expected++ || $upgrade->amount<=$previousAmount) throw new \InvalidArgumentException('Upgrades must be contiguous and improve production');
                $previousAmount=$upgrade->amount;
            }
        }
        unset($levels);
        if (count($buildings)>128) throw new \InvalidArgumentException('Too many building types');
        foreach ($buildings as $type=>$stages) {
            if (!is_string($type)) throw new \InvalidArgumentException('Invalid building type');
            $this->id($type);
            if (!is_array($stages)) throw new \InvalidArgumentException('Invalid building stages');
            $stages=BuildStages::normalize($stages);
            foreach ($stages as $stage) {
                foreach ($stage['costs'] as $resource=>$units) {
                    $definition=$this->definition($resource);
                    if ($units>$definition->capacity) throw new \InvalidArgumentException('Unpayable construction cost');
                }
            }
            $this->buildings[$type]=$stages;
        }
        foreach ($this->buildings as $stages) {
            foreach ($stages as $stage) {
                foreach ($stage['requires'] as $required=>$level) {
                    if (!isset($this->buildings[$required]) || $level>count($this->buildings[$required])) {
                        throw new \InvalidArgumentException('Unknown construction prerequisite');
                    }
                }
            }
        }
    }

    /** Immutable, pinned building costs; null for packs without construction. */
    public function buildingStages(string $type): ?array
    {
        return $this->buildings[$type]??null;
    }

    private function upgrade(string $resource,int $level): ResourceUpgrade
    {
        return $this->upgrades['r:'.$resource][$level] ?? throw new \DomainException('No upgrade available');
    }

    private function level(array $state,string $prefix): int
    {
        $level = $state[$prefix.'level'] ?? 0;
        if (!is_int($level) || $level<0 || $level>100) throw new \DomainException('Invalid resource level');
        return $level;
    }

    private function definition(string $id): ResourceDefinition
    {
        return $this->definitions['r:'.$id] ?? throw new \DomainException('Unknown resource');
    }

    private function payload(Command $command,array $keys): array
    {
        $given = array_keys($command->payload);
        sort($given,SORT_STRING);
        sort($keys,SORT_STRING);
        if ($given !== $keys) throw new \InvalidArgumentException('Invalid economy command payload fields');
        return $command->payload;
    }

    private function id(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$value)) throw new \InvalidArgumentException('Invalid account or resource ID');
        return $value;
    }

    private function prefix(string $account,string $resource): string { return 'econ:'.$account.':'.$resource.':'; }

    private function read(array $state,string $account,string $resource): ProductionState
    {
        $definition = $this->definition($resource);
        $p = $this->prefix($account,$resource);
        foreach (['stock','at','remainder'] as $key) {
            if (!isset($state[$p.$key]) || !is_int($state[$p.$key])) throw new \DomainException('Unknown or invalid resource account');
        }
        $level = $this->level($state,$p);
        $amount = $level===0 ? $definition->amount : $this->upgrade($resource,$level)->amount;
        return new ProductionState($state[$p.'stock'],$definition->capacity,$amount,$definition->interval,$state[$p.'at'],$state[$p.'remainder']);
    }

    private function write(array &$state,string $account,string $resource,ProductionState $balance): void
    {
        $p = $this->prefix($account,$resource);
        $state[$p.'stock'] = $balance->stock;
        $state[$p.'at'] = $balance->at;
        $state[$p.'remainder'] = $balance->remainder;
    }

    private function settle(array &$state,array &$ledger,string $account,string $resource,int $at): ProductionState
    {
        $old = $this->read($state,$account,$resource);
        $new = $old->advanceTo($at);
        if ($new->stock > $old->stock) $ledger[] = new ResourceMovement($account,$resource,$new->stock-$old->stock,$new->stock,'production');
        $this->write($state,$account,$resource,$new);
        return $new;
    }

    /** Derive current stock from one authorized committed row, not a whole JSON world. */
    public function balanceAtRow(array $row,string $resource,int $at): ProductionState
    {
        $definition=$this->definition($this->id($resource));
        $level=(int)($row['level_no']??-1);
        if ($level<0 || $level>100) throw new \DomainException('Invalid indexed resource level');
        $amount=$level===0 ? $definition->amount : $this->upgrade($resource,$level)->amount;
        return (new ProductionState(
            (int)$row['stock_units'],$definition->capacity,$amount,$definition->interval,
            (int)$row['settled_at'],(int)$row['remainder']
        ))->advanceTo($at);
    }

    /** Upgrade metadata reconstructed from the same committed SQL snapshot. */
    public function upgradeProjectionRow(array $row,string $resource): array
    {
        $this->definition($this->id($resource));
        $level=(int)($row['level_no']??-1);
        if ($level<0 || $level>100) throw new \DomainException('Invalid indexed resource level');
        $pending=$row['pending_event_id']??null;
        if ($pending!==null && (!is_string($pending) ||
            !preg_match('/^upgrade:[a-f0-9]{64}$/D',$pending))) {
            throw new \LogicException('Invalid pending indexed upgrade');
        }
        $next=$this->upgrades['r:'.$resource][$level+1]??null;
        if ($pending!==null && $next===null) throw new \LogicException('Pending unavailable indexed upgrade');
        return ['level'=>$level,'pendingId'=>$pending,'next'=>$next];
    }

    /** Read-only projection; not authorization or durable settlement. */
    public function balanceAt(array $state,string $account,string $resource,int $at): ProductionState
    {
        return $this->read($state,$this->id($account),$this->id($resource))->advanceTo($at);
    }

    /** Trusted metadata for a resource already read from the same state snapshot. */
    public function upgradeProjection(array $state,string $account,string $resource): array
    {
        $account=$this->id($account);
        $resource=$this->id($resource);
        $this->read($state,$account,$resource);
        $prefix=$this->prefix($account,$resource);
        $level=$this->level($state,$prefix);
        $pending=$state[$prefix.'pending']??null;
        if ($pending!==null && (!is_string($pending) || !preg_match('/^upgrade:[a-f0-9]{64}$/D',$pending))) {
            throw new \LogicException('Invalid pending upgrade reference');
        }
        $next=$this->upgrades['r:'.$resource][$level+1]??null;
        if ($pending!==null && $next===null) throw new \LogicException('Pending unavailable upgrade');
        return ['level'=>$level,'pendingId'=>$pending,'next'=>$next];
    }

    /**
     * Return an escrow to the original seller without destroying units if
     * production filled storage while the offer remained open. Excess units
     * enter a durable, replayable claim balance, never the player's capped stock.
     */
    private function releaseEscrow(array &$state,array &$ledger,string $prefix,int $at,string $status): void
    {
        $seller=$this->id($state[$prefix.'seller']??null);
        $resource=$this->id($state[$prefix.'giveResource']??null);
        $units=$state[$prefix.'giveUnits']??null;
        if (!is_int($units) || $units<1) throw new \LogicException('Invalid escrow units');
        $stock=$this->settle($state,$ledger,$seller,$resource,$at);
        $credit=min($units,$stock->capacity-$stock->stock);
        if ($credit>0) {
            $stock=$stock->credit($credit);
            $this->write($state,$seller,$resource,$stock);
            $ledger[]=new ResourceMovement($seller,$resource,$credit,$stock->stock,'market_release');
        }
        $remainder=$units-$credit;
        if ($remainder>0) {
            $key='market:refund:'.$seller.':'.$resource;
            $pending=$state[$key]??0;
            if (!is_int($pending) || $pending<0 || $pending>PHP_INT_MAX-$remainder) {
                throw new \OverflowException('Pending refund overflow');
            }
            $state[$key]=$pending+$remainder;
        }
        $state[$prefix.'status']=$status;
    }

    /** Read-only bounded market terms; closed IDs remain reserved forever. */
    private function market(Command $command,array $state,int $at): CommandResult
    {
        $ledger=[];
        $events=[];
        if ($command->type==='market.claim') {
            $p=$this->payload($command,['account','resource']);
            $account=$this->id($p['account']); $resource=$this->id($p['resource']);
            $key='market:refund:'.$account.':'.$resource;
            $pending=$state[$key]??0;
            if (!is_int($pending) || $pending<1) throw new \DomainException('No pending escrow refund');
            $stock=$this->settle($state,$ledger,$account,$resource,$at);
            $units=min($pending,$stock->capacity-$stock->stock);
            if ($units<1) throw new \DomainException('No storage available for escrow refund');
            $stock=$stock->credit($units);
            $this->write($state,$account,$resource,$stock);
            $ledger[]=new ResourceMovement($account,$resource,$units,$stock->stock,'market_claim');
            if ($units===$pending) unset($state[$key]); else $state[$key]=$pending-$units;
            return new CommandResult($state,[],$ledger);
        }
        if ($command->type==='market.create') {
            $fields=array_keys($command->payload);
            sort($fields,SORT_STRING);
            $expected=['offer','seller','giveResource','giveUnits','wantResource','wantUnits'];
            $valid=$expected; sort($valid,SORT_STRING);
            $withExpiry=[...$expected,'expiresAt']; sort($withExpiry,SORT_STRING);
            if ($fields!==$valid && $fields!==$withExpiry) throw new \InvalidArgumentException('Invalid market creation fields');
            $p=$command->payload;
            $expiresAt=$p['expiresAt']??null;
            if (array_key_exists('expiresAt',$p) && (!is_int($expiresAt) || $expiresAt<=$at
                || $at>PHP_INT_MAX-2592000 || $expiresAt>$at+2592000)) {
                throw new \InvalidArgumentException('Invalid market offer expiry');
            }
            $prefix='market:'.$this->id($p['offer']).':';
            $seller=$this->id($p['seller']);
            $give=$this->id($p['giveResource']); $want=$this->id($p['wantResource']);
            $giveUnits=$p['giveUnits']; $wantUnits=$p['wantUnits'];
            if ($give===$want || !is_int($giveUnits) || !is_int($wantUnits) || $giveUnits<1 || $wantUnits<1
                || $giveUnits>$this->definition($give)->capacity || $wantUnits>$this->definition($want)->capacity) {
                throw new \InvalidArgumentException('Invalid market terms');
            }
            if (isset($state[$prefix.'status'])) throw new \DomainException('Market offer ID already used');
            $count=$state['market:count']??0;
            if (!is_int($count) || $count<0 || $count===PHP_INT_MAX) throw new \DomainException('Market offer counter exhausted');
            $this->read($state,$seller,$want);
            $stock=$this->settle($state,$ledger,$seller,$give,$at)->debit($giveUnits);
            $this->write($state,$seller,$give,$stock);
            $ledger[]=new ResourceMovement($seller,$give,-$giveUnits,$stock->stock,'market_reserve');
            foreach (['seller'=>$seller,'giveResource'=>$give,'giveUnits'=>$giveUnits,'wantResource'=>$want,'wantUnits'=>$wantUnits,'status'=>'open'] as $key=>$value) {
                $state[$prefix.$key]=$value;
            }
            $state['market:count']=$count+1;
            if ($expiresAt!==null) {
                $state[$prefix.'expiresAt']=$expiresAt;
                $events[]=new ScheduledEvent($command->worldId,'market-expire:'.hash('sha256',$command->id),
                    'market.expire',$expiresAt,['offer'=>$this->id($p['offer']),'seller'=>$seller]);
            }
        } else {
            $accept=$command->type==='market.accept';
            $p=$this->payload($command,$accept?['offer','seller','buyer']:['offer','seller']);
            $prefix='market:'.$this->id($p['offer']).':';
            $seller=$this->id($p['seller']);
            if (($state[$prefix.'status']??null)!=='open' || ($state[$prefix.'seller']??null)!==$seller) {
                throw new \DomainException('Market offer unavailable');
            }
            $give=$state[$prefix.'giveResource']; $want=$state[$prefix.'wantResource'];
            $giveUnits=$state[$prefix.'giveUnits']; $wantUnits=$state[$prefix.'wantUnits'];
            if ($accept) {
                $buyer=$this->id($p['buyer']);
                if ($buyer===$seller) throw new \InvalidArgumentException('Cannot buy own offer');
                $buyerPay=$this->settle($state,$ledger,$buyer,$want,$at)->debit($wantUnits);
                $sellerPay=$this->settle($state,$ledger,$seller,$want,$at)->credit($wantUnits);
                $buyerGoods=$this->settle($state,$ledger,$buyer,$give,$at)->credit($giveUnits);
                $this->write($state,$buyer,$want,$buyerPay);
                $this->write($state,$seller,$want,$sellerPay);
                $this->write($state,$buyer,$give,$buyerGoods);
                $ledger[]=new ResourceMovement($buyer,$want,-$wantUnits,$buyerPay->stock,'market_payment');
                $ledger[]=new ResourceMovement($seller,$want,$wantUnits,$sellerPay->stock,'market_payment');
                $ledger[]=new ResourceMovement($buyer,$give,$giveUnits,$buyerGoods->stock,'market_delivery');
                $state[$prefix.'status']='filled';
                $state[$prefix.'buyer']=$buyer;
            } else {
                $this->releaseEscrow($state,$ledger,$prefix,$at,'cancelled');
            }
        }
        return new CommandResult($state,$events,$ledger);
    }

    /**
     * Compensating cancellation before the scheduled due time. Reimburse every
     * resource in the same transaction or refuse the whole cancellation when
     * storage cannot accommodate the full refund. No units are silently lost.
     */
    private function cancelBuilding(Command $command,array $state,int $at): CommandResult
    {
        $p=$this->payload($command,['building','account','type']);
        $id=$this->id($p['building']);
        $account=$this->id($p['account']);
        $type=$this->id($p['type']);
        $stages=$this->buildingStages($type);
        if ($stages===null) throw new \DomainException('No pinned building');
        $prefix='build:'.$id.':';
        $pending=$state[$prefix.'pending']??null;
        $level=$state[$prefix.'level']??null;
        $started=$state[$prefix.'started']??null;
        if (!is_string($pending) || !is_int($level) || !isset($stages[$level])
            || !is_int($started) || $started<0
            || ($state[$prefix.'account']??null)!==$account
            || ($state[$prefix.'type']??null)!==$type) {
            throw new \DomainException('No matching pending construction');
        }
        $stage=$stages[$level];
        if ($started>PHP_INT_MAX-$stage['duration'] || $at>=$started+$stage['duration']) {
            throw new \DomainException('Construction already due');
        }
        $ledger=[];
        foreach ($stage['costs'] as $resource=>$units) {
            $balance=$this->settle($state,$ledger,$account,$resource,$at);
            // Capacity is finite: never discard paid resources. Return the
            // immediately storable quantity, place the remainder in durable
            // account-scoped credit until an explicit claim opens storage.
            $immediate=min($units,$balance->capacity-$balance->stock);
            if ($immediate>0) {
                $balance=$balance->credit($immediate);
                $this->write($state,$account,$resource,$balance);
                $ledger[]=new ResourceMovement($account,$resource,$immediate,$balance->stock,
                    'construction_refund');
            }
            $deferred=$units-$immediate;
            if ($deferred>0) {
                $creditKey='build:refund:'.$account.':'.$resource;
                $existing=$state[$creditKey]??0;
                if (!is_int($existing) || $existing<0
                    || $existing>ProductionState::MAX_UNITS-$deferred) {
                    throw new \DomainException('Construction refund credit exhausted');
                }
                $state[$creditKey]=$existing+$deferred;
            }
        }
        unset($state[$prefix.'pending'],$state[$prefix.'started']);
        $state['build:cancelled:'.$pending]=true;
        return new CommandResult($state,[],$ledger);
    }

    /**
     * Deposit a bounded outstanding cancellation credit to physical stock.
     * The authoritative credit is in replay-canonical state, never browser
     * input; duplicate request IDs are idempotent via PdoScheduler.
     */
    private function claimBuilding(Command $command,array $state,int $at): CommandResult
    {
        $p=$this->payload($command,['account','resource']);
        $account=$this->id($p['account']);
        $resource=$this->id($p['resource']);
        $key='build:refund:'.$account.':'.$resource;
        $pending=$state[$key]??null;
        if (!is_int($pending) || $pending<1 || $pending>ProductionState::MAX_UNITS) {
            throw new \DomainException('No pending construction refund');
        }
        $ledger=[];
        $balance=$this->settle($state,$ledger,$account,$resource,$at);
        $units=min($pending,$balance->capacity-$balance->stock);
        if ($units<1) throw new \DomainException('No physical storage capacity for refund');
        $balance=$balance->credit($units);
        $this->write($state,$account,$resource,$balance);
        $ledger[]=new ResourceMovement($account,$resource,$units,$balance->stock,'construction_claim');
        if ($units===$pending) unset($state[$key]); else $state[$key]=$pending-$units;
        return new CommandResult($state,[],$ledger);
    }

    /** Pure deterministic atomic multi-resource construction start. */
    /**
     * Pure, journal-replayable player placement. SQL ownership and type pins
     * are checked by PdoScheduler under the world lock before commit.
     * Placement is free; level-one construction incurs the pinned stage cost.
     */
    private function placeBuilding(Command $command,array $state): CommandResult
    {
        $p=$this->payload($command,['account','building','entity','territory','type']);
        $id=$this->id($p['building']);
        $account=$this->id($p['account']);
        $entity=$this->id($p['entity']);
        $territory=$this->id($p['territory']);
        $type=$this->id($p['type']);
        if ($this->buildingStages($type)===null) throw new \DomainException('Unknown building type');
        $prefix='build:'.$id.':';
        foreach (['type','account','level','pending','entity','territory'] as $key) {
            if (array_key_exists($prefix.$key,$state)) throw new \DomainException('Building identifier already used');
        }
        $state[$prefix.'type']=$type;
        $state[$prefix.'account']=$account;
        $state[$prefix.'level']=0;
        $state[$prefix.'entity']=$entity;
        $state[$prefix.'territory']=$territory;
        return new CommandResult($state);
    }

    private function startBuilding(Command $command,array $state,int $at): CommandResult
    {
        $p=$this->payload($command,['building','account','type']);
        $id=$this->id($p['building']);
        $account=$this->id($p['account']);
        $type=$this->id($p['type']);
        $stages=$this->buildingStages($type);
        if ($stages===null) throw new \DomainException('Building not part of pinned pack');
        $prefix='build:'.$id.':';
        if (isset($state[$prefix.'account']) && $state[$prefix.'account']!==$account
            || isset($state[$prefix.'type']) && $state[$prefix.'type']!==$type) {
            throw new \DomainException('Building identity conflict');
        }
        if (isset($state[$prefix.'pending'])) throw new \DomainException('Construction already pending');
        $level=$state[$prefix.'level']??0;
        if (!is_int($level) || $level<0 || $level>count($stages)) {
            throw new \DomainException('Invalid building level');
        }
        $stage=$stages[$level]??throw new \DomainException('Maximum building level');
        foreach ($stage['requires'] as $requirement=>$requiredLevel) {
            if (($state['build:max:'.$account.':'.$requirement]??0)<$requiredLevel) {
                throw new \DomainException('Missing building prerequisite');
            }
        }
        if ($at>PHP_INT_MAX-$stage['duration']) throw new \OverflowException('Construction due time overflow');
        $ledger=[];
        foreach ($stage['costs'] as $resource=>$units) {
            $balance=$this->settle($state,$ledger,$account,$resource,$at)->debit($units);
            $this->write($state,$account,$resource,$balance);
            $ledger[]=new ResourceMovement($account,$resource,-$units,$balance->stock,'construction_cost');
        }
        $eventId='build:'.hash('sha256',$command->id);
        $state[$prefix.'account']=$account;
        $state[$prefix.'type']=$type;
        $state[$prefix.'level']=$level;
        $state[$prefix.'pending']=$eventId;
        $state[$prefix.'started']=$at;
        return new CommandResult($state,[
            new ScheduledEvent($command->worldId,$eventId,'building.complete',$at+$stage['duration'],
                ['building'=>$id,'account'=>$account,'type'=>$type,'level'=>$level+1])
        ],$ledger);
    }

    /** Explicit player labor: consume grain to settle population and convert
     * available labor into wood. Every state change is journaled atomically. */
    private function settlementAction(Command $command,array $state,int $at): CommandResult
    {
        $rules=$this->settlement??throw new \DomainException('Settlement gameplay disabled for pinned pack');
        $payload=$this->payload($command,['account']);
        $account=$this->id($payload['account']);
        $prefix='settlement:'.$account.':';
        $population=$rules->population($state,$account);
        $ledger=[];
        if ($command->type==='settlement.feed') {
            if ($rules->level($state,$account,$rules->farmType)<1
                || $population>=$rules->capacity($state,$account)) {
                throw new \DomainException('Population capacity requires developed farms and housing');
            }
            $food=$this->settle($state,$ledger,$account,$rules->foodResource,$at)
                ->debit($rules->foodPerPerson);
            $this->write($state,$account,$rules->foodResource,$food);
            $ledger[]=new ResourceMovement($account,$rules->foodResource,
                -$rules->foodPerPerson,$food->stock,'settlement_food');
            $state[$prefix.'population']=$population+1;
            return new CommandResult($state,[],$ledger);
        }
        if ($command->type!=='settlement.work') throw new \DomainException('Unknown settlement command');
        $lumber=$rules->level($state,$account,$rules->lumberType);
        if ($population<2 || $lumber<1) throw new \DomainException('Insufficient workforce or workshop');
        $last=$state[$prefix.'lastWork']??null;
        if ($last!==null && (!is_int($last) || $last<0 || $last>=$at
            || $at-$last<$rules->workCooldownSeconds)) {
            throw new \DomainException('Workforce is resting');
        }
        $food=$this->settle($state,$ledger,$account,$rules->foodResource,$at)
            ->debit($rules->workFoodCost);
        $wood=$this->settle($state,$ledger,$account,$rules->workResource,$at)
            ->credit($rules->workGainPerLumber*$lumber);
        $this->write($state,$account,$rules->foodResource,$food);
        $this->write($state,$account,$rules->workResource,$wood);
        $ledger[]=new ResourceMovement($account,$rules->foodResource,
            -$rules->workFoodCost,$food->stock,'settlement_food');
        $ledger[]=new ResourceMovement($account,$rules->workResource,
            $rules->workGainPerLumber*$lumber,$wood->stock,'settlement_work');
        $state[$prefix.'lastWork']=$at;
        return new CommandResult($state,[],$ledger);
    }

    /**
     * Three industrial operations run entirely inside the standard command
     * scheduler transaction. Every debit, credit, counter and cooldown shares
     * the ordinary replayable world state and the durable resource ledger.
     */
    private function industryAction(Command $command,array $state,int $at): CommandResult
    {
        $rules=$this->industry??throw new \DomainException('Industrial gameplay disabled for pinned pack');
        $payload=$this->payload($command,['account']);
        $account=$this->id($payload['account']);
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
            $fuel=$this->settle($state,$ledger,$account,$rules->rawResource,$at)
                ->debit($rules->fuelCost);
            $energy=$this->settle($state,$ledger,$account,$rules->energyResource,$at)
                ->credit($rules->generationPerLevel*$plants);
            $this->write($state,$account,$rules->rawResource,$fuel);
            $this->write($state,$account,$rules->energyResource,$energy);
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
            $raw=$this->settle($state,$ledger,$account,$rules->rawResource,$at)->debit($rawQty);
            $energy=$this->settle($state,$ledger,$account,$rules->energyResource,$at)->debit($energyQty);
            $product=$this->settle($state,$ledger,$account,$rules->productResource,$at);
            if ($product->stock+$made>$rules->warehouseCapacity($state,$account)) {
                throw new \DomainException('Insufficient warehouse capacity');
            }
            $product=$product->credit($made);
            foreach ([
                [$rules->rawResource,$raw,-$rawQty,'industry_feedstock'],
                [$rules->energyResource,$energy,-$energyQty,'industry_power'],
                [$rules->productResource,$product,$made,'industry_manufacture']
            ] as [$resource,$balance,$delta,$reason]) {
                $this->write($state,$account,$resource,$balance);
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
            $product=$this->settle($state,$ledger,$account,$rules->productResource,$at)->debit($units);
            $energy=$this->settle($state,$ledger,$account,$rules->energyResource,$at)
                ->debit($rules->energyPerShipment*$terminal);
            $delivered=$rules->delivered($state,$account);
            if ($delivered>ProductionState::MAX_UNITS-$units) {
                throw new \DomainException('Delivery counter exhausted');
            }
            $this->write($state,$account,$rules->productResource,$product);
            $this->write($state,$account,$rules->energyResource,$energy);
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

    /** Player-facing command types are only those with reviewed ownership policy. */
    public function allowsActorPackCommand(string $type): bool
    {
        return $this->trustedPackCommands?->allowsActor($type)??false;
    }

    /** @return ?list<string> All actor-owned accounts to verify in SQL. */
    public function actorPackAccounts(Command $command): ?array
    {
        return $this->trustedPackCommands?->actorOwnedAccounts($command);
    }

    public function apply(Command $command,array $state,int $effectiveAt): CommandResult
    {
        // Only trusted operator-bound PHP objects may handle this namespace.
        // HTTP and actor intake remain closed until separately authorized.
        if ($this->trustedPackCommands?->handles($command->type)) {
            return $this->trustedPackCommands->execute($command,$state,$effectiveAt);
        }
        if (in_array($command->type,['industry.generate','industry.refine','industry.ship'],true)) {
            return $this->industryAction($command,$state,$effectiveAt);
        }
        if ($command->type==='settlement.feed' || $command->type==='settlement.work') {
            return $this->settlementAction($command,$state,$effectiveAt);
        }
        if ($command->type==='building.place') return $this->placeBuilding($command,$state);
        if ($command->type==='building.start') return $this->startBuilding($command,$state,$effectiveAt);
        if ($command->type==='building.cancel') return $this->cancelBuilding($command,$state,$effectiveAt);
        if ($command->type==='building.claim') return $this->claimBuilding($command,$state,$effectiveAt);
        if (in_array($command->type,['market.create','market.accept','market.cancel','market.claim'],true)) {
            return $this->market($command,$state,$effectiveAt);
        }
        $ledger = [];
        $events = [];
        if ($command->type === 'economy.open') {
            $payload = $this->payload($command,['account','resource']);
            $account = $this->id($payload['account']); $resource = $this->id($payload['resource']);
            if (array_key_exists($this->prefix($account,$resource).'stock',$state)) throw new \DomainException('Account already exists');
            $count = $state['econ:count'] ?? 0;
            if (!is_int($count) || $count<0 || $count===PHP_INT_MAX) throw new \DomainException('Resource account counter exhausted');
            $balance = $this->definition($resource)->open($effectiveAt);
            $this->write($state,$account,$resource,$balance);
            $state['econ:count'] = $count+1;
            if ($balance->stock>0) $ledger[] = new ResourceMovement($account,$resource,$balance->stock,$balance->stock,'opening');
        } elseif ($command->type === 'economy.settle') {
            $payload = $this->payload($command,['account','resource']);
            $this->settle($state,$ledger,$this->id($payload['account']),$this->id($payload['resource']),$effectiveAt);
        } elseif ($command->type === 'economy.transfer') {
            $payload = $this->payload($command,['from','to','resource','units']);
            $from = $this->id($payload['from']); $to = $this->id($payload['to']); $resource = $this->id($payload['resource']);
            $units = $payload['units'];
            if ($from === $to || !is_int($units) || $units<1 || $units>ProductionState::MAX_UNITS) throw new \InvalidArgumentException('Invalid transfer');
            $source = $this->settle($state,$ledger,$from,$resource,$effectiveAt);
            $target = $this->settle($state,$ledger,$to,$resource,$effectiveAt);
            $source = $source->debit($units);
            $target = $target->credit($units);
            $this->write($state,$from,$resource,$source);
            $this->write($state,$to,$resource,$target);
            $ledger[] = new ResourceMovement($from,$resource,-$units,$source->stock,'transfer');
            $ledger[] = new ResourceMovement($to,$resource,$units,$target->stock,'transfer');
        } elseif ($command->type === 'economy.upgrade') {
            $payload = $this->payload($command,['account','resource']);
            $account = $this->id($payload['account']); $resource = $this->id($payload['resource']);
            $prefix = $this->prefix($account,$resource);
            if (isset($state[$prefix.'pending'])) throw new \DomainException('Upgrade already pending');
            $level = $this->level($state,$prefix)+1;
            $upgrade = $this->upgrade($resource,$level);
            $balance = $this->settle($state,$ledger,$account,$resource,$effectiveAt)->debit($upgrade->cost);
            if ($effectiveAt>PHP_INT_MAX-$upgrade->duration) throw new \OverflowException('Upgrade due time overflow');
            $eventId = 'upgrade:'.hash('sha256',$command->id);
            $state[$prefix.'pending']=$eventId;
            $this->write($state,$account,$resource,$balance);
            $ledger[] = new ResourceMovement($account,$resource,-$upgrade->cost,$balance->stock,'upgrade_cost');
            $events[] = new ScheduledEvent($command->worldId,$eventId,'economy.upgrade.complete',$effectiveAt+$upgrade->duration,
                ['account'=>$account,'resource'=>$resource,'level'=>$level]);
        } else {
            throw new \DomainException('Unknown economy command');
        }
        return new CommandResult($state,$events,$ledger);
    }

    /** Completion settles the old rate at logical due time, then activates the trusted new rate. */
    private function finishBuilding(ScheduledEvent $event,array $state): EventResult
    {
        $p=$event->payload;
        $names=array_keys($p);sort($names,SORT_STRING);
        if ($names!==['account','building','level','type']) throw new \InvalidArgumentException('Invalid construction event');
        $id=$this->id($p['building']);
        $account=$this->id($p['account']);
        $type=$this->id($p['type']);
        $prefix='build:'.$id.':';
        $level=$p['level'];
        $stages=$this->buildingStages($type);
        $cancelKey='build:cancelled:'.$event->id;
        if (($state[$cancelKey]??null)===true) {
            // Canceled work retains its scheduled event for journal continuity.
            // Its eventual delivery is a deterministic, harmless no-op.
            if (($state[$prefix.'account']??null)!==$account
                || ($state[$prefix.'type']??null)!==$type
                || !is_int($level) || $level<1 || $stages===null || $level>count($stages)) {
                throw new \DomainException('Canceled construction envelope differs');
            }
            unset($state[$cancelKey]);
            return new EventResult($state);
        }
        if ($stages===null || !is_int($level) || $level<1 || $level>count($stages)
            || ($state[$prefix.'account']??null)!==$account
            || ($state[$prefix.'type']??null)!==$type
            || ($state[$prefix.'level']??null)!==$level-1
            || ($state[$prefix.'pending']??null)!==$event->id) {
            throw new \DomainException('Construction event no longer matches pending stage');
        }
        $started=$state[$prefix.'started']??null;
        if (!is_int($started) || $started<0
            || $started>PHP_INT_MAX-$stages[$level-1]['duration']
            || $started+$stages[$level-1]['duration']!==$event->dueAt) {
            throw new \DomainException('Construction due date mismatch');
        }
        $state[$prefix.'level']=$level;
        unset($state[$prefix.'pending'],$state[$prefix.'started']);
        $maxKey='build:max:'.$account.':'.$type;
        $state[$maxKey]=max($state[$maxKey]??0,$level);
        return new EventResult($state);
    }

    public function complete(ScheduledEvent $event,array $state): EventResult
    {
        if ($event->type==='building.complete') return $this->finishBuilding($event,$state);
        if ($event->type==='market.expire') {
            $keys=array_keys($event->payload);sort($keys,SORT_STRING);
            if ($keys!==['offer','seller']) throw new \InvalidArgumentException('Invalid expiry envelope');
            $prefix='market:'.$this->id($event->payload['offer']).':';
            $seller=$this->id($event->payload['seller']);
            if (($state[$prefix.'seller']??null)!==$seller
                || ($state[$prefix.'expiresAt']??null)!==$event->dueAt) {
                throw new \DomainException('Offer expiry does not match pinned terms');
            }
            if (($state[$prefix.'status']??null)!=='open') return new EventResult($state,[]);
            $ledger=[];
            $this->releaseEscrow($state,$ledger,$prefix,$event->dueAt,'expired');
            return new EventResult($state,$ledger);
        }
        if ($event->type!=='economy.upgrade.complete') throw new \DomainException('Unknown economy event');
        $keys = array_keys($event->payload); sort($keys,SORT_STRING);
        if ($keys!==['account','level','resource']) throw new \InvalidArgumentException('Invalid upgrade event payload');
        $account = $this->id($event->payload['account']); $resource = $this->id($event->payload['resource']);
        $level = $event->payload['level'];
        $prefix = $this->prefix($account,$resource);
        if (!is_int($level) || $level!==$this->level($state,$prefix)+1 || ($state[$prefix.'pending']??null)!==$event->id) {
            throw new \DomainException('Upgrade completion does not match pending action');
        }
        $this->upgrade($resource,$level);
        $ledger = [];
        $this->settle($state,$ledger,$account,$resource,$event->dueAt);
        $state[$prefix.'level']=$level;
        unset($state[$prefix.'pending']);
        return new EventResult($state,$ledger);
    }
}
