<?php
declare(strict_types=1);
use Aeternum\Core\PdoIdentity;
$i=new PdoIdentity(db());
$actor=$i->register('test-player','correct horse battery staple');
check(strlen($actor)===64,'generated actor identity');
check((new PdoIdentity(db()))->authenticate('test-player','correct horse battery staple')===$actor,'identity survives reconnect');
check($i->authenticate('test-player','incorrect')===null,'wrong password');
check($i->authenticate('unknown-player','incorrect')===null,'unknown login');
rejects(fn()=> $i->register('test-player','another long password'),PDOException::class,'unique login');
rejects(fn()=> $i->register('other-player','short'),InvalidArgumentException::class,'short password');
check($i->authenticate('unknown-player','nonexistent-account-dummy-input-only')===null,'dummy verification can never authenticate unknown identity');
$identityHash=$pdo->prepare('SELECT password_hash FROM engine_identities WHERE actor_id=?');
$identityHash->execute([$actor]);
$identityInfo=password_get_info($identityHash->fetchColumn());
check($identityInfo['algoName']==='argon2id' && $identityInfo['options']===['memory_cost'=>65536,'time_cost'=>4,'threads'=>1],'fixed Argon2id registration policy');
