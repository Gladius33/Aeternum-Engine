<?php
declare(strict_types=1);
use Aeternum\Core\{PdoEmailActivation,SystemClock};
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
try{
    if($argc!==2||!preg_match('/^[1-9][0-9]{0,2}$/D',$argv[1])||(int)$argv[1]>500)
        throw new InvalidArgumentException('Invalid activation cleanup batch (1..500)');
    require __DIR__.'/../vendor/autoload.php';
    $dsn=getenv('AETERNUM_DSN');
    if(!$dsn)throw new RuntimeException('Missing database configuration');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 3');
    $deleted=(new PdoEmailActivation($pdo,new SystemClock()))->purgeExpired((int)$argv[1]);
    echo json_encode(['expired_unverified_signups_deleted'=>$deleted],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable){
    fwrite(STDERR,"Activation cleanup failed: check schema, configuration and limit.\n");
    exit(1);
}
