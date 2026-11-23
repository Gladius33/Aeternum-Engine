<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** Actor-private industrial progress, derived only from committed world state. */
final class PdoIndustryStatus
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $world,
        private readonly RuleSet $rules,
        private readonly IndustryRules $industry
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
        if ($this->pdo->inTransaction()) throw new \LogicException('Nested industry status transaction prohibited');
        $this->pdo->beginTransaction();
        try {
            $world=$this->pdo->prepare('SELECT state_json,rules_key FROM engine_worlds WHERE world_id=? FOR UPDATE');
            $world->execute([$this->world]);
            $snapshot=$world->fetch(PDO::FETCH_ASSOC);
            if ($snapshot===false || $snapshot['rules_key']!==$this->rules->key) {
                throw new \DomainException('Pinned world mismatch');
            }
            $ownership=$this->pdo->prepare('SELECT o.account_id FROM engine_account_owners o
                JOIN engine_memberships m ON m.world_id=o.world_id AND m.actor_id=o.actor_id
                WHERE o.world_id=? AND o.account_id=? AND o.actor_id=? AND m.active=1');
            $ownership->execute([$this->world,$account,$actor]);
            if ($ownership->fetchColumn()===false) throw new \DomainException('Account access denied');
            $state=ScalarMap::decode($snapshot['state_json']);
            $cfg=$this->industry;
            $levels=[];
            foreach ($cfg->victoryLevels as $type=>$required) {
                $levels[$type]=['current'=>$cfg->level($state,$account,$type),'required'=>$required];
            }
            $cooldowns=[];
            foreach (['industry.generate','industry.refine','industry.ship'] as $action) {
                $last=$state['industry:'.$account.':last:'.$action]??null;
                if ($last!==null && (!is_int($last) || $last<0)) {
                    throw new \UnexpectedValueException('Invalid industrial cooldown');
                }
                $cooldowns[$action]=$last===null?null:$last+$cfg->cooldownSeconds;
            }
            $delivered=$cfg->delivered($state,$account);
            $result=[
                'account'=>$account,
                'grid'=>$cfg->grid($state,$account),
                'warehouseCapacity'=>$cfg->warehouseCapacity($state,$account),
                'delivery'=>['current'=>$delivered,'required'=>$cfg->deliveryGoal],
                'nextActionAt'=>$cooldowns,
                'victory'=>['achieved'=>$cfg->won($state,$account),'buildings'=>$levels]
            ];
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
