<?php
declare(strict_types=1);
use Aeternum\Core\{PdoScheduler,PdoIdentity,PdoMemberships,PdoSessions,PdoAdminConsole,
    PremiumHttpApi,FrozenClock};

// Independent disposable M10 worlds. No social fixture, mock gateway,
// external provider, production account or real monetary transaction.
(static function():void {
    $pdo=db();$clock=new FrozenClock(9100);
    $world='m10-console-a';$other='m10-console-b';
    (new PdoScheduler($pdo,$world,$clock))->initialize();
    (new PdoScheduler($pdo,$other,$clock))->initialize();
    $identity=new PdoIdentity($pdo);
    $actor=$identity->register('m10-cosmetic-user','M10 isolated testing password');
    $stranger=$identity->register('m10-foreign-user','M10 isolated testing password');
    $membership=new PdoMemberships($pdo,$world);
    $membership->grant($actor);$membership->grant($stranger);
    (new PdoMemberships($pdo,$other))->grant($actor);
    // The trusted setup tool writes these grants. This test directly seeds
    // them, avoiding a fake public endpoint that could grant operator roles.
    $grant=$pdo->prepare('INSERT INTO engine_admin_scopes
        (world_id,operator_id,role,active,changed_at) VALUES (?,?,?,1,?)');
    $grant->execute([$world,'m10-view','viewer',9100]);
    $grant->execute([$world,'m10-manager','manager',9100]);
    $grant->execute([$other,'m10-other-manager','manager',9100]);
    $admin=new PdoAdminConsole($pdo,$world,$clock);
    $foreign=new PdoAdminConsole($pdo,$other,$clock);
    $status=$admin->describe('m10-view');
    check($status['world']===$world && $status['activeMembers']===2
        && $status['rulesKey']==='legacy',
        'M10 world-scoped viewer sees pinned rules and active member count');
    $directory=$admin->members('m10-view');
    check(count($directory['members'])===2 && $directory['nextAfter']===null,
        'M10 viewer can list only two world-specific memberships');
    $detail=$admin->identity('m10-view',$actor);
    check($detail['world']===$world && $detail['login']==='m10-cosmetic-user'
        && $detail['active']===true && $detail['accounts']===[],
        'M10 private console inspects existing world identity without cross-world accounts');
    rejects(fn()=> $foreign->identity('m10-other-manager',$stranger),
        DomainException::class,'M10 another world cannot disclose an actor absent there');
    rejects(fn()=> $admin->identity('m10-view','unknown-m10-id'),
        DomainException::class,'M10 console identity inspection is fail-closed for absent actor');
    rejects(fn()=> $admin->describe('m10-other-manager'),DomainException::class,
        'M10 an operator scoped to a different world cannot inspect this world');
    rejects(fn()=> $admin->membership('m10-view','m10-deny',''.$actor,false),
        DomainException::class,'M10 viewer cannot revoke identity membership');
    rejects(fn()=> $admin->testPayment('m10-view','m10-deny-pay',$actor,'profile_badge','grant'),
        DomainException::class,'M10 viewer cannot mint cosmetic test entitlement');
    rejects(fn()=> $foreign->testPayment('m10-manager','m10-no-cross',$actor,'profile_badge','grant'),
        DomainException::class,'M10 manager cannot grant premium in an unauthorized world');
    rejects(fn()=> $admin->testPayment('m10-manager','m10-false',$actor,'game_speed','grant'),
        InvalidArgumentException::class,'M10 gameplay-affecting premium entitlement is forbidden');
    rejects(fn()=> $admin->testPayment('m10-manager','m10-real',$actor,'ui_theme','charge'),
        InvalidArgumentException::class,'M10 no real payment event transitions');
    $token='Bearer '.(new PdoSessions($pdo,$clock))->login(
        'm10-cosmetic-user','M10 isolated testing password');
    check(preg_match('/^Bearer [a-f0-9]{64}$/D',$token)===1,
        'M10 authenticated actor has a distinct read-only premium session');
    $api=new PremiumHttpApi($pdo,$clock,[$world=>true,$other=>true]);
    $url='/v1/worlds/'.$world.'/premium/me';
    check($api->handle('GET',$url,$token)===[200,['data'=>[
        'world'=>$world,'features'=>[],'testModeOnly'=>true
    ]]],'M10 no premium unlocked before explicitly tested event');
    check($api->handle('POST',$url,$token)[0]===405,
        'M10 public premium API has no payment or entitlement write method');
    check($api->handle('GET',$url,'')[0]===401,
        'M10 premium projection refuses unauthenticated access');
    check($api->handle('GET','/v1/worlds/'.$world.'/premium/'.$stranger,$token)[0]===404,
        'M10 a player cannot read another actor premium projection');
    check($admin->testPayment('m10-manager','m10-event-1',$actor,'profile_badge','grant'),
        'M10 trusted manager applies one explicit cosmetic test event');
    check($admin->testPayment('m10-manager','m10-event-1',$actor,'profile_badge','grant')===false,
        'M10 exact test event replay does not create another effect');
    rejects(fn()=> $admin->testPayment('m10-manager','m10-event-1',$actor,'ui_theme','grant'),
        DomainException::class,'M10 a conflicting event ID cannot be retargeted');
    rejects(fn()=> $admin->testPayment('m10-manager','m10-event-1',$stranger,'profile_badge','grant'),
        DomainException::class,'M10 event ID cannot be replayed onto a different actor');
    rejects(fn()=> $admin->testPayment('m10-other-manager','m10-event-1',$actor,'profile_badge','grant'),
        DomainException::class,'M10 no operator-identity substitution');
    check($api->handle('GET',$url,$token)[1]['data']['features']===['profile_badge'],
        'M10 granted cosmetic badge appears in own world only');
    check($api->handle('GET','/v1/worlds/'.$other.'/premium/me',$token)[1]['data']['features']===[],
        'M10 same actor sees no feature leak into foreign world');
    check($admin->testPayment('m10-manager','m10-event-2',$actor,'ui_theme','grant'),
        'M10 independent optional cosmetic feature granted');
    check($api->handle('GET',$url,$token)[1]['data']['features']===['profile_badge','ui_theme'],
        'M10 two cosmetic entitlements sort deterministically');
    check($admin->testPayment('m10-manager','m10-event-3',$actor,'profile_badge','revoke'),
        'M10 cosmetic badge can be revoked by distinct test event');
    check($admin->testPayment('m10-manager','m10-event-3',$actor,'profile_badge','revoke')===false,
        'M10 simulated reversal is idempotent');
    check($api->handle('GET',$url,$token)[1]['data']['features']===['ui_theme'],
        'M10 revoked badge immediately disappears without refund or economic mutation');
    check($admin->membership('m10-manager','m10-member-stop',$actor,false),
        'M10 manager can revoke actor membership inside assigned world');
    check($admin->membership('m10-manager','m10-member-stop',$actor,false)===false,
        'M10 membership revocation request has safe exact replay');
    check($api->handle('GET',$url,$token)[0]===404,
        'M10 existing bearer loses premium read on world membership revocation');
    rejects(fn()=> $admin->testPayment('m10-manager','m10-inactive',$actor,'ui_theme','grant'),
        DomainException::class,'M10 no new test grants to revoked world members');
    check($api->handle('GET','/v1/worlds/'.$other.'/premium/me',$token)[0]===200,
        'M10 revoking first world does not revoke membership in independent world');
    check($admin->membership('m10-manager','m10-member-return',$actor,true),
        'M10 manager can restore an existing identity world membership');
    check($api->handle('GET',$url,$token)[1]['data']['features']===['ui_theme'],
        'M10 world reactivation recovers stored surviving cosmetic feature');
    $visitor=$admin->audit('m10-view');
    check(count($visitor['entries'])===5 && $visitor['nextAfter']===null,
        'M10 audit records unique test entitlements and membership changes only');
    check(array_column($visitor['entries'],'operator_id')===array_fill(0,5,'m10-manager'),
        'M10 every audited action is attributed to the authorized manager');
    $count=$pdo->prepare('SELECT COUNT(*) FROM engine_test_payment_events
        WHERE world_id=? AND actor_id=?');
    $count->execute([$world,$actor]);
    check((int)$count->fetchColumn()===3,'M10 three durable TEST-ONLY events despite replay attempts');
    $foreign->testPayment('m10-other-manager','m10-event-1',$actor,'profile_badge','grant');
    check($api->handle('GET','/v1/worlds/'.$other.'/premium/me',$token)[1]['data']['features']
        ===['profile_badge'],'M10 same event ID is safely namespaced per world');
    check($api->handle('GET',$url,$token)[1]['data']['features']===['ui_theme'],
        'M10 second-world test event never changes first-world entitlement');
    // Out-of-band trusted revocation of a scope must take effect before any
    // further write; query scopes with a shared lock to serialize in-flight
    // commands against such privilege updates.
    $scope=$pdo->prepare('UPDATE engine_admin_scopes SET active=? WHERE world_id=? AND operator_id=?');
    $scope->execute([0,$world,'m10-manager']);
    rejects(fn()=> $admin->testPayment('m10-manager','m10-suspended',$actor,'ui_theme','grant'),
        DomainException::class,'M10 suspended host manager cannot produce a new TEST event');
    rejects(fn()=> $admin->membership('m10-manager','m10-suspended-identity',$stranger,false),
        DomainException::class,'M10 suspended host manager cannot revoke a member');
    $audit=$pdo->prepare('SELECT COUNT(*) FROM engine_admin_audit WHERE world_id=? AND request_id=?');
    $audit->execute([$world,'m10-suspended']);
    check((int)$audit->fetchColumn()===0,
        'M10 denied operator action emits no audit pretending to be committed');
    $scope->execute([1,$world,'m10-manager']);
    check($admin->describe('m10-manager')['world']===$world,
        'M10 trusted operator scope reactivation restores only its original world');

})();
