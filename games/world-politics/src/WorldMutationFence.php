<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

/**
 * WP27 — shared durable, transactional world command admission fence.
 *
 * Invariants:
 * - EVERY world-mutating PDO adapter locks the fence row FIRST, inside its
 *   own transaction, before domain state locks and before receipt replay.
 * - The coordinator takes that same fence lock while declaring intent and
 *   atomically when publishing the final committed day.
 * - A pending saga can ONLY execute a trusted clock operation by the
 *   recorded service account and for the exact pending date.
 * - No wall-clock TTL: a crashed worker never silently releases a fence.
 * - Legacy test worlds without world-clock tables remain independent;
 *   worlds WITH a world clock but without this migration FAIL CLOSED.
 *
 * The service principal is authenticated at each domain store boundary.
 * An end user cannot supply a controller_id as a JSON argument.
 */
final class WorldMutationFence
{
    /** Initialize inside the very same transaction as the world clock. */
    public static function seed(\PDO $pdo,string $world):void
    {
        self::transaction($pdo);self::identifier($world);
        $q=$pdo->prepare(
            "INSERT INTO wp_world_mutation_fences(world_id,state,generation)
             VALUES(?,'idle',0)"
        );
        $q->execute([$world]);
    }

    /**
     * Exclusive lock for coordinator intent and publication. This MUST be
     * acquired before SELECT ... FOR UPDATE on wp_world_clock.
     *
     * @return array<string,mixed>
     */
    public static function lockCoordinator(\PDO $pdo,string $world):array
    {
        self::transaction($pdo);self::identifier($world);
        $q=$pdo->prepare(
            'SELECT state,pending_key,target_day,controller_id,generation
             FROM wp_world_mutation_fences WHERE world_id=? FOR UPDATE'
        );
        $q->execute([$world]);
        $row=$q->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) {
            throw new \DomainException('World write fence is not installed for this clock');
        }
        self::valid($row);
        return $row;
    }

    /** Called with the coordinator fence row already locked. */
    public static function begin(
        \PDO $pdo,string $world,array $current,
        string $key,string $targetDay,string $serviceId
    ):void {
        self::transaction($pdo);self::identifier($world);
        self::hash($key);self::day($targetDay);self::service($serviceId);
        self::valid($current);
        if ($current['state']==='advancing') {
            if (!hash_equals($current['pending_key'],$key)
                || $current['target_day']!==$targetDay
                || $current['controller_id']!==$serviceId) {
                throw new \DomainException('Another world transition already holds the fence');
            }
            return;
        }
        $q=$pdo->prepare(
            "UPDATE wp_world_mutation_fences
             SET state='advancing',pending_key=?,target_day=?,controller_id=?,
                 generation=generation+1 WHERE world_id=? AND state='idle'"
        );
        $q->execute([$key,$targetDay,$serviceId,$world]);
        if ($q->rowCount()!==1) {
            throw new \DomainException('World write fence changed during transition');
        }
    }

    /** Called only after all required subsystem checkpoints are verified. */
    public static function finish(
        \PDO $pdo,string $world,array $current,
        string $key,string $targetDay,string $serviceId
    ):void {
        self::transaction($pdo);self::identifier($world);
        self::hash($key);self::day($targetDay);self::service($serviceId);
        self::valid($current);
        if ($current['state']!=='advancing'
            || !hash_equals($current['pending_key'],$key)
            || $current['target_day']!==$targetDay
            || $current['controller_id']!==$serviceId) {
            throw new \DomainException('Only the original authorized world controller may release a fence');
        }
        $q=$pdo->prepare(
            "UPDATE wp_world_mutation_fences
             SET state='idle',pending_key=NULL,target_day=NULL,
                 controller_id=NULL,generation=generation+1
             WHERE world_id=? AND state='advancing' AND pending_key=?"
        );
        $q->execute([$world,$key]);
        if ($q->rowCount()!==1) {
            throw new \UnexpectedValueException('World fence release was not recorded');
        }
    }

    /**
     * Called INSIDE the mutating domain transaction, before acquiring any
     * state row locks, and including before idempotent receipt replay.
     *
     * @param string $operation 'clock' only for trusted world-clock steps;
     *                          all other operations are player/AI actions.
     */
    public static function admit(
        \PDO $pdo,string $world,string $actor,string $operation,
        ?string $targetDay=null
    ):void {
        self::transaction($pdo);self::identifier($world);self::service($actor);
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$operation)) {
            throw new \InvalidArgumentException('Unknown world mutation type');
        }
        try {
            $q=$pdo->prepare(
                'SELECT state,pending_key,target_day,controller_id,generation
                 FROM wp_world_mutation_fences WHERE world_id=? FOR UPDATE'
            );
            $q->execute([$world]);
            $row=$q->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $error) {
            if (!self::missingTable($error)) throw $error;
            // Standalone legacy domain test schemas have no world clock.
            // A world already governed by wp_world_clock MUST NOT run
            // without the WP27 barrier migration.
            try {
                $check=$pdo->prepare(
                    'SELECT 1 FROM wp_world_clock WHERE world_id=?'
                );
                $check->execute([$world]);
                if ($check->fetchColumn()!==false) {
                    throw new \DomainException('World clock requires WP27 write fence migration');
                }
            } catch (\PDOException $legacy) {
                if (!self::missingTable($legacy)) throw $legacy;
            }
            return;
        }
        if ($row===false) {
            // Before a game world receives its world clock, initializing
            // other world-scoped aggregates is legitimate. Afterward the
            // absence of a fence is a security/configuration failure.
            $q=$pdo->prepare('SELECT 1 FROM wp_world_clock WHERE world_id=?');
            $q->execute([$world]);
            if ($q->fetchColumn()!==false) {
                throw new \DomainException('Clock-governed world lacks mandatory write fence');
            }
            return;
        }
        self::valid($row);
        if ($row['state']==='idle') return;
        // Only internal module clock actions pass. They are separately
        // authorized by their domain-specific principal capabilities.
        if ($operation==='clock' && $targetDay!==null
            && $row['controller_id']===$actor
            && $row['target_day']===$targetDay) return;
        throw new \DomainException('World transition in progress; command admission fenced');
    }

    private static function missingTable(\PDOException $e):bool
    {
        return $e->getCode()==='42S02'
            && (int)($e->errorInfo[1]??0)===1146;
    }

    private static function valid(array $r):void
    {
        if (!in_array($r['state']??null,['idle','advancing'],true)
            || !isset($r['generation'])
            || !ctype_digit((string)$r['generation'])) {
            throw new \UnexpectedValueException('Invalid world fence record');
        }
        if ($r['state']==='advancing') {
            self::hash((string)$r['pending_key']);
            self::day((string)$r['target_day']);
            self::service((string)$r['controller_id']);
        } elseif ($r['pending_key']!==null
            || $r['target_day']!==null || $r['controller_id']!==null) {
            throw new \UnexpectedValueException('Idle world fence has pending fields');
        }
    }
    private static function transaction(\PDO $pdo):void
    {
        if (!$pdo->inTransaction()) {
            throw new \LogicException('World write fence must run inside a PDO transaction');
        }
    }
    private static function identifier(string $world):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$world)) {
            throw new \InvalidArgumentException('Invalid world ID for mutation fence');
        }
    }
    private static function service(string $id):void
    {
        if ($id==='' || strlen($id)>128
            || preg_match('/[\x00-\x1f\x7f]/',$id)) {
            throw new \DomainException('Unverified world service identity');
        }
    }
    private static function hash(string $key):void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$key)) {
            throw new \DomainException('Invalid world clock fence command fingerprint');
        }
    }
    private static function day(string $day):void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day)) {
            throw new \InvalidArgumentException('Invalid world transition date');
        }
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$day);
        if ($parsed===false || $parsed->format('Y-m-d')!==$day) {
            throw new \InvalidArgumentException('Invalid world transition calendar day');
        }
    }
}
