<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Reference economy for trusted commands. Authentication/actor ownership belongs to the application adapter. */
final class EconomyCommandHandler implements CommandHandler
{
    public const MAX_ACCOUNTS = 1000;
    private array $definitions = [];

    /** @param list<ResourceDefinition> $definitions */
    public function __construct(array $definitions)
    {
        if ($definitions === [] || count($definitions)>128 || !array_is_list($definitions)) throw new \InvalidArgumentException('Invalid resource definitions');
        foreach ($definitions as $definition) {
            if (!$definition instanceof ResourceDefinition || isset($this->definitions['r:'.$definition->id])) {
                throw new \InvalidArgumentException('Duplicate or invalid resource definition');
            }
            $this->definitions['r:'.$definition->id] = $definition;
        }
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
        return new ProductionState($state[$p.'stock'],$definition->capacity,$definition->amount,$definition->interval,$state[$p.'at'],$state[$p.'remainder']);
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
        } else {
            throw new \DomainException('Unknown economy command');
        }
        return new CommandResult($state,[], $ledger);
    }
}
