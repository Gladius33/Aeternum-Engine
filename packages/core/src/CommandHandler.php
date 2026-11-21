<?php
declare(strict_types=1);
namespace Aeternum\Core;

interface CommandHandler
{
    /** Pure handler: uses server-provided effective time, no I/O or external effects. */
    public function apply(Command $command, array $state, int $effectiveAt): CommandResult;
}
