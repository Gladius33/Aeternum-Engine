<?php
declare(strict_types=1);
use Aeternum\Core\{PdoSessions,PdoInvitations,PdoEmailActivation,PdoIdentity,FrozenClock,SystemClock};
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

// All used invitation claims must survive maintenance for identity-bound replay.
$usedInviteHash=db()->query('SELECT token_hash FROM engine_signup_invitations WHERE actor_id IS NOT NULL LIMIT 1')->fetchColumn();
check($usedInviteHash!==false,'used invitation exists for maintenance preservation test');
$inviteExpired=(new PdoInvitations(db(),new FrozenClock(1000)))->issue('maintenance-invitations',1,60)[0];
$inviteRevoked=(new PdoInvitations(db(),new SystemClock()))->issue('maintenance-invitations',1,3600)[0];
$inviteActive=(new PdoInvitations(db(),new SystemClock()))->issue('maintenance-invitations',1,86400)[0];
check((new PdoInvitations(db(),new SystemClock()))->revoke($inviteRevoked,'maintenance-invitations'),
    'revoked maintenance fixture persisted');
$inviteMaintenanceSvc=new PdoInvitations(db(),new SystemClock());
rejects(fn()=> $inviteMaintenanceSvc->purgeInactive(0),InvalidArgumentException::class,'invitation purge rejects zero limit');
rejects(fn()=> $inviteMaintenanceSvc->purgeInactive(1001),InvalidArgumentException::class,'invitation purge rejects oversized batch');
$invNested=db();
$invNested->beginTransaction();
try {
    rejects(fn()=> (new PdoInvitations($invNested,new SystemClock()))->purgeInactive(1),
        LogicException::class,'invitation purge refuses caller-owned transaction');
    check($invNested->inTransaction(),'invitation purge preserves caller transaction');
} finally {if($invNested->inTransaction())$invNested->rollBack();}
check($inviteMaintenanceSvc->purgeInactive(1)===1,'invitation purge is bounded to one row');
[$cliExit,$cliOut,$cliErr]=runMaintenanceCli('invite-maintenance.php','1000');
check($cliExit===0&&$cliErr==='','invitation maintenance CLI succeeds');
$invReport=json_decode($cliOut,true,8,JSON_THROW_ON_ERROR);
check(is_int($invReport['inactive_unused_invitations_deleted']??null)&&$invReport['inactive_unused_invitations_deleted']>=1,
    'invitation CLI reports actual inactive deletions');
$invLookup=db()->prepare('SELECT COUNT(*) FROM engine_signup_invitations WHERE token_hash=?');
foreach([$inviteExpired,$inviteRevoked] as $spent) {
    $invLookup->execute([hash('sha256',$spent)]);
    check((int)$invLookup->fetchColumn()===0,'unused expired or revoked invitation removed');
}
$invLookup->execute([hash('sha256',$inviteActive)]);
check((int)$invLookup->fetchColumn()===1,'unexpired unused invitation preserved');
$invLookup->execute([$usedInviteHash]);
check((int)$invLookup->fetchColumn()===1,'consumed invitation claim preserved for replay');


// The wall-clock CLI deletes expired unverified accounts but never
// interrupts a verified and still pending activation's durable retry.
$oldActivation=new PdoEmailActivation(db(),new FrozenClock(1000));
$expiredAccount=$oldActivation->begin('maintenance-mail-world','old-mail-expired',
    'older mail registration password','expired-mail@example.org');
$verifiedAccount=$oldActivation->begin('maintenance-mail-world','old-mail-verified',
    'older verified mail password','verified-mail@example.org');
check($expiredAccount!==null && $verifiedAccount!==null,'maintenance email fixtures created');
check($oldActivation->verify('old-mail-verified','older verified mail password',$verifiedAccount['code'])!==null,
    'confirmed pending account exists before maintenance');
[$cliExit,$cliOut,$cliErr]=runMaintenanceCli('activation-maintenance.php','500');
check($cliExit===0&&$cliErr==='','real activation cleanup CLI succeeded');
$activationReport=json_decode($cliOut,true,8,JSON_THROW_ON_ERROR);
check(($activationReport['expired_unverified_signups_deleted']??0)>=1,'expired unverified cleanup reported deletions');
check((new PdoIdentity(db()))->authenticate('old-mail-expired','older mail registration password')===null,
    'expired unverified account removed');
check((new PdoIdentity(db()))->authenticate('old-mail-verified','older verified mail password')!==null,
    'confirmed pending account retained for retry');

foreach(['maintenance.php','rate-maintenance.php','invite-maintenance.php','activation-maintenance.php'] as $cliScript) {
    [$cliExit,$cliOut,$cliErr]=runMaintenanceCli($cliScript,'0');
    check($cliExit===1&&$cliOut===''&&$cliErr!=='','maintenance CLI rejects invalid limit');
}
