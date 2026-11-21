<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoSessions,FrozenClock};
if ($argc!==2 || !preg_match('/^[0-9]+$/D',$argv[1])) exit(1);
echo (new PdoSessions(db(),new FrozenClock((int)$argv[1])))->purgeExpired(2).PHP_EOL;
