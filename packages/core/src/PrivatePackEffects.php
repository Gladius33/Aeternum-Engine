<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Opt-in contract for new, account-local modules with no ledger movements.
 * Reviewed executable code is trusted, not sandboxed. The engine independently
 * checks the resulting state and scheduled envelopes for this contract.
 */
interface PrivatePackEffects
{
    public function stateNamespace(): string;
}
