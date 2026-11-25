<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Frozen API-1 thematic aliases, separate from generic economic routing. */
final class ReferenceGameplayCompatibility
{
    /** Frozen actor admission aliases for API-1 worlds. */
    public static function isLegacyAction(string $type): bool
    {
        return in_array($type,['settlement.feed','settlement.work','industry.generate','industry.refine','industry.ship'],true);
    }

    /** The broader historical HTTP rate bucket also covers unknown aliases. */
    public static function isLegacyNamespace(string $type): bool
    {
        return str_starts_with($type,'settlement.') || str_starts_with($type,'industry.');
    }

    public static function requiresHomestead(GamePack $pack): bool
    {
        return $pack->playerHomestead || $pack->settlement!==null || $pack->industry!==null;
    }

    public static function capabilities(GamePack $pack): array
    {
        return ['settlementEnabled'=>$pack->settlement!==null,'industryEnabled'=>$pack->industry!==null];
    }

    /** Existing public status routes remain stable; all reads use pinned rules. */
    public static function status(\PDO $pdo,string $world,GamePack $pack,RuleSet $rules,string $kind,string $actor,string $account): array
    {
        if ($kind==='settlement' && $pack->settlement!==null)
            return (new PdoSettlementStatus($pdo,$world,$rules,$pack->settlement))->forActor($actor,$account);
        if ($kind==='industrial' && $pack->industry!==null)
            return (new PdoIndustryStatus($pdo,$world,$rules,$pack->industry))->forActor($actor,$account);
        throw new \DomainException('Legacy status not enabled');
    }

    /** @return array<string,CommandHandler> */
    public static function bind(EconomyGameplayPort $economy,?SettlementRules $settlement,?IndustryRules $industry): array
    {
        $handlers=[];
        if ($settlement!==null) {
            $module=new ReferenceSettlementCommand($economy,$settlement);
            $handlers['settlement.feed']=$module;
            $handlers['settlement.work']=$module;
        }
        if ($industry!==null) {
            $module=new ReferenceIndustryCommand($economy,$industry);
            foreach (['industry.generate','industry.refine','industry.ship'] as $type) $handlers[$type]=$module;
        }
        return $handlers;
    }
}
