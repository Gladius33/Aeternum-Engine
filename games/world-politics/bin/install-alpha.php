<?php
declare(strict_types=1);
// Fresh private-alpha schema installer. Never run against generic engine DB.
if (PHP_SAPI!=='cli' || getenv('WP_ALPHA_INSTALL')!=='YES') {fwrite(STDERR,"Explicit WP_ALPHA_INSTALL=YES required\n");exit(2);}
$dsn=getenv('WP_DB_DSN');
if (!is_string($dsn)||!str_starts_with($dsn,'mysql:')) throw new RuntimeException('MariaDB DSN required');
$pdo=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if ((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0)
    throw new RuntimeException('Installer requires an empty dedicated database; use reviewed migrations for existing worlds');
$files=glob(__DIR__.'/../database/*.sql');sort($files,SORT_STRING);
foreach ($files as $file) {
    if (str_starts_with(basename($file),'014_')) continue; // maintenance-only backfill
    $sql=preg_replace('/^\s*--[^\n]*$/m','',file_get_contents($file));
    foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') $pdo->exec(trim($statement));
    echo 'Applied '.basename($file)."\n";
}
