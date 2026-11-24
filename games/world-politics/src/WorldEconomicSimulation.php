<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/EconomicReferenceRegistry.php';
require_once __DIR__.'/DiplomaticSimulation.php';

/**
 * Server-trusted constitutional-policy authorization. The domain will NEVER
 * assume that every country has identical legislative/fiscal powers.
 */
interface EconomicPolicyPrincipal extends DiplomaticPrincipal
{
    public function legalApproval(string $world,string $state,string $instrument):bool;
}

/**
 * WP12: deterministic, conservative policy impact scenarios.
 *
 * A diplomatic declaration or pending sanction never modifies the economy.
 * A restriction takes effect only after explicit approval by a country-aware
 * legal gate and a game-time effective date. Results are scenario projections,
 * not economic forecasts and NEVER change reported historical GDP.
 */
final class WorldEconomicSimulation
{
    private const VERSION='wp12-policy-economy-1';
    /** @var array<string,mixed> */
    private array $state;

    public function __construct(
        string $world,string $startingDay,
        private readonly GeopoliticalActorRegistry $actors,
        private readonly EconomicReferenceRegistry $reference
    ) {
        self::id($world);
        self::day($startingDay);
        if ($reference->asOf>$startingDay) {
            throw new \InvalidArgumentException('Economic snapshot is newer than starting game day');
        }
        $this->state=[
            'world_id'=>$world,'day'=>$startingDay,'rules_version'=>self::VERSION,
            'actors_sha'=>$actors->hash(),'reference_sha'=>$reference->hash(),
            'policies'=>[],'projections'=>[],'events'=>[],'commands'=>[]
        ];
    }

    /**
     * @param array<string,mixed> $snapshot
     * Strict restoration: pinned reference and scenario legal model cannot
     * be mutated by replacing a stored JSON object.
     */
    public static function fromSnapshot(
        array $snapshot,GeopoliticalActorRegistry $actors,
        EconomicReferenceRegistry $reference
    ):self {
        self::fields($snapshot,[
            'world_id','day','rules_version','actors_sha','reference_sha',
            'policies','projections','events','commands'
        ]);
        if (!is_string($snapshot['world_id']) || !is_string($snapshot['day'])) {
            throw new \InvalidArgumentException('Invalid persisted world or date');
        }
        $self=new self($snapshot['world_id'],$snapshot['day'],$actors,$reference);
        if ($snapshot['rules_version']!==self::VERSION
            || !is_string($snapshot['actors_sha'])
            || !is_string($snapshot['reference_sha'])
            || !hash_equals($actors->hash(),$snapshot['actors_sha'])
            || !hash_equals($reference->hash(),$snapshot['reference_sha'])) {
            throw new \DomainException('Scenario inputs changed; do not silently replace a running scenario');
        }
        foreach(['policies','projections','commands'] as $section) {
            if (!is_array($snapshot[$section])
                || (array_is_list($snapshot[$section]) && $snapshot[$section]!==[])) {
                throw new \InvalidArgumentException('Invalid persisted policy state');
            }
        }
        if (!is_array($snapshot['events']) || !array_is_list($snapshot['events'])) {
            throw new \InvalidArgumentException('Invalid persisted policy event list');
        }
        foreach($snapshot['policies'] as $id=>$policy) {
            self::id($id);
            if (!is_array($policy)) throw new \InvalidArgumentException('Invalid policy');
            self::fields($policy,[
                'id','from','to','type','effect_bps','low_bps','high_bps',
                'created_on','effective_on','status','approved_on'
            ]);
            if ($policy['id']!==$id || $policy['type']!=='trade_restriction'
                || !in_array($policy['status'],['proposed','authorized','effective'],true)) {
                throw new \InvalidArgumentException('Invalid policy status');
            }
            self::day($policy['created_on']);self::day($policy['effective_on']);
            if ($policy['approved_on']!==null) self::day($policy['approved_on']);
            $actors->get($policy['from']);$actors->get($policy['to']);
            self::bps($policy['effect_bps']);self::bps($policy['low_bps']);
            self::bps($policy['high_bps']);
            if ($policy['low_bps']>$policy['effect_bps']
                || $policy['effect_bps']>$policy['high_bps']) {
                throw new \InvalidArgumentException('Invalid uncertainty interval');
            }
        }
        foreach($snapshot['projections'] as $id=>$projection) {
            if (!is_string($id) || !isset($snapshot['policies'][$id])
                || !is_array($projection)
                || ($projection['classification']??null)!=='scenario_projection') {
                throw new \InvalidArgumentException('Invalid scenario projection');
            }
        }
        foreach ($snapshot['commands'] as $id=>$receipt) {
            self::id($id);
            if (!is_array($receipt) || !isset($receipt['sha'],$receipt['result'])
                || !is_string($receipt['sha'])
                || !preg_match('/^[a-f0-9]{64}$/D',$receipt['sha'])
                || !is_array($receipt['result'])) {
                throw new \InvalidArgumentException('Invalid idempotency receipt');
            }
        }
        foreach ($snapshot['events'] as $event) {
            if (!is_array($event) || !isset($event['id'],$event['on'],$event['kind'])
                || !is_string($event['id']) || !is_string($event['on'])) {
                throw new \InvalidArgumentException('Invalid policy event');
            }
            self::day($event['on']);
        }
        $self->state=$snapshot;
        return $self;
    }

    /**
     * @return array<string,mixed>
     * Effects in bps are exclusively explicit assumptions of scenario designers.
     * No attempt to derive them from current political leadership.
     */
    public function proposeTradeRestriction(
        EconomicPolicyPrincipal $principal,string $from,string $to,
        int $effectBps,int $lowBps,int $highBps,
        string $effectiveOn,string $commandId
    ):array {
        self::id($commandId);self::day($effectiveOn);
        $this->requireState($from);$this->requireState($to);
        if ($from===$to) throw new \InvalidArgumentException('Same-state trade restriction');
        $this->authorize($principal,$from,'economy.propose');
        foreach ([$effectBps,$lowBps,$highBps] as $v) self::bps($v);
        if ($lowBps>$effectBps || $effectBps>$highBps
            || $effectiveOn<=$this->state['day']) {
            throw new \InvalidArgumentException('Invalid rate uncertainty or future effective date');
        }
        $payload=implode('|',[$from,$to,$effectBps,$lowBps,$highBps,$effectiveOn]);
        if ($seen=$this->previous($commandId,'propose',$payload)) return $seen;
        $this->state['policies'][$commandId]=[
            'id'=>$commandId,'from'=>$from,'to'=>$to,'type'=>'trade_restriction',
            'effect_bps'=>$effectBps,'low_bps'=>$lowBps,'high_bps'=>$highBps,
            'created_on'=>$this->state['day'],'effective_on'=>$effectiveOn,
            'status'=>'proposed','approved_on'=>null
        ];
        return $this->record($commandId,'propose',$payload,[
            'policy_id'=>$commandId,'status'=>'proposed','binding_effect'=>false
        ]);
    }

    /** Authorized country-specific process, not just a player command. */
    public function authorizeRestriction(
        EconomicPolicyPrincipal $principal,string $policyId,string $commandId
    ):array {
        self::id($policyId);self::id($commandId);
        $policy=$this->state['policies'][$policyId]??null;
        if ($policy===null) throw new \DomainException('Unknown policy');
        $from=$policy['from'];
        $this->authorize($principal,$from,'economy.approve');
        if (!$principal->legalApproval($this->state['world_id'],$from,'trade_restriction')) {
            throw new \DomainException('Country legal procedure not satisfied');
        }
        if ($seen=$this->previous($commandId,'authorize',$policyId)) return $seen;
        if ($policy['status']!=='proposed' || $this->state['day']>=$policy['effective_on']) {
            throw new \DomainException('Policy cannot be authorized retroactively');
        }
        $this->state['policies'][$policyId]['status']='authorized';
        $this->state['policies'][$policyId]['approved_on']=$this->state['day'];
        return $this->record($commandId,'authorize',$policyId,[
            'policy_id'=>$policyId,'status'=>'authorized','binding_effect'=>false
        ]);
    }

    /**
     * Only the trusted simulation clock service can materialize an impact.
     * Returns null for genuinely unavailable bilateral data, not a fake zero.
     */
    public function advanceTo(
        EconomicPolicyPrincipal $service,string $targetDay,string $commandId
    ):array {
        self::id($commandId);self::day($targetDay);
        if (!$service->allows($this->state['world_id'],$service->actorId(),'economy.clock')) {
            throw new \DomainException('Trusted simulation scheduler required');
        }
        if ($targetDay<$this->state['day']) throw new \InvalidArgumentException('Time cannot go backwards');
        if ($seen=$this->previous($commandId,'clock',$targetDay)) return $seen;
        $due=[];
        foreach ($this->state['policies'] as $id=>$policy) {
            if ($policy['status']!=='authorized' || $policy['effective_on']>$targetDay) continue;
            $record=$this->reference->latest($policy['from'],'bilateral_trade_usd',$policy['to']);
            $base=($record!==null && $record['kind']!=='missing')?$record['value']:null;
            $projection=[
                'policy_id'=>$id,
                'classification'=>'scenario_projection',
                'metric'=>'hypothetical_annual_bilateral_trade_reduction_usd',
                'observation_year'=>$record['year']??null,
                'reference_kind'=>$record['kind']??'missing',
                'reference_source'=>$record['source_url']??null,
                'model_assumption_bps'=>$policy['effect_bps'],
                'low_assumption_bps'=>$policy['low_bps'],
                'high_assumption_bps'=>$policy['high_bps'],
                'central_usd'=>$base===null?null:self::weightedUsd($base,$policy['effect_bps']),
                'lower_usd'=>$base===null?null:self::weightedUsd($base,$policy['low_bps']),
                'upper_usd'=>$base===null?null:self::weightedUsd($base,$policy['high_bps']),
                'effective_on'=>$policy['effective_on'],
                'does_not_change_recorded_gdp'=>true,
                'not_a_forecast'=>true,
            ];
            $this->state['projections'][$id]=$projection;
            $this->state['policies'][$id]['status']='effective';
            $due[]=$id;
        }
        sort($due,SORT_STRING);
        $this->state['day']=$targetDay;
        return $this->record($commandId,'clock',$targetDay,[
            'day'=>$targetDay,'effective_policies'=>$due
        ]);
    }

    /** Read scenario impacts without exposing them as historical statistics. */
    public function snapshot():array
    {
        $state=$this->state;
        foreach (['policies','projections','commands'] as $key) {
            ksort($state[$key],SORT_STRING);
        }
        return $state;
    }
    public function hash():string
    {
        return hash('sha256',json_encode(
            $this->snapshot(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
        ));
    }

    private function requireState(string $id):void
    {
        if ($this->actors->get($id)['kind']!=='state') {
            throw new \DomainException('Governing authority is not automatically a sovereign state');
        }
    }
    private function authorize(EconomicPolicyPrincipal $principal,string $actor,string $ability):void
    {
        $record=$this->actors->get($actor);
        if (!$principal->allows($this->state['world_id'],$actor,$ability)
            || ($record['controller']==='ai_only'
                && !$principal->allows($this->state['world_id'],$actor,'economy.ai_controller'))) {
            throw new \DomainException('Unverified economic policy authority');
        }
    }
    /** @return array<string,mixed>|null */
    private function previous(string $id,string $type,string $payload):?array
    {
        $receipt=$this->state['commands'][$id]??null;
        if ($receipt===null) return null;
        if (!hash_equals($receipt['sha'],hash('sha256',$type."\n".$payload))) {
            throw new \DomainException('Conflicting command fingerprint');
        }
        return $receipt['result'];
    }
    /** @param array<string,mixed> $result */
    private function record(string $id,string $type,string $payload,array $result):array
    {
        $this->state['commands'][$id]=[
            'sha'=>hash('sha256',$type."\n".$payload),'result'=>$result
        ];
        $this->state['events'][]=[
            'id'=>$id,'on'=>$this->state['day'],'kind'=>$type,'result'=>$result
        ];
        return $result;
    }
    private static function id(string $id):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)) {
            throw new \InvalidArgumentException('Invalid world or policy id');
        }
    }
    private static function day(string $date):void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)
            || ($value=\DateTimeImmutable::createFromFormat('!Y-m-d',$date))===false
            || $value->format('Y-m-d')!==$date) {
            throw new \InvalidArgumentException('Invalid game date');
        }
    }
    /**
     * Integer-only bps multiplication without overflowing 64-bit PHP when
     * an administrator provides large but valid macroeconomic magnitudes.
     */
    private static function weightedUsd(int $base,int $bps):int
    {
        return intdiv($base,10000)*$bps
            + intdiv(($base%10000)*$bps,10000);
    }

    private static function bps(int $value):void
    {
        if ($value<0 || $value>10000) {
            throw new \InvalidArgumentException('Scenario basis-points must lie in 0..10000');
        }
    }
    private static function fields(array $item,array $expected):void
    {
        $keys=array_keys($item);sort($keys);sort($expected);
        if ($keys!==$expected) throw new \InvalidArgumentException('Unexpected economic state fields');
    }
}
