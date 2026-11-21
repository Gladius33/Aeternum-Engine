<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** Fixed-window limiter; trusted server chooses bucket and policy. */
final class PdoRateLimiter
{
    private const RETENTION=604800;
    private const MAX_WINDOW=86400;
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Require native PDO MySQL exceptions');
    }
    public function admit(string $bucket,int $limit,int $seconds): bool
    {
        if ($bucket==='' || strlen($bucket)>512 || $limit<1 || $limit>100000 || $seconds<1 || $seconds>self::MAX_WINDOW) throw new \InvalidArgumentException('Invalid rate policy');
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction prohibited');
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid clock');
        $window=intdiv($now,$seconds)*$seconds;
        $key=hash('sha256',$seconds.':'.$limit.':'.$bucket);
        $this->pdo->beginTransaction();
        try {
            $floor=$this->pdo->query('SELECT minimum_now FROM engine_rate_limit_maintenance WHERE singleton=1 LOCK IN SHARE MODE')->fetchColumn();
            if ($floor===false) throw new \LogicException('Missing limiter maintenance guard');
            if ($now<(int)$floor) { $this->pdo->commit(); return false; }
            $s=$this->pdo->prepare('INSERT INTO engine_rate_limits(bucket_hash,window_start,hits) VALUES (?,?,0) ON DUPLICATE KEY UPDATE bucket_hash=VALUES(bucket_hash)');
            $s->execute([$key,$window]);
            $s=$this->pdo->prepare('SELECT window_start,hits FROM engine_rate_limits WHERE bucket_hash=? FOR UPDATE');
            $s->execute([$key]);
            $row=$s->fetch(PDO::FETCH_ASSOC);
            $previous=(int)$row['window_start'];
            $hits=(int)$row['hits'];
            if ($window>$previous) $hits=0;
            $allowed=$hits<$limit;
            if ($allowed) {
                $s=$this->pdo->prepare('UPDATE engine_rate_limits SET window_start=?,hits=? WHERE bucket_hash=?');
                $s->execute([max($window,$previous),$hits+1,$key]);
            }
            $this->pdo->commit();
            return $allowed;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
    /** Bounded retention with a durable floor: no deleted bucket can be recreated in an old active window. */
    public function purgeStale(int $limit=500): int
    {
        if ($limit<1 || $limit>1000) throw new \InvalidArgumentException('Invalid cleanup limit');
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction prohibited');
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid clock');
        $this->pdo->beginTransaction();
        try {
            $floor=$this->pdo->query('SELECT minimum_now FROM engine_rate_limit_maintenance WHERE singleton=1 FOR UPDATE')->fetchColumn();
            if ($floor===false) throw new \LogicException('Missing limiter maintenance guard');
            if ($now<(int)$floor) throw new \DomainException('Clock below maintenance floor');
            if ($now<self::RETENTION) { $this->pdo->commit(); return 0; }
            $cutoff=$now-self::RETENTION;
            $s=$this->pdo->prepare('DELETE FROM engine_rate_limits WHERE window_start<=? ORDER BY window_start,bucket_hash LIMIT '.$limit);
            $s->execute([$cutoff]);
            $deleted=$s->rowCount();
            if ($deleted>0) {
                $s=$this->pdo->prepare('UPDATE engine_rate_limit_maintenance SET minimum_now=GREATEST(minimum_now,?) WHERE singleton=1');
                $s->execute([$cutoff+self::MAX_WINDOW]);
            }
            $this->pdo->commit();
            return $deleted;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
