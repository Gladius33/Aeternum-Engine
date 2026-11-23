<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * A separately reviewed, explicitly opted-in player command.
 *
 * Every owned account referenced by the handler must be declared here.
 * These fields are verified inside the same world-locked MariaDB
 * transaction that commits the resulting command and receipt.
 */
interface ActorPackCommand extends ApprovedPackCommand
{
    /** @return non-empty-list<string> Names of required string account-ID fields. */
    public function ownedAccountFields(string $commandType): array;
}
