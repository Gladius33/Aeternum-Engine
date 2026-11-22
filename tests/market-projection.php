<?php
declare(strict_types=1);
use Aeternum\Core\MarketProjection;
$marketFixture=[];
foreach (['z'=>'open','10'=>'open','2'=>'open','closed'=>'filled','revoked'=>'open','cancelled'=>'cancelled'] as $id=>$status) {
    foreach (['seller'=>$id==='revoked'?'inactive':'s','giveResource'=>'ore','wantResource'=>'coin','giveUnits'=>3,'wantUnits'=>7,'status'=>$status,'buyer'=>'private'] as $key=>$value) {
        $marketFixture['market:'.$id.':'.$key]=$value;
    }
}
$marketFixture['econ:s:ore:stock']=123;
$projected=MarketProjection::openOffers($marketFixture);
check(array_column($projected,'id')===['10','2','revoked','z'],'market projection sorted bytewise, closed excluded');
$firstPage=MarketProjection::page($projected,['s'],null,2);
check(array_column($firstPage['offers'],'id')===['10','2'] && $firstPage['nextAfter']==='2','market projection first page and cursor');
$secondPage=MarketProjection::page($projected,['s'],$firstPage['nextAfter'],2);
check(array_column($secondPage['offers'],'id')===['z'] && $secondPage['nextAfter']===null,'market projection next page excludes inactive seller');
check(array_keys($firstPage['offers'][0])===['id','seller','giveResource','wantResource','giveUnits','wantUnits'],'market projection exposes only public fields');
check(MarketProjection::page($projected,[],null,2)===['offers'=>[],'nextAfter'=>null],'market projection empty result');
check(MarketProjection::page($projected,['s'],'zz',2)===['offers'=>[],'nextAfter'=>null],'market projection exhausted cursor');
rejects(fn()=>MarketProjection::page($projected,['s'],null,0),InvalidArgumentException::class,'market projection rejects zero limit');
rejects(fn()=>MarketProjection::page($projected,['s'],'bad:cursor',2),InvalidArgumentException::class,'market projection rejects invalid cursor');
$malformed=$marketFixture; unset($malformed['market:z:giveUnits']);
rejects(fn()=>MarketProjection::openOffers($malformed),LogicException::class,'market projection fails closed on malformed stored offer');
