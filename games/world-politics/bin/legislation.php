<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\{LocalLegislationClerk,PdoInstitutionalStore,LocalLegislativePublisher};
require_once __DIR__.'/../src/LocalLegislationClerk.php';
require_once __DIR__.'/../src/LocalLegislativePublisher.php';
if(PHP_SAPI!=='cli'||getenv('WP_LEGISLATION_CLERK')!=='YES'||!in_array($argc,[3,4],true)){
    fwrite(STDERR,"Usage: WP_LEGISLATION_CLERK=YES php bin/legislation.php certify evidence.json OR apply world-id bill-id\n");exit(2);
}
$dsn=getenv('WP_DB_DSN');if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Dedicated DB required');
$db=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if($argv[1]==='certify'&&$argc===3){
    $path=realpath($argv[2]);if($path===false||!is_file($path)||filesize($path)>8192)throw new RuntimeException('Invalid evidence');
    $e=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
    $s=new PdoInstitutionalStore($db,new LocalLegislationClerk($e));$read=$s->read($e['world_id'],'FR');
    $r=$s->decide($e['world_id'],'FR','legislation_evidence',['evidence_id'=>$e['evidence_id']],'legislation-'.hash('sha256',$e['evidence_id']),$read['etag']);
}elseif($argv[1]==='apply'&&$argc===4)$r=LocalLegislativePublisher::apply($db,$argv[2],$argv[3]);
else throw new RuntimeException('Invalid command');
echo json_encode($r,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
