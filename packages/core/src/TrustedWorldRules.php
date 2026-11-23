<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * A trusted application composition root for pinned per-world rules.
 *
 * This is NOT a runtime plugin loader. Operator-controlled PHP supplies
 * already instantiated, reviewed handlers; JSON registries remain data-only.
 */
final class TrustedWorldRules
{
    /**
     * @param array<string,GamePack> $worldPacks
     * @param array<string,list<ApprovedPackCommand>> $approvedPerWorld
     * @return array<string,RuleSet>
     */
    public static function bind(array $worldPacks,array $approvedPerWorld=[]): array
    {
        if ($worldPacks===[] || count($worldPacks)>512 || count($approvedPerWorld)>512) {
            throw new \InvalidArgumentException('Invalid approved world set');
        }
        foreach ($approvedPerWorld as $world=>$handlers) {
            if (!is_string($world) || !array_key_exists($world,$worldPacks)
                || !is_array($handlers) || !array_is_list($handlers)) {
                throw new \InvalidArgumentException('Handler binding targets an unknown or invalid world');
            }
        }
        $rules=[];
        foreach ($worldPacks as $world=>$pack) {
            if (!is_string($world)
                || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world)
                || !$pack instanceof GamePack) {
                throw new \InvalidArgumentException('Invalid trusted world binding');
            }
            $rules[$world]=$pack->rules($approvedPerWorld[$world]??[]);
        }
        return $rules;
    }
}
