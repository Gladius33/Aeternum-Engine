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
