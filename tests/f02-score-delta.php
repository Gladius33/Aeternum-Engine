<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,ScoreDelta,ScoreProjector};
(static function():void {
    $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-guild/versions/0.2.0/manifest.json'));
    $delta=new ScoreDelta($pack->progression);
    $before=['market:o:status'=>'open','market:o:seller'=>'alice','build:max:alice:watchtower'=>1];
    $after=$before;$after['market:o:status']='filled';$after['market:o:buyer']='bob';
    $after['econ:alice:silver:level']=1;$after['build:max:bob:market_hall']=1;
    check($delta->between($before,$after)===['alice'=>[14,0],'bob'=>[18,0]],'F02 weighted sparse changes count each trade once');
    $full=$delta->between([],$after);
    $projector=new ScoreProjector($pack->progression);
    foreach (['alice','bob'] as $account) {
        $s=$projector->forAccount($after,$account);
        check($full[$account]===[$s['development'],$s['military']],'F02 indexed score agrees with historical scalar oracle');
    }
    check($delta->between($after,$before)===['alice'=>[-14,0],'bob'=>[-18,0]],'F02 inverse delta is exact');
    check($delta->between($after,$after)===[],'F02 retries do not create score changes');
    $large=$after;
    for ($i=0;$i<100001;$i++) $large['unrelated:'.$i]=0;
    check($delta->between([],$large)===$full,'F02 indexed projection does not inherit global 100k-state read cap');
})();
