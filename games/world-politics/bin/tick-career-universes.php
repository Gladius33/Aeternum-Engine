<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\CareerUniverseScheduler;
require_once __DIR__.'/../src/CareerUniverseScheduler.php';
if(PHP_SAPI!=='cli'||getenv('WP_CAREER_UNIVERSE_TICKS')!=='YES'||$argc!==1){fwrite(STDERR,"Explicit WP_CAREER_UNIVERSE_TICKS=YES required\n");exit(2);}
try{
    $dsn=getenv('WP_DB_DSN');$secret=getenv('WP_CAREER_EVIDENCE_KEY');
    if(!is_string($dsn)||!str_starts_with($dsn,'mysql:')||!is_string($secret)||strlen($secret)<32)throw new RuntimeException('Dedicated MariaDB and career evidence key required');
    $connect=fn()=>new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $out=(new CareerUniverseScheduler($connect))->runDue();
    echo json_encode($out,JSON_THROW_ON_ERROR).PHP_EOL;
    foreach($out as $r)if($r['status']==='failed')exit(1);
}catch(Throwable $e){fwrite(STDERR,'Career universe tick failed: '.get_class($e).PHP_EOL);exit(1);}
