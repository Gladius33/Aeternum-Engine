<?php
declare(strict_types=1);
use Aeternum\Core\{PdoAlliances,PdoSocialMessages,PdoIdentity,PdoMemberships,PdoSessions,SocialHttpApi,FrozenClock};

// Persistent MariaDB cardinality + authorization test, not an HTTP mock.
// One valid Argon2id hash is reused only among isolated disposable test
// identities to avoid running 106 expensive independent hash derivations.
(static function():void {
    $pdo=db();$clock=new FrozenClock(5200);$world='m09-social-a';
    $q=$pdo->prepare('SELECT actor_id FROM engine_identities WHERE login=?');
    $q->execute(['m09-social-owner']);$owner=$q->fetchColumn();
    if (!is_string($owner))throw new RuntimeException('Missing M09 bulk owner');
    $hash=PdoIdentity::hashNewPassword('M09 isolated disposable testing password');
    $seed=$pdo->prepare('INSERT INTO engine_identities(actor_id,login,password_hash) VALUES (?,?,?)');
    $memberships=new PdoMemberships($pdo,$world);
    $alliances=new PdoAlliances($pdo,$world,$clock);
    $messages=new PdoSocialMessages($pdo,$world,$clock);
    $alliance='m09-bulk-roster';
    $alliances->create($owner,$alliance,'Large alliance roster');
    $actors=[];
    for($i=0;$i<105;$i++) {
        $actor=bin2hex(random_bytes(32));
        $seed->execute([$actor,sprintf('m09-bulk-%03d',$i),$hash]);
        $memberships->grant($actor);
        $alliances->invite($owner,$alliance,$actor);
        $alliances->respond($actor,$alliance,true);
        $actors[]=$actor;
    }
    $auth='Bearer '.(new PdoSessions($pdo,$clock))->login('m09-social-owner',
        'M09 isolated disposable testing password');
    $api=new SocialHttpApi($pdo,$clock,[$world=>true,'m09-social-b'=>true]);
    $prefix='/v1/worlds/'.$world.'/social/';
    [$code,$first]=$api->handle('GET',$prefix.'alliances/'.$alliance,$auth);
    check($code===200 && count($first['data']['members'])===100 &&
        $first['data']['nextAfter']!==null,
        'M09 private roster first page is exactly 100 authenticated active members');
    $after=$first['data']['nextAfter'];
    [$code,$second]=$api->handle('GET',
        $prefix.'alliances/'.$alliance.'/members/page/'.$after,$auth);
    $both=array_merge($first['data']['members'],$second['data']['members']);
    $ids=array_column($both,'actor');
    check($code===200 && count($second['data']['members'])===6 &&
        $second['data']['nextAfter']===null && count(array_unique($ids))===106,
        'M09 106-person roster fully traversed without duplicate or missing members');
    check(in_array($owner,$ids,true),'M09 owner included exactly once in large roster');

    foreach($actors as $actor)$messages->block($owner,$actor);
    [$code,$blocked]=$api->handle('GET',$prefix.'blocks',$auth);
    check($code===200 && count($blocked['data']['actors'])===100 &&
        $blocked['data']['nextAfter']!==null,
        'M09 private block list displays bounded first 100');
    [$code,$next]=$api->handle('GET',
        $prefix.'blocks/page/'.$blocked['data']['nextAfter'],$auth);
    $blockedIds=array_merge($blocked['data']['actors'],$next['data']['actors']);
    sort($blockedIds);$reference=$actors;sort($reference);
    check($code===200 && count($next['data']['actors'])===5 &&
        $next['data']['nextAfter']===null && $blockedIds===$reference,
        'M09 105 blocked accounts fully traversed with no leak or duplication');
    $recipientAuth='Bearer '.(new PdoSessions($pdo,$clock))->login('m09-social-officer',
        'M09 isolated disposable testing password');
    [$code,$foreign]=$api->handle('GET',$prefix.'blocks',$recipientAuth);
    check($code===200 && $foreign['data']['actors']===[],
        'M09 another actor cannot read first actor blocking policy');
    $outsider=$api->handle('GET',
        '/v1/worlds/m09-social-b/social/alliances/'.$alliance,$recipientAuth);
    check($outsider[0]===409 || $outsider[0]===404,
        'M09 private roster not readable in another world');

    // Fresh user, real session and real server-side rate bucket.
    $spammer=bin2hex(random_bytes(32));
    $seed->execute([$spammer,'m09-spam-probe',$hash]);
    $memberships->grant($spammer);
    $spamAuth='Bearer '.(new PdoSessions($pdo,$clock))->login(
        'm09-spam-probe','M09 isolated disposable testing password');
    for($i=0;$i<30;$i++) {
        $body=json_encode(['id'=>sprintf('m09-spam-%03d',$i),
            'recipient'=>$owner,'text'=>'Rate limiter positive '.$i],JSON_THROW_ON_ERROR);
        check($api->handle('POST',$prefix.'messages',$spamAuth,$body)[0]===200,
            'M09 capped authenticated message write '.$i);
    }
    $last=json_encode(['id'=>'m09-spam-030','recipient'=>$owner,
        'text'=>'Must be denied'],JSON_THROW_ON_ERROR);
    check($api->handle('POST',$prefix.'messages',$spamAuth,$last)[0]===429,
        'M09 31st actor write in same minute rejected before persistence');
    $q=$pdo->prepare("SELECT COUNT(*) FROM engine_social_messages WHERE world_id=?
        AND sender_actor=? AND message_id LIKE 'm09-spam-%'");
    $q->execute([$world,$spammer]);
    check((int)$q->fetchColumn()===30,'M09 rate-limited attempted write creates no hidden 31st message');
})();
