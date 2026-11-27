<?php
declare(strict_types=1);
require_once __DIR__.'/../src/GameStripe.php';
if(PHP_SAPI!=='cli'||getenv('WP_BILLING_OPERATOR')!=='YES')exit(2);
try{
    $dsn=getenv('WP_DB_DSN');if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Dedicated game database required');
    $db=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $cmd=$argv[1]??'';
    if($cmd==='bind-environment')$r=\Aeternum\WorldPolitics\DeploymentMode::bind($db);
    elseif($cmd==='resolve-hold'){
        if($argc!==4)throw new RuntimeException('Expected payment and resolution reference');
        $r=(new \Aeternum\WorldPolitics\GameStripe($db,static fn()=>throw new RuntimeException('Offline reconciliation')))->resolveHold($argv[2],$argv[3]);
    }
    elseif($cmd==='checkout'){
        \Aeternum\WorldPolitics\StripeConfiguration::ready();
        $client=new \Aeternum\WorldPolitics\StripeTestClient();
        $r=(new \Aeternum\WorldPolitics\GameStripe($db,$client->request(...)))->checkout($argv[2]??'', $argv[3]??'');
    }else throw new RuntimeException('Unknown game Stripe operation');
    echo json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"Game Stripe operation failed (".get_class($e).")\n");exit(1);}
