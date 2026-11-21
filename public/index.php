<?php
declare(strict_types=1);
use Aeternum\Core\{HttpApi,SystemClock};
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
        [$status,$body]=(new HttpApi($pdo,new SystemClock()))->handle(
            $_SERVER['REQUEST_METHOD'] ?? '',
            explode('?',$_SERVER['REQUEST_URI'] ?? '',2)[0],
            $_SERVER['HTTP_AUTHORIZATION'] ?? ''
        );
    }
    if ($status===401) header('WWW-Authenticate: Bearer');
    if ($status===405) header('Allow: GET');
    http_response_code($status);
    echo json_encode($body,JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(500);
    echo '{"error":"internal_error"}';
}
