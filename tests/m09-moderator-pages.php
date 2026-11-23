<?php
declare(strict_types=1);
use Aeternum\Core\{PdoSocialMessages,PdoSocialModerator,FrozenClock};

// Persistent 115-item operator queue: the API must never expose it.
(static function():void {
    $pdo=db();$clock=new FrozenClock(5200);$world='m09-social-a';
    $q=$pdo->prepare('SELECT actor_id FROM engine_identities WHERE login=?');
    $q->execute(['m09-social-officer']);$recipient=$q->fetchColumn();
    if(!is_string($recipient)) throw new RuntimeException('Missing moderation identity');
    $messages=new PdoSocialMessages($pdo,$world,$clock);
    for($i=0;$i<115;$i++) {
        $messages->report($recipient,sprintf('m09-review-%03d',$i),
            sprintf('m09-page-%03d',$i),'Review queue test '.$i);
    }
    $moderator=new PdoSocialModerator($pdo,$world,$clock);
    $cursor=null;$all=[];$pages=0;
    do {
        $page=$cursor===null?$moderator->openReports():
            $moderator->openReports(50,$cursor['createdAt'],$cursor['id']);
        check(count($page['reports'])<=50,'M09 report queue page bounded');
        check($page['truncated']===($page['nextAfter']!==null),
            'M09 report continuation accurately reflects remaining rows');
        foreach($page['reports'] as $report) {
            check(!isset($all[$report['report_id']]),'M09 report cursor never repeats ID');
            $all[$report['report_id']]=$report;
        }
        $cursor=$page['nextAfter'];++$pages;
        if($pages>10) throw new RuntimeException('Moderator cursor did not terminate');
    } while($cursor!==null);
    check($pages===3 && count($all)===115,'M09 full 115 report queue is traversable');
    $moderator->review('ci-operator','m09-review-050','dismiss','Allowed after review');
    $again=$moderator->openReports(100);
    check(!in_array('m09-review-050',array_column($again['reports'],'report_id'),true),
        'M09 adjudicated reports disappear from operator queue');
    $env=getenv();
    $env['AETERNUM_DSN']=getenv('AETERNUM_TEST_DSN');
    $env['AETERNUM_DB_USER']=getenv('AETERNUM_TEST_USER')?:'';
    $env['AETERNUM_DB_PASSWORD']=getenv('AETERNUM_TEST_PASSWORD')?:'';
    $env['AETERNUM_SOCIAL_MODERATOR']='ci-operator';
    $cli=static function(array $args)use($env):array {
        $process=proc_open(array_merge([PHP_BINARY,__DIR__.'/../bin/social-moderate.php'],$args),
            [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
        if(!is_resource($process)) throw new RuntimeException('Missing operator CLI');
        fclose($pipes[0]);
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        return [proc_close($process),$out,$err];
    };
    [$code,$out]=$cli([$world,'queue']);
    $first=json_decode($out,true,8,JSON_THROW_ON_ERROR);
    check($code===0 && count($first['reports'])===50 && $first['nextAfter']!==null,
        'M09 operator CLI first page includes continuation cursor');
    [$code,$out]=$cli([$world,'queue',(string)$first['nextAfter']['createdAt'],
        $first['nextAfter']['id']]);
    $second=json_decode($out,true,8,JSON_THROW_ON_ERROR);
    check($code===0 && count($second['reports'])===50
        && $second['reports'][0]['report_id']!==$first['reports'][0]['report_id'],
        'M09 operator CLI reads subsequent page from cursor');
    [$code,$out,$err]=$cli([$world,'queue','9999999999999999999','m09-review-001']);
    check($code===1 && $out==='' && str_contains($err,'Cursor time'),
        'M09 operator CLI rejects overflowing cursor before DB access');
})();
