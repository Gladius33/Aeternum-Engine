<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoAdminConsole,FrozenClock};
if($argc!==4)exit(2);
[$script,$world,$actor,$event]=$argv;
if(trim((string)fgets(STDIN))!=='go')exit(3);
try {
    $service=new PdoAdminConsole(db(),$world,new FrozenClock(9100));
    $applied=$service->testPayment('m10-manager',$event,$actor,'profile_badge','grant');
    echo $applied?"applied\n":"replayed\n";
} catch(Throwable $e) {
    fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");
    exit(4);
}
