<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\AlphaGateway;
require_once __DIR__.'/../src/AlphaGateway.php';
ini_set('display_errors','0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'none\'');
header('Cache-Control: no-store');
$peer=$_SERVER['REMOTE_ADDR']??'unknown';
$local=PHP_SAPI==='cli-server' && getenv('WP_ALPHA_LOOPBACK')==='YES' && in_array($peer,['127.0.0.1','::1'],true)
    && in_array($_SERVER['SERVER_ADDR']??'',['127.0.0.1','::1'],true);
if (getenv('WP_ALPHA_ENABLED')!=='YES') {
    $result=AlphaGateway::problem(503,'alpha_disabled');
} elseif (!$local && !in_array($_SERVER['HTTPS']??'',['on','1'],true)) {
    $result=AlphaGateway::problem(403,'https_required');
} else {
    try {
        $connect=static function():PDO {
            $dsn=getenv('WP_DB_DSN');
            if (!is_string($dsn)||!str_starts_with($dsn,'mysql:')) throw new RuntimeException('MariaDB DSN required');
            return new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
            ]);
        };
        $invite=getenv('WP_ALPHA_INVITE')?:'';
        if (strlen($invite)<32) throw new RuntimeException('Private alpha invite missing');
        $h=[];
        foreach (['HTTP_AUTHORIZATION'=>'authorization','HTTP_IF_MATCH'=>'if-match','HTTP_IDEMPOTENCY_KEY'=>'idempotency-key','CONTENT_TYPE'=>'content-type'] as $server=>$name) {
            if (isset($_SERVER[$server])) $h[$name]=$_SERVER[$server];
        }
        $length=$_SERVER['CONTENT_LENGTH']??'0';
        if (!ctype_digit((string)$length) || (int)$length>32768) $result=AlphaGateway::problem(413,'body_too_large');
        else {
            $body=file_get_contents('php://input',false,null,0,32769);
            $result=(new AlphaGateway($connect,$invite))->handle($_SERVER['REQUEST_METHOD']??'',$_SERVER['REQUEST_URI']??'',
                $body===false?'':$body,$h,$peer);
        }
    } catch (Throwable $e) {$result=AlphaGateway::problem(503,'backend_unavailable');}
}
http_response_code($result['status']);
foreach ($result['headers'] as $name=>$value) header($name.': '.$value);
if ($result['status']===401) header('WWW-Authenticate: Bearer');
echo json_encode($result['body'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
