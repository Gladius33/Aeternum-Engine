<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Validates bounded JSON content; never interprets PHP, paths, URLs or scripts from a manifest. */
final class GamePackLoader
{
    public const MAX_BYTES = 262144;
    public const ENGINE_API = '1';

    private static function object(mixed $value,array $keys): array
    {
        if (!$value instanceof \stdClass) throw new \InvalidArgumentException('Expected JSON object');
        $fields=get_object_vars($value);
        $actual=array_keys($fields); sort($actual,SORT_STRING); sort($keys,SORT_STRING);
        if ($actual !== $keys) throw new \InvalidArgumentException('Unexpected or missing manifest fields');
        return $fields;
    }

    private static function string(mixed $value,int $max): string
    {
        if (!is_string($value) || $value==='' || strlen($value)>$max || trim($value)!==$value || preg_match('/[\x00-\x1f\x7f]/',$value)) {
            throw new \InvalidArgumentException('Invalid manifest string');
        }
        return $value;
    }

    private static function integer(mixed $value): int
    {
        if (!is_int($value)) throw new \InvalidArgumentException('Expected exact JSON integer');
        return $value;
    }

    /** @return array<string,PackFields> */
    private static function definitions(mixed $raw,string $key,string $field): array
    {
        if (!is_array($raw) || count($raw)>128) throw new \InvalidArgumentException('Invalid SDK definitions');
        $definitions=[];
        foreach ($raw as $entry) {
            $row=self::object($entry,[$key,$field]);
            $id=self::string($row[$key],128);
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$id)
                || isset($definitions[$id])) throw new \InvalidArgumentException('Invalid or duplicate SDK definition');
            $definitions[$id]=PackFields::load($row[$field]);
        }
        return $definitions;
    }

    public static function load(string $json): GamePack
    {
        if ($json==='' || strlen($json)>self::MAX_BYTES) throw new \InvalidArgumentException('Manifest size out of bounds');
        $decoded=StrictJson::decode($json,false,32);
        if (!$decoded instanceof \stdClass) throw new \InvalidArgumentException('Expected JSON object');
        $fields=get_object_vars($decoded);
        $required=['engine_api','id','version','name','resources','upgrades'];
        $optional=['components','actions','event_hooks','buildings','settlement','industry','player_homestead','progression'];
        foreach ($required as $key) if (!array_key_exists($key,$fields)) throw new \InvalidArgumentException('Missing manifest field');
        foreach (array_keys($fields) as $key) if (!in_array($key,$required,true) && !in_array($key,$optional,true)) throw new \InvalidArgumentException('Unexpected manifest field');
        $id=self::string($fields['id'],128);
        $version=self::string($fields['version'],64);
        $name=self::string($fields['name'],160);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/D',$id)
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.+-]{0,63}$/D',$version)
            || $fields['engine_api']!==self::ENGINE_API) throw new \InvalidArgumentException('Invalid or incompatible pack identity');
        if (!is_array($fields['resources']) || $fields['resources']===[] || count($fields['resources'])>128
            || !is_array($fields['upgrades']) || count($fields['upgrades'])>12800) throw new \InvalidArgumentException('Invalid manifest collections');
        $resources=[];
        foreach ($fields['resources'] as $raw) {
            $r=self::object($raw,['id','initial_stock','capacity','amount','interval_seconds']);
            $resources[]=new ResourceDefinition(self::string($r['id'],64),self::integer($r['initial_stock']),self::integer($r['capacity']),self::integer($r['amount']),self::integer($r['interval_seconds']));
        }
        $upgrades=[];
        foreach ($fields['upgrades'] as $raw) {
            $u=self::object($raw,['resource','level','cost','duration_seconds','amount']);
            $upgrades[]=new ResourceUpgrade(self::string($u['resource'],64),self::integer($u['level']),self::integer($u['cost']),self::integer($u['duration_seconds']),self::integer($u['amount']));
        }
        // Cross-field validation: uniqueness, resource references, contiguous levels, feasibility.
        new EconomyCommandHandler($resources,$upgrades);
        $components=self::definitions($fields['components']??[],'id','fields');
        $actions=self::definitions($fields['actions']??[],'type','payload');
        $hooks=self::definitions($fields['event_hooks']??[],'type','payload');
        // API-1 additive, opt-in construction rules. Legacy packs omit this
        // field and keep their original digest and deterministic handlers.
        $buildings=[];
        $buildingRows=$fields['buildings']??[];
        if (!is_array($buildingRows) || count($buildingRows)>128) {
            throw new \InvalidArgumentException('Invalid building manifest');
        }
        foreach ($buildingRows as $rawBuilding) {
            $definition=self::object($rawBuilding,['type','stages']);
            $type=self::string($definition['type'],64);
            PdoMemberships::validateId($type);
            if (isset($buildings[$type]) || !is_array($definition['stages'])) {
                throw new \InvalidArgumentException('Duplicate or malformed building type');
            }
            $stages=[];
            foreach ($definition['stages'] as $rawStage) {
                $stage=self::object($rawStage,['costs','duration','requires']);
                if (!$stage['costs'] instanceof \stdClass || !$stage['requires'] instanceof \stdClass) {
                    throw new \InvalidArgumentException('Stage maps must be objects');
                }
                $stages[]=[
                    'costs'=>get_object_vars($stage['costs']),
                    'duration'=>$stage['duration'],
                    'requires'=>get_object_vars($stage['requires'])
                ];
            }
            $buildings[$type]=BuildStages::normalize($stages);
        }
        // Additional opt-in settlement mechanics are fully data-only.
        $settlement=null;
        if (array_key_exists('settlement',$fields)) {
            if (!$fields['settlement'] instanceof \stdClass) throw new \InvalidArgumentException('Invalid settlement section');
            $settlement=SettlementRules::load($fields['settlement'],$resources,$buildings);
        }
        // Rules are strictly opt-in: historical pinned packs have no industry
        // actions and retain byte-for-byte identical digests and behavior.
        $industry=null;
        if (array_key_exists('industry',$fields)) {
            if (!$fields['industry'] instanceof \stdClass || $settlement!==null) {
                throw new \InvalidArgumentException('Invalid industrial rules section');
            }
            $industry=IndustryRules::load($fields['industry'],$resources,$buildings);
        }
        // Generic opt-in: an operator-approved game can grant private
        // construction territory without enabling medieval/industrial rules.
        $playerHomestead=$fields['player_homestead']??false;
        if (!is_bool($playerHomestead) || ($playerHomestead && $buildings===[])) {
            throw new \InvalidArgumentException('Invalid player homestead policy');
        }
        $progression=null;
        if (array_key_exists('progression',$fields)) {
            if (!$fields['progression'] instanceof \stdClass) {
                throw new \InvalidArgumentException('Invalid progression rules');
            }
            $progression=ScoreRules::load($fields['progression'],$resources,$buildings);
        }
        // Check cross-references against the exact pack resource definitions.
        new EconomyCommandHandler($resources,$upgrades,$buildings,$settlement,$industry);
        return new GamePack($id,$version,$name,hash('sha256',$json),$resources,$upgrades,$components,$actions,$hooks,$buildings,$settlement,$industry,$playerHomestead,$progression);
    }
}
