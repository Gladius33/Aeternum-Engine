<?php
declare(strict_types=1);
use Aeternum\Core\MigrationRunner;
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    require __DIR__.'/../vendor/autoload.php';
    if ($argc!==1) throw new InvalidArgumentException('Usage: php bin/migrate.php');
    $dsn=getenv('AETERNUM_DSN');
    if (!$dsn) throw new RuntimeException('Missing DSN');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER') ?: '',getenv('AETERNUM_DB_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    echo json_encode(['applied'=>(new MigrationRunner($pdo))->run(__DIR__.'/../database/migrations')],JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Migration failed. Inspect schema ledger and restore backup if necessary; never blindly retry dirty DDL.\n");
    exit(1);
}
