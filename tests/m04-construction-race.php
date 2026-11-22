<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,FrozenClock,GamePackLoader,PdoMemberships,
    PdoEntities,PdoTerritories,PdoBuildings,Command};
$racePack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/m04-construction-fixture/manifest.json'));
$raceRules=$racePack->rules();
$raceClock=new FrozenClock(1000);
$raceWorld='m04-building-race';
$raceStore=new PdoScheduler(db(),$raceWorld,$raceClock,$raceRules);
$raceStore->initialize();
$raceMembers=new PdoMemberships(db(),$raceWorld);
$raceMembers->grant('race-player');$raceMembers->assign('race-account','race-player');
$raceEntities=new PdoEntities(db(),$raceWorld);
$raceEntities->addLocation('race-location');
$raceEntities->provision('race-entity','race-location','race-player','settlement');
$raceLand=new PdoTerritories(db(),$raceWorld);
$raceLand->define('race-territory','race-location','town');
$raceLand->assign('race-territory','race-player');
$raceBuildings=new PdoBuildings(db(),$raceWorld);
$raceBuildings->define('mill',$racePack->buildings['mill']);
$raceBuildings->establish('race-mill','race-territory','race-entity','race-player','mill');
foreach($racePack->resources as $resource) {
    $raceStore->executeForActor('race-player',new Command($raceWorld,
        'race-open-'.$resource->id,'economy.open',
        ['account'=>'race-account','resource'=>$resource->id]),$raceRules->commands);
}
function runRaceWorkers(string $operation): array
{
    $workers=[];
    for($i=0;$i<4;$i++){
        $process=proc_open([PHP_BINARY,__DIR__.'/m04-construction-worker.php',$operation,(string)$i],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)) throw new RuntimeException('Unable to start independent construction worker');
        fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $won=0;$denied=0;
    foreach($workers as [$process,$pipes]){
        $stdout=trim(stream_get_contents($pipes[1]));
        $stderr=trim(stream_get_contents($pipes[2]));
        fclose($pipes[1]);fclose($pipes[2]);
        $exit=proc_close($process);
        if($exit!==0) throw new RuntimeException('M04 worker '.$operation.' failed: '.$stderr);
        if($stdout==='won')$won++;
        elseif($stdout==='denied')$denied++;
        else throw new RuntimeException('Unexpected competing worker output');
    }
    return [$won,$denied];
}
check(runRaceWorkers('start')===[1,3],
 'M04 four independent MariaDB actors race but exactly one building start commits');
check($raceStore->pendingCount()===1,'M04 competing starts schedule one event');
check($raceStore->balanceForActor('race-player','race-account','wood',$raceRules->commands)->stock===93
 && $raceStore->balanceForActor('race-player','race-account','grain',$raceRules->commands)->stock===69,
 'M04 competing starts cannot double charge either resource');
$raceCommandCount=db()->query("SELECT COUNT(*) FROM engine_commands WHERE world_id='m04-building-race' AND command_type='building.start'")->fetchColumn();
check((int)$raceCommandCount===1,'M04 competing starts retain one durable command');
check(runRaceWorkers('cancel')===[1,3],
 'M04 four independent compensating cancellations have exactly one winner');
check($raceStore->balanceForActor('race-player','race-account','wood',$raceRules->commands)->stock===100
 && $raceStore->balanceForActor('race-player','race-account','grain',$raceRules->commands)->stock===80,
 'M04 concurrent cancellation returns costs exactly once');
$raceRefundCount=db()->query("SELECT COUNT(*) FROM engine_resource_ledger WHERE world_id='m04-building-race' AND reason='construction_refund'")->fetchColumn();
check((int)$raceRefundCount===2,'M04 two refund entries from one committed cancellation');
$raceClock->advanceTo(1005);
check(count($raceStore->processDue($raceRules->events))===1,
 'M04 canceled concurrent event drains safely without changing building level');
check($raceBuildings->own('race-player','race-mill')['level']===0,
 'M04 canceled concurrent building remains at foundation level');
check($raceStore->verifyReplay($raceRules->commands,$raceRules->events)->entries===5,
 'M04 concurrent start-cancel journal is deterministically replayable');
