<?php
declare(strict_types=1);
// M10 trusted LOCAL CONSOLE, not reachable through /public or HTTP.
// OS account + file/DB permissions are the authentication boundary.
// AETERNUM_ADMIN_OPERATOR is an attribution identifier, NOT a secret.
use Aeternum\Core\{PdoAdminConsole,SystemClock};
if(PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    require __DIR__.'/../vendor/autoload.php';
    $operator=getenv('AETERNUM_ADMIN_OPERATOR');
    if(!is_string($operator) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$operator))
        throw new RuntimeException('An authenticated host operator ID is required');
    $dsn=getenv('AETERNUM_DSN');
    if(!is_string($dsn) || $dsn==='')
        throw new RuntimeException('Admin database is not configured');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    if($argc<3)throw new InvalidArgumentException('Expected operation and world');
    $operation=$argv[1];$world=$argv[2];
    $service=new PdoAdminConsole($pdo,$world,new SystemClock());
    $result=null;
    if($operation==='world' && $argc===3) {
        $result=$service->describe($operator);
    } elseif($operation==='members' && in_array($argc,[3,4],true)) {
        $result=$service->members($operator,$argv[3]??null);
    } elseif($operation==='identity' && $argc===4) {
        $result=$service->identity($operator,$argv[3]);
    } elseif($operation==='audit' && in_array($argc,[3,4],true)) {
        $result=$service->audit($operator,$argv[3]??null);
    } elseif($operation==='membership' && $argc===6) {
        [$script,$cmd,$world,$actor,$transition,$request]=$argv;
        if(!in_array($transition,['grant','revoke'],true))
            throw new InvalidArgumentException('Invalid membership transition');
        $applied=$service->membership($operator,$request,$actor,$transition==='grant');
        $result=['world'=>$world,'request'=>$request,'action'=>'membership_'.$transition,
            'applied'=>$applied,'replayed'=>!$applied];
    } elseif($operation==='test-event' && $argc===7) {
        [$script,$cmd,$world,$actor,$feature,$transition,$event]=$argv;
        if(getenv('AETERNUM_PAYMENT_MODE')!=='test')
            throw new RuntimeException('Only explicit payment TEST mode is supported');
        $applied=$service->testPayment($operator,$event,$actor,$feature,$transition);
        $result=['world'=>$world,'event'=>$event,'mode'=>'test','feature'=>$feature,
            'transition'=>$transition,'applied'=>$applied,'replayed'=>!$applied];
    } else {
        throw new InvalidArgumentException('Usage: php bin/admin-console.php world WORLD | members WORLD [AFTER_ACTOR] | identity WORLD ACTOR | audit WORLD [AFTER_ID] | membership WORLD ACTOR grant|revoke REQUEST_ID | test-event WORLD ACTOR profile_badge|ui_theme grant|revoke EVENT_ID (requires AETERNUM_PAYMENT_MODE=test)');
    }
    echo json_encode(['data'=>$result],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch(Throwable $e) {
    // No user data, SQL statement, credentials or environment values logged.
    fwrite(STDERR,'M10 admin console denied: '.get_class($e).PHP_EOL);
    exit(1);
}
