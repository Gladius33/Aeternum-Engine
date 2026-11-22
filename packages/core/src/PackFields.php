<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Strict, portable scalar schema for SDK component/action/event payloads. */
final readonly class PackFields
{
    /** @param array<string,string> $types */
    private function __construct(public array $types) {}

    public static function load(mixed $raw): self
    {
        if (!$raw instanceof \stdClass) throw new \InvalidArgumentException('Expected typed field map');
        $fields=get_object_vars($raw);
        if (count($fields)>24) throw new \InvalidArgumentException('Too many typed fields');
        foreach ($fields as $name=>$type) {
            if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D',$name)
                || !is_string($type) || !in_array($type,['integer','string','boolean','nullable_integer','nullable_string','nullable_boolean'],true)) {
                throw new \InvalidArgumentException('Invalid SDK field definition');
            }
        }
        ksort($fields,SORT_STRING);
        return new self($fields);
    }

    /** @param array<string,mixed> $payload */
    public function assertPayload(array $payload): void
    {
        $keys=array_keys($payload);
        sort($keys,SORT_STRING);
        if ($keys!==array_keys($this->types)) throw new \InvalidArgumentException('Invalid typed payload fields');
        foreach ($this->types as $name=>$type) {
            $value=$payload[$name];
            if (str_starts_with($type,'nullable_')) {
                if ($value===null) continue;
                $type=substr($type,9);
            }
            if (!(match($type) {
                'integer'=>is_int($value),
                'boolean'=>is_bool($value),
                'string'=>is_string($value) && strlen($value)<=256 && !preg_match('/[\x00-\x1f\x7f]/',$value),
                default=>false
            })) throw new \InvalidArgumentException('Invalid SDK field type');
        }
    }
}
