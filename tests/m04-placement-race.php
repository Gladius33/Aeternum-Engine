<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,PdoMemberships,PdoEntities,PdoTerritories,
    PdoBuildings,GamePackLoader,FrozenClock};
$racePack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/m04-construction-fixture/manifest.json'));
$raceRules=$racePack->rules();
$raceWorld='m04-foundation-race';
$raceScheduler=new PdoScheduler(db(),$raceWorld,new FrozenClock(3000),$raceRules);
$raceScheduler->initialize();
$raceMembers=new PdoMemberships(db(),$raceWorld);
$raceMembers->grant('alice');
$raceMembers->assign('race-account','alice');
$raceEntities=new PdoEntities(db(),$raceWorld);
$raceEntities->addLocation('race-location');
$raceEntities->provision('race-entity','race-location','alice','settlement');
$raceTerritories=new PdoTerritories(db(),$raceWorld);
$raceTerritories->define('race-land','race-location','town');
$raceTerritories->assign('race-land','alice');
(new PdoBuildings(db(),$raceWorld))->define('mill',$racePack->buildings['mill']);
$workers=[];
for($i=0;$i<4;$i++) {
    $process=proc_open([PHP_BINARY,__DIR__.'/m04-placement-worker.php',(string)$i],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('M04 placement worker unavailable');
    fclose($pipes[0]);$workers[]=[$process,$pipes];
}
$won=0;$denied=0;
foreach($workers as [$process,$pipes]) {
    $stdout=stream_get_contents($pipes[1]);
    $stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if (proc_close($process)!==0) throw new RuntimeException('Placement worker failed: '.$stderr);
    if (trim($stdout)==='won') $won++;
    elseif (trim($stdout)==='denied') $denied++;
    else throw new RuntimeException('Unexpected placement worker output');
}
check($won===1 && $denied===3,'M04 four distinct placement attempts admit precisely one foundation');
$pdo=db();
$q=$pdo->prepare('SELECT COUNT(*) FROM engine_buildings WHERE world_id=? AND building_id=?');
$q->execute([$raceWorld,'contended-mill']);
check((int)$q->fetchColumn()===1,'M04 race creates one relational foundation');
$q=$pdo->prepare('SELECT COUNT(*) FROM engine_commands WHERE world_id=? AND command_type=?');
$q->execute([$raceWorld,'building.place']);
check((int)$q->fetchColumn()===1,'M04 race creates one canonical command receipt');
check($raceScheduler->verifyReplay($raceRules->commands,$raceRules->events)->entries===1,
 'M04 contended foundation replay has no unjournaled side effects');
