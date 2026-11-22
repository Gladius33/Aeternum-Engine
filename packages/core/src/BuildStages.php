<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Strict, data-only stage catalog. This validates costs; it NEVER spends them. */
final class BuildStages
{
    public static function normalize(array $stages): array
    {
        if (!array_is_list($stages) || $stages===[] || count($stages)>16) {
            throw new \InvalidArgumentException('Invalid stage count');
        }
        $normalized=[];
        foreach ($stages as $stage) {
            if (!is_array($stage) || array_keys($stage)!==['costs','duration','requires']) {
                throw new \InvalidArgumentException('Invalid stage fields');
            }
            $costs=$stage['costs'];$requires=$stage['requires'];$duration=$stage['duration'];
            if (!is_array($costs) || $costs===[] || count($costs)>16 || array_is_list($costs)
                || !is_array($requires) || count($requires)>16 || ($requires!==[] && array_is_list($requires))
                || !is_int($duration) || $duration<1 || $duration>2592000) {
                throw new \InvalidArgumentException('Invalid construction stage');
            }
            foreach ($costs as $resource=>$units) {
                if (!is_string($resource)) throw new \InvalidArgumentException('Invalid cost resource');
                PdoMemberships::validateId($resource);
                if (!is_int($units) || $units<1 || $units>ProductionState::MAX_UNITS) {
                    throw new \InvalidArgumentException('Invalid cost quantity');
                }
            }
            foreach ($requires as $type=>$level) {
                if (!is_string($type)) throw new \InvalidArgumentException('Invalid required building');
                PdoMemberships::validateId($type);
                if (!is_int($level) || $level<1 || $level>16) {
                    throw new \InvalidArgumentException('Invalid required building level');
                }
            }
            ksort($costs,SORT_STRING);
            ksort($requires,SORT_STRING);
            $normalized[]=['costs'=>$costs,'duration'=>$duration,'requires'=>$requires];
        }
        return $normalized;
    }
}
