<?php
declare(strict_types=1);
use Aeternum\Core\PeerAddressResolver;
check(PeerAddressResolver::resolve('198.51.100.8','203.0.113.8')==='198.51.100.8','untrusted forwarding header is ignored by default');
check(PeerAddressResolver::resolve('198.51.100.8','203.0.113.8','192.0.2.10')==='198.51.100.8','untrusted direct peer cannot spoof forwarded address');
check(PeerAddressResolver::resolve('192.0.2.10','198.51.100.20, 10.0.0.2','192.0.2.10,10.0.0.2')==='198.51.100.20',
    'approved multi-proxy chain resolves nearest untrusted client');
check(PeerAddressResolver::resolve('192.0.2.10','203.0.113.1, 198.51.100.20','192.0.2.10')==='198.51.100.20',
    'malicious leftmost XFF prefix cannot override untrusted ingress address');
check(PeerAddressResolver::resolve('::ffff:192.0.2.10','198.51.100.20','192.0.2.10')==='198.51.100.20',
    'IPv4 mapped direct peer matches explicit native IPv4 proxy permission');
check(PeerAddressResolver::resolve('2001:db8::2','198.51.100.20','2001:db8::3')==='2001:db8::2',
    'IPv6 proxy trust must match full address, not broad /64 peer quota');
check(PeerAddressResolver::resolve('192.0.2.10','','192.0.2.10')==='192.0.2.10',
    'trusted proxy without a forwarding header falls back to direct peer');
check(PeerAddressResolver::resolve('192.0.2.10','bad address','192.0.2.10')===null,
    'malformed rightmost forwarded address rejected');
check(PeerAddressResolver::resolve('192.0.2.10',str_repeat('x',1025),'192.0.2.10')===null,
    'oversized forwarding header rejected');
check(PeerAddressResolver::resolve('192.0.2.10',implode(',',array_fill(0,17,'192.0.2.1')),'192.0.2.10')===null,
    'overlong forwarding chain rejected');
check(PeerAddressResolver::resolve('not an IP','','')===null,'invalid direct peer rejected');
rejects(fn()=>PeerAddressResolver::resolve('192.0.2.10','192.0.2.1','not-an-IP'),
    InvalidArgumentException::class,'invalid trusted proxy configuration fails closed');
rejects(fn()=>PeerAddressResolver::resolve('192.0.2.10','192.0.2.1',implode(',',array_fill(0,17,'192.0.2.1'))),
    InvalidArgumentException::class,'excess trusted proxy entries fail closed');
