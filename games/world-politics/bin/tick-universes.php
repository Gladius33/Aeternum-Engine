<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\UniverseScheduler;
require_once __DIR__.'/../src/UniverseScheduler.php';
if(PHP_SAPI!=='cli'||getenv('WP_UNIVERSE_TICKS')!=='YES'||$argc!==1){fwrite(STDERR,"Explicit WP_UNIVERSE_TICKS=YES required\n");exit(2);}
try{
    $dsn=getenv('WP_DB_DSN');
    if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Dedicated MariaDB required');
    $connect=fn()=>new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $out=(new UniverseScheduler($connect))->runDue();
    echo json_encode($out,JSON_THROW_ON_ERROR).PHP_EOL;
    foreach($out as $r)if($r['status']==='failed')exit(1);
}catch(Throwable $e){fwrite(STDERR,'Universe tick failed: '.get_class($e).PHP_EOL);exit(1);}
