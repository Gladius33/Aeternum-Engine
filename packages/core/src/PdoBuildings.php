<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * M04 immutable building catalog and trusted level-zero foundation placement.
 * No player mutation, debit, upgrade, construction or journal event yet.
 * All mutations serialize through the world lock and preserve pinned rules.
 */
final class PdoBuildings
{
    public function __construct(private readonly PDO $pdo,private readonly string $world)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world)) throw new \InvalidArgumentException('Invalid world');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Native MySQL required');
    }

    private function sql(string $query,array $params): \PDOStatement
    {
        $statement=$this->pdo->prepare($query);
        $statement->execute($params);
        return $statement;
    }

    private function locked(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction');
        $this->pdo->beginTransaction();
        try {
            $rules=$this->sql('SELECT rules_key FROM engine_worlds WHERE world_id=? FOR UPDATE',[$this->world])->fetchColumn();
            if ($rules===false) throw new \DomainException('Unknown world');
            $result=$work((string)$rules);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** Operator-owned immutable plan (level 1..16 with positive resource costs). */
    public function define(string $type,array $stages): void
    {
        PdoMemberships::validateId($type);
        $json=json_encode(BuildStages::normalize($stages),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if (strlen($json)>8192) throw new \InvalidArgumentException('Building schedule too large');
        $this->locked(function (string $rules) use ($type,$json): void {
            $row=$this->sql('SELECT rules_key,stages_json FROM engine_building_types WHERE world_id=? AND building_type=?',
                [$this->world,$type])->fetch(PDO::FETCH_ASSOC);
            if ($row!==false) {
                if ($row['rules_key']!==$rules || $row['stages_json']!==$json) throw new \DomainException('Conflicting building rules');
                return;
            }
            $this->sql('INSERT INTO engine_building_types(world_id,building_type,rules_key,stages_json) VALUES (?,?,?,?)',
                [$this->world,$type,$rules,$json]);
        });
    }

    /** Operator-only foundation; an actor must already own both site and entity. */
    public function establish(string $id,string $territory,string $entity,string $actor,string $type): void
    {
        foreach ([$id,$territory,$entity,$actor,$type] as $value) PdoMemberships::validateId($value);
        $this->locked(function (string $rules) use ($id,$territory,$entity,$actor,$type): void {
            $member=$this->sql('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',
                [$this->world,$actor])->fetchColumn();
            if ($member===false) throw new \DomainException('Inactive actor');
            $model=$this->sql('SELECT location_id,actor_id FROM engine_entities WHERE world_id=? AND entity_id=?',
                [$this->world,$entity])->fetch(PDO::FETCH_ASSOC);
            $land=$this->sql('SELECT location_id,owner_actor_id FROM engine_territories WHERE world_id=? AND territory_id=?',
                [$this->world,$territory])->fetch(PDO::FETCH_ASSOC);
            $buildingRules=$this->sql('SELECT rules_key FROM engine_building_types WHERE world_id=? AND building_type=?',
                [$this->world,$type])->fetchColumn();
            if ($model===false || $land===false || $buildingRules!==$rules
                || $model['actor_id']!==$actor || $land['owner_actor_id']!==$actor
                || $model['location_id']!==$land['location_id']) {
                throw new \DomainException('Unauthorized or incompatible foundation');
            }
            $current=$this->sql('SELECT entity_id,territory_id,actor_id,building_type FROM engine_buildings
                WHERE world_id=? AND building_id=?',[$this->world,$id])->fetch(PDO::FETCH_ASSOC);
            if ($current!==false) {
                if ($current!==[
                    'entity_id'=>$entity,'territory_id'=>$territory,'actor_id'=>$actor,'building_type'=>$type
                ]) throw new \DomainException('Conflicting building foundation');
                return;
            }
            $this->sql('INSERT INTO engine_buildings(world_id,building_id,entity_id,territory_id,actor_id,building_type)
                VALUES (?,?,?,?,?,?)',[$this->world,$id,$entity,$territory,$actor,$type]);
        });
    }

    /** Own building and next immutable cost schedule; no other actor's site. */
    public function own(string $actor,string $id): array
    {
        PdoMemberships::validateId($actor);PdoMemberships::validateId($id);
        return $this->locked(function (string $rules) use ($actor,$id): array {
            $this->sql('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',
                [$this->world,$actor])->fetchColumn()!==false || throw new \DomainException('Access denied');
            $row=$this->sql('SELECT b.building_id,b.entity_id,b.territory_id,b.building_type,b.level_no,
                t.rules_key,t.stages_json FROM engine_buildings b JOIN engine_building_types t
                ON t.world_id=b.world_id AND t.building_type=b.building_type
                WHERE b.world_id=? AND b.building_id=? AND b.actor_id=?',
                [$this->world,$id,$actor])->fetch(PDO::FETCH_ASSOC);
            if ($row===false || $row['rules_key']!==$rules) throw new \DomainException('Building access denied');
            $stages=BuildStages::normalize(json_decode($row['stages_json'],true,16,JSON_THROW_ON_ERROR));
            // Canonical construction actions live in the world journal/state;
            // the relational building table is immutable ownership metadata.
            $stateJson=$this->sql('SELECT state_json FROM engine_worlds WHERE world_id=?',
                [$this->world])->fetchColumn();
            if ($stateJson===false) throw new \LogicException('Missing world state');
            $state=ScalarMap::decode($stateJson);
            $prefix='build:'.$id.':';
            if (isset($state[$prefix.'type']) && $state[$prefix.'type']!==$row['building_type']) {
                throw new \UnexpectedValueException('Building type differs from replay state');
            }
            $level=$state[$prefix.'level']??0;
            if (!is_int($level) || $level<0 || $level>count($stages)) {
                throw new \UnexpectedValueException('Invalid building level');
            }
            $pending=$state[$prefix.'pending']??null;
            if ($pending!==null && (!is_string($pending)
                || !preg_match('/^build:[a-f0-9]{64}$/D',$pending))) {
                throw new \UnexpectedValueException('Invalid building pending event');
            }
            $account=$state[$prefix.'account']??$actor;
            if (!is_string($account)) throw new \UnexpectedValueException('Invalid building account');
            return ['id'=>$row['building_id'],'entity'=>$row['entity_id'],'territory'=>$row['territory_id'],
                'type'=>$row['building_type'],'account'=>$account,'level'=>$level,'pending'=>$pending,
                'startedAt'=>$pending!==null?($state[$prefix.'started']??null):null,
                'next'=>$stages[$level]??null];
        });
    }
}
