<?php
declare(strict_types=1);
require_once __DIR__.'/../src/PlayerDataAccess.php';
if(PHP_SAPI!=='cli'||getenv('WP_BILLING_OPERATOR')!=='YES'||$argc!==3||!ctype_digit($argv[2]))exit(2);
try{
    $dsn=getenv('WP_DB_DSN');if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Dedicated game database required');
    $db=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    \Aeternum\WorldPolitics\PlayerDataAccess::setEntitlement($db,$argv[1],(int)$argv[2]);
    echo "{\"updated\":true}\n";
}catch(Throwable $e){fwrite(STDERR,"Premium operator failure\n");exit(1);}
