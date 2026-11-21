<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** Trusted operator workflow; retry after interruption with identical arguments. */
final class PlayerProvisioner
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock) {}
    public function provision(string $world,string $actor,GamePack $pack): void
    {
        PdoMemberships::validateId($actor);
        $s=$this->pdo->prepare('SELECT actor_id FROM engine_identities WHERE actor_id=?');
        $s->execute([$actor]);
        if ($s->fetchColumn()===false) throw new \DomainException('Unknown identity');
        $rules=$pack->rules();
        $scheduler=new PdoScheduler($this->pdo,$world,$this->clock,$rules);
        $scheduler->initialize();
        $members=new PdoMemberships($this->pdo,$world);
        $members->grant($actor);
        $members->assign($actor,$actor);
        $entities=new PdoEntities($this->pdo,$world);
        $entities->addLocation($actor);
        $entities->provision($actor,$actor,$actor,'settlement');
        foreach ($pack->resources as $resource) {
            $scheduler->executeForActor($actor,new Command($world,'provision:'.$resource->id,'economy.open',['account'=>$actor,'resource'=>$resource->id]),$rules->commands);
        }
    }
}
