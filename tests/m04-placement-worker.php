<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,GamePackLoader,Command,FrozenClock};
if ($argc!==2 || !preg_match('/^[0-3]$/D',$argv[1])) exit(2);
try {
    $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/m04-construction-fixture/manifest.json'));
    $rules=$pack->rules();
    $scheduler=new PdoScheduler(db(),'m04-foundation-race',new FrozenClock(3000),$rules);
    $scheduler->executeForActor('alice',new Command('m04-foundation-race',
        'place-racer-'.$argv[1],'building.place',[
            'account'=>'race-account','building'=>'contended-mill',
            'entity'=>'race-entity','territory'=>'race-land','type'=>'mill'
        ]),$rules->commands);
    echo "won\n";
} catch (DomainException) {
    echo "denied\n";
} catch (Throwable $e) {
    fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");
    exit(1);
}
