<?php
declare(strict_types=1);
// Compare canonical state before/after additive migration; not a repair tool.
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{WorldRuntime,PdoScheduler,SystemClock};
$runtime=WorldRuntime::load();$pdo=db();
if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='aeternum_m11')
    throw new RuntimeException('Isolated M11 schema required');
$out=[];
foreach($runtime->packs as $world=>$pack){
    $rules=$runtime->rule($world);$scheduler=new PdoScheduler($pdo,$world,new SystemClock(),$rules);
    $replay=$scheduler->verifyReplay($rules->commands,$rules->events);
    $out[]=['world'=>$world,'rulesKey'=>$rules->key,'journalEntries'=>$replay->entries,'stateDigest'=>$replay->stateDigest];
}
echo json_encode($out,JSON_THROW_ON_ERROR).PHP_EOL;
