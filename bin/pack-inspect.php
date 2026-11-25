<?php
declare(strict_types=1);
use Aeternum\Core\{ConfiguredWorlds,ReviewedModuleCatalog,WorldRuntime};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    require __DIR__.'/../vendor/autoload.php';
    if ($argc<2 || $argc>18) throw new InvalidArgumentException('Usage: php bin/pack-inspect.php WORLD [REVIEWED_MODULE ...]');
    $packs=ConfiguredWorlds::load();$world=$argv[1];
    $pack=$packs[$world]??throw new InvalidArgumentException('Unknown configured world');
    $report=\Aeternum\Core\PackDevelopment::inspect($pack,array_slice($argv,2));
    echo json_encode(['world'=>$world]+$report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,"Pack inspection failed: ".get_class($e).PHP_EOL);exit(1);
}
