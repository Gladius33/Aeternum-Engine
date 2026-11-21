<?php
declare(strict_types=1);
use Aeternum\Core\{PlayerProvisioner,GamePackLoader,FrozenClock,PdoScheduler,PdoEntities,PdoIdentity};
$pc=new FrozenClock(600);
$pp=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$pa=(new PdoIdentity(db()))->register('provision-player','another long test password');
$pv=new PlayerProvisioner(db(),$pc);
rejects(fn()=> $pv->provision('provision-world','missing',$pp),DomainException::class,'unknown identity cannot provision');
$pv->provision('provision-world',$pa,$pp);
$pv->provision('provision-world',$pa,$pp);
$pr=$pp->rules();
$ps=new PdoScheduler(db(),'provision-world',$pc,$pr);
foreach ($pp->resources as $resource) check($ps->balanceForActor($pa,$pa,$resource->id,$pr->commands)->stock===$resource->initialStock,'initial resource '.$resource->id);
check((new PdoEntities(db(),'provision-world'))->own($pa,$pa)['kind']==='settlement','provisioned own settlement');
check($ps->verifyReplay($pr->commands,$pr->events)->entries===count($pp->resources),'provisioning retry does not duplicate journal');
