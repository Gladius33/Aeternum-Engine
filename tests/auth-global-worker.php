<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{AuthHttpApi,FrozenClock};
if($argc!==2||!preg_match('/^[0-3]$/D',$argv[1])) exit(1);
$n=(int)$argv[1];
$api=new AuthHttpApi(db(),new FrozenClock(720),2);
$body=json_encode(['login'=>'global-race-'.$n,'password'=>'deliberately wrong'],JSON_THROW_ON_ERROR);
echo $api->handle('POST','/v1/auth/login','',$body,'192.0.2.'.(20+$n))[0].PHP_EOL;
