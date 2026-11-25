<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

final class WorldWorker
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock) {}
    /** Processes one explicitly configured world without implicit initialization. */
    public function run(string $world,GamePack $pack,int $limit=100,int $budgetMs=5000,?RuleSet $approvedRules=null): array
    {
        if ($limit<1 || $limit>1000 || $budgetMs<1 || $budgetMs>15000) throw new \InvalidArgumentException('Invalid worker bounds');
        WorldRuntime::assertBindings([$world=>$pack],$approvedRules===null?[]:[$world=>$approvedRules]);
        $rules=$approvedRules??$pack->rules();
        if ($rules->id!==$pack->id || $rules->version!==$pack->version) {
            throw new \InvalidArgumentException('Worker rules differ from pinned pack');
        }
        $scheduler=new PdoScheduler($this->pdo,$world,$this->clock,$rules);
        return $scheduler->processDue($rules->events,$limit,$budgetMs);
    }
}
