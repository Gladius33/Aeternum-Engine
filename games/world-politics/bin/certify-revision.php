<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\{LocalRevisionClerk,PdoInstitutionalStore};
require_once __DIR__.'/../src/LocalRevisionClerk.php';
if(PHP_SAPI!=='cli'||getenv('WP_REVISION_CLERK')!=='YES'||$argc!==2){
    fwrite(STDERR,"Usage: WP_REVISION_CLERK=YES php bin/certify-revision.php /path/to/evidence.json\n");exit(2);
}
$path=realpath($argv[1]);
if($path===false||!is_file($path)||filesize($path)>8192)throw new RuntimeException('Invalid evidence file');
$e=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
if(!is_array($e)||!is_string($e['world_id']??null)||!is_string($e['evidence_id']??null))throw new RuntimeException('Invalid evidence identity');
$dsn=getenv('WP_DB_DSN');if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Dedicated database required');
$db=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$s=new PdoInstitutionalStore($db,new LocalRevisionClerk($e));
$state=$s->read($e['world_id'],'FR');
$key='revision-'.hash('sha256',$e['evidence_id']);
$r=$s->decide($e['world_id'],'FR','revision_evidence',['evidence_id'=>$e['evidence_id']],$key,$state['etag']);
echo json_encode($r,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
