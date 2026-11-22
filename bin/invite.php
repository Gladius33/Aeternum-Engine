<?php
declare(strict_types=1);
use Aeternum\Core\{ConfiguredWorlds,PdoInvitations,SystemClock};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    require __DIR__.'/../vendor/autoload.php';
    if ($argc!==3 || !preg_match('/^[1-9][0-9]?$/D',$argv[2]) || (int)$argv[2]>50)
        throw new InvalidArgumentException('Usage: php bin/invite.php APPROVED_WORLD COUNT(1-50)');
    $packs=ConfiguredWorlds::load();
    if (!isset($packs[$argv[1]])) throw new InvalidArgumentException('World is not approved');
    $dsn=getenv('AETERNUM_DSN');
    if (!$dsn) throw new RuntimeException('Missing database settings');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    foreach ((new PdoInvitations($pdo,new SystemClock()))->issue($argv[1],(int)$argv[2]) as $code) {
        echo $code.PHP_EOL;
    }
} catch (Throwable) {
    fwrite(STDERR,"Invitation issuance failed; verify approved world and migration 018.\n");
    exit(1);
}
