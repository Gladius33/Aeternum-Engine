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
    // Two independent officers racing for a vacant seat; world + alliance
    // row locks make exactly one winner without two owners or audit duplicates.
    $race='m09-recovery-race';
    $alliances->create($old,$race,'Concurrent recovery');
    foreach([$new,$guest] as $candidate) {
        $alliances->invite($old,$race,$candidate);
        $alliances->respond($candidate,$race,true);
        $alliances->setRole($old,$race,$candidate,'officer');
    }
    $worldMembers->revoke($old);
    $children=[];$env=getenv();$env['AETERNUM_M09_ACTION']='recover';
    foreach([$new,$guest] as $candidate) {
        $proc=proc_open([PHP_BINARY,__DIR__.'/m09-social-worker.php',$world,$race,$candidate],
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
        if(!is_resource($proc)) throw new RuntimeException('Recovery race worker unavailable');
        $children[]=[$proc,$pipes];
    }
    foreach($children as [, $pipes]) {
        fwrite($pipes[0],"go\n");fclose($pipes[0]);
    }
    $won=0;$lost=0;
    foreach($children as [$proc,$pipes]) {
        $out=trim((string)stream_get_contents($pipes[1]));
        $err=trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($proc)!==0)
            throw new RuntimeException('Governance race child: '.$err);
        if($out==='recovered')++$won;
        elseif($out==='denied')++$lost;
        else throw new RuntimeException('Unexpected recovery race result: '.$out);
    }
    check($won===1 && $lost===1,'M09 two concurrent officers elect precisely one active owner');
    $count=$pdo->prepare("SELECT COUNT(*) FROM engine_alliance_members
        WHERE world_id=? AND alliance_id=? AND role='owner'");
    $count->execute([$world,$race]);
    check((int)$count->fetchColumn()===1,'M09 one owner after competing independent MariaDB workers');
    $audit=$pdo->prepare('SELECT old_owner,new_owner FROM engine_alliance_owner_recoveries
        WHERE world_id=? AND alliance_id=?');
    $audit->execute([$world,$race]);
    $recoveries=$audit->fetchAll(PDO::FETCH_ASSOC);
    check(count($recoveries)===1 && $recoveries[0]['old_owner']===$old &&
        in_array($recoveries[0]['new_owner'],[$new,$guest],true),
        'M09 exactly one durable audit identifies winning recovery');
    $worldMembers->grant($old);
    $winner=$recoveries[0]['new_owner'];
    $roles=array_column($alliances->detailsFor($winner,$race)['members'],'role','actor');
    check($roles[$winner]==='owner' && $roles[$old]==='member',
        'M09 reinstated founder stays demoted after concurrent recovery');

})();
