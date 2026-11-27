<?php
declare(strict_types=1);
require_once __DIR__.'/../src/PdoCareerInstitutionStore.php';
use Aeternum\WorldPolitics\{PdoCareerInstitutionStore,CareerInstitutionEvidence};
if(PHP_SAPI!=='cli'||getenv('WP_CAREER_CLOCK')!=='YES'||$argc!==4){fwrite(STDERR,"Explicit WP_CAREER_CLOCK=YES and world/action/evidence-file required\n");exit(2);}
try{
    $dsn=getenv('WP_DB_DSN');$secret=getenv('WP_CAREER_EVIDENCE_KEY');
    if(!is_string($dsn)||!str_starts_with($dsn,'mysql:')||!is_string($secret)||strlen($secret)<32)throw new DomainException('Dedicated database and evidence key required');
    $raw=file_get_contents($argv[3],false,null,0,65537);if(!is_string($raw)||strlen($raw)>65536)throw new DomainException('Evidence file size');
    $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($p))throw new DomainException('Evidence object required');
    $db=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $store=new PdoCareerInstitutionStore($db,new CareerInstitutionEvidence($secret));
    echo json_encode($store->applyForeignEvidence($argv[1],$argv[2],$p,$secret),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Foreign evidence refused: '.get_class($e).PHP_EOL);exit(1);}
