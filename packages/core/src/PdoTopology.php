<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** Trusted topology editor and bounded, actor-authorized route discovery. */
final class PdoTopology
{
    public function __construct(private readonly PDO $pdo, private readonly string $world)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world)) throw new \InvalidArgumentException('Invalid world');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Native MySQL required');
    }

    private function query(string $sql,array $params): \PDOStatement
    {
        $stmt=$this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function transaction(callable $fn): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction');
        $this->pdo->beginTransaction();
        try {
            if ($this->query('SELECT world_id FROM engine_worlds WHERE world_id=? FOR UPDATE',[$this->world])->fetchColumn()===false) throw new \DomainException('Unknown world');
            $result=$fn();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Immutable directed edge; repeat is idempotent, conflicting cost is rejected. */
    public function connect(string $from,string $to,int $cost): void
    {
        PdoMemberships::validateId($from);
        PdoMemberships::validateId($to);
        if ($from===$to || $cost<1 || $cost>1000000) throw new \InvalidArgumentException('Invalid edge');
        $this->transaction(function () use ($from,$to,$cost): void {
            foreach ([$from,$to] as $id) {
                if ($this->query('SELECT location_id FROM engine_locations WHERE world_id=? AND location_id=?',[$this->world,$id])->fetchColumn()===false) throw new \DomainException('Unknown location');
            }
            $existing=$this->query('SELECT travel_cost FROM engine_location_links WHERE world_id=? AND from_location=? AND to_location=?',[$this->world,$from,$to])->fetchColumn();
            if ($existing!==false) {
                if ((int)$existing!==$cost) throw new \DomainException('Conflicting edge');
                return;
            }
            $this->query('INSERT INTO engine_location_links(world_id,from_location,to_location,travel_cost) VALUES (?,?,?,?)',[$this->world,$from,$to,$cost]);
        });
    }

    /** Only active members may inspect topology; no foreign-world identifiers leak. */
    public function neighbors(string $actor,string $from,int $limit=50): array
    {
        PdoMemberships::validateId($actor);
        PdoMemberships::validateId($from);
        if ($limit<1 || $limit>100) throw new \InvalidArgumentException('Invalid page size');
        return $this->transaction(function () use ($actor,$from,$limit): array {
            if ($this->query('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',[$this->world,$actor])->fetchColumn()===false) throw new \DomainException('Access denied');
            if ($this->query('SELECT location_id FROM engine_locations WHERE world_id=? AND location_id=?',[$this->world,$from])->fetchColumn()===false) throw new \DomainException('Unknown location');
            $rows=$this->query('SELECT to_location,travel_cost FROM engine_location_links WHERE world_id=? AND from_location=? ORDER BY to_location LIMIT '.(int)$limit,[$this->world,$from])->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static fn(array $r): array => ['location'=>$r['to_location'],'cost'=>(int)$r['travel_cost']],$rows);
        });
    }
}
