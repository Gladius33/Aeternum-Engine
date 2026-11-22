<?php
declare(strict_types=1);
namespace Aeternum\Core;
/** Stable, canonical network identity for defensive rate-limit buckets. */
final class PeerRateKey
{
    public static function fromIp(string $peer): ?string
    {
        $packed=inet_pton($peer);
        if ($packed===false) return null;
        if (strlen($packed)===4) return 'v4:'.bin2hex($packed);
        if (strlen($packed)!==16) return null;
        // Normalize IPv4-mapped IPv6 so a proxy cannot double the same peer's quota.
        if (substr($packed,0,12)===str_repeat("\0",10)."\xff\xff") return 'v4:'.bin2hex(substr($packed,12,4));
        // IPv6 privacy addresses rotate their lower 64 bits. Use one /64 bucket.
        return 'v6-64:'.bin2hex(substr($packed,0,8));
    }
}
