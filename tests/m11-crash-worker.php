<?php
declare(strict_types=1);
// Independent disposable MariaDB connection that deliberately owns an
// operational advisory lock until the parent kills the PHP worker.
require __DIR__.'/db-bootstrap.php';
if (PHP_SAPI!=='cli' || $argc!==2 || !preg_match('/^[a-f0-9]{64}$/D',$argv[1])) exit(2);
$pdo=db();
$q=$pdo->prepare('SELECT GET_LOCK(?,0)');
$q->execute([$argv[1]]);
if ((int)$q->fetchColumn()!==1) exit(3);
echo "LOCKED\n";
fflush(STDOUT);
// Do not release explicitly: the integration test sends SIGKILL and proves
// MariaDB releases the connection's advisory lock after actual process death.
while (true) usleep(250000);
