<?php
declare(strict_types=1);
use Aeternum\Core\{PdoSessions,FrozenClock,SystemClock};
// Execute after all fixed-clock integration tests. The CLI intentionally uses real wall time.
function runMaintenanceCli(string $script,string $limit): array {
    $env=getenv();
    $env['AETERNUM_DSN']=getenv('AETERNUM_TEST_DSN');
    $env['AETERNUM_DB_USER']=getenv('AETERNUM_TEST_USER') ?: '';
    $env['AETERNUM_DB_PASSWORD']=getenv('AETERNUM_TEST_PASSWORD') ?: '';
    $p=proc_open([PHP_BINARY,__DIR__.'/../bin/'.$script,$limit],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    if(!is_resource($p)) throw new RuntimeException('Cannot spawn maintenance CLI');
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return [proc_close($p),$out,$err];
}
$cliOldSession=(new PdoSessions(db(),new FrozenClock(1000)))->login('test-player','correct horse battery staple',60);
$cliLiveSvc=new PdoSessions(db(),new SystemClock());
$cliLiveToken=$cliLiveSvc->login('test-player','correct horse battery staple',3600);
[$cliExit,$cliOut,$cliErr]=runMaintenanceCli('maintenance.php','1000');
check($cliExit===0&&$cliErr==='','session maintenance CLI succeeds');
$cliReport=json_decode($cliOut,true,8,JSON_THROW_ON_ERROR);
check(is_int($cliReport['expired_sessions_deleted']??null)&&$cliReport['expired_sessions_deleted']>=1,'session CLI reports actual deletions');
$cliStmt=db()->prepare('SELECT COUNT(*) FROM engine_sessions WHERE token_hash=?');
$cliStmt->execute([hash('sha256',$cliOldSession)]);
check((int)$cliStmt->fetchColumn()===0,'session CLI deleted old fixture');
check($cliLiveSvc->resolve($cliLiveToken)===$actor,'session CLI preserves live token');
[$cliExit,$cliOut,$cliErr]=runMaintenanceCli('rate-maintenance.php','1000');
check($cliExit===0&&$cliErr==='','rate maintenance CLI succeeds');
$cliReport=json_decode($cliOut,true,8,JSON_THROW_ON_ERROR);
check(is_int($cliReport['stale_rate_buckets_deleted']??null)&&$cliReport['stale_rate_buckets_deleted']>=1,'rate CLI reports actual deletions');
foreach(['maintenance.php','rate-maintenance.php'] as $cliScript) {
    [$cliExit,$cliOut,$cliErr]=runMaintenanceCli($cliScript,'0');
    check($cliExit===1&&$cliOut===''&&$cliErr!=='','maintenance CLI rejects invalid limit');
}
