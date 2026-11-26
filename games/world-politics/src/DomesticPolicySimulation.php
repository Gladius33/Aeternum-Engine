<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrenchMacroBaseline.php';
require_once __DIR__.'/SovereignDebtLedger.php';
require_once __DIR__.'/FrenchDemography.php';
require_once __DIR__.'/FrenchPublicServices.php';
require_once __DIR__.'/ProfessionalCorporations.php';

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
    private array $crisisDecisions=[];
    private array $corporationDecisions=[];
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
        private readonly array $catalog,
        private readonly ?array $macroBaseline=null,
        private readonly ?array $debtModel=null,
        private readonly ?array $populationModel=null,
        private readonly ?array $servicesModel=null,
        private readonly bool $corporationsEnabled=false
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
            $base=['revenue_delta','expense_delta','pressure_delta','duration_days'];
            $extra=['concessions','concession_daily_cost','production_delta_bps','employment_delta_bps'];
            $extended=array_key_exists('concessions',$p);
            self::fields($p,$extended?[...$base,...$extra]:$base);
            if ($extended) {
                self::amount($p['concession_daily_cost']);
                if (!is_array($p['concessions'])) throw new \InvalidArgumentException('Invalid concessions');
                self::fields($p['concessions'],array_keys($cohorts));
                foreach ($p['concessions'] as $v) self::score($v);
                foreach (['production_delta_bps','employment_delta_bps'] as $field) {
                    if (!is_int($p[$field]) || abs($p[$field])>10000) throw new \InvalidArgumentException('Invalid economic index shift');
                }
            }
            self::amount($p['revenue_delta'], true); self::amount($p['expense_delta'], true);
            if (!is_int($p['duration_days']) || $p['duration_days'] < 1 || $p['duration_days'] > 3650
                || !is_array($p['pressure_delta'])) throw new \InvalidArgumentException('Invalid duration or cohort assumptions');
            self::fields($p['pressure_delta'], array_keys($cohorts));
            foreach ($p['pressure_delta'] as $v) {
                if (!is_int($v) || abs($v) > 1000) throw new \InvalidArgumentException('Invalid pressure delta');
            }
        }
        if($this->macroBaseline!==null){
            FrenchMacroBaseline::assertValid($this->macroBaseline);
            if($currency!=='EUR'||$baselineRevenue!==$this->macroBaseline['daily_revenue']
                ||$baselineExpense!==$this->macroBaseline['daily_primary_expense']||$openingBalance!==$this->macroBaseline['assumed_opening_cash'])
                throw new \DomainException('Macro baseline and fiscal assumptions disagree');
        }
        if($this->debtModel!==null){
            if($this->macroBaseline===null)throw new \DomainException('Debt model needs macro baseline');
            SovereignDebtLedger::assertValid($this->debtModel,$this->macroBaseline,$startDay);
        }
        if($this->populationModel!==null){
            if($this->macroBaseline===null)throw new \DomainException('Population model needs macro baseline');
            FrenchDemography::assertValid($this->populationModel,$startDay,$this->macroBaseline['initial_unemployment_bps']);
            if($this->populationModel['opening_indicators']['population']<=0)throw new \DomainException('Invalid opening population');
        }
        if($this->servicesModel!==null){
            if($this->populationModel===null||empty($this->debtModel['crisis_enabled']))throw new \DomainException('Services need population and crisis model');
            FrenchPublicServices::assertValid($this->servicesModel,$startDay,$this->populationModel,$this->macroBaseline);
        }
        if($corporationsEnabled&&$servicesModel===null)throw new \DomainException('Corporations require services scenario');
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

    /** Explicit fictional world-owner decision, not an enacted legal instrument. */
    public function crisisDecision(DomesticPolicyAuthority $principal,string $action):array
    {
        $this->require($principal,'domestic.crisis');
        if(($this->debtModel['crisis_enabled']??false)!==true)throw new \DomainException('Crisis scenario required');
        $event=['day'=>$this->day,'action'=>$action];
        $next=[...$this->crisisDecisions,$event];
        FinancialCrisis::validate($next,$this->startDay,$this->day);
        $this->crisisDecisions=$next;
        return $event;
    }

    public function corporationDecision(DomesticPolicyAuthority $principal,string $action):array
    {
        $this->require($principal,'domestic.corporation');
        if(!$this->corporationsEnabled)throw new \DomainException('Corporations scenario required');
        // Retain historic branch events for replay only; new requests select a company.
        if(str_starts_with($action,'request-nationalisation:'))throw new \InvalidArgumentException('Select an individual company');
        $last=$this->corporationDecisions===[]?null:$this->corporationDecisions[array_key_last($this->corporationDecisions)];
        if(($last['day']??null)===$this->day||count($this->corporationDecisions)>=3650)throw new \DomainException('One corporation decision per day');
        $state=$this->days===[]?ProfessionalCorporations::initial($this->startDay):$this->days[array_key_last($this->days)]['corporations'];
        ProfessionalCorporations::validateEvent($state,$action);
        $e=['day'=>$this->day,'action'=>$action];$this->corporationDecisions[]=$e;return $e;
    }

    private function calculate(string $target, int $balance, array $pressure): array
    {
        $revenue = $this->baselineRevenue; $expense = $this->baselineExpense;
        $deltas = array_fill_keys(array_keys($this->cohorts), 0);
        $active = []; $extended=false; $concessionCost=0; $production=10000; $employment=10000;
        foreach ($this->catalog as $a) if (isset($a['concessions'])) $extended=true;
        foreach ($this->policies as $id => $p) {
            if ($p['authorized_on'] === null || $p['authorized_on'] >= $target || $p['effective_on'] > $target) continue;
            $elapsed = (int)(new \DateTimeImmutable($p['effective_on'], new \DateTimeZone('UTC')))
                ->diff(new \DateTimeImmutable($target, new \DateTimeZone('UTC')))->days;
            $a = $this->catalog[$id];
            if ($elapsed >= $a['duration_days']) continue;
            $active[] = $id;
            $revenue += $a['revenue_delta']; $expense += $a['expense_delta'];
            foreach ($a['pressure_delta'] as $sector => $delta) $deltas[$sector] += $delta-($a['concessions'][$sector]??0);
            $concessionCost+=$a['concession_daily_cost']??0;
            $production+=$a['production_delta_bps']??0; $employment+=$a['employment_delta_bps']??0;
        }
        if ($revenue < 0 || $expense < 0) throw new \DomainException('Scenario implies negative gross fiscal flow');
        $expense+=$concessionCost;
        $loop=null;$servicePlan=null;$corporations=null;
        if($this->corporationsEnabled){
            $previous=$this->days===[]?null:$this->days[array_key_last($this->days)];
            $corporations=ProfessionalCorporations::step($previous['corporations']??ProfessionalCorporations::initial($this->startDay),$target,ProfessionalCorporations::actionFor($this->corporationDecisions,$target),$previous['macro']??null);
            $deltas['workers']+=intdiv($corporations['worker_opposition'],4);
            $production-=intdiv($corporations['employer_resistance'],2);
        }
        if($this->macroBaseline!==null){
            $prior=$this->days===[]?null:$this->days[array_key_last($this->days)];
            if($this->populationModel!==null)$production+=FrenchDemography::outputAdjustment($this->populationModel,$prior);
            if($this->servicesModel!==null){
                $servicePlan=FrenchPublicServices::plan($this->servicesModel,$prior);
                $expense+=$servicePlan['total_incremental_expense_minor'];
                if($expense<=0)throw new \DomainException('Service adjustment exceeds fiscal flow');
                foreach($prior['services']['next_day_pressure_delta']??[] as $k=>$v)if(isset($deltas[$k]))$deltas[$k]+=$v;
            }
            $loop=MacroFiscalLoop::step($this->macroBaseline,$prior,$balance,$revenue,$expense,$production,$employment,$this->debtModel,$target,FinancialCrisis::actionFor($this->crisisDecisions,$target),$servicePlan['pension_transfer_delta_minor']??0);
            $revenue=$loop['revenue'];$expense=$loop['expense'];
        }
        $closing = $loop===null?$balance+$revenue-$expense:$loop['balance'];
        self::amount($closing, true);
        foreach ($pressure as $id => $previous) {
            $desired = max(0, min(1000, $this->cohorts[$id] + $deltas[$id]));
            $diff = $desired - $previous;
            // Deterministic gradual adjustment (one quarter, minimum one).
            $step = $diff === 0 ? 0 : ($diff > 0 ? 1 : -1) * max(1, intdiv(abs($diff), 4));
            $pressure[$id] = $previous + $step;
        }
        ksort($pressure, SORT_STRING);
        $row=['day'=>$target,'classification'=>'fictional_scenario','currency'=>$this->currency,
            'active_policies'=>$active,'opening_balance'=>$balance,'revenue'=>$revenue,'expense'=>$expense,
            'closing_balance'=>$closing,'pressure'=>$pressure];
        if ($extended) $row['economic']=['net_flow'=>$revenue-$expense,'concession_cost'=>$concessionCost,
            'production_index_bps'=>max(0,$production),'employment_index_bps'=>max(0,$employment)];
        if($loop!==null){
            $row['macro']=$loop['macro'];$row['treasury']=$loop['treasury'];
            if($this->populationModel!==null)$row['population']=FrenchDemography::step($this->populationModel,$prior,$loop['macro']);
            if($servicePlan!==null)$row['services']=FrenchPublicServices::settle($this->servicesModel,$servicePlan,$prior,$loop['treasury'],$expense);
            $row['classification']='fictional_simulation_observed_anchors';
            $row['economic']['production_index_bps']=$loop['macro']['real_output_index_bps'];
            $row['economic']['employment_index_bps']=max(0,10000-($loop['macro']['unemployment_bps']-$this->macroBaseline['initial_unemployment_bps']));
        }
        if($corporations!==null)$row['corporations']=$corporations;
        return $row;
    }

    public function assumptionsHash(): string
    {
        $catalog = $this->catalog; $cohorts = $this->cohorts;
        ksort($catalog, SORT_STRING); ksort($cohorts, SORT_STRING);
        foreach ($catalog as &$a) {
            ksort($a, SORT_STRING); ksort($a['pressure_delta'], SORT_STRING);
            if (isset($a['concessions'])) ksort($a['concessions'],SORT_STRING);
        }
        unset($a);
        return self::digest([$this->world,$this->startDay,$this->currency,$this->openingBalance,
            $this->baselineRevenue,$this->baselineExpense,$cohorts,$catalog,...($this->macroBaseline===null?[]:[$this->macroBaseline]),...($this->debtModel===null?[]:[$this->debtModel]),...($this->populationModel===null?[]:[$this->populationModel]),...($this->servicesModel===null?[]:[$this->servicesModel]),...($this->corporationsEnabled?[ProfessionalCorporations::VERSION]:[])]);
    }

    public function snapshot(): array
    {
        $pressure = $this->pressure; ksort($pressure, SORT_STRING);
        return ['version'=>self::VERSION,'world_id'=>$this->world,'assumptions_sha'=>$this->assumptionsHash(),
            'day'=>$this->day,'balance'=>$this->balance,'pressure'=>$pressure,
            'policies'=>$this->policies,'days'=>$this->days,...($this->macroBaseline===null?[]:['macro_baseline'=>$this->macroBaseline]),...($this->debtModel===null?[]:['debt_model'=>$this->debtModel]),...(!empty($this->debtModel['crisis_enabled'])?['crisis_decisions'=>$this->crisisDecisions]:[]),...($this->populationModel===null?[]:['population_model'=>$this->populationModel]),...($this->servicesModel===null?[]:['services_model'=>$this->servicesModel]),...($this->corporationsEnabled?['corporations_version'=>ProfessionalCorporations::VERSION,'corporation_decisions'=>$this->corporationDecisions]:[])];
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
        if(!empty($this->debtModel['crisis_enabled'])){
            if(!is_array($snapshot['crisis_decisions']))throw new \DomainException('Invalid crisis history');
            FinancialCrisis::validate($snapshot['crisis_decisions'],$this->startDay,$snapshot['day']);
            $copy->crisisDecisions=$snapshot['crisis_decisions'];
        }
        if($this->corporationsEnabled){
            $events=$snapshot['corporation_decisions'];
            if(!is_array($events)||!array_is_list($events)||count($events)>3650)throw new \DomainException('Invalid corporation journal');
            $last=null;
            foreach($events as $e){
                if(!is_array($e)||array_keys($e)!==['day','action']||!is_string($e['action']))throw new \DomainException('Invalid corporation event');
                self::date($e['day']);
                if($e['day']<$this->startDay||$e['day']>$snapshot['day']||($last!==null&&$e['day']<=$last))throw new \DomainException('Invalid corporation date');
                $last=$e['day'];
            }
            $copy->corporationDecisions=$events;
        }
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
        if($this->corporationsEnabled&&($lastEvent=$copy->corporationDecisions[array_key_last($copy->corporationDecisions)]??null)!==null&&$lastEvent['day']===$copy->day){
            $state=$copy->days===[]?ProfessionalCorporations::initial($this->startDay):$copy->days[array_key_last($copy->days)]['corporations'];
            ProfessionalCorporations::validateEvent($state,$lastEvent['action']);
        }
        if ($copy->snapshot() !== $snapshot) throw new \DomainException('Final snapshot replay mismatch');
        $this->policies = $copy->policies; $this->days = $copy->days; $this->crisisDecisions=$copy->crisisDecisions;$this->corporationDecisions=$copy->corporationDecisions;
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
