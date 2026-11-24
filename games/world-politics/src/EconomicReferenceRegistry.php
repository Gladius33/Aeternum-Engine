<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/GeopoliticalActorRegistry.php';

/**
 * World Politics WP12. Immutable, dated, provenance-explicit numeric inputs.
 *
 * An observation is not evidence of official accuracy merely because it has
 * a URL: publication/license verification is a separate administrator task.
 * Scenario assumptions and missing values are never shown as observations.
 */
final class EconomicReferenceRegistry
{
    public const VERSION='wp12-economic-reference-1';
    private const METRIC_UNITS=[
        'gdp_current_usd'=>'USD',
        'population_total'=>'persons',
        'government_budget_usd'=>'USD',
        'bilateral_trade_usd'=>'USD',
    ];
    /** @var array<string,array<string,mixed>> */
    private array $records=[];
    public readonly string $asOf;

    /**
     * @param list<array<string,mixed>> $records
     */
    public function __construct(
        string $asOf,array $records,
        private readonly GeopoliticalActorRegistry $actors
    ) {
        self::day($asOf);
        $this->asOf=$asOf;
        if (!array_is_list($records) || count($records)>50000) {
            throw new \InvalidArgumentException('Reference data must be a bounded list');
        }
        foreach ($records as $item) {
            if (!is_array($item)) throw new \InvalidArgumentException('Invalid reference record');
            self::fields($item,[
                'actor_id','partner_id','metric','value','unit','year',
                'kind','quality','source_url','license'
            ]);
            $actor=$this->actors->get($item['actor_id']);
            if ($actor['kind']!=='state') throw new \InvalidArgumentException('Only country data is supported');
            if (!array_key_exists($item['metric'],self::METRIC_UNITS)
                || $item['unit']!==self::METRIC_UNITS[$item['metric']]) {
                throw new \InvalidArgumentException('Unsupported metric or unit');
            }
            if ($item['metric']==='bilateral_trade_usd') {
                if (!is_string($item['partner_id']) || $item['actor_id']===$item['partner_id']
                    || $this->actors->get($item['partner_id'])['kind']!=='state') {
                    throw new \InvalidArgumentException('A bilateral observation requires a different state');
                }
            } elseif ($item['partner_id']!==null) {
                throw new \InvalidArgumentException('National metrics cannot have partner identifiers');
            }
            if (!is_int($item['year']) || $item['year']<1900 || $item['year']>2100
                || $item['year']>(int)substr($asOf,0,4)) {
                throw new \InvalidArgumentException('Observation year beyond scenario snapshot');
            }
            if ($item['value']!==null
                && (!is_int($item['value']) || $item['value']<0
                    || $item['value']>1000000000000000)) {
                throw new \InvalidArgumentException('Unsafe metric magnitude');
            }
            if (!in_array($item['kind'],['observed','scenario_assumption','missing'],true)
                || !in_array($item['quality'],['published','estimated','assumed','missing'],true)) {
                throw new \InvalidArgumentException('Unrecognized provenance or quality');
            }
            if ($item['kind']==='observed') {
                if (!in_array($item['quality'],['published','estimated'],true)
                    || $item['value']===null || !is_string($item['source_url'])
                    || !str_starts_with($item['source_url'],'https://')
                    || filter_var($item['source_url'],FILTER_VALIDATE_URL)===false
                    || !is_string($item['license']) || trim($item['license'])==='') {
                    throw new \InvalidArgumentException('Observed statistics require source and explicit license');
                }
            } elseif ($item['kind']==='scenario_assumption') {
                if ($item['quality']!=='assumed' || $item['value']===null
                    || $item['source_url']!==null || $item['license']!=='fictional_scenario') {
                    throw new \InvalidArgumentException('Fictional estimates may not claim official provenance');
                }
            } elseif ($item['quality']!=='missing' || $item['value']!==null
                || $item['source_url']!==null || $item['license']!=='not_applicable') {
                throw new \InvalidArgumentException('Missing measurements must stay null');
            }
            $key=$item['actor_id']."\n".($item['partner_id']??'-')."\n".$item['metric']."\n".$item['year'];
            if (isset($this->records[$key])) throw new \DomainException('Conflicting duplicate observation');
            $this->records[$key]=$item;
        }
        ksort($this->records,SORT_STRING);
    }

    /**
     * @return array<string,mixed>|null
     * Latest historical value at or before a pinned scenario date. Missing
     * values are NOT interpolated or silently replaced by other years.
     */
    public function latest(
        string $actor,string $metric,?string $partner=null
    ):?array {
        $best=null;
        foreach ($this->records as $item) {
            if ($item['actor_id']!==$actor || $item['metric']!==$metric
                || $item['partner_id']!==$partner) continue;
            if ($best===null || $item['year']>$best['year']) $best=$item;
        }
        return $best;
    }

    /** @return list<array<string,mixed>> */
    public function all():array
    {
        return array_values($this->records);
    }

    public function count():int
    {
        return count($this->records);
    }

    public function hash():string
    {
        return hash('sha256',json_encode([
            'version'=>self::VERSION,
            'as_of'=>$this->asOf,
            'actors_sha'=>$this->actors->hash(),
            'records'=>$this->records,
        ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    private static function fields(array $item,array $fields):void
    {
        $keys=array_keys($item);sort($keys);sort($fields);
        if ($keys!==$fields) throw new \InvalidArgumentException('Invalid indicator schema');
    }

    private static function day(string $value):void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)
            || ($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value))===false
            || $d->format('Y-m-d')!==$value) {
            throw new \InvalidArgumentException('Invalid observation date');
        }
    }
}
