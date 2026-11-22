<?php
declare(strict_types=1);
// Exercise the real M03 operator command against the populated pre-020 world.
$env=[
    'AETERNUM_DSN'=>getenv('AETERNUM_TEST_DSN'),
    'AETERNUM_DB_USER'=>getenv('AETERNUM_TEST_USER')?:'',
    'AETERNUM_DB_PASSWORD'=>getenv('AETERNUM_TEST_PASSWORD')?:'',
    'AETERNUM_MEDIEVAL_WORLD'=>'m01-legacy-world',
    'AETERNUM_INDUSTRIAL_WORLD'=>'',
    'PATH'=>getenv('PATH')?:'/usr/bin',
];
$runM03=static function(array $args) use($env):array {
    $process=proc_open([PHP_BINARY,__DIR__.'/../bin/m03-materialize.php',...$args],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    if (!is_resource($process)) throw new RuntimeException('Cannot spawn M03 operator CLI');
    fclose($pipes[0]);
    $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($process),$output,$error];
};
check($runM03(['m01-legacy-world'])[0]!==0,
    'M03 operator refuses to touch database without explicit backup acknowledgement');
check($runM03(['foreign-world','--backup-verified'])[0]!==0,
    'M03 operator refuses an unconfigured world');
[$status,$reportJson]=$runM03(['m01-legacy-world','--backup-verified']);
check($status===0,'M03 real operator CLI validates populated migrated world');
$report=json_decode($reportJson,true,8,JSON_THROW_ON_ERROR);
check($report['world']==='m01-legacy-world'
    && $report['projectionVersion']===1
    && $report['balances']===count($legacyPack->resources)
    && $report['offers']===1 && $report['deferredRefunds']===0
    && $report['journal']===$legacyJournalEntries
    && $report['replayedEntries']===$legacyJournalEntries
    && strlen($report['replayedDigest'])===64
    && $report['productionRestoreCertified']===false,
    'M03 CLI reports accurate SQL counts and pinned replay without claiming host restore');
check($runM03(['m01-legacy-world','--backup-verified'])[0]===0,
    'M03 operator recheck is idempotent on already-materialized world');
