<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;
/** Bounded concurrent expensive public registrations, shared by all PHP workers. */
final class PdoSignupSlots
{
    public function __construct(private readonly PDO $pdo,private readonly int $limit=8)
    {
        if ($limit<1 || $limit>32) throw new \InvalidArgumentException('Invalid signup parallel limit');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES))
            throw new \InvalidArgumentException('Require native PDO MySQL exceptions');
    }
    public function run(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Cannot acquire signup slot inside a transaction');
        $schema=$this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($schema) || $schema==='') throw new \RuntimeException('Database not selected');
        // Named locks are scoped to the database so separate installations on a
        // shared MariaDB host do not contend. A dead PHP connection releases them.
        $prefix='aeternum:signup:'.substr(hash('sha256',$schema),0,16).':';
        $start=random_int(0,$this->limit-1);
        $acquire=$this->pdo->prepare('SELECT GET_LOCK(?,0)');
        $release=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');
        for ($n=0;$n<$this->limit;$n++) {
            $name=$prefix.(($start+$n)%$this->limit);
            $acquire->execute([$name]);
            $held=$acquire->fetchColumn();
            $acquire->closeCursor();
            if ($held===null || $held===false) throw new \RuntimeException('Signup slot service unavailable');
            if ((int)$held!==1) continue;
            try {
                return $work();
            } finally {
                $release->execute([$name]);
                $result=$release->fetchColumn();
                $release->closeCursor();
                if ((int)$result!==1) throw new \RuntimeException('Signup slot release failed');
            }
        }
        throw new SignupBusy('Signup capacity exhausted');
    }
}
