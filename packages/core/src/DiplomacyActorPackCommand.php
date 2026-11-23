<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Approved, world-locked diplomacy action with an independent target. */
interface DiplomacyActorPackCommand extends ActorPackCommand
{
    public function targetAccountField(string $type): string;
}
