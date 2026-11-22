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

    /**
     * Deterministic shortest directed path by positive travel cost.
     * No cross-world reads; at most 500 expanded nodes and 100 edges/node.
     * Equal-cost alternatives are resolved lexicographically by the whole path.
     * This is a read-only route planner, NOT a movement action.
     */
    public function shortestPath(string $actor,string $from,string $to,int $maxVisited=500): ?array
    {
        foreach ([$actor,$from,$to] as $id) PdoMemberships::validateId($id);
        if ($maxVisited<1 || $maxVisited>500) throw new \InvalidArgumentException('Invalid search budget');
        return $this->transaction(function () use ($actor,$from,$to,$maxVisited): ?array {
            if ($this->query('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',
                [$this->world,$actor])->fetchColumn()===false) throw new \DomainException('Access denied');
            foreach ([$from,$to] as $id) {
                if ($this->query('SELECT location_id FROM engine_locations WHERE world_id=? AND location_id=?',
                    [$this->world,$id])->fetchColumn()===false) throw new \DomainException('Unknown location');
            }
            $frontier=[$from=>['cost'=>0,'locations'=>[$from]]];
            $visited=[];
            while ($frontier!==[]) {
                $best=null;
                foreach ($frontier as $id=>$candidate) {
                    if ($best===null || $candidate['cost']<$frontier[$best]['cost'] ||
                        ($candidate['cost']===$frontier[$best]['cost'] &&
                         strcmp(implode("\0",$candidate['locations']),implode("\0",$frontier[$best]['locations']))<0)) {
                        $best=$id;
                    }
                }
                $current=$frontier[$best];
                unset($frontier[$best]);
                if (count($visited)>=$maxVisited) throw new \DomainException('Path search budget exhausted');
                $visited[$best]=true;
                if ((string)$best===$to) return $current;
                $edges=$this->query('SELECT to_location,travel_cost FROM engine_location_links
                    WHERE world_id=? AND from_location=? ORDER BY to_location LIMIT 101',
                    [$this->world,$best])->fetchAll(PDO::FETCH_ASSOC);
                if (count($edges)>100) throw new \DomainException('Too many outgoing links');
                foreach ($edges as $edge) {
                    $next=$edge['to_location'];
                    if (isset($visited[$next])) continue;
                    $candidate=[
                        'cost'=>$current['cost']+(int)$edge['travel_cost'],
                        'locations'=>[...$current['locations'],$next]
                    ];
                    if (!isset($frontier[$next]) ||
                        $candidate['cost']<$frontier[$next]['cost'] ||
                        ($candidate['cost']===$frontier[$next]['cost'] &&
                        strcmp(implode("\0",$candidate['locations']),implode("\0",$frontier[$next]['locations']))<0)) {
                        $frontier[$next]=$candidate;
                    }
                }
            }
            return null;
        });
    }

    /** Only active members may inspect topology; no foreign-world identifiers leak. */
    public function neighbors(string $actor,string $from,int $limit=50,?string $after=null): array
    {
        PdoMemberships::validateId($actor);
        PdoMemberships::validateId($from);
        if ($limit<1 || $limit>100) throw new \InvalidArgumentException('Invalid page size');
        if ($after!==null) PdoMemberships::validateId($after);
        return $this->transaction(function () use ($actor,$from,$limit,$after): array {
            if ($this->query('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',[$this->world,$actor])->fetchColumn()===false) throw new \DomainException('Access denied');
            if ($this->query('SELECT location_id FROM engine_locations WHERE world_id=? AND location_id=?',[$this->world,$from])->fetchColumn()===false) throw new \DomainException('Unknown location');
            $sql='SELECT to_location,travel_cost FROM engine_location_links WHERE world_id=? AND from_location=?';
            $params=[$this->world,$from];
            if ($after!==null) {
                $sql.=' AND to_location > ?';
                $params[]=$after;
            }
            $sql.=' ORDER BY to_location LIMIT '.(int)$limit;
            $rows=$this->query($sql,$params)->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static fn(array $r): array => ['location'=>$r['to_location'],'cost'=>(int)$r['travel_cost']],$rows);
        });
    }
}
