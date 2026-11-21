<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoRateLimiter,FrozenClock};
if ($argc!==2) exit(1);
switch ($argv[1]) {
    case 'admit':
        $r=new PdoRateLimiter(db(),new FrozenClock(2000000));
        echo ($r->admit('rate-cleanup-race',2,60)?'1':'0').PHP_EOL;
        break;
    case 'stale':
        $r=new PdoRateLimiter(db(),new FrozenClock(1000000));
        echo ($r->admit('rate-cleanup-stale-race',1,86400)?'1':'0').PHP_EOL;
        break;
    case 'purge':
        $r=new PdoRateLimiter(db(),new FrozenClock(2000000));
        echo $r->purgeStale(1000).PHP_EOL;
        break;
    default: exit(1);
}
