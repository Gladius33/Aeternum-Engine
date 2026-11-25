<?php
declare(strict_types=1);
// Read-only, secret-free on-host preflight. A PASS is NOT a deployment approval.
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
$checks=[];
$record=static function(string $name,bool $passed)use(&$checks):void{$checks[$name]=$passed;};
$record('php84',PHP_VERSION_ID>=80400);
$record('pdo_mysql',extension_loaded('pdo_mysql'));
$record('argon2id',defined('PASSWORD_ARGON2ID')&&in_array(PASSWORD_ARGON2ID,password_algos(),true));
$public=getenv('AETERNUM_PUBLIC_REGISTRATION')==='1';
$invited=getenv('AETERNUM_SIGNUP_REQUIRE_INVITE')==='1';
$email=getenv('AETERNUM_SIGNUP_REQUIRE_EMAIL_ACTIVATION')==='1';
$record('public_signup_has_identity_proof',!$public||$invited||$email);
$record('no_unverified_browser_bypass_on_host',getenv('AETERNUM_LAB_ALLOW_UNVERIFIED_SIGNUP')!=='1');
// A test-only mailbox sink must never be enabled on a host.
$record('no_lab_runtime',getenv('AETERNUM_LAB_ONLY')!=='1');
$record('no_test_mail_sink',getenv('AETERNUM_BROWSER_TEST')!=='1'
    && (getenv('AETERNUM_TEST_ACTIVATION_OUTBOX')===false||getenv('AETERNUM_TEST_ACTIVATION_OUTBOX')===''));
$mailFrom=getenv('AETERNUM_ACTIVATION_MAIL_FROM');
$mailUrl=getenv('AETERNUM_ACTIVATION_URL');
$record('activation_mail_configured',!$email||(
    is_string($mailFrom)&&filter_var($mailFrom,FILTER_VALIDATE_EMAIL)!==false
    && is_string($mailUrl)&&preg_match('~^https://[a-zA-Z0-9.-]+(?::[0-9]{2,5})?/[a-zA-Z0-9/_-]+\.html$~D',$mailUrl)===1
    && function_exists('mail')));
$limits=['AETERNUM_SIGNUP_PARALLEL_LIMIT'=>32,
    'AETERNUM_SIGNUP_WORLD_HOURLY_LIMIT'=>100000,
    'AETERNUM_AUTH_GLOBAL_LIMIT'=>100000];
foreach($limits as $name=>$max) {
    $v=getenv($name);
    $record('valid_'.strtolower($name),$v===false||(
        preg_match('/^[1-9][0-9]{0,5}$/D',$v)===1&&(int)$v<=$max));
}
$parallel=getenv('AETERNUM_SIGNUP_PARALLEL_LIMIT');
$parallel=$parallel===false?8:(int)$parallel;
$record('parallel_hash_limit',1<=$parallel&&$parallel<=32);
try {
    require __DIR__.'/../vendor/autoload.php';
    $runtime=\Aeternum\Core\WorldRuntime::load();
    $record('configured_approved_worlds',count($runtime->packs)>0&&count($runtime->packs)<=16);
} catch(Throwable) { $record('configured_approved_worlds',false); }
try {
    $trusted=getenv('AETERNUM_TRUSTED_PROXY_IPS');
    $trusted=$trusted===false?'':$trusted;
    $record('trusted_proxy_syntax',
        \Aeternum\Core\PeerAddressResolver::resolve('127.0.0.1','',$trusted)==='127.0.0.1');
} catch(Throwable) { $record('trusted_proxy_syntax',false); }
try {
    $dsn=getenv('AETERNUM_DSN');
    if(!$dsn)throw new RuntimeException('Missing DSN');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    $record('mariadb_engine',str_contains(strtolower((string)$pdo->query('SELECT VERSION()')->fetchColumn()),'mariadb'));
    $root=realpath(__DIR__.'/../database/migrations');
    if(!$root)throw new RuntimeException('Missing migration root');
    $files=glob($root.'/*.sql')?:[];sort($files,SORT_STRING);
    $rows=$pdo->query('SELECT filename,digest,completed FROM engine_schema_migrations ORDER BY filename')->fetchAll(PDO::FETCH_ASSOC);
    $consistent=count($rows)===count($files)&&count($files)>=19;
    if($consistent)foreach($files as $i=>$path) {
        if($rows[$i]['filename']!==basename($path)||(int)$rows[$i]['completed']!==1
            || !hash_equals($rows[$i]['digest'],hash_file('sha256',$path))){
            $consistent=false;break;
        }
    }
    $record('completed_unchanged_schema_migrations',$consistent);
    $bound=isset($runtime);
    if($bound)foreach($runtime->rules as $world=>$rules){
        $q=$pdo->prepare('SELECT rules_key FROM engine_worlds WHERE world_id=?');$q->execute([$world]);$key=$q->fetchColumn();
        if($key!==false&&!hash_equals($rules->key,(string)$key)){$bound=false;break;}
    }
    $record('existing_world_rules_match',$bound);
} catch(Throwable) {
    $record('existing_world_rules_match',false);
    $record('mariadb_engine',false);
    $record('completed_unchanged_schema_migrations',false);
}
// Optional strict layout checks: the operator supplies the actual webroot
// and private runtime directory. CLI cannot discover a vhost's active root.
if($argc!==1){
    $valid=$argc===4&&$argv[1]==='--layout';
    $publicRoot=$valid?realpath($argv[2]):false;
    $privateRoot=$valid?realpath($argv[3]):false;
    $install=realpath(__DIR__.'/..');
    $record('document_root_is_public',$publicRoot!==false&&$publicRoot===$install.'/public');
    $private=$privateRoot!==false&&is_dir($privateRoot)&&$privateRoot!==$install
        &&!str_starts_with($privateRoot.'/', $install.'/')
        &&!str_starts_with($install.'/', $privateRoot.'/')
        &&(fileperms($privateRoot)&0007)===0;
    $record('private_runtime_directory',$private);
    $safe=$private;
    foreach(['AETERNUM_WORLD_REGISTRY','AETERNUM_MODULE_REGISTRY'] as $name){
        $path=getenv($name);
        if($path===false&&$name==='AETERNUM_MODULE_REGISTRY')continue;
        $real=is_string($path)&&str_starts_with($path,'/')&&!is_link($path)?realpath($path):false;
        $safe=$safe&&$real!==false&&is_file($real)&&str_starts_with($real,$privateRoot.'/')
            &&(fileperms($real)&0027)===0;
    }
    $record('private_read_only_registries',$safe);
    $record('client_bundle_present',is_file($install.'/public/client-dist/aeternum-shell.js'));
}
$pass=!in_array(false,$checks,true);
echo json_encode([
    'checks'=>$checks,'configurationPassed'=>$pass,
    'registrationPolicy'=>$public?($email?'verified-email':($invited?'invitation-only':'unsafe-unverified')):'disabled',
    'approximateMaxArgon2idMiB'=>$parallel*64,
    'manualHostAcceptanceRequired'=>[
        'real TLS ingress and trusted proxy','real mailbox delivery when enabled',
        'measured PHP worker memory/latency and anti-abuse','off-instance backup and verified restore',
        'operator-approved test-player journey and signoff'
    ]
],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
exit($pass?0:1);
