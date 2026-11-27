<?php
declare(strict_types=1);
require_once __DIR__.'/../src/PdoCareerInstitutionStore.php';
use Aeternum\WorldPolitics\{CareerInstitutionEvidence,PdoCareerInstitutionStore};
if(PHP_SAPI!=='cli'||getenv('WP_CAREER_BALLOT_IMPORT')!=='YES'||$argc!==4){fwrite(STDERR,"Explicit WP_CAREER_BALLOT_IMPORT=YES and world/command/fictional-ballot-file required\n");exit(2);}
try{
    $dsn=getenv('WP_DB_DSN');$secret=getenv('WP_CAREER_EVIDENCE_KEY');
    if(!is_string($dsn)||!str_starts_with($dsn,'mysql:')||!is_string($secret)||strlen($secret)<32||!is_file($argv[3])||filesize($argv[3])>65536)throw new RuntimeException('Invalid dedicated DB, key or ballot file');
    $db=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $p=json_decode(file_get_contents($argv[3]),true,32,JSON_THROW_ON_ERROR);
    echo json_encode((new PdoCareerInstitutionStore($db,new CareerInstitutionEvidence($secret)))->certifyBallot($argv[1],$p,$argv[2],$secret),JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Career ballot rejected: '.get_class($e).PHP_EOL);exit(1);}
