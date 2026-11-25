<?php
declare(strict_types=1);
use Aeternum\Core\{ConfiguredWorlds,ReviewedModuleCatalog,WorldRuntime};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    require __DIR__.'/../vendor/autoload.php';
    if ($argc<2 || $argc>18) throw new InvalidArgumentException('Usage: php bin/pack-inspect.php WORLD [REVIEWED_MODULE ...]');
    $packs=ConfiguredWorlds::load();$world=$argv[1];
    $pack=$packs[$world]??throw new InvalidArgumentException('Unknown configured world');
    $commands=[];$events=[];$modules=[];$seen=[];
    foreach (array_slice($argv,2) as $id) {
        if (isset($seen[$id])) throw new InvalidArgumentException('Duplicate module');
        $seen[$id]=true;
        $module=ReviewedModuleCatalog::module($id,$pack);
        $commands=array_merge($commands,$module['commands']);
        $events=array_merge($events,$module['events']);
        $modules[]=['id'=>$id,'sha256'=>$module['sha256']];
    }
    $runtime=new WorldRuntime([$world=>$pack],[$world=>$commands],[$world=>$events]);
    echo json_encode(['world'=>$world,'pack'=>$pack->id,'version'=>$pack->version,
        'pack_sha256'=>$pack->digest,'rules_key'=>$runtime->rule($world)->key,
        'modules'=>$modules],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,"Pack inspection failed: ".get_class($e).PHP_EOL);exit(1);
}
