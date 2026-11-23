<?php
declare(strict_types=1);
use Aeternum\Core\{PdoSocialMessages,SocialHttpApi,PdoSessions,FrozenClock};

// Exercise real MariaDB index/keyset semantics: all 115 sends use the SAME
// timestamp, so timestamp-only pagination would silently lose most records.
(static function():void {
    $pdo=db();
    $clock=new FrozenClock(5200);
    $actorByLogin=static function(string $login)use($pdo):string {
        $q=$pdo->prepare('SELECT actor_id FROM engine_identities WHERE login=?');
        $q->execute([$login]);
        $actor=$q->fetchColumn();
        if (!is_string($actor)) throw new RuntimeException('Missing M09 pagination actor');
        return $actor;
    };
    $sender=$actorByLogin('m09-social-owner');
    $recipient=$actorByLogin('m09-social-officer');
    $world='m09-social-a';
    $model=new PdoSocialMessages($pdo,$world,$clock);
    for ($i=0;$i<115;$i++) {
        $id=sprintf('m09-page-%03d',$i);
        $model->send($sender,$recipient,$id,'Page content '.$i);
    }
    // Replaying a page record cannot create duplicate mail or notification.
    $model->send($sender,$recipient,'m09-page-114','Page content 114');
    $sessions=new PdoSessions($pdo,$clock);
    $bearer='Bearer '.$sessions->login('m09-social-officer',
        'M09 isolated disposable testing password');
    $api=new SocialHttpApi($pdo,$clock,[$world=>true,'m09-social-b'=>true]);
    $root='/v1/worlds/'.$world.'/social/';
    $collect=static function(string $kind)use($api,$root,$bearer):array {
        $cursor=null;$seen=[];$pages=0;$lastTime=null;$lastId=null;
        do {
            $suffix=$kind.($cursor===null?'':'/page/'.$cursor['createdAt'].'/'.$cursor['id']);
            [$status,$payload]=$api->handle('GET',$root.$suffix,$bearer);
            check($status===200,'M09 paginated '.$kind.' accepted by authenticated API');
            $page=$payload['data'];
            $items=$page[$kind];
            check(count($items)<=50 && $page['truncated']===($page['nextAfter']!==null),
                'M09 '.$kind.' uses bounded pages and truthful continuation');
            foreach ($items as $item) {
                $id=$item['id'];$time=$item['createdAt'];
                check(!isset($seen[$id]),'M09 no duplicate across '.$kind.' pages');
                if ($lastTime!==null) {
                    check($time<$lastTime || ($time===$lastTime && strcmp($id,$lastId)<0),
                        'M09 '.$kind.' cursor preserves total descending order with timestamp ties');
                }
                $seen[$id]=$item;$lastTime=$time;$lastId=$id;
            }
            $cursor=$page['nextAfter'];
            if ($cursor!==null) {
                check(count($items)===50 && $cursor===['createdAt'=>$lastTime,'id'=>$lastId],
                    'M09 continuation corresponds to the last emitted visible item');
            }
            ++$pages;
            if ($pages>10) throw new RuntimeException('M09 pagination loop did not terminate');
        } while($cursor!==null);
        check($pages>=3,'M09 more than one complete social inbox page was traversed');
        return $seen;
    };
    $mail=$collect('messages');
    $notices=$collect('notifications');
    for ($i=0;$i<115;$i++) {
        $id=sprintf('m09-page-%03d',$i);
        check(isset($mail[$id]) && $mail[$id]['text']==='Page content '.$i,
            'M09 persistent message survives complete cursor traversal '.$i);
        $notification=hash('sha256',$world.'|message|'.$id);
        check(isset($notices[$notification]) && $notices[$notification]['subject']===$id,
            'M09 exactly one durable notification per message '.$i);
    }
    check($api->handle('GET',$root.'messages/page/9999999999999999999/m09-page-114',$bearer)[0]===400,
        'M09 overlong timestamp cursor rejected without integer overflow');
    check($api->handle('GET',$root.'notifications/page/5200/%2F',$bearer)[0]===404,
        'M09 malformed notification cursor never accepted');
    check($api->handle('GET',$root.'messages/page/5200/m09-page-114','')[0]===401,
        'M09 cursor cannot bypass bearer authentication');
    $foreign=$api->handle('GET',
        '/v1/worlds/m09-social-b/social/messages/page/5200/m09-page-114',$bearer);
    check($foreign[0]===200 && $foreign[1]['data']['messages']===[],
        'M09 cursor never leaks messages into another configured world');
    check($model->inbox($sender)['messages']===[],
        'M09 sender cannot read another recipient inbox through model');
})();
