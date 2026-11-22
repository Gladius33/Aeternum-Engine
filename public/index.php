<?php
declare(strict_types=1);
use Aeternum\Core\{HttpApi,AuthHttpApi,EconomyHttpApi,PlayerDiscoveryApi,WorldMapHttpApi,WorldBuildingHttpApi,ConfiguredWorlds,SystemClock,PeerAddressResolver};
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
// Authentication and activation responses must not leak via referrers or framing.
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Content-Security-Policy: frame-ancestors \'none\'');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// Only advertise HSTS when TLS is actually active at this entry point.
// Reverse proxies must terminate HTTPS and supply the HTTPS server variable
// through trusted web-server configuration, never an arbitrary client header.
if (in_array($_SERVER['HTTPS'] ?? '', ['on', '1'], true)) {
    header('Strict-Transport-Security: max-age=31536000');
}
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
        // Production public signup requires a trusted invitation or mailbox proof.
        // The legacy unverified path exists ONLY for the isolated browser fixture.
        $publicEnabled=getenv('AETERNUM_PUBLIC_REGISTRATION')==='1';
        $identityProofEnabled=getenv('AETERNUM_SIGNUP_REQUIRE_INVITE')==='1'
            || getenv('AETERNUM_SIGNUP_REQUIRE_EMAIL_ACTIVATION')==='1';
        $unverifiedLab=getenv('AETERNUM_BROWSER_TEST')==='1'
            && getenv('AETERNUM_LAB_ALLOW_UNVERIFIED_SIGNUP')==='1'
            && PHP_SAPI==='cli-server'
            && ($_SERVER['REMOTE_ADDR']??'')==='127.0.0.1';
        $publicSignupAllowed=$publicEnabled && ($identityProofEnabled || $unverifiedLab);
        $path=explode('?',$_SERVER['REQUEST_URI'] ?? '',2)[0];
        if ($path==='/v1/public/worlds') {
            if ($_SERVER['REQUEST_METHOD']!=='GET') {
                $status=405; $body=['error'=>'method_not_allowed'];
            } elseif (!$publicSignupAllowed) {
                $status=404; $body=['error'=>'not_found'];
            } else {
                $worlds=[];
                foreach (ConfiguredWorlds::load() as $world=>$pack) {
                    $worlds[]=['world'=>$world,'pack'=>$pack->id,'name'=>$pack->name];
                }
                $status=200; $body=['data'=>['worlds'=>$worlds,'invitationRequired'=>getenv('AETERNUM_SIGNUP_REQUIRE_INVITE')==='1','activationRequired'=>getenv('AETERNUM_SIGNUP_REQUIRE_EMAIL_ACTIVATION')==='1']];
            }
        } elseif (str_starts_with($path,'/v1/auth/')) {
            $globalLimit=getenv('AETERNUM_AUTH_GLOBAL_LIMIT');
            if ($globalLimit!==false && (!preg_match('/^[1-9][0-9]{0,5}$/D',$globalLimit) || (int)$globalLimit>100000)) throw new RuntimeException('Invalid auth verification configuration');
            $worldLimit=getenv('AETERNUM_SIGNUP_WORLD_HOURLY_LIMIT');
            if ($worldLimit!==false && (!preg_match('/^[1-9][0-9]{0,5}$/D',$worldLimit) || (int)$worldLimit>100000)) throw new RuntimeException('Invalid world signup configuration');
            $parallelSignup=getenv('AETERNUM_SIGNUP_PARALLEL_LIMIT');
            if ($parallelSignup!==false && (!preg_match('/^[1-9][0-9]?$/D',$parallelSignup) || (int)$parallelSignup>32)) throw new RuntimeException('Invalid signup parallel configuration');
            $trustedProxyIps=getenv('AETERNUM_TRUSTED_PROXY_IPS');
            $resolvedPeer=PeerAddressResolver::resolve($_SERVER['REMOTE_ADDR'] ?? '',$_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',$trustedProxyIps===false?'':$trustedProxyIps);
            [$status,$body]=(new AuthHttpApi($pdo,new SystemClock(),$globalLimit===false?120:(int)$globalLimit,$publicSignupAllowed?ConfiguredWorlds::load():[],getenv('AETERNUM_SIGNUP_REQUIRE_INVITE')==='1',$worldLimit===false?30:(int)$worldLimit,$parallelSignup===false?8:(int)$parallelSignup,getenv('AETERNUM_SIGNUP_REQUIRE_EMAIL_ACTIVATION')==='1'))->handle(
                $_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? '',
                file_get_contents('php://input',false,null,0,4097) ?: '',$resolvedPeer ?? ''
            );
        } elseif (str_starts_with($path,'/v1/worlds/') && str_contains($path,'/buildings/')) {
            [$status,$body]=(new WorldBuildingHttpApi($pdo,new SystemClock(),ConfiguredWorlds::load()))->handle(
                $_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? ''
            );
        } elseif (str_starts_with($path,'/v1/worlds/') && str_contains($path,'/map/')) {
            [$status,$body]=(new WorldMapHttpApi($pdo,new SystemClock(),ConfiguredWorlds::load()))->handle(
                $_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? ''
            );
        } elseif ($path==='/v1/me/worlds' || preg_match('~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/recipients$~D',$path,$recipientMatch) || preg_match('~^/v1/worlds/[^/]+/economy/~',$path)) {
            $packs=ConfiguredWorlds::load();
            $rules=[];
            foreach ($packs as $world=>$pack) $rules[$world]=$pack->rules();
            if ($path==='/v1/me/worlds' || !empty($recipientMatch)) {
                [$status,$body]=(new PlayerDiscoveryApi($pdo,new SystemClock(),$packs))->handle($_SERVER['REQUEST_METHOD'] ?? '',$_SERVER['HTTP_AUTHORIZATION'] ?? '',$recipientMatch[1] ?? null);
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
