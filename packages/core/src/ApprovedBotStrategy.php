<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Operator-reviewed deterministic bot decision. It receives only the same
 * own-account economy overview exposed to a human; no direct SQL or privileged
 * world state is accessible through this API.
 */
interface ApprovedBotStrategy
{
    public function codeDigest(): string;
    public function choose(array $overview,string $account,string $resource,int $logicalTick): ?BotPlan;
}
