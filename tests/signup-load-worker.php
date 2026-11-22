<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{AuthHttpApi,GamePackLoader,SystemClock};
if ($argc!==2 || !preg_match('/^[0-9]{1,2}$/D',$argv[1])) exit(2);
$i=(int)$argv[1];
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$api=new AuthHttpApi(db(),new SystemClock(),120,['m01-ci-load-world'=>$pack],false,8,2);
$start=hrtime(true);
[$status,$body]=$api->handle('POST','/v1/auth/register','',json_encode([
    'login'=>'m01-ci-load-'.$i,
    'password'=>'real worker load test password',
    'world'=>'m01-ci-load-world'
],JSON_THROW_ON_ERROR),'198.51.100.'.(140+$i));
$elapsed=(hrtime(true)-$start)/1000000;
if (!in_array($status,[201,429,503],true)) {
    fwrite(STDERR,'unexpected HTTP '. $status . PHP_EOL);
    exit(1);
}
if ($status===503 && !in_array($body['error']??'',['registration_busy','registration_retry'],true)) {
    fwrite(STDERR,'unexpected transient failure'.PHP_EOL);
    exit(1);
}
if ($status===201 && (!isset($body['data']['account']) || !preg_match('/^[a-f0-9]{64}$/D',$body['data']['account']))) {
    fwrite(STDERR,'success without actor'.PHP_EOL);
    exit(1);
}
echo json_encode(['status'=>$status,'latencyMs'=>round($elapsed,3),
    // This is PHP process RSS high-water, not a host-wide memory measurement.
    'rssKiB'=>is_readable('/proc/self/status') && preg_match('/^VmHWM:\s+(\d+) kB/m',file_get_contents('/proc/self/status'),$m)?(int)$m[1]:null
],JSON_THROW_ON_ERROR).PHP_EOL;
