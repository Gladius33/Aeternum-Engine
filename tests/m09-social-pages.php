<?php
declare(strict_types=1);
use Aeternum\Core\{PdoAlliances,PdoSocialMessages,SocialHttpApi,PdoSessions,FrozenClock};

// A dense, persistent alliance + invitation directory. The old code failed
// once >100 pending invitations existed; no HTTP rate limiter is bypassed.
(static function():void {
    $pdo=db();$clock=new FrozenClock(5200);
    $getId=static function(string $login)use($pdo):string {
        $q=$pdo->prepare('SELECT actor_id FROM engine_identities WHERE login=?');
        $q->execute([$login]);$v=$q->fetchColumn();
        if(!is_string($v)) throw new RuntimeException('Missing pagination identity');
        return $v;
    };
    $owner=$getId('m09-social-owner');
    $recipient=$getId('m09-social-officer');
    $world='m09-social-a';
    $alliances=new PdoAlliances($pdo,$world,$clock);
    for($i=0;$i<105;$i++) {
        $id=sprintf('m09-pages-%03d',$i);
        $alliances->create($owner,$id,'Paged alliance '.$i);
        $alliances->invite($owner,$id,$recipient);
    }
    $session=new PdoSessions($pdo,$clock);
    $bearer='Bearer '.$session->login('m09-social-officer',
        'M09 isolated disposable testing password');
    $api=new SocialHttpApi($pdo,$clock,[$world=>true]);
    $root='/v1/worlds/'.$world.'/social/';
    $traverse=static function(string $resource,string $key,int $pageLimit)use($api,$root,$bearer):array {
        $cursor=null;$found=[];$pages=0;
        do {
            $path=$resource.($cursor===null?'':'/page/'.$cursor);
            [$status,$body]=$api->handle('GET',$root.$path,$bearer);
            check($status===200,'M09 '.$resource.' keyset page authorized');
            $data=$body['data'];$rows=$data[$key];
            check(count($rows)<=$pageLimit,'M09 '.$resource.' bounded page size');
            foreach($rows as $entry) {
                $id=$entry['id']??$entry['alliance'];
                check(!isset($found[$id]),'M09 '.$resource.' no repeated cursor entry');
                $found[$id]=$entry;
            }
            if($data['nextAfter']!==null) {
                check(count($rows)===$pageLimit,'M09 intermediate page full');
                $last=$rows[count($rows)-1];
                check($data['nextAfter']===($last['id']??$last['alliance']),
                    'M09 continuation ID matches last emitted item');
            }
            $cursor=$data['nextAfter'];++$pages;
            if($pages>10) throw new RuntimeException('M09 directory cursor loop');
        } while($cursor!==null);
        check($pages>=2,'M09 '.$resource.' actually traverses multiple pages');
        return $found;
    };
    $directory=$traverse('alliances','alliances',50);
    $invitations=$traverse('invitations','invitations',100);
    // Rank ties are not a pagination boundary. All alliance IDs must appear
    // exactly once with their SQL-derived ordinal rank across pages.
    $cursor=null;$ranked=[];$pages=0;$lastCount=null;$lastId=null;
    do {
        $path='leaderboard'.($cursor===null?'':'/page/'.$cursor['activeMembers'].'/'.$cursor['id']);
        [$status,$answer]=$api->handle('GET',$root.$path,$bearer);
        check($status===200,'M09 paginated leaderboard accessible to active member');
        $rows=$answer['data']['alliances'];
        check(count($rows)<=50,'M09 leaderboard pages bounded to 50');
        foreach($rows as $row) {
            check(!isset($ranked[$row['id']]),'M09 ranking page does not repeat alliance ID');
            check($row['rank']===count($ranked)+1,'M09 rank is computed by SQL, not cursor offset');
            if($lastCount!==null) check(
                $row['activeMembers']<$lastCount ||
                ($row['activeMembers']===$lastCount && strcmp($row['id'],$lastId)>0),
                'M09 ranked ordering by active members and stable ID ties');
            $ranked[$row['id']]=$row;
            $lastCount=$row['activeMembers'];$lastId=$row['id'];
        }
        $cursor=$answer['data']['nextAfter'];++$pages;
        if($pages>10)throw new RuntimeException('M09 ranking page loop');
    } while($cursor!==null);
    check($pages>=3 && count($ranked)>=105,'M09 ranked world completely traversed');
    check($api->handle('GET',$root.'leaderboard/page/9999999999999999999/m09-pages-000',$bearer)[0]===400,
        'M09 overflowing rank cursor rejected');

    for($i=0;$i<105;$i++) {
        $id=sprintf('m09-pages-%03d',$i);
        check(isset($directory[$id]) && isset($invitations[$id]),
            'M09 paginated directory and invitation retain common alliance '.$i);
    }
    check(count($invitations)>=105,'M09 no 100-entry lifetime invitation cap');
    $crew=$api->handle('GET',$root.'alliances/crew',$bearer);
    check($crew[0]===200 && array_key_exists('nextAfter',$crew[1]['data']),
        'M09 private roster advertises a stable continuation field');
    $first=$crew[1]['data']['members'][0]['actor'];
    $roster=$api->handle('GET',$root.'alliances/crew/members/page/'.$first,$bearer);
    check($roster[0]===200 &&
        count($roster[1]['data']['members'])<=count($crew[1]['data']['members']),
        'M09 member-page endpoint remains role authorized and bounded');
    $inboxOwner='Bearer '.$session->login('m09-social-owner',
        'M09 isolated disposable testing password');
    check($api->handle('GET',$root.'invitations/page/m09-pages-050',$inboxOwner)[1]['data']['invitations']===[],
        'M09 invitation cursor does not grant access to another actor inbox');
    $mail=new PdoSocialMessages($pdo,$world,$clock);
    $mail->block($recipient,$owner);
    check($api->handle('GET',$root.'invitations',$bearer)[1]['data']['invitations']===[],
        'M09 blocking filters pending invitations before pagination');
    check($api->handle('GET',$root.'blocks',$bearer)[1]['data']['actors']===[$owner],
        'M09 blocking projection is actor private');
    $mail->unblock($recipient,$owner);
    check(count($api->handle('GET',$root.'invitations',$bearer)[1]['data']['invitations'])===100,
        'M09 unblocking restores first pending invitation page without duplicates');
})();
