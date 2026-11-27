<?php
declare(strict_types=1);
require_once __DIR__.'/../src/UniverseAudit.php';
if(PHP_SAPI!=='cli'||count($argv)!==2||!preg_match('/^[1-9][0-9]{0,8}$/D',$argv[1])){
    fwrite(STDERR,"Usage: php audit-universe.php UNIVERSE_NO\n");exit(2);
}
try{
    $dsn=getenv('WP_DB_DSN');
    if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Missing database DSN');
    $db=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    echo json_encode(\Aeternum\WorldPolitics\UniverseAudit::inspect($db,(int)$argv[1]),JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){fwrite(STDERR,"Universe audit failed (".get_class($e).")\n");exit(1);}
