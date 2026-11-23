<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Trusted resource operations already validated by the generic economy.
 * Modules cannot create new HTTP authorization or persistence boundaries.
 * Every call remains inside the same canonical world-state transition.
 */
interface EconomyGameplayPort
{
    public function payload(Command $command,array $keys): array;
    public function id(mixed $value): string;
    public function settle(array &$state,array &$ledger,string $account,string $resource,int $at): ProductionState;
    public function write(array &$state,string $account,string $resource,ProductionState $balance): void;
}
