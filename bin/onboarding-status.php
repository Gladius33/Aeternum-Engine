<?php
declare(strict_types=1);
use Aeternum\Core\{ConfiguredWorlds,PdoOnboardingStatus,SystemClock};
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
try {
    if ($argc!==2) throw new InvalidArgumentException('Usage: php bin/onboarding-status.php APPROVED_WORLD');
    require __DIR__.'/../vendor/autoload.php';
    if (!isset(ConfiguredWorlds::load()[$argv[1]])) throw new InvalidArgumentException('Unapproved world');
    $dsn=getenv('AETERNUM_DSN');
    if (!$dsn) throw new RuntimeException('Missing database configuration');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    echo json_encode((new PdoOnboardingStatus($pdo,new SystemClock()))->snapshot($argv[1]),JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Onboarding status unavailable; check approved world, migrations and database configuration.\n");
    exit(1);
}
