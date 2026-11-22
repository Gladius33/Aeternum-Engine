<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\PdoSignupSlots;
if ($argc!==2 || $argv[1]!=='2') exit(2);
(new PdoSignupSlots(db(),2))->run(static function():void {
    echo "locked\n";
    flush();
    usleep(2500000);
});
