<?php
declare(strict_types=1);
// Test-only loopback adapter. Never packaged and never a production TLS proxy.
if(PHP_SAPI!=='cli-server'||getenv('AETERNUM_F06_DISPOSABLE')!=='1'||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){
    http_response_code(403);return true;
}
$root=realpath(getenv('AETERNUM_F06_PACKAGE_ROOT')?:'');
if($root===false||realpath($_SERVER['DOCUMENT_ROOT'])!==$root.'/public'||!is_file($root.'/PACKAGE.SHA256')){
    http_response_code(500);return true;
}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(in_array($path,['/play.html','/client-dist/aeternum-shell.js'],true))return false;
if(is_string($path)&&str_starts_with($path,'/v1/')){
    $_SERVER['HTTPS']='on';require $root.'/public/index.php';return true;
}
http_response_code(404);return true;
