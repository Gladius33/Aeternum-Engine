<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/../src/ResearchGateway.php';
function researchDatabase():\PDO{
    $dsn=getenv('WP_RESEARCH_DB_DSN');
    if(!is_string($dsn)||!str_starts_with($dsn,'mysql:')||$dsn===getenv('WP_DB_DSN'))throw new \RuntimeException('Separate research database required');
    $db=new \PDO($dsn,getenv('WP_RESEARCH_DB_USER')?:'',getenv('WP_RESEARCH_DB_PASSWORD')?:'',
        [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_EMULATE_PREPARES=>false]);
    // A misconfigured shared database is rejected, not merely discouraged.
    $q=$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('wp_accounts','wp_alpha_worlds','wp_world_clock')");
    if((int)$q->fetchColumn()!==0)throw new \RuntimeException('Gameplay database forbidden');
    return $db;
}
