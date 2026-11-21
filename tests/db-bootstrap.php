<?php
declare(strict_types=1);
spl_autoload_register(static function(string $class): void {
    $prefix = 'Aeternum\\Core\\';
    if (str_starts_with($class, $prefix)) require __DIR__ . '/../packages/core/src/' . substr($class, strlen($prefix)) . '.php';
});
function db(): PDO {
    $dsn = getenv('AETERNUM_TEST_DSN');
    if (!$dsn) throw new RuntimeException('AETERNUM_TEST_DSN required: use a dedicated empty test database');
    $pdo = new PDO($dsn, getenv('AETERNUM_TEST_USER') ?: '', getenv('AETERNUM_TEST_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 3');
    return $pdo;
}
function check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS $message\n";
}
function rejects(callable $fn, string $class, string $message): void {
    try { $fn(); } catch (Throwable $e) {
        if (!$e instanceof $class) throw $e;
        check(true,$message);
        return;
    }
    throw new RuntimeException("Expected exception: $message");
}
