<?php
declare(strict_types=1);
// Runs only against named isolated CI databases; loads *extracted* PHP code.
if(PHP_SAPI!=='cli'||$argc!==3||getenv('AETERNUM_F06_DISPOSABLE')!=='1')exit(2);
$root=realpath($argv[1]);$private=realpath($argv[2]);
if($root===false||$private===false||!is_file($root.'/PACKAGE.SHA256')||is_dir($root.'/tests'))exit(2);
require $root.'/vendor/autoload.php';
use Aeternum\Core\{GamePackLoader,PackDevelopment,WorldModuleRegistry,WorldRuntime,PdoScheduler,PdoIdentity,PdoEconomyProjection,SystemClock,ScalarMap};
function requireTrue(bool $pass,string $check):void {
    if(!$pass)throw new RuntimeException('F06 failed: '.$check);
    echo 'PASS F06 '.$check.PHP_EOL;
}
$world='f06-guild-world';$password=bin2hex(random_bytes(24));$password2=bin2hex(random_bytes(24));
$env=getenv();
$dsn=$env['AETERNUM_DSN']??'';$restore=$env['AETERNUM_RESTORE_DSN']??'';
if(!preg_match('/;dbname=aeternum_f06(?:;|$)/D',$dsn)||!preg_match('/;dbname=aeternum_restore_f06(?:;|$)/D',$restore))exit(2);
$connect=static fn(string $s):PDO=>new PDO($s,$env['AETERNUM_DB_USER'],$env['AETERNUM_DB_PASSWORD'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false]);
$src=$connect($dsn);$dst=$connect($restore);
requireTrue($src->query('SELECT DATABASE()')->fetchColumn()==='aeternum_f06'&&$dst->query('SELECT DATABASE()')->fetchColumn()==='aeternum_restore_f06','isolated DB identities');
requireTrue($src->query('SHOW TABLES')->fetchColumn()===false&&$dst->query('SHOW TABLES')->fetchColumn()===false,'clean source and restore databases');
$pack=GamePackLoader::load(file_get_contents($root.'/games/reference-guild/versions/0.5.0/manifest.json'));
$entry=PackDevelopment::inspect($pack,['campaign-v1','guild-challenge-v1','bilateral-truce-v1']);
unset($entry['pack'],$entry['version']);
$registry=$private.'/worlds.json';$modules=$private.'/modules.json';
file_put_contents($registry,json_encode(['registry_api'=>'1','worlds'=>[['world'=>$world,'pack'=>$pack->id,'version'=>$pack->version,'sha256'=>$pack->digest]]],JSON_THROW_ON_ERROR));
file_put_contents($modules,json_encode(['module_api'=>'1','worlds'=>[['world'=>$world]+$entry]],JSON_THROW_ON_ERROR));
chmod($registry,0600);chmod($modules,0600);
$runtime=WorldModuleRegistry::loadFile($modules,[$world=>$pack]);$rules=$runtime->rule($world);
$env['AETERNUM_WORLD_REGISTRY']=$registry;$env['AETERNUM_MODULE_REGISTRY']=$modules;
$env['AETERNUM_PUBLIC_REGISTRATION']='0';$env['AETERNUM_VERIFY_QUIESCED']='YES';
$env['AETERNUM_F06_PACKAGE_ROOT']=$root;
foreach(['AETERNUM_MEDIEVAL_WORLD','AETERNUM_INDUSTRIAL_WORLD','AETERNUM_BROWSER_TEST','AETERNUM_LAB_ONLY',
    'AETERNUM_TEST_ACTIVATION_OUTBOX','AETERNUM_LAB_ALLOW_UNVERIFIED_SIGNUP','PHP_CLI_SERVER_WORKERS'] as $k)unset($env[$k]);
$exec=static function(array $args,array $e,string $stdin='',bool $fail=false)use($root):string {
    $proc=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,$e);
    if(!is_resource($proc))throw new RuntimeException('Cannot start package operation');
    fwrite($pipes[0],$stdin);fclose($pipes[0]);
    stream_set_timeout($pipes[1],30);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);
    if(($code!==0)!==$fail)throw new RuntimeException('Unexpected package operation exit: '.basename($args[1]??$args[0]).' / '.$code);
    return $out;
};
$cli=static fn(array $args,array $e,string $stdin='',bool $fail=false):string=>$exec([PHP_BINARY,...$args],$e,$stdin,$fail);
$json=static fn(string $s):array=>json_decode($s,true,32,JSON_THROW_ON_ERROR);
$m=$json($cli(['bin/migrate.php'],$env));
requireTrue(count($m['applied'])===count(glob($root.'/database/migrations/*.sql')),'fresh package migrations');
requireTrue($json($cli(['bin/migrate.php'],$env))['applied']===[],'repeated migration no-op');
$pre=$json($cli(['bin/m01-preflight.php','--layout',$root.'/public',$private],$env));
requireTrue($pre['configurationPassed'],'strict preflight accepts private configuration');
$bad=$json($cli(['bin/m01-preflight.php','--layout',$root,$private],$env,'',true));
requireTrue(!$bad['checks']['document_root_is_public'],'preflight rejects repository webroot');
$provision=$json($cli(['bin/provision.php',$pack->id,$world,'f06-alice'],$env,$password."\n"));
$other=$json($cli(['bin/provision.php',$pack->id,$world,'f06-bob'],$env,$password2."\n"));
$a=$provision['account'];$b=$other['account'];
requireTrue($a!==$b&&$json($cli(['bin/provision.php',$pack->id,$world,'f06-alice'],$env,$password."\n"))===$provision,'repeat provisioning preserves identity');
$start=static function(array $e)use($root,$private):array {
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
    if($socket===false)throw new RuntimeException('Cannot reserve port');
    $address=stream_socket_get_name($socket,false);fclose($socket);
    $p=proc_open([PHP_BINARY,'-S',$address,'-t',$root.'/public',__DIR__.'/f06-package-router.php'],
        [0=>['file','/dev/null','r'],1=>['file',$private.'/http.log','a'],2=>['file',$private.'/http.log','a']],$pipes,$root,$e);
    if(!is_resource($p))throw new RuntimeException('Cannot start loopback HTTP');
    $until=microtime(true)+5;
    do{
        $s=@stream_socket_client('tcp://'.$address,$errno,$errstr,.1);
        if($s!==false){fclose($s);return [$p,'http://'.$address];}
        usleep(50000);
    }while(microtime(true)<$until);
    proc_terminate($p);proc_close($p);throw new RuntimeException('Loopback HTTP startup timeout');
};
$stop=static function($p):void {proc_terminate($p);proc_close($p);};
$http=static function(string $base,string $path,?array $body=null,?string $token=null):array {
    $headers="Accept: application/json\r\nConnection: close\r\n";
    if($token!==null)$headers.="Authorization: Bearer ".$token."\r\n";
    if($body!==null)$headers.="Content-Type: application/json\r\n";
    $context=stream_context_create(['http'=>['method'=>$body===null?'GET':'POST','header'=>$headers,
        'content'=>$body===null?'':json_encode($body,JSON_THROW_ON_ERROR),'timeout'=>10,'ignore_errors'=>true]]);
    $raw=file_get_contents($base.$path,false,$context);
    if(!is_string($raw)||!preg_match('/^HTTP\/\S+ (\d{3})/', $http_response_header[0]??'',$m))throw new RuntimeException('HTTP response unavailable');
    return [(int)$m[1],$raw];
};
$login=static function(string $base,string $name,string $secret)use($http,$json):string{
    [$status,$body]=$http($base,'/v1/auth/login',['login'=>$name,'password'=>$secret]);
    requireTrue($status===200,'packaged HTTP login');
    return $json($body)['data']['token'];
};
[$server,$url]=$start($env);
try{
    requireTrue($http($url,'/play.html')[0]===200&&$http($url,'/client-dist/aeternum-shell.js')[0]===200,'packaged static player shell');
    requireTrue($http($url,'/PACKAGE.json')[0]===404&&$http($url,'/bin/migrate.php')[0]===404,'private deployment files not routed');
    $ta=$login($url,'f06-alice',$password);$tb=$login($url,'f06-bob',$password2);
    $api='/v1/worlds/'.$world.'/economy/';
    $send=static function(string $token,string $id,string $type,array $payload)use($url,$api,$http,$json):array{
        [$status,$raw]=$http($url,$api.'commands',['id'=>$id,'type'=>$type,'payload'=>$payload],$token);
        requireTrue($status===200,'packaged durable command '.$type);
        return $json($raw)['data'];
    };
    $send($ta,'place','building.place',['building'=>'f06-hall','account'=>$a,'territory'=>$a,'entity'=>$a,'type'=>'market_hall']);
    $send($ta,'build','building.start',['building'=>'f06-hall','account'=>$a,'type'=>'market_hall']);
    $drain=static function()use($cli,$env,$json,$src,$world):void{
        for($i=0;$i<20;$i++){
            $q=$src->prepare('SELECT COUNT(*) FROM engine_events WHERE world_id=? AND completed=0');$q->execute([$world]);
            if((int)$q->fetchColumn()===0)return;
            $cli(['bin/ops-cron.php'],$env);usleep(500000);
        }
        throw new RuntimeException('Packaged worker failed to drain due work');
    };
    $drain();
    $send($ta,'research','pack.campaign.research',['account'=>$a]);$drain();
    $send($tb,'sell','market.create',['offer'=>'f06-offer','seller'=>$b,'giveResource'=>'spice','giveUnits'=>1,'wantResource'=>'silver','wantUnits'=>1]);
    $payload=['offer'=>'f06-offer','seller'=>$b,'buyer'=>$a];
    $receipt=$send($ta,'buy','market.accept',$payload);
    requireTrue($receipt===$send($ta,'buy','market.accept',$payload),'packaged exact trade receipt');
    [$status,$raw]=$http($url,$api.'pack-view/campaign/'.$a,null,$ta);
    requireTrue($status===200&&$json($raw)['data']['research']===1,'module completion through packaged HTTP and cron');
    requireTrue($http($url,$api.'pack-view/campaign/'.$a,null,$tb)[0]===404,'packaged private player view');
    $q=$src->prepare('INSERT INTO engine_admin_scopes(world_id,operator_id,role,active,changed_at) VALUES (?,?,?,1,?)');
    $q->execute([$world,'f06-operator','manager',time()]);
    $adminEnv=$env+['AETERNUM_ADMIN_OPERATOR'=>'f06-operator','AETERNUM_PAYMENT_MODE'=>'test'];
    $adminEnv['AETERNUM_ADMIN_OPERATOR']='f06-operator';$adminEnv['AETERNUM_PAYMENT_MODE']='test';
    $args=['bin/admin-console.php','test-event',$world,$a,'profile_badge','grant','f06-grant'];
    $cli($args,array_replace($adminEnv,['AETERNUM_PAYMENT_MODE'=>'disabled']),'',true);
    requireTrue($json($cli($args,$adminEnv))['data']['applied']===true&&$json($cli($args,$adminEnv))['data']['replayed']===true,'simulated grant and exact replay');
    [$code,$body]=$http($url,'/v1/worlds/'.$world.'/premium/me',null,$ta);
    requireTrue($code===200&&$json($body)['data']['features']===['profile_badge']&&$json($body)['data']['testModeOnly'],'packaged test entitlement read');
    $rev=['bin/admin-console.php','test-event',$world,$a,'profile_badge','revoke','f06-revoke'];
    requireTrue($json($cli($rev,$adminEnv))['data']['applied']===true&&$json($cli($rev,$adminEnv))['data']['replayed']===true,'simulated revoke and exact replay');
    requireTrue($json($http($url,'/v1/worlds/'.$world.'/premium/me',null,$ta)[1])['data']['features']===[],'revoked cosmetic absent');
}finally{$stop($server);}
$before=$json($cli(['bin/ops-verify.php'],$env));
requireTrue($before['verified']&&$before['worlds'][0]['pendingEvents']===0,'quiesced source canonical replay');
requireTrue(str_contains($cli(['bin/ops-metrics.php'],$env),'aeternum_world_rules_verified{world="'.$world.'"} 1'),'private metrics match pinned rules');
$wrong=$env;unset($wrong['AETERNUM_MODULE_REGISTRY']);
requireTrue(!$json($cli(['bin/ops-status.php'],$wrong,'',true))['healthy'],'wrong effective module binding cannot report healthy');
$backup=$private.'/backups';mkdir($backup,0700);
$dump=$json($exec(['bash','tools/m11-backup.sh',$backup],array_replace($env,['M11_SOURCE_DATABASE'=>'aeternum_f06','M11_BACKUP_APPROVED'=>'YES'])));
$exec(['bash','tools/m11-restore-lab.sh',$backup.'/'.$dump['archive']],array_replace($env,['M11_RESTORE_DATABASE'=>'aeternum_restore_f06','M11_RESTORE_DISPOSABLE'=>'YES']));
$tables=$src->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$restored=$dst->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);sort($tables);sort($restored);
requireTrue($tables===$restored&&count($tables)>30,'restored full table inventory');
$fingerprint=static function(PDO $db,string $table):array{
    if(!preg_match('/^[a-z][a-z0-9_]*$/D',$table))throw new RuntimeException('Unexpected table');
    $rows=[];foreach($db->query('SELECT * FROM '.$table) as $r){if(count($rows)>=10000)throw new RuntimeException('Fixture too large');ksort($r);$rows[]=hash('sha256',json_encode($r,JSON_THROW_ON_ERROR));}
    sort($rows);return $rows;
};
$rowCount=0;
foreach($tables as $table){$x=$fingerprint($src,$table);$y=$fingerprint($dst,$table);if($x!==$y)throw new RuntimeException('Restored table differs: '.$table);$rowCount+=count($x);}
requireTrue($rowCount>0,'all restored row fingerprints identical');
$restEnv=array_replace($env,['AETERNUM_DSN'=>$restore]);
requireTrue($json($cli(['bin/migrate.php'],$restEnv))['applied']===[],'restored migration ledger converges');
$after=$json($cli(['bin/ops-verify.php'],$restEnv));requireTrue($after===$before,'restored journal, canonical digest and projections identical');
[$server,$url]=$start($restEnv);
try{
    $token=$login($url,'f06-alice',$password);
    $view=$json($http($url,'/v1/worlds/'.$world.'/economy/pack-view/campaign/'.$a,null,$token)[1])['data'];
    requireTrue($view['research']===1,'existing player progress survives restore and login');
    requireTrue($json($http($url,'/v1/worlds/'.$world.'/premium/me',null,$token)[1])['data']['features']===[],'test revocation survives restore');
}finally{$stop($server);}
$proof=['status'=>'passed','sourceHead'=>getenv('F06_SOURCE_SHA')?:'local','checkout'=>getenv('GITHUB_SHA')?:'local',
    'freshInstall'=>true,'strictPreflight'=>true,'tables'=>count($tables),'fingerprintedRows'=>$rowCount,
    'restoredPlayer'=>true,'restoredModule'=>true,'testPaymentReplay'=>true,'testRevocation'=>true,'privateMetrics'=>true,
    'verification'=>$after];
file_put_contents(getenv('F06_REPORT_PATH')?:'f06-distribution.json',json_encode($proof,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL);
echo 'F06_DISTRIBUTION_JSON '.json_encode($proof,JSON_THROW_ON_ERROR).PHP_EOL;
