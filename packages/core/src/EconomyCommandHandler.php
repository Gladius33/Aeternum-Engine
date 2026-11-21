<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Reference economy for trusted commands. Authentication/actor ownership belongs to the application adapter. */
final class EconomyCommandHandler implements CommandHandler
{
    public const MAX_ACCOUNTS = 1000;
    private array $definitions = [];
    private array $upgrades = [];

    /** @param list<ResourceDefinition> $definitions */
    public function __construct(array $definitions,array $upgrades = [])
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

    /** Read-only projection; not authorization or durable settlement. */
    public function balanceAt(array $state,string $account,string $resource,int $at): ProductionState
    {
        return $this->read($state,$this->id($account),$this->id($resource))->advanceTo($at);
    }

    public function apply(Command $command,array $state,int $effectiveAt): CommandResult
    {
        $ledger = [];
        $events = [];
        if ($command->type === 'economy.open') {
            $payload = $this->payload($command,['account','resource']);
            $account = $this->id($payload['account']); $resource = $this->id($payload['resource']);
            if (array_key_exists($this->prefix($account,$resource).'stock',$state)) throw new \DomainException('Account already exists');
            $count = $state['econ:count'] ?? 0;
            if (!is_int($count) || $count<0 || $count>=self::MAX_ACCOUNTS) throw new \DomainException('Resource account limit reached');
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
    public function complete(ScheduledEvent $event,array $state): EventResult
    {
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
