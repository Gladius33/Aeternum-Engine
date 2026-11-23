<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoIdentity,GamePackLoader,PdoScheduler,FrozenClock,OperationsSnapshot};
if (PHP_SAPI!=='cli' || $argc!==2 || $argv[1]!=='verified') exit(2);
// Lab-only: exact schema name, no broad restore DSN accepted.
$dsn=getenv('AETERNUM_RESTORE_DSN');
if (!is_string($dsn) || !preg_match('/(?:^|;)dbname=aeternum_restore(?:;|$)/D',$dsn))
    throw new RuntimeException('M11 demands an explicit disposable restore DSN');
$src=db();
$dst=new PDO($dsn,getenv('AETERNUM_TEST_USER')?:'',getenv('AETERNUM_TEST_PASSWORD')?:'',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_STRINGIFY_FETCHES=>false
]);
$sourceTables=$src->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$restoredTables=$dst->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($sourceTables);sort($restoredTables);
if ($sourceTables!==$restoredTables || count($sourceTables)<30)
    throw new RuntimeException('M11 restored table inventory differs');
$total=0;
foreach ($sourceTables as $table) {
    if (!preg_match('/^[a-z][a-z0-9_]*$/D',$table))
        throw new RuntimeException('Unsafe table in SQL fixture');
    $fingerprint=static function(PDO $db,string $table): array {
        $s=$db->query('SELECT * FROM '.$table);
        $rows=[];
        while (($r=$s->fetch(PDO::FETCH_ASSOC))!==false) {
            if (count($rows)>=10000) throw new RuntimeException('Lab fingerprint size limit');
            ksort($r,SORT_STRING);
            $rows[]=hash('sha256',json_encode($r,JSON_THROW_ON_ERROR));
        }
        sort($rows,SORT_STRING);
        return [count($rows),hash('sha256',implode("\n",$rows))];
    };
    $left=$fingerprint($src,$table);
    $right=$fingerprint($dst,$table);
    if ($left!==$right) throw new RuntimeException('M11 restored table differs: '.$table);
    $total+=$left[0];
}
$actor=(new PdoIdentity($dst))->authenticate('m11-lab-player','m11-lab-isolated-password');
if ($actor===null) throw new RuntimeException('M11 restored identity cannot authenticate');
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$rules=$pack->rules();
$world='m11-lab-world';
$restoreGame=new PdoScheduler($dst,$world,new FrozenClock(time()),$rules);
$sourceGame=new PdoScheduler($src,$world,new FrozenClock(time()),$rules);
$before=$sourceGame->verifyReplay($rules->commands,$rules->events);
$after=$restoreGame->verifyReplay($rules->commands,$rules->events);
if ($before->entries!==$after->entries || $before->entries<count($pack->resources))
    throw new RuntimeException('M11 replay entries differ after restoration');
foreach($pack->resources as $resource) {
    $sourceBalance=$sourceGame->balanceForActor($actor,$actor,$resource->id,$rules->commands);
    $restoredBalance=$restoreGame->balanceForActor($actor,$actor,$resource->id,$rules->commands);
    if ($sourceBalance!=$restoredBalance)
        throw new RuntimeException('M11 live resource state differs: '.$resource->id);
}
$clock=new FrozenClock(time());
$first=(new OperationsSnapshot($src,$clock,__DIR__.'/../database/migrations'))->snapshot([$world=>$pack]);
$second=(new OperationsSnapshot($dst,$clock,__DIR__.'/../database/migrations'))->snapshot([$world=>$pack]);
if ($first!==$second || !$second['healthy']) throw new RuntimeException('M11 operational status changed after restore');
echo json_encode(['verified'=>true,'scope'=>'disposable-database-only',
    'tables'=>count($sourceTables),'fingerprintedRows'=>$total,
    'restoredIdentity'=>true,'journalEntries'=>$after->entries,
    'restoredOpsStatus'=>true],JSON_THROW_ON_ERROR).PHP_EOL;
