<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Single-run CRON cycle with database advisory lease. A competing process
 * skips without touching durable state. All world writes use existing
 * transactional scheduler and retention services; no external I/O in locks.
 */
final class OperationsCron
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES))
            throw new \InvalidArgumentException('Native PDO MySQL required');
    }

    /** @param array<string,GamePack> $worlds */
    public function run(array $worlds,int $eventLimit=50,int $batch=100,int $budgetMs=9000): array
    {
        if (count($worlds)>16 || $eventLimit<1 || $eventLimit>100
            || $batch<1 || $batch>500 || $budgetMs<100 || $budgetMs>15000)
            throw new \InvalidArgumentException('Invalid bounded CRON policy');
        if ($this->pdo->inTransaction()) throw new \LogicException('CRON cannot own outer transaction');
        foreach ($worlds as $id=>$pack) {
            if (!is_string($id)
                || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$id)
                || !$pack instanceof GamePack) throw new \InvalidArgumentException('Unapproved CRON world');
        }
        // MariaDB GET_LOCK namespace is limited to 64 bytes. A fixed digest
        // names the database-scoped lease, never a secret or raw DSN.
        $db=(string)$this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($db==='') throw new \RuntimeException('Database not selected');
        $name=hash('sha256','aeternum:m11:cron:'.$db);
        $lock=$this->pdo->prepare('SELECT GET_LOCK(?,0)');
        $lock->execute([$name]);
        if ((int)$lock->fetchColumn()!==1)
            return ['schemaVersion'=>1,'skipped'=>'already_running','worlds'=>[],
                'cleanup'=>null];
        $begin=hrtime(true);
        try {
            $processed=[];
            foreach ($worlds as $id=>$pack) {
                $remaining=$budgetMs-(int)((hrtime(true)-$begin)/1000000);
                if ($remaining<100) {
                    $processed[]=['world'=>$id,'processed'=>0,'deferred'=>true];
                    continue;
                }
                $completed=(new WorldWorker($this->pdo,$this->clock))
                    ->run($id,$pack,$eventLimit,min($remaining,3000));
                $processed[]=['world'=>$id,'processed'=>count($completed),'deferred'=>false];
            }
            $remaining=$budgetMs-(int)((hrtime(true)-$begin)/1000000);
            if ($remaining<100) return ['schemaVersion'=>1,'skipped'=>null,
                'worlds'=>$processed,'cleanup'=>null,'maintenanceDeferred'=>true];
            $cleanup=[
                'expiredSessions'=>(new PdoSessions($this->pdo,$this->clock))->purgeExpired($batch),
                'staleRateBuckets'=>(new PdoRateLimiter($this->pdo,$this->clock))->purgeStale($batch),
                'unusedInvitations'=>(new PdoInvitations($this->pdo,$this->clock))->purgeInactive($batch),
                'expiredActivations'=>(new PdoEmailActivation($this->pdo,$this->clock))->purgeExpired(min($batch,500))
            ];
            return ['schemaVersion'=>1,'skipped'=>null,'worlds'=>$processed,
                'cleanup'=>$cleanup,'maintenanceDeferred'=>false];
        } finally {
            $release=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$name]);
        }
    }
}
