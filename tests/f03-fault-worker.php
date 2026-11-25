<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{ApiErrorPolicy,EconomyHttpApi,SocialHttpApi,GamePackLoader,FrozenClock,WorldWorker};
if ($argc!==2 || getenv('AETERNUM_F03_DISPOSABLE')!=='1') exit(2);
$pdo=db();
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='aeternum_f03') exit(2);
$input=json_decode(stream_get_contents(STDIN),true,8,JSON_THROW_ON_ERROR);
$world='f03-world';
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-guild/versions/0.2.0/manifest.json'));
$rules=$pack->rules();$clock=new FrozenClock($input['at']);
echo json_encode(['connection'=>(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn()],JSON_THROW_ON_ERROR).PHP_EOL;
flush();
try {
    if ($argv[1]==='due') {
        $result=['processed'=>count((new WorldWorker($pdo,$clock))->run($world,$pack,10,1000,$rules))];
    } elseif ($argv[1]==='economy' || $argv[1]==='social') {
        $social=$argv[1]==='social';
        $api=$social?new SocialHttpApi($pdo,$clock,[$world=>$pack]):new EconomyHttpApi($pdo,$clock,[$world=>$rules]);
        [$status,$body]=$api->handle('POST','/v1/worlds/'.$world.($social?'/social/messages':'/economy/commands'),
            'Bearer '.$input['token'],json_encode($input['body'],JSON_THROW_ON_ERROR));
        $result=['status'=>$status,'digest'=>hash('sha256',json_encode($body,JSON_THROW_ON_ERROR))];
    } else exit(2);
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    // Never print secrets or connection-dependent error text.
    [$status,$body]=ApiErrorPolicy::response($e);
    echo json_encode(['status'=>$status,'error'=>$body['error']],JSON_THROW_ON_ERROR).PHP_EOL;
    exit(3);
}
