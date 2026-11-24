<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

/**
 * WP29A: scenario-only domestic policy ledger. All monetary amounts are
 * integer minor units in one pinned currency; social pressures are indices,
 * not polls. The trusted enactment verifier is NOT a player-supplied flag.
 * Assembly adoption alone does not authorize implementation.
 */
interface DomesticPolicyAuthority
{
    public function allows(string $world, string $ability): bool;
    public function enactedInstrument(string $world, string $policy): ?string;
    public function clockDay(string $world): string;
}

final class DomesticPolicySimulation
{
    public const VERSION = 'wp29a-domestic-policy-1';
    private array $policies = [];
    private array $days = [];
    private array $pressure;
    private int $balance;
    private string $day;

    /**
     * Trusted scenario assumptions. Daily revenue, expense and sector
     * pressure targets are explicit inputs, not inferred real-world effects.
     * No policy can be added or edited by player JSON.
     */
    public function __construct(
        private readonly string $world,
        private readonly string $startDay,
        private readonly string $currency,
        private readonly int $openingBalance,
        private readonly int $baselineRevenue,
        private readonly int $baselineExpense,
        private readonly array $cohorts,
        private readonly array $catalog
    ) {
        self::id($world); self::date($startDay);
        if (!preg_match('/^[A-Z]{3}$/D', $currency)) throw new \InvalidArgumentException('Invalid currency');
        self::amount($openingBalance, true);
        self::amount($baselineRevenue); self::amount($baselineExpense);
        if (count($cohorts) < 1 || count($cohorts) > 32 || count($catalog) > 128) {
            throw new \InvalidArgumentException('Scenario size');
        }
        foreach ($cohorts as $id => $score) {
            self::id($id); self::score($score);
        }
        foreach ($catalog as $id => $p) {
            self::id($id);
            if (!is_array($p)) throw new \InvalidArgumentException('Invalid assumption');
            self::fields($p, ['revenue_delta','expense_delta','pressure_delta','duration_days']);
            self::amount($p['revenue_delta'], true); self::amount($p['expense_delta'], true);
            if (!is_int($p['duration_days']) || $p['duration_days'] < 1 || $p['duration_days'] > 3650
                || !is_array($p['pressure_delta'])) throw new \InvalidArgumentException('Invalid duration or cohort assumptions');
            self::fields($p['pressure_delta'], array_keys($cohorts));
            foreach ($p['pressure_delta'] as $v) {
                if (!is_int($v) || abs($v) > 1000) throw new \InvalidArgumentException('Invalid pressure delta');
            }
        }
        $this->pressure = $cohorts;
        $this->balance = $openingBalance;
        $this->day = $startDay;
    }

    public function propose(DomesticPolicyAuthority $principal, string $id, string $effectiveDay): void
    {
        $this->require($principal, 'domestic.propose');
        self::id($id); self::date($effectiveDay);
        if (!isset($this->catalog[$id]) || $effectiveDay <= $this->day || isset($this->policies[$id])) {
            throw new \DomainException('Unknown, duplicate or retroactive proposal');
        }
        $this->policies[$id] = ['proposed_on'=>$this->day,'effective_on'=>$effectiveDay,'authorized_on'=>null,'instrument_sha'=>null];
        ksort($this->policies, SORT_STRING);
    }

    public function authorize(DomesticPolicyAuthority $principal, string $id): void
    {
        $this->require($principal, 'domestic.enact');
        self::id($id);
        $policy = $this->policies[$id] ?? null;
        if ($policy === null || $policy['authorized_on'] !== null || $policy['effective_on'] <= $this->day) {
            throw new \DomainException('No pending timely proposal');
        }
        $proof = $principal->enactedInstrument($this->world, $id);
        if ($proof === null || !preg_match('/^[a-f0-9]{64}$/D', $proof)) {
            throw new \DomainException('Verified enacted instrument required');
        }
        $this->policies[$id]['authorized_on'] = $this->day;
        $this->policies[$id]['instrument_sha'] = $proof;
    }

    /** Advance one day at a time; reject skips, replay and uncertified time. */
    public function advance(DomesticPolicyAuthority $principal, string $target): array
    {
        $this->require($principal, 'domestic.clock');
        self::date($target);
        if ($principal->clockDay($this->world) !== $target || self::next($this->day) !== $target
            || count($this->days) >= 3650) throw new \DomainException('Invalid or uncertified daily transition');
        $row = $this->calculate($target, $this->balance, $this->pressure);
        $this->days[] = $row;
        $this->balance = $row['closing_balance'];
        $this->pressure = $row['pressure'];
        $this->day = $target;
        return $row;
    }

    private function calculate(string $target, int $balance, array $pressure): array
    {
        $revenue = $this->baselineRevenue; $expense = $this->baselineExpense;
        $deltas = array_fill_keys(array_keys($this->cohorts), 0);
        $active = [];
        foreach ($this->policies as $id => $p) {
            if ($p['authorized_on'] === null || $p['authorized_on'] >= $target || $p['effective_on'] > $target) continue;
            $elapsed = (int)(new \DateTimeImmutable($p['effective_on'], new \DateTimeZone('UTC')))
                ->diff(new \DateTimeImmutable($target, new \DateTimeZone('UTC')))->days;
            $a = $this->catalog[$id];
            if ($elapsed >= $a['duration_days']) continue;
            $active[] = $id;
            $revenue += $a['revenue_delta']; $expense += $a['expense_delta'];
            foreach ($a['pressure_delta'] as $sector => $delta) $deltas[$sector] += $delta;
        }
        if ($revenue < 0 || $expense < 0) throw new \DomainException('Scenario implies negative gross fiscal flow');
        $closing = $balance + $revenue - $expense;
        self::amount($closing, true);
        foreach ($pressure as $id => $previous) {
            $desired = max(0, min(1000, $this->cohorts[$id] + $deltas[$id]));
            $diff = $desired - $previous;
            // Deterministic gradual adjustment (one quarter, minimum one).
            $step = $diff === 0 ? 0 : ($diff > 0 ? 1 : -1) * max(1, intdiv(abs($diff), 4));
            $pressure[$id] = $previous + $step;
        }
        ksort($pressure, SORT_STRING);
        return ['day'=>$target,'classification'=>'fictional_scenario','currency'=>$this->currency,
            'active_policies'=>$active,'opening_balance'=>$balance,'revenue'=>$revenue,'expense'=>$expense,
            'closing_balance'=>$closing,'pressure'=>$pressure];
    }

    public function assumptionsHash(): string
    {
        $catalog = $this->catalog; $cohorts = $this->cohorts;
        ksort($catalog, SORT_STRING); ksort($cohorts, SORT_STRING);
        foreach ($catalog as &$a) {
            ksort($a, SORT_STRING); ksort($a['pressure_delta'], SORT_STRING);
        }
        unset($a);
        return self::digest([$this->world,$this->startDay,$this->currency,$this->openingBalance,
            $this->baselineRevenue,$this->baselineExpense,$cohorts,$catalog]);
    }

    public function snapshot(): array
    {
        $pressure = $this->pressure; ksort($pressure, SORT_STRING);
        return ['version'=>self::VERSION,'world_id'=>$this->world,'assumptions_sha'=>$this->assumptionsHash(),
            'day'=>$this->day,'balance'=>$this->balance,'pressure'=>$pressure,
            'policies'=>$this->policies,'days'=>$this->days];
    }

    public function hash(): string { return self::digest($this->snapshot()); }

    /**
     * Restore only against identical trusted assumptions. Replay all daily
     * rows and reject altered balances, dates, effects and final projections.
     * The caller MUST also verify the externally persisted snapshot digest;
     * a digest inside untrusted JSON cannot establish legal provenance.
     */
    public function restore(array $snapshot, string $trustedSha): void
    {
        if ($this->policies !== [] || $this->days !== []) throw new \DomainException('Restore requires fresh aggregate');
        if (!hash_equals($trustedSha, self::digest($snapshot))) throw new \DomainException('Snapshot checksum mismatch');
        self::fields($snapshot, array_keys($this->snapshot()));
        if ($snapshot['version'] !== self::VERSION || $snapshot['world_id'] !== $this->world
            || $snapshot['assumptions_sha'] !== $this->assumptionsHash()
            || !is_array($snapshot['policies']) || !is_array($snapshot['days'])
            || !array_is_list($snapshot['days']) || count($snapshot['days']) > 3650) {
            throw new \DomainException('Incompatible scenario snapshot');
        }
        self::date($snapshot['day']);
        // Work on a clone, so failed recovery leaves this object unchanged.
        $copy = clone $this;
        foreach ($snapshot['policies'] as $id => $p) {
            self::id($id);
            if (!isset($this->catalog[$id]) || !is_array($p)) throw new \DomainException('Unknown persisted policy');
            self::fields($p, ['proposed_on','effective_on','authorized_on','instrument_sha']);
            self::date($p['proposed_on']); self::date($p['effective_on']);
            if ($p['proposed_on'] < $this->startDay || $p['proposed_on'] > $snapshot['day']
                || $p['effective_on'] <= $p['proposed_on']) throw new \DomainException('Invalid persisted proposal dates');
            if ($p['authorized_on'] !== null) {
                self::date($p['authorized_on']);
                if ($p['authorized_on'] < $p['proposed_on'] || $p['authorized_on'] >= $p['effective_on']
                    || $p['authorized_on'] > $snapshot['day'] || !is_string($p['instrument_sha'])
                    || !preg_match('/^[a-f0-9]{64}$/D', $p['instrument_sha'])) throw new \DomainException('Invalid persisted enactment');
            } elseif ($p['instrument_sha'] !== null) throw new \DomainException('Unexpected enactment proof');
            $copy->policies[$id] = $p;
        }
        ksort($copy->policies, SORT_STRING);
        foreach ($snapshot['days'] as $stored) {
            $target = self::next($copy->day);
            $row = $copy->calculate($target, $copy->balance, $copy->pressure);
            if ($stored !== $row) throw new \DomainException('Daily ledger replay mismatch');
            $copy->days[] = $row; $copy->day = $target;
            $copy->balance = $row['closing_balance']; $copy->pressure = $row['pressure'];
        }
        if ($copy->snapshot() !== $snapshot) throw new \DomainException('Final snapshot replay mismatch');
        $this->policies = $copy->policies; $this->days = $copy->days;
        $this->day = $copy->day; $this->balance = $copy->balance; $this->pressure = $copy->pressure;
    }

    private function require(DomesticPolicyAuthority $p, string $ability): void
    {
        if (!$p->allows($this->world, $ability)) throw new \DomainException('Unauthorized domestic policy operation');
    }
    private static function id(mixed $v): void {
        if (!is_string($v) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $v)) throw new \InvalidArgumentException('Invalid identifier');
    }
    private static function date(mixed $v): void {
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v)
            || ($d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v, new \DateTimeZone('UTC'))) === false
            || $d->format('Y-m-d') !== $v || $v < '1900-01-01' || $v > '2199-12-31') throw new \InvalidArgumentException('Invalid day');
    }
    private static function next(string $v): string {
        return (new \DateTimeImmutable($v, new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
    }
    private static function amount(mixed $v, bool $signed = false): void {
        if (!is_int($v) || $v < ($signed ? -1000000000000000 : 0) || $v > 1000000000000000)
            throw new \InvalidArgumentException('Amount outside supported range');
    }
    private static function score(mixed $v): void {
        if (!is_int($v) || $v < 0 || $v > 1000) throw new \InvalidArgumentException('Invalid pressure index');
    }
    private static function fields(array $a, array $fields): void {
        $keys = array_keys($a); sort($keys); sort($fields);
        if ($keys !== $fields) throw new \InvalidArgumentException('Unexpected fields');
    }
    private static function digest(array $a): string {
        return hash('sha256', json_encode($a, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
