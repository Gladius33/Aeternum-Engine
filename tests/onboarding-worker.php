<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{AuthHttpApi,GamePackLoader,FrozenClock};
if ($argc!==3) exit(2);
$world=$argv[1]; $i=(int)$argv[2];
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$api=new AuthHttpApi(db(),new FrozenClock(1900),120,[$world=>$pack]);
[$status,$data]=$api->handle('POST','/v1/auth/register','',json_encode([
    'login'=>'public-same-race','password'=>'race test password for all workers','world'=>$world
],JSON_THROW_ON_ERROR),'198.51.100.'.(100+$i));
if (!in_array($status,[200,201],true)) {
    fwrite(STDERR,'Unexpected signup status '.$status.' '.json_encode($data).PHP_EOL);
    exit(1);
}
echo json_encode(['status'=>$status,'account'=>$data['data']['account']],JSON_THROW_ON_ERROR);
