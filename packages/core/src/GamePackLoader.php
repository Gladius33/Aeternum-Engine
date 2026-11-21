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
        $fields = get_object_vars($value);
        $actual = array_keys($fields); sort($actual,SORT_STRING); sort($keys,SORT_STRING);
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

    public static function load(string $json): GamePack
    {
        if ($json==='' || strlen($json)>self::MAX_BYTES) throw new \InvalidArgumentException('Manifest size out of bounds');
        $root = self::object(json_decode($json,false,32,JSON_THROW_ON_ERROR),['engine_api','id','version','name','resources','upgrades']);
        $id = self::string($root['id'],128);
        $version = self::string($root['version'],64);
        $name = self::string($root['name'],160);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/D',$id)
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.+-]{0,63}$/D',$version)
            || $root['engine_api']!==self::ENGINE_API) throw new \InvalidArgumentException('Invalid or incompatible pack identity');
        if (!is_array($root['resources']) || $root['resources']===[] || count($root['resources'])>128
            || !is_array($root['upgrades']) || count($root['upgrades'])>12800) throw new \InvalidArgumentException('Invalid manifest collections');
        $resources = [];
        foreach ($root['resources'] as $raw) {
            $r = self::object($raw,['id','initial_stock','capacity','amount','interval_seconds']);
            $resources[] = new ResourceDefinition(self::string($r['id'],64),self::integer($r['initial_stock']),self::integer($r['capacity']),self::integer($r['amount']),self::integer($r['interval_seconds']));
        }
        $upgrades = [];
        foreach ($root['upgrades'] as $raw) {
            $u = self::object($raw,['resource','level','cost','duration_seconds','amount']);
            $upgrades[] = new ResourceUpgrade(self::string($u['resource'],64),self::integer($u['level']),self::integer($u['cost']),self::integer($u['duration_seconds']),self::integer($u['amount']));
        }
        // Cross-field validation: uniqueness, resource references, contiguous levels, feasibility.
        new EconomyCommandHandler($resources,$upgrades);
        return new GamePack($id,$version,$name,hash('sha256',$json),$resources,$upgrades);
    }
}
