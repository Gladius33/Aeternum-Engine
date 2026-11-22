<?php
declare(strict_types=1);
require __DIR__.'/db-bootstrap.php';
use Aeternum\Core\{PdoIdentity,PdoScheduler,FrozenClock,GamePackLoader};
$src=db();
$dsn=getenv('AETERNUM_RESTORE_DSN');
if (!$dsn || !str_contains($dsn,'dbname=aeternum_restore')) throw new RuntimeException('Explicit disposable restore DSN required');
$dst=new PDO($dsn,getenv('AETERNUM_TEST_USER')?:'',getenv('AETERNUM_TEST_PASSWORD')?:'',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_STRINGIFY_FETCHES=>false
]);
$tables=$src->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$restoredTables=$dst->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);sort($restoredTables);
if($tables!==$restoredTables || count($tables)<15) throw new RuntimeException('Restored schema differs from source');
$rowsChecked=0;
foreach ($tables as $table) {
    if (!preg_match('/^[a-z][a-z0-9_]*$/D',$table)) throw new RuntimeException('Unsafe table name');
    $digest=static function(PDO $db) use($table,&$rowsChecked):array {
        $stmt=$db->query('SELECT * FROM '.$table);
        $rows=[];
        while(($record=$stmt->fetch(PDO::FETCH_ASSOC))!==false) {
            if(count($rows)>100000) throw new RuntimeException('CI fixture too large to fingerprint');
            ksort($record,SORT_STRING);
            $rows[]=hash('sha256',json_encode($record,JSON_THROW_ON_ERROR));
        }
        sort($rows,SORT_STRING);
        return [count($rows),hash('sha256',implode("\n",$rows))];
    };
    $before=$digest($src);$after=$digest($dst);
    if($before!==$after) throw new RuntimeException('Restore integrity mismatch for table '.$table);
    $rowsChecked+=$before[0];
}
$identity=new PdoIdentity($dst);
if($identity->authenticate('test-player','correct horse battery staple')===null)
    throw new RuntimeException('Restored existing login invalid');
$actor=$identity->authenticate('migration-old-player','legacy account long-lived password');
if($actor===null) throw new RuntimeException('Restored legacy account invalid');
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$rules=$pack->rules();
$game=new PdoScheduler($dst,'m01-legacy-world',new FrozenClock(50),$rules);
$replay=$game->verifyReplay($rules->commands,$rules->events);
if($replay->entries!==count($pack->resources)) throw new RuntimeException('Restored journal failed deterministic replay');
foreach ($pack->resources as $resource) {
    $balance=$game->balanceForActor($actor,$actor,$resource->id,$rules->commands);
    if($balance->stock!==$resource->initialStock) throw new RuntimeException('Restored resource stock differs');
}
echo json_encode([
    'restoreVerified'=>true,'tablesCompared'=>count($tables),
    'rowsFingerprinted'=>$rowsChecked,
    'legacyActorLogin'=>true,'journalReplayEntries'=>$replay->entries,
    'scope'=>'isolated disposable DB; no host production restore'
],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
