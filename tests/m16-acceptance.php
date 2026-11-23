<?php
declare(strict_types=1);
require __DIR__ . '/db-bootstrap.php';

use Aeternum\Core\MigrationRunner;

// Isolated M16 lab: never reset or reuse an existing database. A dedicated
// disposable CI database makes bot authorization and replay reproducible.
$pdo=db();
if ($pdo->query('SHOW TABLES')->fetchColumn()!==false) {
    throw new RuntimeException('M16 acceptance requires a dedicated empty database');
}
$migrations=(new MigrationRunner($pdo))->run(__DIR__.'/../database/migrations');
check(count($migrations)>=20,'M16 isolated database installs production migrations');

require __DIR__ . '/m16-bots.php';
require __DIR__ . '/m16-bots-db.php';
echo "M16 isolated bot acceptance OK\n";
