<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Server-authoritative, read-only victory and workforce projection.
 * Every counter is derived from the one locked, replayable world state.
 */
final class PdoSettlementStatus
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $world,
        private readonly RuleSet $rules,
        private readonly SettlementRules $settlement
    ) {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION
            || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
            throw new \InvalidArgumentException('Native MySQL required');
        }
    }

    public function forActor(string $actor,string $account): array
    {
        PdoMemberships::validateId($actor);
        PdoMemberships::validateId($account);
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested status transaction prohibited');
        $this->pdo->beginTransaction();
        try {
            $world=$this->pdo->prepare('SELECT state_json,rules_key FROM engine_worlds WHERE world_id=? FOR UPDATE');
            $world->execute([$this->world]);
            $snapshot=$world->fetch(PDO::FETCH_ASSOC);
            if ($snapshot===false || $snapshot['rules_key']!==$this->rules->key) throw new \DomainException('Pinned world mismatch');
            $ownership=$this->pdo->prepare('SELECT o.account_id FROM engine_account_owners o
                JOIN engine_memberships m ON m.world_id=o.world_id AND m.actor_id=o.actor_id
                WHERE o.world_id=? AND o.account_id=? AND o.actor_id=? AND m.active=1');
            $ownership->execute([$this->world,$account,$actor]);
            if ($ownership->fetchColumn()===false) throw new \DomainException('Account access denied');
            $state=ScalarMap::decode($snapshot['state_json']);
            $cfg=$this->settlement;
            $population=$cfg->population($state,$account);
            $capacity=$cfg->capacity($state,$account);
            $levels=[];
            foreach ($cfg->victoryLevels as $type=>$required) {
                $levels[$type]=['current'=>$cfg->level($state,$account,$type),'required'=>$required];
            }
            $last=$state['settlement:'.$account.':lastWork']??null;
            if ($last!==null && (!is_int($last) || $last<0)) throw new \UnexpectedValueException('Invalid labor cooldown');
            $result=[
                'account'=>$account,
                'population'=>$population,
                'capacity'=>$capacity,
                'workers'=>max(0,$population-1),
                'foodCost'=>$cfg->foodPerPerson,
                'workFoodCost'=>$cfg->workFoodCost,
                'workYield'=>$cfg->workGainPerLumber*$cfg->level($state,$account,$cfg->lumberType),
                'workCooldown'=>$cfg->workCooldownSeconds,
                'nextWorkAt'=>$last===null?null:$last+$cfg->workCooldownSeconds,
                'victory'=>[
                    'achieved'=>$cfg->won($state,$account),
                    'populationRequired'=>$cfg->victoryPopulation,
                    'buildings'=>$levels
                ]
            ];
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
