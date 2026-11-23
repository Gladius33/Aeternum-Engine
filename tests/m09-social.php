<?php
declare(strict_types=1);
use Aeternum\Core\{PdoAlliances,SocialHttpApi,PdoMemberships,PdoIdentity,PdoScheduler,PdoSessions,FrozenClock};

(static function(): void {
// Exercise real 028 tables on two persistent worlds and independently
// authenticated identities. Never rely on test HTTP mocks or in-memory state.
$swA='m09-social-a'; $swB='m09-social-b';
$sc=new FrozenClock(5200);
(new PdoScheduler(db(),$swA,$sc))->initialize();
(new PdoScheduler(db(),$swB,$sc))->initialize();
$identities=new PdoIdentity(db());
$secret='M09 isolated disposable testing password';
$so=$identities->register('m09-social-owner',$secret);
$sf=$identities->register('m09-social-officer',$secret);
$sg=$identities->register('m09-social-guest',$secret);
$sr=$identities->register('m09-social-race',$secret);
$ma=new PdoMemberships(db(),$swA);
$mb=new PdoMemberships(db(),$swB);
foreach ([$so,$sf,$sg,$sr] as $socialActor) $ma->grant($socialActor);
foreach ([$so,$sf] as $socialActor) $mb->grant($socialActor);
$sessions=new PdoSessions(db(),$sc);
$auth=[
    $so=>'Bearer '.$sessions->login('m09-social-owner',$secret),
    $sf=>'Bearer '.$sessions->login('m09-social-officer',$secret),
    $sg=>'Bearer '.$sessions->login('m09-social-guest',$secret),
    $sr=>'Bearer '.$sessions->login('m09-social-race',$secret)
];
$sapi=new SocialHttpApi(db(),$sc,[$swA=>true,$swB=>true]);
$surl=static fn(string $world,string $suffix):string=>'/v1/worlds/'.$world.'/social/'.$suffix;
$sget=static fn(string $actor,string $world,string $suffix):array=>
    $sapi->handle('GET',$surl($world,$suffix),$auth[$actor]);
$spost=static fn(string $actor,string $world,string $suffix,array $payload=[]):array=>
    $sapi->handle('POST',$surl($world,$suffix),$auth[$actor],
        $payload===[]?'{}':json_encode($payload,JSON_THROW_ON_ERROR));

check($sapi->handle('GET',$surl($swA,'alliances'),'')[0]===401,
    'M09 no anonymous alliance enumeration');
check($sget($so,'not-approved','alliances')[0]===404,
    'M09 unapproved world cannot be enumerated');
check($sapi->handle('DELETE',$surl($swA,'alliances'),$auth[$so])[0]===405,
    'M09 disallowed methods fail closed');
check($sapi->handle('POST',$surl($swA,'alliances'),$auth[$so],'{broken')[0]===400,
    'M09 malformed JSON is rejected');
check($sapi->handle('POST',$surl($swA,'alliances'),$auth[$so],'[]')[0]===400,
    'M09 list-shaped payload rejected');
check($sapi->handle('POST',$surl($swA,'alliances'),$auth[$so],
    '{"id":"crew","name":"Crew","actor":"spoof"}')[0]===400,
    'M09 caller actor spoofing payload cannot pass strict schema');
check($spost($so,$swA,'alliances',['id'=>'crew','name'=>'Crew'])[0]===200,
    'M09 authenticated alliance creation');
check($spost($so,$swA,'alliances',['id'=>'crew','name'=>'Crew'])[0]===200,
    'M09 identical creation retry is idempotent');
check($spost($sf,$swA,'alliances',['id'=>'crew','name'=>'Stolen'])[0]===409,
    'M09 same-world alliance ID cannot be hijacked');
check($spost($so,$swB,'alliances',['id'=>'crew','name'=>'Other world'])[0]===200,
    'M09 same alliance ID permitted independently in a second world');
check($sget($sg,$swA,'alliances/crew')[0]===404,
    'M09 nonmember cannot read private roles and identities');
check($sget($sg,$swA,'alliances')[1]['data']['alliances'][0]['members']===1,
    'M09 public directory exposes only name and aggregate member count');
check($sget($sf,$swB,'alliances')[1]['data']['alliances'][0]['name']==='Other world',
    'M09 directory never mixes same IDs across worlds');
check($sget($sg,$swB,'alliances')[0]===404,
    'M09 world membership cannot be borrowed from another world');
check($spost($sf,$swA,'alliances/crew/invite',['actor'=>$sg])[0]===409,
    'M09 nonofficer cannot invite players');
check($spost($so,$swA,'alliances/crew/invite',['actor'=>$sf])[0]===200,
    'M09 owner creates durable invitation');
check(count($sget($sf,$swA,'invitations')[1]['data']['invitations'])===1
    && $sget($sg,$swA,'invitations')[1]['data']['invitations']===[],
    'M09 invitation inbox is scoped to its recipient');
check($spost($sg,$swA,'alliances/crew/respond',['accept'=>true])[0]===409,
    'M09 uninvited actor cannot self-admit');
check($spost($sf,$swB,'alliances/crew/respond',['accept'=>true])[0]===409,
    'M09 invitation cannot cross worlds');
check($spost($sf,$swA,'alliances/crew/respond',['accept'=>true])[0]===200,
    'M09 invited actor can accept');
check($spost($sf,$swA,'alliances/crew/respond',['accept'=>true])[0]===200,
    'M09 repeated acceptance does not duplicate membership');
check($sget($sf,$swA,'invitations')[1]['data']['invitations']===[],
    'M09 accepted invitation disappears from pending inbox');
check($spost($sf,$swA,'alliances/crew/role',['actor'=>$so,'role'=>'member'])[0]===409,
    'M09 nonowner cannot demote owner');
check($spost($so,$swA,'alliances/crew/role',['actor'=>$sf,'role'=>'officer'])[0]===200,
    'M09 owner grants officer role');
check($spost($sf,$swA,'alliances/crew/invite',['actor'=>$sg])[0]===200,
    'M09 officer may invite world member');
check($spost($so,$swA,'alliances/crew/revoke',['actor'=>$sg])[0]===200,
    'M09 owner may revoke a pending invitation');
check($spost($sg,$swA,'alliances/crew/respond',['accept'=>true])[0]===409,
    'M09 revoked invitation cannot be accepted');
check($spost($sf,$swA,'alliances/crew/invite',['actor'=>$sg])[0]===200,
    'M09 revoked invitation can be reissued');
check($spost($sg,$swA,'alliances/crew/respond',['accept'=>true])[0]===200,
    'M09 reissued invitation is valid');
check($spost($sf,$swA,'alliances/crew/remove',['actor'=>$so])[0]===409,
    'M09 officer cannot kick owner');
check($spost($sf,$swA,'alliances/crew/remove',['actor'=>$sg])[0]===200,
    'M09 officer can remove member');
check($sget($sg,$swA,'alliances/crew')[0]===404,
    'M09 kicked actor loses private alliance access');
check($spost($so,$swA,'alliances/crew/invite',['actor'=>$sg])[0]===200,
    'M09 accepted invite can be renewed after member removal');
check($spost($sg,$swA,'alliances/crew/respond',['accept'=>true])[0]===200,
    'M09 expelled player can rejoin only after a new invitation');
check($spost($so,$swA,'alliances/crew/owner',['actor'=>$sf])[0]===200,
    'M09 ownership transfer is atomic');
check($spost($so,$swA,'alliances/crew/role',['actor'=>$sg,'role'=>'officer'])[0]===409,
    'M09 former owner loses owner-only rights immediately');
check($spost($sf,$swA,'alliances/crew/role',['actor'=>$sg,'role'=>'officer'])[0]===200,
    'M09 new owner can exercise role management');
check($spost($sf,$swA,'alliances/crew/leave')[0]===409,
    'M09 owner cannot leave and orphan an alliance');
check($spost($so,$swA,'alliances/crew/leave')[0]===200
    && $spost($so,$swA,'alliances/crew/leave')[0]===200,
    'M09 member leave is idempotent');
$detail=$sget($sf,$swA,'alliances/crew');
$owners=array_values(array_filter($detail[1]['data']['members'],
    static fn(array $entry):bool=>$entry['role']==='owner'));
check($detail[0]===200 && count($owners)===1 && $owners[0]['actor']===$sf,
    'M09 single owner retained after handover and member departure');
$ma->revoke($sf);
$ranking=$sget($so,$swA,'leaderboard');
$rankedCrew=array_values(array_filter($ranking[1]['data']['alliances'],
    static fn(array $entry):bool=>$entry['id']==='crew'));
check($ranking[0]===200 && count($rankedCrew)===1
    && $rankedCrew[0]['activeMembers']===1,
    'M09 leaderboard excludes revoked world members from reported active count');
check($sget($sf,$swA,'alliances/crew')[0]===404
    && $spost($sf,$swA,'alliances/crew/invite',['actor'=>$so])[0]===409,
    'M09 revoked world membership disables old bearer social privileges');
$ma->grant($sf);
check($sget($sf,$swA,'alliances/crew')[0]===200,
    'M09 existing ownership persists after world membership reactivation');

check($spost($so,$swA,'alliances',['id'=>'racecrew','name'=>'Race crew'])[0]===200,
    'M09 persistent independent invitation race fixture');
check($spost($so,$swA,'alliances/racecrew/invite',['actor'=>$sr])[0]===200,
    'M09 concurrent accept race invitation prepared');
$children=[];
for ($i=0;$i<4;$i++) {
    $p=proc_open([PHP_BINARY,__DIR__.'/m09-social-worker.php',$swA,'racecrew',$sr],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($p)) throw new RuntimeException('M09 unable to start separate DB worker');
    $children[]=[$p,$pipes];
}
foreach ($children as [, $pipes]) { fwrite($pipes[0],"go\n"); fclose($pipes[0]); }
foreach ($children as [$process,$pipes]) {
    $out=trim((string)stream_get_contents($pipes[1]));
    $err=trim((string)stream_get_contents($pipes[2]));
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process)!==0 || $out!=='accepted')
        throw new RuntimeException('M09 concurrent invitation acceptance failed: '.$err);
}
$rc=db()->prepare('SELECT COUNT(*) FROM engine_alliance_members WHERE world_id=? AND alliance_id=? AND actor_id=?');
$rc->execute([$swA,'racecrew',$sr]);
check((int)$rc->fetchColumn()===1,
    'M09 four independent competing connections produce precisely one durable membership');
$ri=db()->prepare('SELECT status FROM engine_alliance_invites WHERE world_id=? AND alliance_id=? AND invited_actor=?');
$ri->execute([$swA,'racecrew',$sr]);
check($ri->fetchColumn()==='accepted',
    'M09 invitation remains accepted after concurrent retries');
check($sget($sr,$swA,'alliances/racecrew')[0]===200,
    'M09 committed concurrent membership is visible after reconnect');

$createContenders=[$so,$sf,$sg,$sr];
$createProcesses=[];
foreach($createContenders as $contender) {
    $env=getenv();
    $env['AETERNUM_M09_ACTION']='create';
    $child=proc_open([PHP_BINARY,__DIR__.'/m09-social-worker.php',$swA,'shared-create-race',$contender],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$streams,null,$env);
    if (!is_resource($child)) throw new RuntimeException('M09 rival creator worker unavailable');
    $createProcesses[]=[$child,$streams];
}
foreach ($createProcesses as [, $streams]) { fwrite($streams[0],"go\n"); fclose($streams[0]); }
$created=0;$denied=0;
foreach ($createProcesses as [$proc,$streams]) {
    $output=trim((string)stream_get_contents($streams[1]));
    $error=trim((string)stream_get_contents($streams[2]));
    fclose($streams[1]);fclose($streams[2]);
    if(proc_close($proc)!==0) throw new RuntimeException('M09 rival create worker: '.$error);
    if($output==='created') ++$created;
    elseif($output==='denied') ++$denied;
    else throw new RuntimeException('Unexpected rival creation worker response: '.$output);
}
check($created===1 && $denied===3,
    'M09 four independent rival founders commit exactly one creation');
$creatorCount=db()->prepare('SELECT COUNT(*) FROM engine_alliance_members
    WHERE world_id=? AND alliance_id=? AND role=?');
$creatorCount->execute([$swA,'shared-create-race','owner']);
check((int)$creatorCount->fetchColumn()===1,
    'M09 rival creation retains one authoritative alliance owner');


})();
