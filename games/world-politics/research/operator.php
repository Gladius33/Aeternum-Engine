<?php
declare(strict_types=1);
require_once __DIR__.'/connection.php';
if(PHP_SAPI!=='cli'||getenv('WP_RESEARCH_OPERATOR')!=='YES'){exit(2);}
try{
    $db=\Aeternum\WorldPolitics\researchDatabase();$q=new \Aeternum\WorldPolitics\ResearchQueue($db);
    $cmd=$argv[1]??'';
    $r=match($cmd){
        'install'=>(function()use($db){if((int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0)throw new RuntimeException('Empty database required');$db->exec(file_get_contents(__DIR__.'/database/001_research.sql'));return ['installed'=>true];})(),
        'create-tenant'=>$q->createTenant(),
        'rotate'=>$q->rotate($argv[2]??''),
        'work'=>$q->work(),
        default=>throw new RuntimeException('Unknown command')
    };
    echo json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Research operation failed (".get_class($e).")\n");exit(1);}
