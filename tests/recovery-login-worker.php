<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoSessions,FrozenClock};
if($argc!==2) exit(2);
$password=trim(stream_get_contents(STDIN));
$token=(new PdoSessions(db(),new FrozenClock(1200)))->login($argv[1],$password);
echo $token??'NONE';
