<?php
declare(strict_types=1);
use Aeternum\Core\{ApiRequestSecurity,ApiErrorPolicy};
(static function():void {
    $base=['REQUEST_URI'=>'/v1/auth/login','REQUEST_METHOD'=>'POST','CONTENT_TYPE'=>'application/json','CONTENT_LENGTH'=>'48'];
    check(ApiRequestSecurity::reject($base)===null,'F03 native JSON client accepted without Fetch Metadata');
    check(ApiRequestSecurity::reject([...$base,'HTTP_SEC_FETCH_SITE'=>'same-origin'])===null,'F03 same-origin browser admitted');
    foreach (['cross-site','same-site','invalid'] as $site)
        check(ApiRequestSecurity::reject([...$base,'HTTP_SEC_FETCH_SITE'=>$site])[0]===403,'F03 deny foreign browser origin');
    foreach (['text/plain','application/x-www-form-urlencoded','multipart/form-data','application/json; charset=latin1',''] as $type)
        check(ApiRequestSecurity::reject([...$base,'CONTENT_TYPE'=>$type])[0]===415,'F03 non-JSON credential request denied');
    foreach (['/v1/../auth/login','/v1/%2e%2e/auth/login','/v1//auth/login','/v1/auth/login/'] as $path)
        check(ApiRequestSecurity::reject([...$base,'REQUEST_URI'=>$path])[0]===404,'F03 reject ambiguous route encoding');
    check(ApiRequestSecurity::reject([...$base,'CONTENT_LENGTH'=>'4097'])[0]===413,'F03 oversized credential body rejected before SQL');
    check(ApiRequestSecurity::reject([...$base,'CONTENT_LENGTH'=>'-1'])[0]===400,'F03 malformed content length rejected');
    check(ApiRequestSecurity::reject([...$base,'REQUEST_METHOD'=>'OPTIONS'])[0]===405,'F03 no cross-origin preflight grant');
    check(ApiRequestSecurity::reject([...$base,'REQUEST_URI'=>'/v1/auth/logout','CONTENT_TYPE'=>'','CONTENT_LENGTH'=>'0'])===null,'F03 bodyless logout remains available');
    $secret=new RuntimeException('secret=do-not-log password=example');
    check(ApiErrorPolicy::response($secret)===[500,['error'=>'internal_error']]
        && !str_contains(ApiErrorPolicy::logRecord($secret),'secret'),'F03 exception responses and diagnostics redact raw messages');
    foreach ([1205,1213,2006,2013] as $code) {
        $e=new PDOException('secret DSN and SQL');$e->errorInfo=['HY000',$code,'secret'];
        check(ApiErrorPolicy::response($e)===[503,['error'=>'database_busy','retryable'=>true]]
            && !str_contains(ApiErrorPolicy::logRecord($e),'secret'),'F03 transient DB failure redacted with retry contract');
    }
})();
