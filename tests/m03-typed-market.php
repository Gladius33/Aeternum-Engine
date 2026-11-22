<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,Command,ScheduledEvent};
$source=json_decode(file_get_contents(__DIR__.'/fixtures/m02-packs/sdk-fixture/manifest.json'),true,32,JSON_THROW_ON_ERROR);
// A two-resource version of the M02 fixture with the same typed upgrade hook
// must handle the new trusted M03 expiry hook without arbitrary code loading.
$source['resources'][]=['id'=>'coins','initial_stock'=>100,'capacity'=>500,
    'amount'=>0,'interval_seconds'=>60];
$bytes=json_encode($source,JSON_THROW_ON_ERROR);
$pack=GamePackLoader::load($bytes);
$rules=$pack->rules();
$econ=$rules->commands;
$state=[];
foreach(['amber','coins'] as $res) {
    $opened=$econ->apply(new Command('typed-m03','open-'.$res,'economy.open',
        ['account'=>'typed-seller','resource'=>$res]),$state,100);
    $state=$opened->state;
}
$created=$econ->apply(new Command('typed-m03','publish','market.create',[
    'offer'=>'typed-expiration','seller'=>'typed-seller','giveResource'=>'amber','giveUnits'=>10,
    'wantResource'=>'coins','wantUnits'=>1,'expiresAt'=>110
]),$state,100);
check(count($created->events)===1,
    'M03 typed third pack schedules built-in expiration');
// Accept the native engine envelope through the strict M02 typed wrapper.
$event=$created->events[0];
$after=$rules->events->resolve($event,$created->state);
check($after->state['market:typed-expiration:status']==='expired',
    'M03 builtin market expiry passes M02 third-party typed hook validation');
rejects(fn()=> $rules->events->resolve(new ScheduledEvent('typed-m03','bad-hook',
    'market.expire',110,['offer'=>'typed-expiration','seller'=>123]),$created->state),
    InvalidArgumentException::class,'M03 typed hook rejects wrong built-in expiry field type');
$source['event_hooks'][]=['type'=>'market.expire','payload'=>['offer'=>'string','seller'=>'integer']];
$malicious=GamePackLoader::load(json_encode($source,JSON_THROW_ON_ERROR));
rejects(fn()=> $malicious->rules(),InvalidArgumentException::class,
    'M03 pack metadata cannot redefine trusted market expiration types');
