<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** Fixed-window limiter; trusted server chooses bucket and policy. */
final class PdoRateLimiter
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Require native PDO MySQL exceptions');
    }
    public function admit(string $bucket,int $limit,int $seconds): bool
    {
        if ($bucket==='' || strlen($bucket)>512 || $limit<1 || $limit>100000 || $seconds<1 || $seconds>86400) throw new \InvalidArgumentException('Invalid rate policy');
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction prohibited');
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid clock');
        $window=intdiv($now,$seconds)*$seconds;
        $key=hash('sha256',$seconds.':'.$limit.':'.$bucket);
        $this->pdo->beginTransaction();
        try {
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
}
