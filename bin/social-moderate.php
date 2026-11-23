<?php
declare(strict_types=1);
// Operator-only moderation entry point. Run from a restricted server console;
// NEVER expose this script or the report queue to the web server document root.
use Aeternum\Core\{PdoSocialModerator,SystemClock};
require __DIR__.'/../vendor/autoload.php';
try {
    if (PHP_SAPI!=='cli' || !in_array($argc,[3,4,5],true))
        throw new \InvalidArgumentException('Usage: php bin/social-moderate.php WORLD queue [afterTime afterId] | WORLD REPORT hide|dismiss');
    [$script,$world,$report]=$argv;
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world))
        throw new \InvalidArgumentException('Invalid world');
    $operator=getenv('AETERNUM_SOCIAL_MODERATOR');
    if (!is_string($operator) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$operator))
        throw new \RuntimeException('Trusted operator identity required');
    $dsn=getenv('AETERNUM_DSN');
    if (!is_string($dsn) || $dsn==='')
        throw new \RuntimeException('Database not configured');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER') ?: '',getenv('AETERNUM_DB_PASSWORD') ?: '',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    $moderation=new PdoSocialModerator($pdo,$world,new SystemClock());
    if ($report==='queue' && in_array($argc,[3,5],true)) {
        $afterTime=null;$afterId=null;
        if ($argc===5) {
            if (!preg_match('/^(0|[1-9][0-9]{0,18})$/D',$argv[3]))
                throw new \InvalidArgumentException('Invalid cursor time');
            $afterTime=filter_var($argv[3],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if ($afterTime===false)
                throw new \InvalidArgumentException('Cursor time outside integer range');
            $afterId=$argv[4];
        }
        $result=$moderation->openReports(50,$afterTime,$afterId);
        echo json_encode($result,JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    if ($argc!==4 || !in_array($argv[3],['hide','dismiss'],true))
        throw new \InvalidArgumentException('Invalid action');
    $reason=stream_get_contents(STDIN,256);
    if (!is_string($reason) || trim($reason)==='')
        throw new \InvalidArgumentException('Reason must be supplied on stdin');
    $moderation->review($operator,$report,$argv[3],trim($reason));
    echo json_encode(['world'=>$world,'report'=>$report,'action'=>$argv[3],'reviewed'=>true],
        JSON_THROW_ON_ERROR)."\n";
} catch (\Throwable $e) {
    fwrite(STDERR,'Social moderation failed: '.$e->getMessage()."\n");
    exit(1);
}
