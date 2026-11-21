<?php
declare(strict_types=1);
use Aeternum\Core\{HttpApi,AuthHttpApi,EconomyHttpApi,PlayerDiscoveryApi,GamePackLoader,SystemClock};
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    require __DIR__.'/../vendor/autoload.php';
    // Behind a trusted reverse proxy, configure HTTPS at the web server.
    if (!in_array($_SERVER['HTTPS'] ?? '',['on','1'],true)) {
        $status=403; $body=['error'=>'https_required'];
    } else {
        $dsn=getenv('AETERNUM_DSN');
        if (!$dsn) throw new RuntimeException('Missing database configuration');
        $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER') ?: '',getenv('AETERNUM_DB_PASSWORD') ?: '',[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES=>false
        ]);
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 3');
        $path=explode('?',$_SERVER['REQUEST_URI'] ?? '',2)[0];
        if (str_starts_with($path,'/v1/auth/')) {
            [$status,$body]=(new AuthHttpApi($pdo,new SystemClock()))->handle(
                $_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? '',
                file_get_contents('php://input',false,null,0,4097) ?: '',$_SERVER['REMOTE_ADDR'] ?? ''
            );
        } elseif ($path==='/v1/me/worlds' || preg_match('~^/v1/worlds/[^/]+/economy/~',$path)) {
            $rules=[]; $packs=[];
            foreach (['AETERNUM_MEDIEVAL_WORLD'=>'reference-medieval','AETERNUM_SPACE_WORLD'=>'reference-space'] as $env=>$pack) {
                $world=getenv($env);
                if ($world!==false && $world!=='') {
                    if (isset($rules[$world])) throw new RuntimeException('Duplicate configured world');
                    $packs[$world]=GamePackLoader::load(file_get_contents(__DIR__.'/../games/'.$pack.'/manifest.json'));
                    $rules[$world]=$packs[$world]->rules();
                }
            }
            if ($path==='/v1/me/worlds') {
                [$status,$body]=(new PlayerDiscoveryApi($pdo,new SystemClock(),$packs))->handle($_SERVER['REQUEST_METHOD'] ?? '',$_SERVER['HTTP_AUTHORIZATION'] ?? '');
            } else {
            [$status,$body]=(new EconomyHttpApi($pdo,new SystemClock(),$rules))->handle(
                $_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? '',
                file_get_contents('php://input',false,null,0,8193) ?: ''
            );
            }
        } else {
        [$status,$body]=(new HttpApi($pdo,new SystemClock()))->handle(
            $_SERVER['REQUEST_METHOD'] ?? '',
            explode('?',$_SERVER['REQUEST_URI'] ?? '',2)[0],
            $_SERVER['HTTP_AUTHORIZATION'] ?? ''
        );
        }
    }
    if ($status===401) header('WWW-Authenticate: Bearer');
    if ($status===405) header('Allow: '.((str_starts_with($path ?? '','/v1/auth/') || str_ends_with($path ?? '','/economy/commands')) ? 'POST' : 'GET'));
    http_response_code($status);
    echo json_encode($body,JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(500);
    echo '{"error":"internal_error"}';
}
