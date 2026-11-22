<?php
declare(strict_types=1);
use Aeternum\Core\PdoTerritories;
// Lab-only subprocesses use the disposable CI database, never a production DSN.
$mapCliEnv=[
 'AETERNUM_DSN'=>(string)getenv('AETERNUM_TEST_DSN'),
 'AETERNUM_DB_USER'=>getenv('AETERNUM_TEST_USER') ?: '',
 'AETERNUM_DB_PASSWORD'=>getenv('AETERNUM_TEST_PASSWORD') ?: '',
 'AETERNUM_MEDIEVAL_WORLD'=>'entity-A'
];
function spawnMap(array $args,array $env): array
{
    $cmd=[PHP_BINARY,__DIR__.'/../bin/m04-map.php',...$args];
    $process=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    if (!is_resource($process)) throw new RuntimeException('Unable to spawn map CLI');
    fclose($pipes[0]);
    return [$process,$pipes];
}
function finishMap(array $spawn): array
{
    [$process,$pipes]=$spawn;
    $stdout=stream_get_contents($pipes[1]);
    $stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($process),trim($stdout),trim($stderr)];
}
function executeMap(array $args,array $env): array { return finishMap(spawnMap($args,$env)); }
check(executeMap(['entity-A','location','cli-crossroad'],$mapCliEnv)[0]===0,'M04 CLI creates an approved location');
check(executeMap(['entity-A','location','cli-crossroad'],$mapCliEnv)[0]===0,'M04 CLI location replay idempotent');
check(executeMap(['entity-A','edge','cli-crossroad','home','4'],$mapCliEnv)[0]===0,'M04 CLI creates a directed route');
check(executeMap(['entity-A','territory','cli-east','cli-crossroad','outpost'],$mapCliEnv)[0]===0,'M04 CLI defines territory');
check(executeMap(['entity-A','owner','cli-east','bob'],$mapCliEnv)[0]===0,'M04 CLI assigns territory to active member');
check(executeMap(['entity-A','owner','cli-east','bob'],$mapCliEnv)[0]===0,'M04 CLI same ownership is idempotent');
check(executeMap(['entity-A','owner','cli-east','alice'],$mapCliEnv)[0]!==0,'M04 CLI rejects takeover');
check(executeMap(['entity-B','location','foreign'],$mapCliEnv)[0]!==0,'M04 CLI refuses unapproved world');
check(executeMap(['entity-A','edge','home','cli-crossroad','0'],$mapCliEnv)[0]!==0,'M04 CLI rejects invalid travel cost');
check(executeMap(['entity-A','inspect','bob'],$mapCliEnv)[0]===0,'M04 CLI requires actor-scoped inspection');
check(executeMap(['entity-A','inspect','absent'],$mapCliEnv)[0]!==0,'M04 CLI cannot reveal map to nonmember');
// Two independent PHP processes race for one unset territory; world-level InnoDB
// serialization permits one and only one assignment, never a partial transfer.
check(executeMap(['entity-A','territory','cli-race','cli-crossroad','outpost'],$mapCliEnv)[0]===0,
 'M04 CLI race setup');
$a=spawnMap(['entity-A','owner','cli-race','alice'],$mapCliEnv);
$b=spawnMap(['entity-A','owner','cli-race','bob'],$mapCliEnv);
$results=[finishMap($a),finishMap($b)];
check(count(array_filter($results,static fn(array $r):bool=>$r[0]===0))===1,
 'M04 concurrent competing claims have exactly one winner');
$ownerStatement=db()->query("SELECT owner_actor_id FROM engine_territories WHERE world_id='entity-A' AND territory_id='cli-race'");
check(in_array($ownerStatement->fetchColumn(),['alice','bob'],true),
 'M04 race result retains exactly one real member');
