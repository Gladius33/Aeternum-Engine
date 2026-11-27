<?php
declare(strict_types=1);
require_once __DIR__.'/../src/GameDataset.php';
if(PHP_SAPI!=='cli'||getenv('WP_DATASET_OPERATOR')!=='YES'||$argc!==2||!ctype_digit($argv[1]))exit(2);
try{
    $dsn=getenv('WP_DATASET_DB_DSN');if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Read-only export DSN required');
    $db=new PDO($dsn,getenv('WP_DATASET_DB_USER')?:'',getenv('WP_DATASET_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    echo json_encode(\Aeternum\WorldPolitics\GameDataset::page($db,(int)$argv[1]),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Dataset export failed\n");exit(1);}
