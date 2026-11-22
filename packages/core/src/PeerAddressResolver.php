<?php
declare(strict_types=1);
namespace Aeternum\Core;
/** Resolve the peer conservatively across an explicitly approved proxy chain. */
final class PeerAddressResolver
{
    public static function resolve(string $direct,string $forwarded,string $trustedIpList=''): ?string
    {
        $directKey=self::exactKey($direct);
        if ($directKey===null) return null;
        $trusted=self::trusted($trustedIpList);
        // Never honor client-controlled forwarding fields unless the TCP peer is trusted.
        if (!isset($trusted[$directKey]) || $forwarded==='') return $direct;
        if (strlen($forwarded)>1024) return null;
        $hops=explode(',',$forwarded);
        if (count($hops)>16) return null;
        // Start with the rightmost untrusted address. A client-controlled prefix
        // cannot override an address appended by the trusted ingress proxy.
        for($i=count($hops)-1;$i>=0;$i--) {
            $ip=trim($hops[$i]);
            $key=self::exactKey($ip);
            if ($key===null) return null;
            if (!isset($trusted[$key])) return $ip;
        }
        return $direct;
    }
    private static function trusted(string $input): array
    {
        if ($input==='') return [];
        if (strlen($input)>1024) throw new \InvalidArgumentException('Invalid trusted proxies');
        $hops=explode(',',$input);
        if (count($hops)>16) throw new \InvalidArgumentException('Too many trusted proxies');
        $keys=[];
        foreach($hops as $hop) {
            $key=self::exactKey(trim($hop));
            if ($key===null) throw new \InvalidArgumentException('Invalid trusted proxy IP');
            $keys[$key]=true;
        }
        return $keys;
    }
    private static function exactKey(string $ip): ?string
    {
        $packed=inet_pton($ip);
        if ($packed===false) return null;
        if (strlen($packed)===4) return 'v4:'.bin2hex($packed);
        if (strlen($packed)!==16) return null;
        // Trust an exact address, never an entire IPv6 /64 as the rate limiter does.
        if (substr($packed,0,12)===str_repeat("\0",10)."\xff\xff") return 'v4:'.bin2hex(substr($packed,12,4));
        return 'v6:'.bin2hex($packed);
    }
}
