<?php
declare(strict_types=1);
require_once __DIR__.'/connection.php';
if(PHP_SAPI!=='cli'||getenv('WP_RESEARCH_OPERATOR')!=='YES'){exit(2);}
try{
    $db=\Aeternum\WorldPolitics\researchDatabase();$q=new \Aeternum\WorldPolitics\ResearchQueue($db);
    $cmd=$argv[1]??'';
    if($cmd==='stripe-bind-environment'){
        require_once __DIR__.'/../src/DeploymentMode.php';
        echo json_encode(\Aeternum\WorldPolitics\DeploymentMode::bind($db),JSON_THROW_ON_ERROR).PHP_EOL;exit(0);
    }
    if($cmd==='stripe-preflight'){
        require_once __DIR__.'/../src/ResearchStripe.php';
        \Aeternum\WorldPolitics\StripeConfiguration::ready();
        $stripe=new \Aeternum\WorldPolitics\StripeTestClient();
        echo json_encode(\Aeternum\WorldPolitics\StripeConfiguration::preflight($db,$stripe->request(...),'research'),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(0);
    }
    if($cmd==='stripe-resolve-hold'){
        require_once __DIR__.'/../src/ResearchStripe.php';
        if($argc!==7||!ctype_digit($argv[4])||!ctype_digit($argv[5])||!in_array($argv[6],['end-monthly','keep-monthly'],true))throw new RuntimeException('Expected payment, resolution ref, included debit, prepaid debit, monthly decision');
        $stripe=new \Aeternum\WorldPolitics\ResearchStripe($db,static fn()=>throw new RuntimeException('Offline reconciliation'));
        echo json_encode($stripe->resolveHold($argv[2],$argv[3],(int)$argv[4],(int)$argv[5],$argv[6]==='end-monthly'),JSON_THROW_ON_ERROR).PHP_EOL;exit(0);
    }
    if($cmd==='stripe-portal'){
        require_once __DIR__.'/../src/ResearchStripe.php';
        \Aeternum\WorldPolitics\StripeConfiguration::ready();
        $stripe=new \Aeternum\WorldPolitics\StripeTestClient();
        echo json_encode((new \Aeternum\WorldPolitics\ResearchStripe($db,$stripe->request(...)))->portal($argv[2]??''),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(0);
    }
    if($cmd==='stripe-checkout'){
        require_once __DIR__.'/../src/ResearchStripe.php';
        \Aeternum\WorldPolitics\StripeConfiguration::ready();
        $stripe=new \Aeternum\WorldPolitics\StripeTestClient();
        $r=(new \Aeternum\WorldPolitics\ResearchStripe($db,$stripe->request(...)))->checkout($argv[2]??'', $argv[3]??'', $argv[4]??'');
        echo json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(0);
    }
    $r=match($cmd){
        'install'=>(function()use($db){if((int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0)throw new RuntimeException('Empty database required');$db->exec(file_get_contents(__DIR__.'/database/001_research.sql'));return ['installed'=>true];})(),
        'create-admin'=>$q->createAdmin($argv[2]??'', $argv[3]??''),
        'create-tenant'=>$q->createTenant($argv[2]??'standard',(int)($argv[3]??30),($argv[4]??'private')==='share',$argv[5]??'monthly'),
        'change-plan'=>$q->changePlan($argv[2]??'', $argv[3]??'',(int)($argv[4]??0),($argv[5]??'private')==='share'),
        'set-capacity'=>$q->configureCapacity($argv[2]??'',(int)($argv[3]??0),(int)($argv[4]??-1),(int)($argv[5]??-1)),
        'topup'=>$q->fund($argv[2]??'', $argv[3]??'','topup',(int)($argv[4]??0)),
        'renew'=>$q->fund($argv[2]??'', $argv[3]??'','renewal',(int)($argv[4]??0),(int)($argv[5]??0),$argv[6]??'', $argv[7]??'',($argv[8]??'no')==='overage'),
        'rotate'=>$q->rotate($argv[2]??''),
        'import-game-dataset'=>(function()use($q){$raw=stream_get_contents(STDIN,1048577);if($raw===false||strlen($raw)>1048576)throw new RuntimeException('Dataset batch bound');return $q->importGameDataset(json_decode($raw,true,32,JSON_THROW_ON_ERROR));})(),
        'withdraw-game-dataset'=>$q->withdrawGameDataset($argv[2]??''),
        'work'=>$q->work(),
        default=>throw new RuntimeException('Unknown command')
    };
    echo json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Research operation failed (".get_class($e).")\n");exit(1);}
