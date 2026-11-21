<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** MariaDB DDL commits implicitly. A dirty marker requires operator recovery. */
final class MigrationRunner
{
    public function __construct(private readonly PDO $pdo)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Require native PDO MySQL exceptions');
    }
    public function run(string $directory): array
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested migration prohibited');
        $files=glob(rtrim($directory,'/').'/*.sql');
        if (!$files) throw new \InvalidArgumentException('No migration files');
        sort($files,SORT_STRING);
        $lock=$this->pdo->query("SELECT GET_LOCK(CONCAT('aeternum:',SHA2(DATABASE(),224)),5)")->fetchColumn();
        if ((int)$lock!==1) throw new \RuntimeException('Migration lock unavailable');
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS engine_schema_migrations (filename VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, digest CHAR(64) NOT NULL, completed TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB');
            $applied=[];
            $known=$this->pdo->query('SELECT filename,digest,completed FROM engine_schema_migrations ORDER BY filename')->fetchAll(PDO::FETCH_ASSOC);
            $names=array_map('basename',$files);
            foreach ($known as $index=>$row) {
                if ((int)$row['completed']!==1 || ($names[$index]??null)!==$row['filename'] || hash_file('sha256',$files[$index])!==$row['digest']) throw new \RuntimeException('Dirty, changed or missing migration; operator recovery required');
            }
            foreach ($files as $index=>$file) {
                if ($index<count($known)) continue;
                $name=basename($file);
                if (!preg_match('/^[0-9]{3}_[a-z0-9_]+\.sql$/D',$name)) throw new \RuntimeException('Invalid migration filename');
                $sql=file_get_contents($file);
                if ($sql===false || strlen($sql)>1048576) throw new \RuntimeException('Migration unreadable or oversized');
                $s=$this->pdo->prepare('INSERT INTO engine_schema_migrations(filename,digest,completed) VALUES (?,?,0)');
                $s->execute([$name,hash('sha256',$sql)]);
                // Repository migrations use simple DDL without quoted semicolons or stored routines.
                $sql=preg_replace('/^\s*--.*$/m','',$sql);
                foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') $this->pdo->exec($statement);
                $s=$this->pdo->prepare('UPDATE engine_schema_migrations SET completed=1 WHERE filename=?');
                $s->execute([$name]);
                $applied[]=$name;
            }
            return $applied;
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK(CONCAT('aeternum:',SHA2(DATABASE(),224)))")->fetchColumn();
        }
    }
}
