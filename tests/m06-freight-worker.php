<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoScheduler,FrozenClock,Command,GamePackLoader};
if ($argc!==3 || !preg_match('/^[0-3]$/D',$argv[1])
    || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$argv[2])) exit(2);
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-industrial/versions/0.3.0/manifest.json'));
$rules=$pack->rules();
try {
    (new PdoScheduler(db(),'m06-industrial-campaign',new FrozenClock(1056),$rules))
        ->executeForActor($argv[2],new Command('m06-industrial-campaign',
            'parallel-freight-'.$argv[1],'industry.ship',['account'=>$argv[2]]),
            $rules->commands);
    echo "won\n";
} catch (DomainException) {
    echo "denied\n";
} catch (Throwable $error) {
    fwrite(STDERR,get_class($error).': '.$error->getMessage()."\n");
    exit(1);
}
