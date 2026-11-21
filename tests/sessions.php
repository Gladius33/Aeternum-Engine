<?php
declare(strict_types=1);
use Aeternum\Core\{PdoSessions,FrozenClock};
$sessionClock=new FrozenClock(100);
$sessions=new PdoSessions(db(),$sessionClock);
check($sessions->login('test-player','wrong')===null,'invalid session credentials');
$t=$sessions->login('test-player','correct horse battery staple',60);
$t2=$sessions->login('test-player','correct horse battery staple',120);
check(is_string($t)&&strlen($t)===64&&$t!==$t2,'unique session tokens');
check((new PdoSessions(db(),$sessionClock))->resolve($t)===$actor,'persistent session');
check(db()->query("SELECT COUNT(*) FROM engine_sessions WHERE token_hash=".$pdo->quote($t))->fetchColumn()==0,'plaintext token not stored');
$sessions->revoke($t2);
$sessions->revoke($t2);
check($sessions->resolve($t2)===null,'revoked token');
check($sessions->resolve('invalid')===null,'invalid token');
$sessionClock->advanceTo(160);
check($sessions->resolve($t)===null,'exact expiration boundary');
