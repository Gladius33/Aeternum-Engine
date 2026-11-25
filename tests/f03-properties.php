<?php
declare(strict_types=1);
use Aeternum\Core\{Command,EconomyCommandHandler,ResourceDefinition,ScalarMap,PackFields,ApiRequestSecurity};
(static function():void {
    $handler=new EconomyCommandHandler([new ResourceDefinition('ore',100,200,0,1)]);
    $state=[];
    foreach (['alice','bob'] as $actor) $state=$handler->apply(new Command('f03','open-'.$actor,'economy.open',
        ['account'=>$actor,'resource'=>'ore']),$state,100)->state;
    $seed=0xF03123;$accepted=0;$denied=0;
    $random=static function(int $bound)use(&$seed):int {
        $seed=($seed*1664525+1013904223)&0xffffffff;
        return ($seed>>8)%$bound;
    };
    for ($i=0;$i<512;$i++) {
        $from=$random(2)===0?'alice':'bob';$to=$from==='alice'?'bob':'alice';
        $units=$random(240)+1;
        $before=$state;
        $command=new Command('f03','transfer-'.$i,'economy.transfer',['from'=>$from,'to'=>$to,'resource'=>'ore','units'=>$units]);
        try {
            $result=$handler->apply($command,$state,100);
            $source=$result->state['econ:'.$from.':ore:stock'];$target=$result->state['econ:'.$to.':ore:stock'];
            if ($source<0 || $target>200 || $source+$target!==200
                || $source!==$before['econ:'.$from.':ore:stock']-$units
                || $target!==$before['econ:'.$to.':ore:stock']+$units
                || count($result->movements)!==2 || $result->events!==[]
                || $handler->apply($command,$before,100)!=$result)
                throw new RuntimeException('F03 conservation or determinism property failed');
            $state=$result->state;$accepted++;
        } catch (DomainException $e) {
            if ($units<=$before['econ:'.$from.':ore:stock'] && $units<=200-$before['econ:'.$to.':ore:stock']) throw $e;
            if ($state!==$before) throw new RuntimeException('F03 denied transfer mutated input');
            $denied++;
        }
        $schema=PackFields::load((object)['account'=>'string','units'=>'integer']);
        $schema->assertPayload(['account'=>$from,'units'=>$units]);
        rejects(fn()=> $schema->assertPayload(['account'=>$from,'units'=>(string)$units]),InvalidArgumentException::class,
            'F03 integer/string coercion rejected case '.$i);
    }
    check($accepted>0 && $denied>0 && $accepted+$denied===512,'F03 seeded transfer fuzz includes valid and denied operations');
    check(ScalarMap::decode(ScalarMap::encode($state))===ScalarMap::validate($state),'F03 canonical state survives exact serialization');
    $base=['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/v1/worlds/f03/progression/alice/rank'];
    foreach (['%2f','%00','%5c','%252e','%0a','%3f','..','.',str_repeat('a',513)] as $bad) {
        $request=[...$base,'REQUEST_URI'=>'/v1/worlds/'.$bad.'/progression/alice/rank'];
        check(ApiRequestSecurity::reject($request)!==null,'F03 ambiguous or oversized path fails closed');
    }
    echo 'F03_PROPERTY_JSON '.json_encode(['seed'=>'0xF03123','trials'=>512,'accepted'=>$accepted,'denied'=>$denied],JSON_THROW_ON_ERROR).PHP_EOL;
})();
