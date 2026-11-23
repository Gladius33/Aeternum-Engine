<?php
declare(strict_types=1);
use Aeternum\Core\{PdoAdminConsole,PdoIdentity,FrozenClock,PdoSessions};

// Exercise actual php CLI with a disposable DB and permissioned roles.
// Host registration of the operator scope remains a trusted setup operation.
(static function():void {
    $pdo=db();$world='m10-console-a';$clock=new FrozenClock(9100);
    $target=(new PdoIdentity($pdo))->authenticate('m10-cosmetic-user',
        'M10 isolated testing password');
    if(!is_string($target))throw new RuntimeException('Missing M10 CLI actor');
    $env=getenv();
    $env['AETERNUM_DSN']=getenv('AETERNUM_TEST_DSN');
    $env['AETERNUM_DB_USER']=getenv('AETERNUM_TEST_USER')?:'';
    $env['AETERNUM_DB_PASSWORD']=getenv('AETERNUM_TEST_PASSWORD')?:'';
    $env['AETERNUM_ADMIN_OPERATOR']='m10-manager';
    $env['AETERNUM_PAYMENT_MODE']='test';
    $run=static function(array $args,array $variables)use($env):array {
        $proc=proc_open(array_merge([PHP_BINARY,__DIR__.'/../bin/admin-console.php'],$args),
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,
            array_replace($env,$variables));
        if(!is_resource($proc))throw new RuntimeException('M10 local admin console unavailable');
        fclose($pipes[0]);
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        return [proc_close($proc),$out,$err];
    };
    [$code,$out]=$run(['world',$world],[]);
    $status=json_decode($out,true,8,JSON_THROW_ON_ERROR);
    check($code===0 && $status['data']['world']===$world,
        'M10 CLI restricted host operator can inspect only assigned world');
    [$code,$out]=$run(['world','m10-console-b'],[]);
    check($code===1 && $out==='','M10 CLI refuses unassigned world inspection');
    [$code,$out]=$run(['world',$world],['AETERNUM_ADMIN_OPERATOR'=>'']);
    check($code===1 && $out==='','M10 CLI refuses missing operator attribution');
    [$code,$out]=$run(['test-event',$world,$target,'profile_badge','grant','m10-cli-01'],
        ['AETERNUM_PAYMENT_MODE'=>'']);
    check($code===1 && $out==='',
        'M10 explicit test mode cannot be inferred or bypassed');
    [$code,$out]=$run(['test-event',$world,$target,'profile_badge','grant','m10-cli-01'],[]);
    $applied=json_decode($out,true,8,JSON_THROW_ON_ERROR)['data'];
    check($code===0 && $applied['applied']===true && $applied['mode']==='test',
        'M10 CLI can grant a cosmetic test-only event');
    [$code,$out]=$run(['test-event',$world,$target,'profile_badge','grant','m10-cli-01'],[]);
    $replay=json_decode($out,true,8,JSON_THROW_ON_ERROR)['data'];
    check($code===0 && $replay['applied']===false && $replay['replayed']===true,
        'M10 CLI reports exact replay without repeating the mutation');
    [$code,$out]=$run(['test-event',$world,$target,'ui_theme','grant','m10-cli-01'],[]);
    check($code===1 && $out==='','M10 CLI refuses conflicting test payment replay');
    [$code,$out]=$run(['test-event',$world,$target,'game_boost','grant','m10-cli-02'],[]);
    check($code===1 && $out==='','M10 CLI never enables game-affecting paid effects');
    [$code,$out]=$run(['test-event','m10-console-b',$target,'profile_badge','grant','m10-cli-03'],[]);
    check($code===1 && $out==='','M10 CLI cannot cross world boundary');
    [$code,$out]=$run(['audit',$world],['AETERNUM_ADMIN_OPERATOR'=>'m10-view']);
    $audit=json_decode($out,true,8,JSON_THROW_ON_ERROR);
    check($code===0 && count($audit['data']['entries'])===6,
        'M10 viewer can inspect attributed trusted operator history');
    [$code,$out]=$run(['membership',$world,$target,'revoke','m10-cli-revoke'],
        ['AETERNUM_ADMIN_OPERATOR'=>'m10-view']);
    check($code===1 && $out==='','M10 viewer cannot change another actor membership');
    // No public routes or premium access for an operator merely because
    // their private console identity exists.
    $forgedBearer='Bearer '.str_repeat('a',64);
    check((new \Aeternum\Core\PremiumHttpApi($pdo,$clock,[$world=>true]))
        ->handle('GET','/v1/worlds/'.$world.'/premium/me',$forgedBearer)[0]===401,
        'M10 operator IDs are not interchangeable with signed-in player bearers');
})();
