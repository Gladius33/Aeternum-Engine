<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Deterministic lazy production of integer smallest units at a rational rate.
 * The immutable rate is amount / interval seconds. Fractional carry is lost at saturation.
 */
final readonly class ProductionState
{
    public const MAX_UNITS = 1000000000000;
    public const MAX_INTERVAL = 2592000;

    public function __construct(
        public int $stock,
        public int $capacity,
        public int $amount,
        public int $interval,
        public int $at,
        public int $remainder = 0,
    ) {
        if (PHP_INT_SIZE !== 8) throw new \RuntimeException('64-bit PHP required');
        if ($capacity < 0 || $capacity > self::MAX_UNITS || $stock < 0 || $stock > $capacity
            || $amount < 0 || $amount > self::MAX_UNITS || $interval < 1 || $interval > self::MAX_INTERVAL
            || $at < 0 || $remainder < 0 || $remainder >= $interval
            || ($stock === $capacity && $remainder !== 0)) {
            throw new \InvalidArgumentException('Invalid production state');
        }
    }

    public function advanceTo(int $at): self
    {
        if ($at < $this->at) throw new \DomainException('Production clock cannot regress');
        $elapsed = $at - $this->at;
        if ($elapsed === 0) return $this;
        if ($this->stock === $this->capacity || $this->amount === 0) {
            return new self($this->stock,$this->capacity,$this->amount,$this->interval,$at,$this->remainder);
        }
        // Determine saturation before multiplication. MAX_UNITS * MAX_INTERVAL fits int64.
        $needed = ($this->capacity - $this->stock) * $this->interval - $this->remainder;
        $threshold = intdiv($needed,$this->amount) + (($needed % $this->amount) === 0 ? 0 : 1);
        if ($elapsed >= $threshold) {
            return new self($this->capacity,$this->capacity,$this->amount,$this->interval,$at);
        }
        $numerator = $elapsed * $this->amount + $this->remainder;
        return new self($this->stock+intdiv($numerator,$this->interval),$this->capacity,
            $this->amount,$this->interval,$at,$numerator % $this->interval);
    }

    /** Settle first; these operations never advance wall time implicitly. */
    public function debit(int $units): self
    {
        if ($units < 1 || $units > $this->stock) throw new \DomainException('Invalid or insufficient debit');
        return new self($this->stock-$units,$this->capacity,$this->amount,$this->interval,$this->at,$this->remainder);
    }

    public function credit(int $units): self
    {
        if ($units < 1 || $units > $this->capacity-$this->stock) throw new \DomainException('Invalid or overflowing credit');
        $stock = $this->stock+$units;
        return new self($stock,$this->capacity,$this->amount,$this->interval,$this->at,
            $stock === $this->capacity ? 0 : $this->remainder);
    }
}
