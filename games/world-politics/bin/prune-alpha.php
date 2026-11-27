<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\AlphaMaintenance;
require_once __DIR__.'/../src/AlphaMaintenance.php';
if(PHP_SAPI!=='cli'||getenv('WP_ALPHA_MAINTENANCE')!=='YES'||$argc!==1){
    fwrite(STDERR,"Explicit WP_ALPHA_MAINTENANCE=YES required\n");exit(2);
}
try{
    $dsn=getenv('WP_DB_DSN');
    if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Dedicated MariaDB DSN required');
    $db=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    echo json_encode(AlphaMaintenance::prune($db,time()),JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Maintenance failed: ".get_class($e).PHP_EOL);exit(1);}
