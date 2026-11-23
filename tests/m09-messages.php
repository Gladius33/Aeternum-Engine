<?php
declare(strict_types=1);
use Aeternum\Core\{PdoIdentity,PdoSessions,PdoSocialMessages,SocialHttpApi,FrozenClock,PdoMemberships};

// Short-lived test scope releases every PDO connection before the older
// high-concurrency MariaDB fixture runs in this same PHP process.
(static function():void {
    $pdo=db();
    $clock=new FrozenClock(5200);
    $world='m09-social-a';
    $other='m09-social-b';
    $ids=$pdo->prepare('SELECT actor_id FROM engine_identities WHERE login=?');
    $resolve=static function(string $login)use($ids):string {
        $ids->execute([$login]);
        $id=$ids->fetchColumn();
        if (!is_string($id)) throw new RuntimeException('M09 missing durable player '.$login);
        return $id;
    };
    $owner=$resolve('m09-social-owner');
    $recipient=$resolve('m09-social-officer');
    $stranger=$resolve('m09-social-guest');
    $model=new PdoSocialMessages($pdo,$world,$clock);
    $foreign=new PdoSocialMessages($pdo,$other,$clock);
    $http=new SocialHttpApi($pdo,$clock,[$world=>true,$other=>true]);
    $sessions=new PdoSessions($pdo,$clock);
    $password='M09 isolated disposable testing password';
    $ownerBearer='Bearer '.$sessions->login('m09-social-owner',$password);
    $recipientBearer='Bearer '.$sessions->login('m09-social-officer',$password);
    $prefix='/v1/worlds/'.$world.'/social/';
    check($http->handle('GET',$prefix.'messages','')[0]===401,
        'M09 private mail requires valid authenticated session');
    check($http->handle('POST',$prefix.'messages',$ownerBearer,'{"id":"m09-hi","recipient":"'.
        $recipient.'","text":"Hello from the alliance"}')[0]===200,
        'M09 HTTP bearer sends persistent private mail to active actor');
    check($model->inbox($recipient)['messages'][0]['text']==='Hello from the alliance',
        'M09 real recipient can read persisted message body');
    check($model->inbox($stranger)['messages']===[],
        'M09 unrelated actor cannot enumerate private messages');
    check($foreign->inbox($recipient)['messages']===[],
        'M09 message cannot leak to second world with identical actor');
    $initialNotices=$model->notifications($recipient)['notifications'];
    check(count($initialNotices)===1 && $initialNotices[0]['kind']==='message'
        && $initialNotices[0]['readAt']===null,
        'M09 new message commits exactly one unread notification');
    $model->send($owner,$recipient,'m09-hi','Hello from the alliance');
    check(count($model->notifications($recipient)['notifications'])===1,
        'M09 same message ID and payload are idempotent including notification');
    rejects(fn()=> $model->send($owner,$recipient,'m09-hi','Tampered duplicate'),
        DomainException::class,'M09 same message ID with different payload fails closed');
    rejects(fn()=> $model->send($owner,$owner,'m09-self','self'),
        DomainException::class,'M09 self-messaging prohibited');
    rejects(fn()=> $model->send($owner,$recipient,'m09-invalid','<'.str_repeat('q',2050).'>'),
        InvalidArgumentException::class,'M09 oversized messages rejected before DB mutation');
    check($http->handle('POST',$prefix.'messages',$ownerBearer,
        '{"id":"m09-x","recipient":"'.$recipient.'","text":"hi","sender":"'.$stranger.'"}')[0]===400,
        'M09 HTTP caller cannot spoof message sender');
    check($http->handle('POST',$prefix.'messages',$ownerBearer,
        '{"id":"m09-x","recipient":"'.$recipient.'","text":"hi","extra":false}')[0]===400,
        'M09 HTTP rejects unknown schema keys');
    check($http->handle('POST',$prefix.'messages',$ownerBearer,'[]')[0]===400,
        'M09 HTTP rejects nonobject JSON');
    check($http->handle('GET',$prefix.'messages',$ownerBearer)[1]['data']['messages']===[],
        'M09 sender private inbox cannot reveal outgoing recipient mail');
    check($http->handle('GET',$prefix.'messages',$recipientBearer)[0]===200,
        'M09 authorized recipient HTTP inbox available after reconnect');
    $nid=$initialNotices[0]['id'];
    rejects(fn()=> $model->acknowledge($owner,$nid),
        DomainException::class,'M09 actor cannot acknowledge foreign notification');
    $ack=$http->handle('POST',$prefix.'notifications/'.$nid.'/read',$recipientBearer,'{}');
    check($ack[0]===200 && $model->notifications($recipient)['notifications'][0]['readAt']===5200,
        'M09 recipient acknowledges durable notification exactly once');
    $model->acknowledge($recipient,$nid);
    check($model->notifications($recipient)['notifications'][0]['readAt']===5200,
        'M09 notification acknowledgement retry preserves original timestamp');
    check($http->handle('POST',$prefix.'blocks',$recipientBearer,
        '{"actor":"'.$owner.'"}')[0]===200,
        'M09 recipient can block harassing actor by authenticated request');
    check($model->blocksFor($recipient)===[$owner],
        'M09 blocked actor set persists with world isolation');
    rejects(fn()=> $model->send($owner,$recipient,'m09-blocked','Should fail'),
        DomainException::class,'M09 block forbids new message before notification creation');
    check($model->inbox($recipient)['messages']===[],
        'M09 blocking hides preexisting mail from blocked sender');
    $model->unblock($recipient,$owner);
    $model->unblock($recipient,$owner);
    check(count($model->inbox($recipient)['messages'])===1,
        'M09 unblocking is idempotent and does not delete mail');
    $model->block($owner,$recipient);
    rejects(fn()=> $model->send($owner,$recipient,'m09-reverse','Should also fail'),
        DomainException::class,'M09 outgoing block also prevents sending');
    $model->unblock($owner,$recipient);
    rejects(fn()=> $foreign->send($owner,$stranger,'m09-illegal','Nonmember'),
        DomainException::class,'M09 cross-world recipient lacks membership');
    rejects(fn()=> $model->report($stranger,'m09-report-denied','m09-hi','Not party'),
        DomainException::class,'M09 outsider cannot report message they cannot read');
    $model->report($recipient,'m09-report','m09-hi','Unwelcome contact');
    $model->report($recipient,'m09-report','m09-hi','Unwelcome contact');
    rejects(fn()=> $model->report($recipient,'m09-report','m09-hi','Changed accusation'),
        DomainException::class,'M09 report idempotency refuses conflicting replay');
    $rpt=$pdo->prepare('SELECT status,reason FROM engine_social_reports WHERE world_id=? AND report_id=?');
    $rpt->execute([$world,'m09-report']);
    check($rpt->fetch(PDO::FETCH_ASSOC)===['status'=>'open','reason'=>'Unwelcome contact'],
        'M09 report permanently queued once for operator review');
    $ranking=$http->handle('GET',$prefix.'leaderboard',$ownerBearer);
    check($ranking[0]===200 && count($ranking[1]['data']['alliances'])>=2,
        'M09 reproducible alliance leaderboard available to active member');
    $rankingRepeat=$http->handle('GET',$prefix.'leaderboard',$ownerBearer);
    check($ranking[1]['data']===$rankingRepeat[1]['data'],
        'M09 leaderboard has deterministic ordering on unchanged persistent data');
    (new PdoMemberships($pdo,$world))->revoke($recipient);
    check($http->handle('GET',$prefix.'messages',$recipientBearer)[0]===404
        && $http->handle('GET',$prefix.'notifications',$recipientBearer)[0]===404,
        'M09 revoked member loses messaging and notification read privileges');
    (new PdoMemberships($pdo,$world))->grant($recipient);
    check($http->handle('GET',$prefix.'notifications',$recipientBearer)[0]===200,
        'M09 reactivated member recovers previously persisted notices');
})();
