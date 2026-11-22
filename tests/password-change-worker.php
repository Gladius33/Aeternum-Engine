<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{AuthHttpApi,FrozenClock};
$n=(int)($argv[1]??-1);
if($n<0 || $n>3) throw new InvalidArgumentException('Invalid password change worker index');
$input=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);
if(!is_array($input) || !is_string($input['token']??null)
    || !is_string($input['currentPassword']??null) || !is_string($input['newPassword']??null)) {
    throw new InvalidArgumentException('Invalid password change worker input');
}
$api=new AuthHttpApi(db(),new FrozenClock(30200));
[$status,$body]=$api->handle('POST','/v1/auth/password/change','Bearer '.$input['token'],
    json_encode(['currentPassword'=>$input['currentPassword'],'newPassword'=>$input['newPassword']],JSON_THROW_ON_ERROR),
    '192.0.2.'.(220+$n));
echo json_encode(['status'=>$status,'recoveryCode'=>$body['data']['recoveryCode']??null],JSON_THROW_ON_ERROR);
