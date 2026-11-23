<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Explicit reviewed opt-in for a player-targeting pack command. The attacker's
 * exactly one owned account and the independent target account are verified
 * under the world lock before any gameplay handler executes.
 */
interface EngagementActorPackCommand extends ActorPackCommand
{
    /** The typed payload field naming the account being targeted. */
    public function targetAccountField(string $commandType): string;
}
