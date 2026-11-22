<?php
declare(strict_types=1);
use Aeternum\Core\{PdoEmailActivation,FrozenClock,PdoSessions,PdoIdentity,PdoOnboarding,GamePackLoader};
// Keep fixture-local actor and pack variables from shadowing earlier tests.
(static function():void {
$actClock=new FrozenClock(15000);
$act=new PdoEmailActivation(db(),$actClock);
$world='email-verification-world';
$pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
rejects(fn()=>PdoEmailActivation::normalizeEmail('bad@example.org'."\r\nBcc:attacker@example.org"),
    InvalidArgumentException::class,'activation email rejects header injection');
$beg=$act->begin($world,'email-new-player','valid activation registration password',' player@example.org ');
check($beg!==null && $beg['email']==='player@example.org' && preg_match('/^[a-f0-9]{64}$/D',$beg['code'])===1,
    'pending activation issues high-entropy nonpersistent secret');
$s=db()->prepare('SELECT actor_id FROM engine_identities WHERE login=?');
$s->execute(['email-new-player']);$actor=$s->fetchColumn();
check(strlen($actor)===64,'email identity bound to actor');
check((new PdoSessions(db(),$actClock))->login('email-new-player','valid activation registration password')===null,
    'unverified email player cannot obtain session');
$on=db()->prepare('SELECT status FROM engine_onboarding WHERE actor_id=?');
$on->execute([$actor]);check($on->fetchColumn()==='pending','unverified email player is never provisioned');
check($act->verify('email-new-player','wrong password',$beg['code'])===null,'activation requires original password');
check($act->verify('email-new-player','valid activation registration password',str_repeat('b',64))===null,
    'activation rejects forged code');
check($act->begin($world,'email-new-player','another long password','new@example.org')===null,
    'duplicate login does not create second activation');
check($act->begin($world,'different-email-user','another long password','player@example.org')===null,
    'duplicate verified mailbox cannot create another account');
check($act->resend('email-new-player','valid activation registration password','player@example.org')===null,
    'activation resend too soon refused');
$actClock->advanceTo(15061);
$replacement=$act->resend('email-new-player','valid activation registration password','player@example.org');
check($replacement!==null && $replacement['code']!==$beg['code'],'resend rotates activation proof');
check($act->verify('email-new-player','valid activation registration password',$beg['code'])===null,
    'superseded activation code is not replayable');
$claim=$act->verify('email-new-player','valid activation registration password',$replacement['code']);
check($claim===['actor'=>$actor,'world'=>$world],'valid secret and password confirm original world and actor');
check((new PdoSessions(db(),$actClock))->login('email-new-player','valid activation registration password')===null,
    'email verification alone cannot bypass incomplete provisioning');
$claimRetry=$act->verify('email-new-player','valid activation registration password',$replacement['code']);
check($claimRetry===$claim,'confirmed but interrupted pending claim remains resumable');
$enrolled=(new PdoOnboarding(db(),$actClock))->enroll($world,$pack,'email-new-player','valid activation registration password');
check($enrolled['actor']===$actor && $enrolled['created']===false,
    'activation completes existing identity with no double initial grant');
$act->consume($actor);
check((new PdoSessions(db(),$actClock))->login('email-new-player','valid activation registration password')!==null,
    'verified and provisioned player can obtain genuine bearer');
check($act->verify('email-new-player','valid activation registration password',$replacement['code'])===null,
    'completed activation refuses token replay');
$usedToken=$on=$actClock->now();
$expired=$act->begin('email-expiry-world','email-expired','valid expiry password','expiry@example.org');
$actClock->advanceTo(15061+86401);
check($act->verify('email-expired','valid expiry password',$expired['code'])===null,
    'expired mailbox proof cannot activate account');
check((new PdoSessions(db(),$actClock))->login('email-expired','valid expiry password')===null,
    'expired but unverified identity remains inaccessible');

})();
