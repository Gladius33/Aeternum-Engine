<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class Command
{
    public function __construct(
        public string $worldId,
        public string $id,
        public string $type,
        public array $payload = [],
    ) {
        foreach ([$worldId,$id,$type] as $value) {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$value)) {
                throw new \InvalidArgumentException('Invalid command identifier');
            }
        }
        ScalarMap::validate($payload);
    }
}
