<?php
declare(strict_types=1);
use Aeternum\Core\{HttpApi,AuthHttpApi,EconomyHttpApi,PlayerDiscoveryApi,WorldMapHttpApi,PublicWorldMapHttpApi,WorldBuildingHttpApi,SocialHttpApi,PremiumHttpApi,ProgressionHttpApi,WorldRuntime,SystemClock,PeerAddressResolver,ApiRequestSecurity,ApiErrorPolicy};
// The JSON API has a strict, separately testable response policy.
// Load before Composer: plaintext rejection must also work without database setup.
require_once __DIR__.'/../packages/core/src/ApiResponseSecurity.php';
require_once __DIR__.'/../packages/core/src/ApiRequestSecurity.php';
require_once __DIR__.'/../packages/core/src/ApiErrorPolicy.php';
// Never permit PHP warning/stack output to corrupt JSON or expose secrets.
ini_set('display_errors','0');
set_error_handler(static function(int $severity): bool {
    if ((error_reporting() & $severity)===0) return true;
    // Route handled diagnostics through the same redacted exception policy.
    throw new ErrorException('PHP request diagnostic',0,$severity);
});
\Aeternum\Core\ApiResponseSecurity::emit(in_array($_SERVER['HTTPS'] ?? '', ['on','1'], true));
try {
    // Deny plaintext before loading Composer or connecting to a database.
    // HTTPS comes only from the trusted web server, never X-Forwarded-Proto.
    if (!in_array($_SERVER['HTTPS'] ?? '',['on','1'],true)) {
        $status=403; $body=['error'=>'https_required'];
    } elseif (($rejection=ApiRequestSecurity::reject($_SERVER))!==null) {
        $path=is_string($_SERVER['REQUEST_URI']??null)?explode('?',$_SERVER['REQUEST_URI'],2)[0]:'';
        [$status,$body]=$rejection;
    } else {
        require __DIR__.'/../vendor/autoload.php';
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
        $runtime=null;
        $worldRuntime=static function() use (&$runtime): WorldRuntime { return $runtime??=WorldRuntime::load(); };
        $path=explode('?',$_SERVER['REQUEST_URI'] ?? '',2)[0];
        if ($path==='/v1/public/worlds') {
            if ($_SERVER['REQUEST_METHOD']!=='GET') {
                $status=405; $body=['error'=>'method_not_allowed'];
            } elseif (!$publicSignupAllowed) {
                $status=404; $body=['error'=>'not_found'];
            } else {
                $worlds=[];
                foreach ($worldRuntime()->packs as $world=>$pack) {
                    $worlds[]=['world'=>$world,'pack'=>$pack->id,'name'=>$pack->name];
                }
                $status=200; $body=['data'=>['worlds'=>$worlds,'invitationRequired'=>getenv('AETERNUM_SIGNUP_REQUIRE_INVITE')==='1','activationRequired'=>getenv('AETERNUM_SIGNUP_REQUIRE_EMAIL_ACTIVATION')==='1']];
            }
        } elseif (str_starts_with($path,'/v1/public/worlds/') && str_contains($path,'/map/')) {
            $trustedProxies=getenv('AETERNUM_TRUSTED_PROXY_IPS');
            $peer=PeerAddressResolver::resolve($_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
                $trustedProxies===false?'':$trustedProxies);
            [$status,$body]=(new PublicWorldMapHttpApi($pdo,new SystemClock(),$worldRuntime()->packs))
                ->handle($_SERVER['REQUEST_METHOD'] ?? '',$path,$peer ?? '');
        } elseif (str_starts_with($path,'/v1/auth/')) {
            $globalLimit=getenv('AETERNUM_AUTH_GLOBAL_LIMIT');
            if ($globalLimit!==false && (!preg_match('/^[1-9][0-9]{0,5}$/D',$globalLimit) || (int)$globalLimit>100000)) throw new RuntimeException('Invalid auth verification configuration');
            $worldLimit=getenv('AETERNUM_SIGNUP_WORLD_HOURLY_LIMIT');
            if ($worldLimit!==false && (!preg_match('/^[1-9][0-9]{0,5}$/D',$worldLimit) || (int)$worldLimit>100000)) throw new RuntimeException('Invalid world signup configuration');
            $parallelSignup=getenv('AETERNUM_SIGNUP_PARALLEL_LIMIT');
            if ($parallelSignup!==false && (!preg_match('/^[1-9][0-9]?$/D',$parallelSignup) || (int)$parallelSignup>32)) throw new RuntimeException('Invalid signup parallel configuration');
            $trustedProxyIps=getenv('AETERNUM_TRUSTED_PROXY_IPS');
            $resolvedPeer=PeerAddressResolver::resolve($_SERVER['REMOTE_ADDR'] ?? '',$_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',$trustedProxyIps===false?'':$trustedProxyIps);
            $registration=$publicSignupAllowed?$worldRuntime():null;
            [$status,$body]=(new AuthHttpApi($pdo,new SystemClock(),$globalLimit===false?120:(int)$globalLimit,$registration?->packs??[],getenv('AETERNUM_SIGNUP_REQUIRE_INVITE')==='1',$worldLimit===false?30:(int)$worldLimit,$parallelSignup===false?8:(int)$parallelSignup,getenv('AETERNUM_SIGNUP_REQUIRE_EMAIL_ACTIVATION')==='1',approvedRules:$registration?->rules??[]))->handle(
                $_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? '',
                file_get_contents('php://input',false,null,0,4097) ?: '',$resolvedPeer ?? ''
            );
        } elseif (str_starts_with($path,'/v1/worlds/') && str_contains($path,'/progression/')) {
            [$status,$body]=(new ProgressionHttpApi($pdo,new SystemClock(),$worldRuntime()->packs,$worldRuntime()->rules))
                ->handle($_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? '');
        } elseif (str_starts_with($path,'/v1/worlds/') && str_contains($path,'/premium/')) {
            [$status,$body]=(new PremiumHttpApi($pdo,new SystemClock(),$worldRuntime()->packs))
                ->handle($_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? '');
        } elseif (str_starts_with($path,'/v1/worlds/') && str_contains($path,'/social/')) {
            [$status,$body]=(new SocialHttpApi($pdo,new SystemClock(),$worldRuntime()->packs))
                ->handle($_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? '',
                    file_get_contents('php://input',false,null,0,4097) ?: '');
        } elseif (str_starts_with($path,'/v1/worlds/') && (str_contains($path,'/buildings/') || str_contains($path,'/construction/') || str_contains($path,'/settlement/') || str_contains($path,'/industrial/'))) {
            [$status,$body]=(new WorldBuildingHttpApi($pdo,new SystemClock(),$worldRuntime()->packs,$worldRuntime()->rules))->handle(
                $_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? ''
            );
        } elseif (str_starts_with($path,'/v1/worlds/') && str_contains($path,'/map/')) {
            [$status,$body]=(new WorldMapHttpApi($pdo,new SystemClock(),$worldRuntime()->packs))->handle(
                $_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? ''
            );
        } elseif ($path==='/v1/me/worlds' || preg_match('~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/recipients$~D',$path,$recipientMatch) || preg_match('~^/v1/worlds/[^/]+/economy/~',$path)) {
            $packs=$worldRuntime()->packs;
            $rules=$worldRuntime()->rules;
            if ($path==='/v1/me/worlds' || !empty($recipientMatch)) {
                [$status,$body]=(new PlayerDiscoveryApi($pdo,new SystemClock(),$packs,$rules))->handle($_SERVER['REQUEST_METHOD'] ?? '',$_SERVER['HTTP_AUTHORIZATION'] ?? '',$recipientMatch[1] ?? null);
            } else {
            [$status,$body]=(new EconomyHttpApi($pdo,new SystemClock(),$rules))->handle(
                $_SERVER['REQUEST_METHOD'] ?? '',$path,$_SERVER['HTTP_AUTHORIZATION'] ?? '',
                file_get_contents('php://input',false,null,0,8193) ?: ''
            );
            }
        } else {
        [$status,$body]=(new HttpApi($pdo,new SystemClock(),$worldRuntime()->packs))->handle(
            $_SERVER['REQUEST_METHOD'] ?? '',
            explode('?',$_SERVER['REQUEST_URI'] ?? '',2)[0],
            $_SERVER['HTTP_AUTHORIZATION'] ?? ''
        );
        }
    }
    if ($status===503 && ($body['retryable']??false)===true) header('Retry-After: 1');
    if ($status===401) header('WWW-Authenticate: Bearer');
    if ($status===405) header('Allow: '.(str_contains($path ?? '','/social/') ? 'GET, POST' : ((str_starts_with($path ?? '','/v1/auth/') || str_ends_with($path ?? '','/economy/commands')) ? 'POST' : 'GET')));
    http_response_code($status);
    echo json_encode($body,JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    [$status,$body]=ApiErrorPolicy::response($e);
    error_log(ApiErrorPolicy::logRecord($e));
    if ($status===503) header('Retry-After: 1');
    http_response_code($status);
    echo json_encode($body,JSON_THROW_ON_ERROR);
}
