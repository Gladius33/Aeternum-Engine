<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class EventResult
{
    /** @param list<ResourceMovement> $movements */
    public function __construct(public array $state,public array $movements = [])
    {
        new CommandResult($state,[],$movements);
    }
}
