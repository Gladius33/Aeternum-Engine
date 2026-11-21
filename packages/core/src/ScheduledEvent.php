<?php
declare(strict_types=1);
namespace Aeternum\Core;
final readonly class ScheduledEvent
{
    /** @param array<string,int|string|bool|null> $payload */
    public function __construct(
        public string $worldId,
        public string $id,
        public string $type,
        public int $dueAt,
        public array $payload = [],
    ) {
        foreach ([$worldId, $id, $type] as $value) {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $value)) {
                throw new \InvalidArgumentException('Invalid event identifier');
            }
        }
        if ($dueAt < 0) throw new \InvalidArgumentException('Negative due time');
        foreach ($payload as $key => $value) {
            if (!is_string($key) || !(is_int($value) || is_string($value) || is_bool($value) || $value === null)) {
                throw new \InvalidArgumentException('Payload must be a scalar string-keyed map');
            }
        }
    }
    public function equals(self $other): bool {
        $left = $this->payload; $right = $other->payload;
        ksort($left, SORT_STRING); ksort($right, SORT_STRING);
        return $this->worldId === $other->worldId && $this->id === $other->id
            && $this->type === $other->type && $this->dueAt === $other->dueAt && $left === $right;
    }
}
