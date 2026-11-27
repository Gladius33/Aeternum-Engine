<?php
declare(strict_types=1);
require_once __DIR__.'/../research/connection.php';
require_once __DIR__.'/../src/GameDatasetSync.php';
if(PHP_SAPI!=='cli'||getenv('WP_DATASET_OPERATOR')!=='YES')exit(2);
try{
    $dsn=getenv('WP_DATASET_DB_DSN');if(!is_string($dsn)||!str_starts_with($dsn,'mysql:')||$dsn===getenv('WP_RESEARCH_DB_DSN'))throw new RuntimeException('Independent source required');
    $source=new PDO($dsn,getenv('WP_DATASET_DB_USER')?:'',getenv('WP_DATASET_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db=\Aeternum\WorldPolitics\researchDatabase();
    $stream=getenv('WP_DATASET_SOURCE_ID')?:'';
    $pages=getenv('WP_DATASET_SYNC_MAX_PAGES')?:'100';
    if(!ctype_digit($pages))throw new RuntimeException('Invalid page budget');
    $r=\Aeternum\WorldPolitics\GameDatasetSync::run($db,new \Aeternum\WorldPolitics\ResearchQueue($db),$stream,(int)$pages,
        fn(int $after)=>\Aeternum\WorldPolitics\GameDataset::page($source,$after));
    echo json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
    if($r['status']==='backlog_remaining')exit(75);
}catch(Throwable $e){fwrite(STDERR,"Dataset synchronization failed (".get_class($e).")\n");exit(1);}
