<?php
declare(strict_types=1);
use Aeternum\Core\PeerRateKey;
check(PeerRateKey::fromIp('192.0.2.7')===PeerRateKey::fromIp('::ffff:192.0.2.7'),'IPv4-mapped IPv6 has canonical peer bucket');
check(PeerRateKey::fromIp('2001:db8:1:2::1')===PeerRateKey::fromIp('2001:db8:1:2::abcd'),'rotating IPv6 interface identifiers share a /64 bucket');
check(PeerRateKey::fromIp('2001:db8:1:2::1')!==PeerRateKey::fromIp('2001:db8:1:3::1'),'independent IPv6 /64 retains own quota');
check(PeerRateKey::fromIp('192.0.2.7')!==PeerRateKey::fromIp('192.0.2.8'),'independent IPv4 addresses retain own quota');
check(PeerRateKey::fromIp('192.0.2.7')!==PeerRateKey::fromIp('2001:db8::7'),'IPv4 and IPv6 buckets never collide');
check(PeerRateKey::fromIp('not an IP')===null && PeerRateKey::fromIp('2001:db8::1%eth0')===null,'untrusted invalid peer rejected');
