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
// The nonempty pre-020 migration fixture includes one durable escrow offer
// after the opening commands. Restore must replay that offer as well as the
// initial resource openings, without modifying an existing journal.
$expectedEntries=count($pack->resources)+1;
if($replay->entries!==$expectedEntries) throw new RuntimeException(
    'Restored journal count differs: expected '.$expectedEntries.', received '.$replay->entries
);
$offers=$game->marketForActor($actor);
if(array_column($offers['offers'],'id')!==['legacy-m03-offer']) {
    throw new RuntimeException('Restored pre-020 escrow offer missing from active market');
}
$offer=$dst->prepare('SELECT status,seller_id,give_resource,give_units,want_resource,want_units FROM engine_market_offers WHERE world_id=? AND offer_id=?');
$offer->execute(['m01-legacy-world','legacy-m03-offer']);
$persisted=$offer->fetch(PDO::FETCH_ASSOC);
if($persisted===false || $persisted['status']!=='open' || $persisted['seller_id']!==$actor
    || $persisted['give_resource']!=='wood' || (int)$persisted['give_units']!==1
    || $persisted['want_resource']!=='grain' || (int)$persisted['want_units']!==1) {
    throw new RuntimeException('Restored migrated escrow projection differs from source fixture');
}
foreach ($pack->resources as $resource) {
    $balance=$game->balanceForActor($actor,$actor,$resource->id,$rules->commands);
    // The legacy escrow offer reserves one wood unit before migration.
    // Verify post-escrow stock, not the pre-market opening balance.
    $expectedStock=$resource->initialStock-($resource->id==='wood'?1:0);
    if($balance->stock!==$expectedStock) throw new RuntimeException(
        'Restored resource stock differs for '.$resource->id
    );
    $row=$dst->prepare('SELECT stock_units FROM engine_economy_balances WHERE world_id=? AND account_id=? AND resource_id=?');
    $row->execute(['m01-legacy-world',$actor,$resource->id]);
    if((int)$row->fetchColumn()!==$expectedStock) {
        throw new RuntimeException('Restored relational stock differs for '.$resource->id);
    }
}
// Validate the public social projections after an actual SQL dump/restore,
// in addition to the existing all-table row fingerprints and legacy replay.
$socialOwner=$identity->authenticate('m09-social-owner','M09 isolated disposable testing password');
$socialRecipient=$identity->authenticate('m09-social-officer','M09 isolated disposable testing password');
if ($socialOwner===null || $socialRecipient===null)
    throw new RuntimeException('M09 restored identities unavailable');
foreach (['m09-social-a','m09-social-b'] as $socialWorld) {
    $sourceRank=(new \Aeternum\Core\PdoAlliances($src,$socialWorld))->leaderboardFor($socialOwner);
    $restoredRank=(new \Aeternum\Core\PdoAlliances($dst,$socialWorld))->leaderboardFor($socialOwner);
    if ($sourceRank!==$restoredRank || $sourceRank['alliances']===[])
        throw new RuntimeException('M09 ranking diverged after restore for '.$socialWorld);
}
$sourceMessages=new \Aeternum\Core\PdoSocialMessages($src,'m09-social-a',new FrozenClock(5200));
$restoredMessages=new \Aeternum\Core\PdoSocialMessages($dst,'m09-social-a',new FrozenClock(5200));
if ($sourceMessages->inbox($socialRecipient)!==$restoredMessages->inbox($socialRecipient)
    || $sourceMessages->notifications($socialRecipient)!==$restoredMessages->notifications($socialRecipient))
    throw new RuntimeException('M09 messaging or notification replay after restore differs');
$auditCount=$dst->query("SELECT COUNT(*) FROM engine_social_moderation_audit
    WHERE world_id='m09-social-a'")->fetchColumn();
if ((int)$auditCount!==2) throw new RuntimeException('M09 moderation audits lost on restore');

echo json_encode([
    'restoreVerified'=>true,'tablesCompared'=>count($tables),
    'rowsFingerprinted'=>$rowsChecked,
    'legacyActorLogin'=>true,'journalReplayEntries'=>$replay->entries,'escrowOfferRestored'=>true,
    'socialRankingsRestored'=>true,'socialMailRestored'=>true,'moderationAuditsRestored'=>true,
    'scope'=>'isolated disposable DB; no host production restore'
],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
