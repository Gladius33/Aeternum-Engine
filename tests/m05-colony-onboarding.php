<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PdoIdentity,PdoMemberships,PdoBuildings,PdoTerritories,
    PlayerProvisioner,PdoScheduler,FrozenClock};
$colonyPack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/versions/0.2.0/manifest.json'));
$colonyWorld='m05-colony-onboarding';
$colonyClock=new FrozenClock(2000);
$colonyIdentity=new PdoIdentity(db());
$colonyAlice=$colonyIdentity->register('m05-colony-alice','colony onboarding password');
$colonyBob=$colonyIdentity->register('m05-colony-bob','colony onboarding password');
$colonyProvisioner=new PlayerProvisioner(db(),$colonyClock);
$colonyProvisioner->provision($colonyWorld,$colonyAlice,$colonyPack);
$colonyProvisioner->provision($colonyWorld,$colonyBob,$colonyPack);
$colonyProvisioner->provision($colonyWorld,$colonyAlice,$colonyPack);
$colonySites=new PdoBuildings(db(),$colonyWorld);
$colonyA=$colonySites->choicesFor($colonyAlice);
$colonyB=$colonySites->choicesFor($colonyBob);
check(count($colonyA['sites'])===1 && $colonyA['sites'][0]===[
    'territory_id'=>$colonyAlice,'location_id'=>$colonyAlice,'entity_id'=>$colonyAlice
], 'M05 signup provisions an owned founding settlement without an operator CLI');
check(count($colonyA['types'])===4 && count($colonyB['types'])===4
    && $colonyA['buildings']===[] && $colonyB['buildings']===[],
    'M05 startup installs pinned building catalog but leaves player construction decisions open');
check($colonyA['sites'][0]['territory_id']!==$colonyB['sites'][0]['territory_id'],
    'M05 two actors receive disjoint colony territories');
check((int)db()->query("SELECT COUNT(*) FROM engine_territories WHERE world_id='m05-colony-onboarding'")->fetchColumn()===2,
    'M05 retry preserves exactly one territory per player');
(new PdoMemberships(db(),$colonyWorld))->revoke($colonyAlice);
rejects(fn()=> $colonySites->choicesFor($colonyAlice),DomainException::class,
    'M05 revoked player cannot discover their settlement');
check(count($colonySites->choicesFor($colonyBob)['sites'])===1,
    'M05 another active colony unaffected by revocation');
(new PdoMemberships(db(),$colonyWorld))->grant($colonyAlice);
check((new PdoBuildings(db(),$colonyWorld))->choicesFor($colonyAlice)['sites']===$colonyA['sites'],
    'M05 reconnect after membership restoration preserves colony topology');
$legacyPack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$legacyUser=$colonyIdentity->register('m05-legacy-player','legacy onboarding password');
$colonyProvisioner->provision('m05-colony-legacy',$legacyUser,$legacyPack);
check((int)db()->query("SELECT COUNT(*) FROM engine_territories WHERE world_id='m05-colony-legacy'")->fetchColumn()===0,
    'M05 legacy medieval world never acquires new geography implicitly');
