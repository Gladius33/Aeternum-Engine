<?php
declare(strict_types=1);
namespace Aeternum\Core;

use PDO;

/** Trusted provisioning and authenticated own-entity projection. */
final class PdoEntities
{
    public function __construct(private readonly PDO $pdo, private readonly string $world)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $world)) throw new \InvalidArgumentException('Invalid world');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Require native PDO MySQL exceptions');
    }

    private function query(string $sql, array $args): \PDOStatement
    {
        $s=$this->pdo->prepare($sql);
        $s->execute($args);
        return $s;
    }

    private function locked(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction prohibited');
        $this->pdo->beginTransaction();
        try {
            if ($this->query('SELECT world_id FROM engine_worlds WHERE world_id=? FOR UPDATE',[$this->world])->fetchColumn()===false) throw new \DomainException('Unknown world');
            $result=$work();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function active(string $actor): void
    {
        if ($this->query('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',[$this->world,$actor])->fetchColumn()===false) throw new \DomainException('Access denied');
    }

    public function addLocation(string $location): void
    {
        PdoMemberships::validateId($location);
        $this->locked(fn()=> $this->query('INSERT INTO engine_locations(world_id,location_id) VALUES (?,?) ON DUPLICATE KEY UPDATE location_id=VALUES(location_id)',[$this->world,$location]));
    }

    public function provision(string $id,string $location,string $actor,string $kind): void
    {
        foreach ([$id,$location,$actor,$kind] as $value) PdoMemberships::validateId($value);
        $this->locked(function () use ($id,$location,$actor,$kind): void {
            $this->active($actor);
            if ($this->query('SELECT location_id FROM engine_locations WHERE world_id=? AND location_id=?',[$this->world,$location])->fetchColumn()===false) throw new \DomainException('Unknown location');
            $row=$this->query('SELECT location_id,actor_id,kind FROM engine_entities WHERE world_id=? AND entity_id=?',[$this->world,$id])->fetch(PDO::FETCH_ASSOC);
            if ($row!==false) {
                if ($row!==['location_id'=>$location,'actor_id'=>$actor,'kind'=>$kind]) throw new \DomainException('Conflicting entity');
                return;
            }
            $this->query('INSERT INTO engine_entities(world_id,entity_id,location_id,actor_id,kind) VALUES (?,?,?,?,?)',[$this->world,$id,$location,$actor,$kind]);
        });
    }

    public function own(string $actor,string $id): array
    {
        PdoMemberships::validateId($actor);
        PdoMemberships::validateId($id);
        return $this->locked(function () use ($actor,$id): array {
            $this->active($actor);
            $row=$this->query('SELECT entity_id,location_id,kind FROM engine_entities WHERE world_id=? AND actor_id=? AND entity_id=?',[$this->world,$actor,$id])->fetch(PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('Entity access denied');
            return $row;
        });
    }
}
