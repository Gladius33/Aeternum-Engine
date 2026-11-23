<?php
declare(strict_types=1);
// Disposable MariaDB concurrent social acceptance worker; never production code.
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\PdoAlliances;
if ($argc!==4) exit(2);
[$script,$world,$alliance,$actor]=$argv;
if (trim((string)fgets(STDIN))!=='go') exit(3);
try {
    (new PdoAlliances(db(),$world))->respond($actor,$alliance,true);
    echo "accepted\n";
} catch (Throwable $e) {
    fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");
    exit(4);
}
