<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PdoIdentity,PlayerProvisioner,PdoScheduler,
    PdoBuildings,PdoSessions,PdoIndustryStatus,WorldBuildingHttpApi,FrozenClock,Command};

$industrialPack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-industrial/versions/0.3.0/manifest.json'));
$industrialRules=$industrialPack->rules();
$industrialWorld='m06-industrial-campaign';
$industrialClock=new FrozenClock(1000);
$industrialIds=new PdoIdentity(db());
$industrialA=$industrialIds->register('industry-play-a','industrial test password long');
$industrialB=$industrialIds->register('industry-play-b','industrial test password long');
$industrialProvision=new PlayerProvisioner(db(),$industrialClock);
$industrialProvision->provision($industrialWorld,$industrialA,$industrialPack);
$industrialProvision->provision($industrialWorld,$industrialB,$industrialPack);
$industrialStore=new PdoScheduler(db(),$industrialWorld,$industrialClock,$industrialRules);
$industrialBuildings=new PdoBuildings(db(),$industrialWorld);
$industrialStatus=new PdoIndustryStatus(db(),$industrialWorld,$industrialRules,$industrialPack->industry);
$industrialCommand=function(string $actor,string $id,string $type,array $payload)
    use ($industrialStore,$industrialRules,$industrialWorld) {
    return $industrialStore->executeForActor($actor,
        new Command($industrialWorld,$id,$type,$payload),$industrialRules->commands);
};
$industrialPlace=function(string $actor,string $id,string $type)use($industrialCommand):void {
    $industrialCommand($actor,'place-'.$id,'building.place',[
        'account'=>$actor,'building'=>$id,'entity'=>$actor,'territory'=>$actor,'type'=>$type
    ]);
};
$industrialStart=function(string $actor,string $id,string $type)use($industrialCommand) {
    return $industrialCommand($actor,'build-'.$id.'-'.bin2hex(random_bytes(4)),'building.start',[
        'account'=>$actor,'building'=>$id,'type'=>$type
    ]);
};
$industrialAdvance=function(int $at)use($industrialClock,$industrialStore,$industrialRules):void {
    $industrialClock->advanceTo($at);
    $industrialStore->processDue($industrialRules->events);
};
check($industrialStatus->forActor($industrialA,$industrialA)['delivery']['current']===0
    && !$industrialStatus->forActor($industrialA,$industrialA)['victory']['achieved'],
    'M06 new industrial colony starts without deliveries or premature victory');
foreach (['power_plant'=>'power','steelworks'=>'steelworks','warehouse'=>'warehouse',
    'freight_terminal'=>'freight'] as $type=>$id) {
    $industrialPlace($industrialA,'a-'.$id,$type);
}
$industrialPlace($industrialB,'b-power','power_plant');
rejects(fn()=> $industrialCommand($industrialB,'m06-steal','industry.generate',['account'=>$industrialA]),
    DomainException::class,'M06 industrial operations cannot spend foreign account resources');
rejects(fn()=> $industrialCommand($industrialB,'m06-steal-plot','building.place',[
    'account'=>$industrialB,'building'=>'b-stolen','entity'=>$industrialB,
    'territory'=>$industrialA,'type'=>'power_plant'
]),DomainException::class,'M06 industrial foundations cannot occupy foreign territory');
rejects(fn()=> $industrialCommand($industrialA,'m06-no-power','industry.generate',['account'=>$industrialA]),
    DomainException::class,'M06 generation needs a completed power plant');
rejects(fn()=> $industrialCommand($industrialA,'m06-no-factory','industry.refine',['account'=>$industrialA]),
    DomainException::class,'M06 manufacturing needs a factory and storage');
rejects(fn()=> $industrialCommand($industrialA,'m06-no-freight','industry.ship',['account'=>$industrialA]),
    DomainException::class,'M06 logistics cannot dispatch before freight infrastructure');
$industrialStart($industrialA,'a-power','power_plant');
$industrialStart($industrialB,'b-power','power_plant');
$industrialAdvance(1004);
check($industrialBuildings->own($industrialA,'a-power')['level']===1
    && $industrialBuildings->own($industrialB,'b-power')['level']===1,
    'M06 two independent players complete separate factory foundations');
$gen=$industrialCommand($industrialA,'m06-generate-1','industry.generate',['account'=>$industrialA]);
check($industrialCommand($industrialA,'m06-generate-1','industry.generate',['account'=>$industrialA])==$gen,
    'M06 electrical generation is idempotent on identical request replay');
rejects(fn()=> $industrialCommand($industrialA,'m06-generate-again','industry.generate',['account'=>$industrialA]),
    DomainException::class,'M06 energy cooldown rejects duplicate rapid generation');
check($industrialStore->balanceForActor($industrialA,$industrialA,'goods',$industrialRules->commands)->stock===0,
    'M06 manufactured goods have no passive production');
$industrialStart($industrialA,'a-steelworks','steelworks');
$industrialAdvance(1009);
$industrialStart($industrialA,'a-warehouse','warehouse');
$industrialAdvance(1014);
$industrialStart($industrialA,'a-power','power_plant');
$industrialAdvance(1020);
$industrialCommand($industrialA,'m06-generate-2','industry.generate',['account'=>$industrialA]);
$industrialStart($industrialA,'a-steelworks','steelworks');
$industrialAdvance(1028);
$industrialStart($industrialA,'a-warehouse','warehouse');
$industrialAdvance(1035);
$industrialStart($industrialA,'a-freight','freight_terminal');
$industrialAdvance(1044);
$prepared=$industrialStatus->forActor($industrialA,$industrialA);
check($prepared['grid']['supply']===160 && $prepared['grid']['load']===75
    && $prepared['warehouseCapacity']===120
    && !$prepared['victory']['achieved'],
    'M06 completed electrical grid and warehouse remain non-winning before deliveries');
$industrialCommand($industrialA,'m06-generate-3','industry.generate',['account'=>$industrialA]);
$before=$industrialStore->balanceForActor($industrialA,$industrialA,'goods',$industrialRules->commands)->stock;
$made=$industrialCommand($industrialA,'m06-manufacture-1','industry.refine',['account'=>$industrialA]);
check($industrialCommand($industrialA,'m06-manufacture-1','industry.refine',['account'=>$industrialA])==$made
    && $industrialStore->balanceForActor($industrialA,$industrialA,'goods',$industrialRules->commands)->stock===$before+40,
    'M06 factory uses energy and materials to manufacture durable inventory exactly once');
$shipped=$industrialCommand($industrialA,'m06-ship-1','industry.ship',['account'=>$industrialA]);
check($industrialCommand($industrialA,'m06-ship-1','industry.ship',['account'=>$industrialA])==$shipped
    && $industrialStatus->forActor($industrialA,$industrialA)['delivery']['current']===15,
    'M06 freight debits goods and credits delivery counter once even on replay');
$industrialAdvance(1048);
$industrialCommand($industrialA,'m06-ship-2','industry.ship',['account'=>$industrialA]);
$industrialCommand($industrialA,'m06-manufacture-2','industry.refine',['account'=>$industrialA]);
$industrialAdvance(1052);
$industrialCommand($industrialA,'m06-ship-3','industry.ship',['account'=>$industrialA]);
$won=$industrialStatus->forActor($industrialA,$industrialA);
check($won['delivery']['current']===45 && $won['victory']['achieved'],
    'M06 finished factory campaign wins only after 45 committed freight deliveries');
check(!$industrialStatus->forActor($industrialB,$industrialB)['victory']['achieved']
    && $industrialStatus->forActor($industrialB,$industrialB)['delivery']['current']===0,
    'M06 a separate industrial player cannot inherit another player win');
rejects(fn()=> $industrialStatus->forActor($industrialB,$industrialA),DomainException::class,
    'M06 private industrial status rejects another account');
$industrialSnapshot=$industrialStore->state();
$industrialReplay=$industrialStore->verifyReplay($industrialRules->commands,$industrialRules->events);
check($industrialReplay->entries>=15 && $industrialStore->state()===$industrialSnapshot,
    'M06 factory and freight economy replays with identical canonical state and ledger');
$industrialToken=(new PdoSessions(db(),$industrialClock))->login('industry-play-a','industrial test password long');
$industrialApi=new WorldBuildingHttpApi(db(),$industrialClock,[$industrialWorld=>$industrialPack]);
$industrialUri='/v1/worlds/'.$industrialWorld.'/industrial/status/'.$industrialA;
check($industrialApi->handle('GET',$industrialUri,'Bearer '.$industrialToken)[1]['data']['victory']['achieved'],
    'M06 authenticated web API retains server victory after relogin');
check($industrialApi->handle('GET','/v1/worlds/'.$industrialWorld.'/industrial/status/'.$industrialB,
    'Bearer '.$industrialToken)[0]===404
    && $industrialApi->handle('GET',$industrialUri,'')[0]===401
    && $industrialApi->handle('POST',$industrialUri,'Bearer '.$industrialToken)[0]===405,
    'M06 industrial status enforces actor scope, authentication and read-only contract');
// Four distinct requests concurrently compete for the same bounded inventory
// and one cooldown slot. Exactly one shipment can cross the InnoDB barrier.
$freightWorkers=[];
for ($i=0;$i<4;$i++) {
    $proc=proc_open([PHP_BINARY,__DIR__.'/m06-freight-worker.php',(string)$i,$industrialA],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($proc)) throw new RuntimeException('M06 freight worker unavailable');
    fclose($pipes[0]);
    $freightWorkers[]=[$proc,$pipes];
}
$freightWon=0;$freightDenied=0;
foreach ($freightWorkers as [$proc,$pipes]) {
    $out=trim(stream_get_contents($pipes[1]));
    $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    if (proc_close($proc)!==0) throw new RuntimeException('M06 freight worker failed: '.$err);
    if ($out==='won') $freightWon++;
    elseif ($out==='denied') $freightDenied++;
    else throw new RuntimeException('Unexpected M06 competing delivery outcome: '.$out);
}
// Child workers commit at 1056; advance the parent's explicit logical clock
// before querying the canonical post-race economy.
$industrialClock->advanceTo(1056);
check($freightWon===1 && $freightDenied===3,
    'M06 four concurrent PDO deliveries commit exactly one shipment');
check($industrialStatus->forActor($industrialA,$industrialA)['delivery']['current']===60
    && $industrialStore->balanceForActor($industrialA,$industrialA,'goods',$industrialRules->commands)->stock===20,
    'M06 concurrent freight charges only one goods inventory debit');
$freightAfter=$industrialStore->state();
check($industrialStore->verifyReplay($industrialRules->commands,$industrialRules->events)->entries>=20
    && $industrialStore->state()===$freightAfter,
    'M06 contended freight remains deterministic after journal replay');
// Negative energy/capacity scenarios are evaluated by the SAME trusted engine
// against intentionally corrupted copies of state. No test stock injection is
// committed, and unsuccessful actions must never mutate the canonical world.
$blackout=$freightAfter;
$blackout['build:max:'.$industrialA.':power_plant']=0;
rejects(fn()=> $industrialRules->commands->apply(new Command(
    $industrialWorld,'m06-blackout','industry.refine',['account'=>$industrialA]),
    $blackout,1056),DomainException::class,
    'M06 insufficient grid capacity blocks manufacturing');
$warehouseFull=$freightAfter;
$warehouseFull['econ:'.$industrialA.':goods:stock']=120;
rejects(fn()=> $industrialRules->commands->apply(new Command(
    $industrialWorld,'m06-full-storage','industry.refine',['account'=>$industrialA]),
    $warehouseFull,1056),DomainException::class,
    'M06 full warehouse rejects excess production before credit');
$powerEmpty=$freightAfter;
$powerEmpty['econ:'.$industrialA.':energy:stock']=0;
$powerEmpty['econ:'.$industrialA.':energy:at']=1056;
$powerEmpty['econ:'.$industrialA.':energy:remainder']=0;
rejects(fn()=> $industrialRules->commands->apply(new Command(
    $industrialWorld,'m06-empty-power','industry.ship',['account'=>$industrialA]),
    $powerEmpty,1056),DomainException::class,
    'M06 freight refuses insufficient energy inventory');
check($industrialStore->state()===$freightAfter,
    'M06 rejected grid, warehouse and energy operations never change durable state');
unset($industrialCommand,$industrialPlace,$industrialStart,$industrialAdvance,$industrialStore,
    $industrialBuildings,$industrialStatus,$industrialApi,$industrialProvision,$industrialIds);
