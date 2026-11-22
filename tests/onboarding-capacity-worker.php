<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{AuthHttpApi,GamePackLoader,FrozenClock};
if ($argc!==3 || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$argv[1])) exit(2);
$world=$argv[1]; $i=(int)$argv[2];
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$api=new AuthHttpApi(db(),new FrozenClock(22000),120,[$world=>$pack],false,2);
[$status]=$api->handle('POST','/v1/auth/register','',json_encode([
    'login'=>'capacity-race-'.$i,'password'=>'capacity test password for workers','world'=>$world
],JSON_THROW_ON_ERROR),'192.0.2.'.(210+$i));
if (!in_array($status,[201,429],true)) {
    fwrite(STDERR,'Unexpected signup status '.$status.PHP_EOL);
    exit(1);
}
echo $status;
