<?php
declare(strict_types=1);
namespace Aeternum\Core;
final class FrozenClock implements Clock
{
    public function __construct(private int $timestamp) {
        if ($timestamp < 0) throw new \InvalidArgumentException('Negative timestamp');
    }
    public function now(): int { return $this->timestamp; }
    public function advanceTo(int $timestamp): void {
        if ($timestamp < $this->timestamp) throw new \InvalidArgumentException('Clock cannot move backwards');
        $this->timestamp = $timestamp;
    }
}
