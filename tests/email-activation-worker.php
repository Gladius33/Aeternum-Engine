<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{AuthHttpApi,FrozenClock,GamePackLoader};
if($argc!==2 || !preg_match('/^[0-3]$/D',$argv[1]))exit(2);
$code=trim(stream_get_contents(STDIN));
if(!preg_match('/^[a-f0-9]{64}$/D',$code))exit(2);
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$api=new AuthHttpApi(db(),new FrozenClock(48000),120,['email-activation-concurrent'=>$pack],false,30,8,true);
[$status]=$api->handle('POST','/v1/auth/activate','',json_encode([
    'login'=>'concurrent-email-confirmation','password'=>'parallel email proof and activation','code'=>$code
],JSON_THROW_ON_ERROR),'192.0.2.'.(180+(int)$argv[1]));
if(!in_array($status,[200,401,503],true)) {
    fwrite(STDERR,'Unexpected activation response '.$status.PHP_EOL);exit(1);
}
echo $status.PHP_EOL;
