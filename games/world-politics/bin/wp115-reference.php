<?php
declare(strict_types=1);
require_once __DIR__.'/../src/InternationalReferenceCatalog.php';
use Aeternum\WorldPolitics\InternationalReferenceCatalog;
try{
    if($argc>4)throw new InvalidArgumentException('Usage: php wp115-reference.php [ISO2 [metric|--coverage|--opening [snapshot_sha]]]');
    $catalog=new InternationalReferenceCatalog(null,$argv[3]??null);
        $out=$argc===1?$catalog->summary():($argc===2?$catalog->country($argv[1]):match($argv[2]){
        '--coverage'=>$catalog->coverage($argv[1]),
        '--opening'=>$catalog->openingReference($argv[1]),
        default=>['snapshot_sha'=>$catalog->hash(),'iso2'=>$argv[1],'metric'=>$argv[2],'records'=>$catalog->series($argv[1],$argv[2])]
    });
    echo json_encode($out,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
