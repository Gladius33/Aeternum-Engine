<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Optional, data-only, immutable progression rules.
 * Never accepts executable expressions, PHP class names or client scores.
 */
final readonly class ScoreRules
{
    /** @param array<string,int> $buildingPoints
     *  @param array<string,int> $upgradePoints
     *  @param array<string,int> $militaryBuildingPoints
     */
    private function __construct(
        public array $buildingPoints,
        public array $upgradePoints,
        public array $militaryBuildingPoints,
        public int $tradePoints,
    ) {}

    /** @param list<ResourceDefinition> $resources
     *  @param array<string,array> $buildings
     */
    public static function load(\stdClass $raw,array $resources,array $buildings): self
    {
        $fields=get_object_vars($raw);
        $keys=array_keys($fields);
        sort($keys,SORT_STRING);
        if ($keys!==['building_points','military_building_points','trade_points','upgrade_points']) {
            throw new \InvalidArgumentException('Invalid progression fields');
        }
        $resourceIds=[];
        foreach ($resources as $resource) $resourceIds[$resource->id]=true;
        $readWeights=static function(mixed $rawWeights,array $ids): array {
            if (!$rawWeights instanceof \stdClass) {
                throw new \InvalidArgumentException('Progression weights must be objects');
            }
            $weights=get_object_vars($rawWeights);
            if (count($weights)>128) throw new \InvalidArgumentException('Too many progression weights');
            foreach ($weights as $id=>$points) {
                if (!is_string($id) || !array_key_exists($id,$ids)
                    || !is_int($points) || $points<0 || $points>1000000) {
                    throw new \InvalidArgumentException('Invalid or unapproved progression weight');
                }
            }
            ksort($weights,SORT_STRING);
            return $weights;
        };
        $trade=$fields['trade_points'];
        if (!is_int($trade) || $trade<0 || $trade>1000000) {
            throw new \InvalidArgumentException('Invalid commerce progression weight');
        }
        $build=array_fill_keys(array_keys($buildings),true);
        $development=$readWeights($fields['building_points'],$build);
        $upgrade=$readWeights($fields['upgrade_points'],$resourceIds);
        $military=$readWeights($fields['military_building_points'],$build);
        if (array_sum($development)+array_sum($upgrade)+array_sum($military)+$trade===0) {
            throw new \InvalidArgumentException('Progression must have positive weights');
        }
        return new self($development,$upgrade,$military,$trade);
    }
}
