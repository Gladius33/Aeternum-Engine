<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\CareerScorePublication;
require_once __DIR__.'/../src/CareerScorePublication.php';
if(PHP_SAPI!=='cli'||getenv('WP_RELEASE_AUDIT')!=='YES'||$argc!==2){
    fwrite(STDERR,"Usage: WP_RELEASE_AUDIT=YES php bin/audit-career-score.php WORLD_ID\n");exit(2);
}
try{
    $dsn=getenv('WP_DB_DSN');
    if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('MariaDB DSN required');
    $db=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    echo json_encode(CareerScorePublication::inspect($db,$argv[1]),JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Career score audit failed: ".get_class($e).PHP_EOL);exit(1);}
