<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/WorldClockCoordinator.php';

/**
 * WP27 — bounded recovery queue for incomplete world-time sagas.
 *
 * No persistent background process is started here. A trusted operator
 * must invoke runOnce through a scheduled, authenticated service worker
 * with a world-specific coordinator factory.
 *
 * For each world, the factory MUST create independently authenticated and
 * source-pinned services (country rulebook, world authority, actor registry).
 * A single global coordinator must not be reused for unrelated game worlds.
 */
final class WorldTickRecoveryWorker
{
    private readonly \Closure $resolve;

    /**
     * @param callable(string):WorldClockCoordinator $factory trusted server-side
     */
    public function __construct(
        private readonly \PDO $pdo,callable $factory
    ) {
        $this->resolve=\Closure::fromCallable($factory);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    /**
     * Bounded, deterministic polling. Multiple workers may encounter the
     * same pending row; coordinator fencing and domain receipts serialize
     * actual execution. Recovery has no lease-based timeout.
     *
     * @return list<array{world_id:string,status:string,committed_day?:string}>
     */
    public function runOnce(int $maxWorlds=10):array
    {
        if ($maxWorlds<1 || $maxWorlds>100) {
            throw new \InvalidArgumentException('Bounded scheduler batch must be 1..100 worlds');
        }
        $q=$this->pdo->prepare(
            'SELECT world_id,pending_day
             FROM wp_world_clock
             WHERE pending_day IS NOT NULL
             ORDER BY world_id LIMIT ?'
        );
        $q->bindValue(1,$maxWorlds,\PDO::PARAM_INT);
        $q->execute();
        $rows=$q->fetchAll(\PDO::FETCH_ASSOC);
        $results=[];
        foreach ($rows as $row) {
            $world=$row['world_id'];
            if (!is_string($world)
                || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$world)) {
                throw new \UnexpectedValueException('Corrupted game world in recovery work queue');
            }
            try {
                $coordinator=($this->resolve)($world);
                if (!$coordinator instanceof WorldClockCoordinator) {
                    throw new \DomainException('No trustworthy coordinator provisioned for this world');
                }
                $result=$coordinator->resumePending($world);
                $results[]=$result===null
                    ?['world_id'=>$world,'status'=>'already_coherent']
                    :[
                        'world_id'=>$world,'status'=>'recovered',
                        'committed_day'=>$result['result']['committed_day']
                    ];
            } catch (\DomainException|\UnexpectedValueException $error) {
                // One legally blocked or unavailable world must not freeze
                // unrelated worlds. The pending intent remains in MariaDB.
                $results[]=[
                    'world_id'=>$world,'status'=>'blocked'
                ];
            }
        }
        return $results;
    }
}
