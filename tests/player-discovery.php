<?php
declare(strict_types=1);
use Aeternum\Core\{PlayerDiscoveryApi,GamePackLoader,FrozenClock,PdoIdentity,PdoSessions,PlayerProvisioner,PdoMemberships};
$dc=new FrozenClock(900);
$dp=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$di=new PdoIdentity(db());
$da=$di->register('discover-alice','discovery test password');
$dbob=$di->register('discover-bob','discovery test password');
$dv=new PlayerProvisioner(db(),$dc);
$dv->provision('discover-world',$da,$dp);
$dv->provision('discover-world',$dbob,$dp);
$dt=(new PdoSessions(db(),$dc))->login('discover-alice','discovery test password');
$api=new PlayerDiscoveryApi(db(),$dc,['discover-world'=>$dp]);
check($api->handle('GET','')[0]===401,'discovery requires session');
check($api->handle('POST','Bearer '.$dt)[0]===405,'discovery read only');
[$ds,$dd]=$api->handle('GET','Bearer '.$dt);
check($ds===200 && $dd['data']['worlds'][0]['accounts']===[$da],'discovery isolates accounts');
(new PdoMemberships(db(),'discover-world'))->revoke($da);
check($api->handle('GET','Bearer '.$dt)[1]['data']['worlds']===[],'revoked membership excluded');
