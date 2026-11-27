<?php
declare(strict_types=1);
require_once __DIR__.'/../src/PoliticalMovementReference.php';
use Aeternum\WorldPolitics\PoliticalMovementReference;
try{
    $r=new PoliticalMovementReference();
    $code=$argv[1]??null;
    $out=$code===null?['pin'=>$r->pin(),'summary'=>$r->summary()]:['pin'=>$r->pin(),'country'=>$r->country($code)];
    echo json_encode($out,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
