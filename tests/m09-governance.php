<?php
declare(strict_types=1);
use Aeternum\Core\{PdoAlliances,PdoMemberships,SocialHttpApi,PdoSessions,FrozenClock};

// Revoked-owner handover must be atomic, auditable and resistant to replay.
(static function():void {
    $pdo=db();$world='m09-social-a';$clock=new FrozenClock(5200);
    $q=$pdo->prepare('SELECT actor_id FROM engine_identities WHERE login=?');
    $get=static function(string $login)use($q):string {
        $q->execute([$login]);$id=$q->fetchColumn();
        if(!is_string($id)) throw new RuntimeException('Missing governance identity');
        return $id;
    };
    $old=$get('m09-social-owner');$new=$get('m09-social-officer');$guest=$get('m09-social-guest');
    $alliances=new PdoAlliances($pdo,$world,$clock);
    $alliances->create($old,'m09-recovery','Recovery fixture');
    $alliances->invite($old,'m09-recovery',$new);
    $alliances->respond($new,'m09-recovery',true);
    $alliances->setRole($old,'m09-recovery',$new,'officer');
    $bearer='Bearer '.(new PdoSessions($pdo,$clock))->login('m09-social-officer',
        'M09 isolated disposable testing password');
    $api=new SocialHttpApi($pdo,$clock,[$world=>true,'m09-social-b'=>true]);
    $path='/v1/worlds/'.$world.'/social/alliances/m09-recovery/recover';
    check($api->handle('POST',$path,$bearer,'{}')[0]===409,
        'M09 active world owner cannot be displaced');
    $worldMembers=new PdoMemberships($pdo,$world);
    $worldMembers->revoke($old);
    check($api->handle('POST',$path,'','{}')[0]===401,
        'M09 ownership recovery requires real authentication');
    check($api->handle('POST',$path,$bearer,'{"actor":"spoof"}')[0]===400,
        'M09 ownership recovery never trusts actor payload');
    check($api->handle('POST',$path,$bearer,'{}')[0]===200,
        'M09 active officer recovers alliance from revoked owner');
    check($api->handle('POST',$path,$bearer,'{}')[0]===200,
        'M09 recovered owner retry has no duplicate side effect');
    $q=$pdo->prepare('SELECT old_owner,new_owner FROM engine_alliance_owner_recoveries
        WHERE world_id=? AND alliance_id=?');
    $q->execute([$world,'m09-recovery']);
    check($q->fetchAll(PDO::FETCH_ASSOC)===[['old_owner'=>$old,'new_owner'=>$new]],
        'M09 one durable governance audit on recovery and replay');
    $worldMembers->grant($old);
    $details=$alliances->detailsFor($new,'m09-recovery');
    $roles=array_column($details['members'],'role','actor');
    check($roles[$new]==='owner' && $roles[$old]==='member',
        'M09 former owner returns without elevated privileges');
    rejects(fn()=> $alliances->recoverRevokedOwner($guest,'m09-recovery'),
        DomainException::class,'M09 nonofficer cannot seize a recovered alliance');
    check($api->handle('POST',
        '/v1/worlds/m09-social-b/social/alliances/m09-recovery/recover',
        $bearer,'{}')[0]===409,'M09 foreign world cannot borrow recovery powers');
})();
