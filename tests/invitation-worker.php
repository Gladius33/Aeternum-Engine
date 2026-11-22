<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{AuthHttpApi,GamePackLoader,FrozenClock};
if($argc!==3)exit(2);
$world=$argv[1]; $n=(int)$argv[2];
$code=trim(stream_get_contents(STDIN));
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-industrial/manifest.json'));
$api=new AuthHttpApi(db(),new FrozenClock(2400),120,[$world=>$pack],true);
[$status]=$api->handle('POST','/v1/auth/register','',json_encode([
 'login'=>'invited-race-'.$n,'password'=>'long concurrent invitation password',
 'world'=>$world,'inviteCode'=>$code
],JSON_THROW_ON_ERROR),'203.0.113.'.(150+$n));
echo $status;
