<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Frozen API-1 thematic aliases, separate from generic economic routing. */
final class ReferenceGameplayCompatibility
{
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
