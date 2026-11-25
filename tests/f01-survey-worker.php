<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{GamePackLoader,ReviewedModuleCatalog,PdoScheduler,FrozenClock,Command,PdoMemberships};
if ($argc!==3 || !preg_match('/^[0-3]$/D',$argv[2])) exit(2);
try {
    PdoMemberships::validateId($argv[1]);
    $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/sdk-observatory/versions/1.0.0/manifest.json'));
    $module=ReviewedModuleCatalog::module('survey-v1',$pack);
    $rules=$pack->rules($module['commands'],$module['events']);
    $world='f01-observatory';
    (new PdoScheduler(db(),$world,new FrozenClock(19210),$rules))
        ->executeForActor($argv[1],new Command($world,'survey-race-'.$argv[2],
            'pack.survey.start',['account'=>$argv[1]]),$rules->commands);
    echo "won\n";
} catch (DomainException) {
    echo "denied\n";
} catch (Throwable $e) {
    fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n");exit(1);
}
