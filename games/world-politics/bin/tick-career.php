<?php
declare(strict_types=1);
require_once __DIR__.'/../src/CareerEconomicWorld.php';
use Aeternum\WorldPolitics\CareerEconomicWorld;
if(PHP_SAPI!=='cli'||getenv('WP_CAREER_CLOCK')!=='YES'||$argc!==3){fwrite(STDERR,"Explicit WP_CAREER_CLOCK=YES and world/day required\n");exit(2);}
try{
    $dsn=getenv('WP_DB_DSN');if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Dedicated MariaDB DSN required');
    $connect=fn()=>new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db=$connect();$q=$db->prepare('SELECT reference_bundle_sha,scenario FROM wp_alpha_worlds WHERE world_id=?');$q->execute([$argv[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if($r===false||$r['scenario']!=='wp-career-v1')throw new DomainException('Career world required');
    if($r['reference_bundle_sha']===null){
        require_once __DIR__.'/../src/PdoCareerInstitutionStore.php';
        $secret=getenv('WP_CAREER_EVIDENCE_KEY');if(!is_string($secret)||strlen($secret)<32)throw new DomainException('Career evidence key required');
        $store=new \Aeternum\WorldPolitics\PdoCareerInstitutionStore($db,new \Aeternum\WorldPolitics\CareerInstitutionEvidence($secret));
        $result=$store->tickForeignInstitution($argv[1],$argv[2]);
    }else $result=CareerEconomicWorld::tick($connect,$argv[1],$argv[2]);
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Career clock refused: '.get_class($e).PHP_EOL);exit(1);}
