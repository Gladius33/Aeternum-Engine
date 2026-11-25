<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PackDevelopment};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    require __DIR__.'/../vendor/autoload.php';
    $load=static function(string $file): \Aeternum\Core\GamePack {
        $data=file_get_contents($file,false,null,0,GamePackLoader::MAX_BYTES+1);
        if ($data===false) throw new RuntimeException('Cannot read manifest');
        return GamePackLoader::load($data);
    };
    $op=$argv[1]??'';
    if ($op==='scaffold' && $argc===5) {
        echo PackDevelopment::scaffold($argv[2],$argv[3],$argv[4]);exit(0);
    }
    if ($op==='validate' && $argc>=3 && $argc<=19) {
        $result=PackDevelopment::inspect($load($argv[2]),array_slice($argv,3));
    } elseif ($op==='upgrade-plan' && $argc===4) {
        $result=PackDevelopment::upgradePlan($load($argv[2]),$load($argv[3]));
    } else {
        throw new InvalidArgumentException('Usage: pack-sdk.php scaffold ID VERSION NAME | validate MANIFEST [MODULE ...] | upgrade-plan OLD_MANIFEST NEW_MANIFEST');
    }
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,"Pack SDK rejected input: ".get_class($e).PHP_EOL);exit(1);
}
