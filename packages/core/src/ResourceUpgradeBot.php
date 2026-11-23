<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** A conservative example strategy, not a client-visible fake human. */
final readonly class ResourceUpgradeBot implements ApprovedBotStrategy
{
    public function codeDigest(): string
    {
        return hash_file('sha256',__FILE__)
            ?: throw new \RuntimeException('Reviewed bot source hash unavailable');
    }

    public function choose(array $overview,string $account,string $resource,int $logicalTick): ?BotPlan
    {
        PdoMemberships::validateId($account);
        PdoMemberships::validateId($resource);
        if ($logicalTick<0) throw new \InvalidArgumentException('Invalid bot logical tick');
        $stock=$overview['stock']??null;
        $next=$overview['next']??null;
        $pending=$overview['pending']??null;
        if (!is_int($stock) || $stock<0) {
            throw new \DomainException('Invalid human-visible economy overview');
        }
        if ($pending===null && is_array($next) && isset($next['cost'])
            && is_int($next['cost']) && $next['cost']>=0
            && $stock>=$next['cost']) {
            return new BotPlan('economy.upgrade',['account'=>$account,'resource'=>$resource]);
        }
        return new BotPlan('economy.settle',['account'=>$account,'resource'=>$resource]);
    }
}
