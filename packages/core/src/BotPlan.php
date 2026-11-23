<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** One bounded, own-account economy action selected by approved bot policy. */
final readonly class BotPlan
{
    public function __construct(public string $type,public array $payload)
    {
        if (!in_array($type,['economy.settle','economy.upgrade'],true)) {
            throw new \InvalidArgumentException('Bot command not on the human economic allowlist');
        }
        $keys=array_keys($payload);sort($keys,SORT_STRING);
        if ($keys!==['account','resource']) {
            throw new \InvalidArgumentException('Bot payload must name exactly one owned account and resource');
        }
        foreach ($payload as $id) {
            if (!is_string($id)) throw new \InvalidArgumentException('Invalid bot payload identity');
            PdoMemberships::validateId($id);
        }
    }
}
