<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{AuthHttpApi,FrozenClock};
if ($argc!==3) exit(2);
$code=trim(stream_get_contents(STDIN));
$api=new AuthHttpApi(db(),new FrozenClock(1200));
[$status]=$api->handle('POST','/v1/auth/recover','',json_encode(['login'=>$argv[1],'code'=>$code,'newPassword'=>'concurrent recovery test password'],JSON_THROW_ON_ERROR),'203.0.113.'.(50+(int)$argv[2]));
echo $status;
