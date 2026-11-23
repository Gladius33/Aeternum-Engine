<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Compatibility module for immutable existing game packs. */
final readonly class ReferenceSettlementCommand implements CommandHandler
{
    public function __construct(private EconomyGameplayPort $economy,private SettlementRules $rules) {}

    /** Explicit player labor: consume grain to settle population and convert
     * available labor into wood. Every state change is journaled atomically. */
    public function apply(Command $command,array $state,int $at): CommandResult
    {
        $rules=$this->rules;
        $payload=$this->economy->payload($command,['account']);
        $account=$this->economy->id($payload['account']);
        $prefix='settlement:'.$account.':';
        $population=$rules->population($state,$account);
        $ledger=[];
        if ($command->type==='settlement.feed') {
            if ($rules->level($state,$account,$rules->farmType)<1
                || $population>=$rules->capacity($state,$account)) {
                throw new \DomainException('Population capacity requires developed farms and housing');
            }
            $food=$this->economy->settle($state,$ledger,$account,$rules->foodResource,$at)
                ->debit($rules->foodPerPerson);
            $this->economy->write($state,$account,$rules->foodResource,$food);
            $ledger[]=new ResourceMovement($account,$rules->foodResource,
                -$rules->foodPerPerson,$food->stock,'settlement_food');
            $state[$prefix.'population']=$population+1;
            return new CommandResult($state,[],$ledger);
        }
        if ($command->type!=='settlement.work') throw new \DomainException('Unknown settlement command');
        $lumber=$rules->level($state,$account,$rules->lumberType);
        if ($population<2 || $lumber<1) throw new \DomainException('Insufficient workforce or workshop');
        $last=$state[$prefix.'lastWork']??null;
        if ($last!==null && (!is_int($last) || $last<0 || $last>=$at
            || $at-$last<$rules->workCooldownSeconds)) {
            throw new \DomainException('Workforce is resting');
        }
        $food=$this->economy->settle($state,$ledger,$account,$rules->foodResource,$at)
            ->debit($rules->workFoodCost);
        $wood=$this->economy->settle($state,$ledger,$account,$rules->workResource,$at)
            ->credit($rules->workGainPerLumber*$lumber);
        $this->economy->write($state,$account,$rules->foodResource,$food);
        $this->economy->write($state,$account,$rules->workResource,$wood);
        $ledger[]=new ResourceMovement($account,$rules->foodResource,
            -$rules->workFoodCost,$food->stock,'settlement_food');
        $ledger[]=new ResourceMovement($account,$rules->workResource,
            $rules->workGainPerLumber*$lumber,$wood->stock,'settlement_work');
        $state[$prefix.'lastWork']=$at;
        return new CommandResult($state,[],$ledger);
    }

}
