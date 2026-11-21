<?php
declare(strict_types=1);
namespace Aeternum\Core;

final class ScalarMap
{
    public static function validate(array $map): array
    {
        foreach ($map as $key => $value) {
            if (!is_string($key) || !(is_int($value) || is_string($value) || is_bool($value) || $value === null)) {
                throw new \UnexpectedValueException('Expected string-keyed scalar map');
            }
        }
        ksort($map, SORT_STRING);
        return $map;
    }
    public static function encode(array $map): string
    {
        return json_encode((object) self::validate($map), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
    public static function decode(string $json): array
    {
        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        if (!$decoded instanceof \stdClass) throw new \UnexpectedValueException('Expected JSON object');
        return self::validate((array) $decoded);
    }
}
