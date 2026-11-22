<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,FrozenClock,Command,GamePackLoader};
if ($argc!==3 || !in_array($argv[1],['start','cancel'],true)
    || !preg_match('/^[0-3]$/D',$argv[2])) exit(2);
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/m04-construction-fixture/manifest.json'));
$rules=$pack->rules();
try {
    $command=new Command('m04-building-race',
        'race-'.$argv[1].'-'.$argv[2],
        'building.'.$argv[1],
        ['building'=>'race-mill','account'=>'race-account','type'=>'mill']);
    (new PdoScheduler(db(),'m04-building-race',new FrozenClock(1000),$rules))
        ->executeForActor('race-player',$command,$rules->commands);
    echo "won\n";
} catch (DomainException) {
    echo "denied\n";
} catch (Throwable $error) {
    fwrite(STDERR,get_class($error).': '.$error->getMessage()."\n");
    exit(1);
}
