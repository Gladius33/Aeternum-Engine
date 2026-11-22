<?php
declare(strict_types=1);
use Aeternum\Core\{PdoInvitations,AuthHttpApi,GamePackLoader,FrozenClock,PdoIdentity,PdoSessions};
$inviteClock=new FrozenClock(2400);
$inviteWorld='restricted-world';
$invitePack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-industrial/manifest.json'));
$inviteService=new PdoInvitations(db(),$inviteClock);
rejects(fn()=> $inviteService->issue($inviteWorld,0),InvalidArgumentException::class,'zero invitations rejected');
rejects(fn()=> $inviteService->issue($inviteWorld,51),InvalidArgumentException::class,'unbounded invite batches rejected');
[$codeA,$codeB,$codeC]=$inviteService->issue($inviteWorld,3);
check(strlen($codeA)===64 && $codeA!==$codeB && $codeB!==$codeC,'invitation codes are independent random 256-bit secrets');
$inviteHashQuery=db()->prepare('SELECT token_hash FROM engine_signup_invitations WHERE token_hash=?');
$inviteHashQuery->execute([hash('sha256',$codeA)]);
check($inviteHashQuery->fetchColumn()===hash('sha256',$codeA),'invitation plaintext not stored');
$inviteApi=new AuthHttpApi(db(),$inviteClock,120,[$inviteWorld=>$invitePack],true);
$invPayload=['login'=>'invited-one','password'=>'invitation long password','world'=>$inviteWorld];
$invitePost=fn(array $body,string $peer='192.0.2.100')=>$inviteApi->handle(
    'POST','/v1/auth/register','',json_encode($body,JSON_THROW_ON_ERROR),$peer);
check($invitePost($invPayload)[0]===400,'invitation required for controlled beta');
check($invitePost([...$invPayload,'inviteCode'=>'invalid'])[0]===400,'invalid invite format rejected');
check($invitePost([...$invPayload,'inviteCode'=>str_repeat('a',64)])[0]===409,'nonexistent invitation rejected');
[$inviteStatus,$inviteResult]=$invitePost([...$invPayload,'inviteCode'=>$codeA]);
check($inviteStatus===201,'valid invitation creates one player');
$inviteActor=$inviteResult['data']['account'];
check((new PdoSessions(db(),$inviteClock))->resolve($inviteResult['data']['token'])===$inviteActor,'invited player has valid bearer');
check($invitePost([...$invPayload,'inviteCode'=>$codeA])[0]===200,'same identity and invitation allow safe retry');
check($invitePost(['login'=>'invited-thief','password'=>'different long password','world'=>$inviteWorld,'inviteCode'=>$codeA],'192.0.2.101')[0]===409,'spent invitation cannot enroll another identity');
check((new PdoIdentity(db()))->authenticate('invited-thief','different long password')===null,'failed invite claim does not leave orphan identity');
[$otherStatus,$otherResult]=$invitePost(['login'=>'invited-two','password'=>'second valid invitation password','world'=>$inviteWorld,'inviteCode'=>$codeB],'192.0.2.102');
check($otherStatus===201 && $otherResult['data']['account']!==$inviteActor,'second invitation admits independent identity');
check($invitePost([...$invPayload,'inviteCode'=>$codeB],'192.0.2.103')[0]===409,'existing identity cannot switch invitation');
// Emulate interruption after atomic invitation+pending identity, before any world grant.
$db=db();
$db->beginTransaction();
try {
    $preActor=(new PdoIdentity($db))->register('invited-partial','partial invitation password');
    (new PdoInvitations($db,$inviteClock))->claim($codeC,$inviteWorld,$preActor);
    $s=$db->prepare("INSERT INTO engine_onboarding(actor_id,world_id,status,created_at) VALUES (?,?,'pending',2400)");
    $s->execute([$preActor,$inviteWorld]);
    $db->commit();
} catch (Throwable $e) {if($db->inTransaction())$db->rollBack();throw $e;}
check((new PdoSessions(db(),$inviteClock))->login('invited-partial','partial invitation password')===null,'pending invited identity cannot obtain bearer');
[$recoverStatus,$recoverData]=$invitePost(['login'=>'invited-partial','password'=>'partial invitation password','world'=>$inviteWorld,'inviteCode'=>$codeC],'192.0.2.104');
check($recoverStatus===200 && $recoverData['data']['account']===$preActor,'invited partial claim resumes after interruption');
check($invitePost(['login'=>'invited-other','password'=>'other password is long','world'=>$inviteWorld,'inviteCode'=>$codeC],'192.0.2.105')[0]===409,'spent recovery invitation remains bound');


// An unused invite expires; an unclaimed credential can also be revoked by its issuer.
[$expiredCode,$revokedCode]=$inviteService->issue($inviteWorld,2,60);
check($inviteService->revoke($revokedCode,$inviteWorld),'operator revokes one unused invitation');
check(!$inviteService->revoke($revokedCode,$inviteWorld),'revocation is single transition');
check(!$inviteService->revoke($codeA,$inviteWorld),'spent invitation cannot be revoked retroactively');
check($invitePost(['login'=>'invite-revoked','password'=>'revoked invitation password','world'=>$inviteWorld,'inviteCode'=>$revokedCode],'192.0.2.106')[0]===409,'revoked invite cannot create an account');
$inviteClock->advanceTo(2460);
check($invitePost(['login'=>'invite-expired','password'=>'expired invitation password','world'=>$inviteWorld,'inviteCode'=>$expiredCode],'192.0.2.107')[0]===409,'expired invite cannot create an account');
check((new PdoIdentity(db()))->authenticate('invite-expired','expired invitation password')===null,'expiration rollback leaves no identity');
rejects(fn()=> $inviteService->issue($inviteWorld,1,59),InvalidArgumentException::class,'invitation TTL has lower bound');
rejects(fn()=> $inviteService->issue($inviteWorld,1,2592001),InvalidArgumentException::class,'invitation TTL has upper bound');


// Four different identities competing for ONE invitation: exactly one may own it.
// The three losers must leave no orphaned application identities or memberships.
[$raceInvite]=$inviteService->issue($inviteWorld,1);
$inviteChildren=[];
for($n=0;$n<4;$n++) {
    $proc=proc_open([PHP_BINARY,__DIR__.'/invitation-worker.php',$inviteWorld,(string)$n],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($proc)) throw new RuntimeException('Unable to spawn invitation worker');
    fwrite($pipes[0],$raceInvite.PHP_EOL);
    fclose($pipes[0]);
    $inviteChildren[]=[$proc,$pipes];
}
$admitted=0;
foreach($inviteChildren as [$proc,$pipes]) {
    $output=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($proc)===0,'invitation concurrency worker completed: '.$stderr);
    $status=(int)trim($output);
    check(in_array($status,[201,409],true),'invite race has controlled outcome');
    if($status===201)++$admitted;
}
check($admitted===1,'one invite cannot be spent by two competing identities');
$s=db()->query("SELECT COUNT(*) FROM engine_identities WHERE login LIKE 'invited-race-%'");
check((int)$s->fetchColumn()===1,'failed competing invites rollback their identities');


// The global signup quota applies independently of source IP. A throttled
// request must not claim its invitation or create a half-initialized account.
[$quotaCode]=$inviteService->issue($inviteWorld,1);
$signupQuota=new \Aeternum\Core\PdoRateLimiter(db(),$inviteClock);
$signupGranted=0;
while($signupQuota->admit('auth:registration-global',100,3600)) {
    if (++$signupGranted>100) throw new RuntimeException('Registration global budget did not close');
}
$quotaPayload=['login'=>'invite-quota-blocked','password'=>'valid password for signup quota','world'=>$inviteWorld,'inviteCode'=>$quotaCode];
check($invitePost($quotaPayload,'192.0.2.245')[0]===429,
    'global signup quota rejects registration from fresh peer');
check((new PdoIdentity(db()))->authenticate($quotaPayload['login'],$quotaPayload['password'])===null,
    'throttled signup leaves no identity and cannot consume invitation');
// Earlier password-change fixtures use server time 30200. The shared
// global limiter deliberately resists a backward clock, so advance beyond
// that already observed window rather than assuming 3600 is a rollover.
$inviteClock->advanceTo(32400);
[$afterQuotaStatus,$afterQuotaBody]=$invitePost($quotaPayload,'192.0.2.246');
check($afterQuotaStatus===201 && preg_match('/^[a-f0-9]{64}$/D',$afterQuotaBody['data']['account']??'')===1,
    'same invitation succeeds after global signup quota rollover (HTTP '.$afterQuotaStatus.')');
check((new PdoIdentity(db()))->authenticate($quotaPayload['login'],$quotaPayload['password'])===$afterQuotaBody['data']['account'],
    'global quota reset never duplicates the claimed identity');
