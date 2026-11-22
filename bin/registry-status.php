<?php
declare(strict_types=1);
use Aeternum\Core\WorldPackRegistry;
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    require __DIR__.'/../vendor/autoload.php';
    if ($argc!==2) throw new InvalidArgumentException('Usage: php bin/registry-status.php ABSOLUTE_CONFIG_PATH');
    $worlds=WorldPackRegistry::loadFile($argv[1],__DIR__.'/../games');
    $rows=[];
    foreach ($worlds as $world=>$pack) {
        $rows[]=['world'=>$world,'pack'=>$pack->id,'version'=>$pack->version,'sha256'=>$pack->digest,'rulesKey'=>$pack->rules()->key];
    }
    echo json_encode(['registry_api'=>'1','worlds'=>$rows],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"World registry validation failed. Check file location, digest, pack identity and version.\n");
    exit(1);
}
