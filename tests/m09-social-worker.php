<?php
declare(strict_types=1);
// Disposable MariaDB concurrent social acceptance worker; never production code.
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\PdoAlliances;
if ($argc!==4) exit(2);
[$script,$world,$alliance,$actor]=$argv;
if (trim((string)fgets(STDIN))!=='go') exit(3);
$operation=getenv('AETERNUM_M09_ACTION')==='create'?'create':'respond';
try {
    $social=new PdoAlliances(db(),$world);
    if ($operation==='create') {
        $social->create($actor,$alliance,'Race create');
        echo "created\n";
    } else {
        $social->respond($actor,$alliance,true);
        echo "accepted\n";
    }
} catch (DomainException $e) {
    if ($operation!=='create') {
        fwrite(STDERR,$e->getMessage()."\n");
        exit(4);
    }
    echo "denied\n";
} catch (Throwable $e) {
    fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");
    exit(4);
}
