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

    /**
     * Live entity development component derived from replay-canonical state.
     * No secondary mutable counter can drift from the journal. Buildings are
     * owned by the same actor and linked to this entity by relational FKs.
     */
    public function ownDevelopment(string $actor,string $entity): array
    {
        PdoMemberships::validateId($actor);
        PdoMemberships::validateId($entity);
        return $this->locked(function () use ($actor,$entity): array {
            $this->active($actor);
            if ($this->query('SELECT entity_id FROM engine_entities WHERE world_id=? AND entity_id=? AND actor_id=?',
                [$this->world,$entity,$actor])->fetchColumn()===false) {
                throw new \DomainException('Entity access denied');
            }
            $rows=$this->query('SELECT building_id,building_type FROM engine_buildings
                WHERE world_id=? AND entity_id=? AND actor_id=?
                ORDER BY building_id LIMIT 1001',[$this->world,$entity,$actor])->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows)>1000) throw new \DomainException('Entity development projection too large');
            $json=$this->query('SELECT state_json FROM engine_worlds WHERE world_id=?',
                [$this->world])->fetchColumn();
            if ($json===false) throw new \LogicException('Missing canonical state');
            $state=ScalarMap::decode($json);
            $components=[];$total=0;$pending=0;$typeLevels=[];
            foreach ($rows as $row) {
                $prefix='build:'.$row['building_id'].':';
                if (isset($state[$prefix.'type']) && $state[$prefix.'type']!==$row['building_type']) {
                    throw new \UnexpectedValueException('Building type state drift');
                }
                $level=$state[$prefix.'level']??0;
                if (!is_int($level) || $level<0 || $level>16) {
                    throw new \UnexpectedValueException('Building level state drift');
                }
                $busy=isset($state[$prefix.'pending']);
                $total+=$level;
                if ($busy) $pending++;
                $typeLevels[$row['building_type']]=max($typeLevels[$row['building_type']]??0,$level);
                $components[]=['building'=>$row['building_id'],'type'=>$row['building_type'],
                    'level'=>$level,'pending'=>$busy];
            }
            ksort($typeLevels,SORT_STRING);
            return ['entity'=>$entity,'development'=>[
                'levelSum'=>$total,'buildingCount'=>count($components),
                'pendingCount'=>$pending,'types'=>$typeLevels
            ],'buildings'=>$components];
        });
    }

    /** Trusted immutable component attachment; not a simulation command. */
    public function attach(string $id,string $component,array $data): void
    {
        PdoMemberships::validateId($id);
        PdoMemberships::validateId($component);
        if (count($data)>64) throw new \InvalidArgumentException('Too many component fields');
        $json=ScalarMap::encode($data);
        if (strlen($json)>8192) throw new \InvalidArgumentException('Component too large');
        $this->locked(function () use ($id,$component,$json): void {
            if ($this->query('SELECT entity_id FROM engine_entities WHERE world_id=? AND entity_id=?',[$this->world,$id])->fetchColumn()===false) throw new \DomainException('Unknown entity');
            $previous=$this->query('SELECT data_json FROM engine_components WHERE world_id=? AND entity_id=? AND component_id=?',[$this->world,$id,$component])->fetchColumn();
            if ($previous!==false) {
                if ($previous!==$json) throw new \DomainException('Conflicting component');
                return;
            }
            if ((int)$this->query('SELECT COUNT(*) FROM engine_components WHERE world_id=? AND entity_id=?',[$this->world,$id])->fetchColumn()>=64) throw new \DomainException('Component limit');
            $this->query('INSERT INTO engine_components(world_id,entity_id,component_id,data_json) VALUES (?,?,?,?)',[$this->world,$id,$component,$json]);
        });
    }

    public function ownComponent(string $actor,string $id,string $component): array
    {
        foreach ([$actor,$id,$component] as $value) PdoMemberships::validateId($value);
        return $this->locked(function () use ($actor,$id,$component): array {
            $this->active($actor);
            $json=$this->query('SELECT c.data_json FROM engine_components c JOIN engine_entities e ON e.world_id=c.world_id AND e.entity_id=c.entity_id WHERE c.world_id=? AND c.entity_id=? AND c.component_id=? AND e.actor_id=?',[$this->world,$id,$component,$actor])->fetchColumn();
            if ($json===false) throw new \DomainException('Component access denied');
            return ScalarMap::decode($json);
        });
    }
}
