<?php
declare(strict_types=1);
namespace Aeternum\Core;

use PDO;

/**
 * M04 trusted territory registry. Assignments are one-way and immutable.
 * This is administrative world seeding, NOT a free player claim or a
 * simulated construction command. Existing pinned worlds are unaffected.
 */
final class PdoTerritories
{
    public function __construct(private readonly PDO $pdo,private readonly string $world)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world)) throw new \InvalidArgumentException('Invalid world');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Native MySQL required');
    }

    private function sql(string $query,array $args): \PDOStatement
    {
        $statement=$this->pdo->prepare($query);
        $statement->execute($args);
        return $statement;
    }

    private function transaction(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested transaction prohibited');
        $this->pdo->beginTransaction();
        try {
            if ($this->sql('SELECT world_id FROM engine_worlds WHERE world_id=? FOR UPDATE',[$this->world])->fetchColumn()===false) {
                throw new \DomainException('Unknown world');
            }
            $result=$work();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Operator-only registration, immutable territory/location/type pair. */
    public function define(string $territory,string $location,string $kind): void
    {
        foreach ([$territory,$location,$kind] as $id) PdoMemberships::validateId($id);
        $this->transaction(function () use ($territory,$location,$kind): void {
            if ($this->sql('SELECT location_id FROM engine_locations WHERE world_id=? AND location_id=?',
                [$this->world,$location])->fetchColumn()===false) throw new \DomainException('Unknown location');
            $old=$this->sql('SELECT location_id,kind FROM engine_territories WHERE world_id=? AND territory_id=?',
                [$this->world,$territory])->fetch(PDO::FETCH_ASSOC);
            if ($old!==false) {
                if ($old!==['location_id'=>$location,'kind'=>$kind]) throw new \DomainException('Conflicting territory definition');
                return;
            }
            $this->sql('INSERT INTO engine_territories(world_id,territory_id,location_id,kind) VALUES (?,?,?,?)',
                [$this->world,$territory,$location,$kind]);
        });
    }

    /** Operator-only grant. A subsequent attempt with another owner fails. */
    public function assign(string $territory,string $owner): void
    {
        PdoMemberships::validateId($territory);
        PdoMemberships::validateId($owner);
        $this->transaction(function () use ($territory,$owner): void {
            if ($this->sql('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',
                [$this->world,$owner])->fetchColumn()===false) throw new \DomainException('Inactive owner');
            $row=$this->sql('SELECT owner_actor_id FROM engine_territories WHERE world_id=? AND territory_id=? FOR UPDATE',
                [$this->world,$territory])->fetch(PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('Unknown territory');
            if ($row['owner_actor_id']===$owner) return;
            if ($row['owner_actor_id']!==null) throw new \DomainException('Territory already assigned');
            $this->sql('UPDATE engine_territories SET owner_actor_id=? WHERE world_id=? AND territory_id=? AND owner_actor_id IS NULL',
                [$owner,$this->world,$territory]);
        });
    }

    /**
     * Bounded stable ordering. Actor IDs are NEVER revealed for other players.
     * Only active members of this world may see its map. Each page observes
     * one committed snapshot; successive pages are not a frozen snapshot.
     */
    public function pageFor(string $actor,int $limit=50,?string $after=null): array
    {
        PdoMemberships::validateId($actor);
        if ($after!==null) PdoMemberships::validateId($after);
        if ($limit<1 || $limit>100) throw new \InvalidArgumentException('Invalid page size');
        return $this->transaction(function () use ($actor,$limit,$after): array {
            if ($this->sql('SELECT actor_id FROM engine_memberships WHERE world_id=? AND actor_id=? AND active=1',
                [$this->world,$actor])->fetchColumn()===false) throw new \DomainException('Access denied');
            $rows=$this->sql('SELECT territory_id,location_id,kind,owner_actor_id FROM engine_territories
                WHERE world_id=? AND territory_id>? ORDER BY territory_id LIMIT '.($limit+1),
                [$this->world,$after??''])->fetchAll(PDO::FETCH_ASSOC);
            $more=count($rows)>$limit;
            if ($more) array_pop($rows);
            $items=[];
            foreach ($rows as $row) {
                $items[]=[
                    'id'=>$row['territory_id'],
                    'location'=>$row['location_id'],
                    'kind'=>$row['kind'],
                    'control'=>$row['owner_actor_id']===null?'free':($row['owner_actor_id']===$actor?'own':'occupied')
                ];
            }
            return ['territories'=>$items,'nextAfter'=>$more?$items[count($items)-1]['id']:null];
        });
    }
}
