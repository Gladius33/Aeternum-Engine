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
    $messageNotices=array_values(array_filter($initialNotices,
        static fn(array $n):bool=>$n['kind']==='message'));
    check(count($messageNotices)===1 && $messageNotices[0]['readAt']===null,
        'M09 new message commits exactly one unread notification');
    $model->send($owner,$recipient,'m09-hi','Hello from the alliance');
    check(count(array_filter($model->notifications($recipient)['notifications'],
        static fn(array $n):bool=>$n['kind']==='message'))===1,
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
    $nid=$messageNotices[0]['id'];
    rejects(fn()=> $model->acknowledge($owner,$nid),
        DomainException::class,'M09 actor cannot acknowledge foreign notification');
    $ack=$http->handle('POST',$prefix.'notifications/'.$nid.'/read',$recipientBearer,'{}');
    check($ack[0]===200 && array_values(array_filter($model->notifications($recipient)['notifications'],
            static fn(array $n):bool=>$n['kind']==='message'))[0]['readAt']===5200,
        'M09 recipient acknowledges durable notification exactly once');
    $model->acknowledge($recipient,$nid);
    check(array_values(array_filter($model->notifications($recipient)['notifications'],
            static fn(array $n):bool=>$n['kind']==='message'))[0]['readAt']===5200,
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
    $moderator=new \Aeternum\Core\PdoSocialModerator($pdo,$world,$clock);
    $queue=$moderator->openReports();
    check(count($queue['reports'])===1 && $queue['reports'][0]['report_id']==='m09-report',
        'M09 operator-only report queue is durable and bounded');
    check($http->handle('GET',$prefix.'operator/reports',$ownerBearer)[0]===404,
        'M09 moderation queue has no public player HTTP endpoint');
    rejects(fn()=> $moderator->review('ci-operator','m09-report','delete','Invalid action'),
        InvalidArgumentException::class,'M09 invalid operator action rejected');
    rejects(fn()=> $moderator->review('ci-operator','missing-report','hide','No report'),
        DomainException::class,'M09 operator cannot fabricate a report');
    $moderator->review('ci-operator','m09-report','hide','Verified abusive mail');
    $moderator->review('ci-operator','m09-report','hide','Verified abusive mail');
    rejects(fn()=> $moderator->review('ci-operator','m09-report','dismiss','Conflicting action'),
        DomainException::class,'M09 immutable moderation decision cannot be reversed silently');
    rejects(fn()=> $moderator->review('other-operator','m09-report','hide','Verified abusive mail'),
        DomainException::class,'M09 moderation replay cannot change operator identity');
    check($model->inbox($recipient)['messages']===[],
        'M09 approved concealment hides reported message from recipient inbox');
    $audit=$pdo->prepare('SELECT operator_id,action,reason FROM engine_social_moderation_audit
        WHERE world_id=? AND report_id=?');
    $audit->execute([$world,'m09-report']);
    check($audit->fetchAll(PDO::FETCH_ASSOC)===[[
        'operator_id'=>'ci-operator','action'=>'hide','reason'=>'Verified abusive mail'
    ]],'M09 moderation records one immutable attribution and justification');
    check($moderator->openReports()['reports']===[],
        'M09 resolved report disappears from operator queue');
    $model->send($owner,$recipient,'m09-safe-mail','Legitimate informational message');
    $model->report($recipient,'m09-dismiss-report','m09-safe-mail','Review needed');
    // Test the separately permissioned CLI against the real disposable DB.
    $cliEnv=getenv();
    $cliEnv['AETERNUM_DSN']=getenv('AETERNUM_TEST_DSN');
    $cliEnv['AETERNUM_DB_USER']=getenv('AETERNUM_TEST_USER') ?: '';
    $cliEnv['AETERNUM_DB_PASSWORD']=getenv('AETERNUM_TEST_PASSWORD') ?: '';
    $cliEnv['AETERNUM_SOCIAL_MODERATOR']='';
    $cli=(static function(array $args,array $env,?string $input=null):array {
        $proc=proc_open(array_merge([PHP_BINARY,__DIR__.'/../bin/social-moderate.php'],$args),
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
        if(!is_resource($proc)) throw new RuntimeException('M09 operator CLI unavailable');
        if($input!==null) fwrite($pipes[0],$input);
        fclose($pipes[0]);
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        return [proc_close($proc),$stdout,$stderr];
    });
    [$exit]=$cli([$world,'queue'],$cliEnv);
    check($exit===1,'M09 CLI refuses operator identity missing in configuration');
    $cliEnv['AETERNUM_SOCIAL_MODERATOR']='ci-console-operator';
    [$exit,$stdout,$stderr]=$cli([$world,'queue'],$cliEnv);
    $listed=json_decode($stdout,true,8,JSON_THROW_ON_ERROR);
    check($exit===0 && $stderr==='' && count($listed['reports'])===1 &&
        $listed['reports'][0]['report_id']==='m09-dismiss-report',
        'M09 trusted operator CLI lists pending abusive-content reports');
    [$exit,$stdout,$stderr]=$cli([$world,'m09-dismiss-report','dismiss'],$cliEnv,"Content is permitted\n");
    check($exit===0 && $stderr==='' &&
        json_decode($stdout,true,8,JSON_THROW_ON_ERROR)['reviewed']===true,
        'M09 restricted CLI can dismiss a report with durable audit');
    check(count($model->inbox($recipient)['messages'])===1,
        'M09 dismissed report preserves allowed private message');
    $audit->execute([$world,'m09-dismiss-report']);
    check($audit->fetch(PDO::FETCH_ASSOC)===[
        'operator_id'=>'ci-console-operator','action'=>'dismiss','reason'=>'Content is permitted'
    ],'M09 CLI audit records operator and adjudication reason');
    (new PdoMemberships($pdo,$world))->revoke($recipient);
    check($http->handle('GET',$prefix.'messages',$recipientBearer)[0]===404
        && $http->handle('GET',$prefix.'notifications',$recipientBearer)[0]===404,
        'M09 revoked member loses messaging and notification read privileges');
    (new PdoMemberships($pdo,$world))->grant($recipient);
    check($http->handle('GET',$prefix.'notifications',$recipientBearer)[0]===200,
        'M09 reactivated member recovers previously persisted notices');
})();
