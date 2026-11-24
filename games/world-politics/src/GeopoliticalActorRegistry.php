<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

/**
 * Dated international actor registry: states, observer states and governing
 * authorities are separate, and nothing here determines territorial control.
 *
 * No direct ingestion from a URL at runtime; operator pins a curated file.
 */
final class GeopoliticalActorRegistry
{
    /** @var array<string,array<string,mixed>> */
    private array $entities=[];
    public readonly string $version;
    public readonly string $observationDate;

    /** @param array<string,mixed> $document */
    public function __construct(array $document)
    {
        self::exact($document,['version','observation_date','disclaimer','entities']);
        self::id($document['version']);
        self::day($document['observation_date']);
        if (!is_string($document['disclaimer']) || trim($document['disclaimer'])==='') {
            throw new \InvalidArgumentException('Missing data disclaimer');
        }
        $this->version=$document['version'];
        $this->observationDate=$document['observation_date'];
        if (!is_array($document['entities']) || !array_is_list($document['entities'])) {
            throw new \InvalidArgumentException('Invalid actor registry');
        }
        foreach ($document['entities'] as $record) {
            if (!is_array($record)) throw new \InvalidArgumentException('Invalid actor record');
            self::exact($record,[
                'id','label','iso2','kind','un_status','parent_id','controller',
                'representation','sources','strategic_programmes'
            ]);
            self::id($record['id']);
            self::iso($record['iso2']);
            if (isset($this->entities[$record['id']])) throw new \DomainException('Duplicate actor');
            if (!is_string($record['label']) || trim($record['label'])==='' || strlen($record['label'])>160) {
                throw new \InvalidArgumentException('Invalid actor label');
            }
            if (!in_array($record['kind'],['state','governing_authority'],true)
                || !in_array($record['un_status'],['un_member','non_member_observer_state','not_applicable'],true)
                || !in_array($record['controller'],['ai_only','human_or_ai'],true)
                || !in_array($record['representation'],[
                    'international_state_actor','international_state_actor_with_distinct_authorities',
                    'local_administrative_interlocutor'
                ],true)) {
                throw new \InvalidArgumentException('Unsupported actor legal status');
            }
            if ($record['kind']==='governing_authority') {
                if ($record['un_status']!=='not_applicable'
                    || !is_string($record['parent_id']) || $record['parent_id']==='') {
                    throw new \InvalidArgumentException('Authority has no separate UN membership');
                }
                self::id($record['parent_id']);
            } elseif ($record['parent_id']!==null || $record['un_status']==='not_applicable') {
                throw new \InvalidArgumentException('State status invalid');
            }
            if (in_array($record['iso2'],['IL','IR','PS'],true) && $record['controller']!=='ai_only') {
                throw new \DomainException('Restricted real-world faction cannot become human-playable');
            }
            self::sources($record['sources']);
            if (!is_array($record['strategic_programmes'])
                || !array_is_list($record['strategic_programmes'])
                || count($record['strategic_programmes'])>12) {
                throw new \InvalidArgumentException('Invalid strategic dataset');
            }
            $seen=[];
            foreach ($record['strategic_programmes'] as $programme) {
                if (!is_array($programme)) throw new \InvalidArgumentException('Invalid strategic programme');
                self::exact($programme,[
                    'id','status','quantified_operational_capacity','verification','sources'
                ]);
                self::id($programme['id']);
                if (isset($seen[$programme['id']])) throw new \InvalidArgumentException('Duplicate programme');
                $seen[$programme['id']]=true;
                if ($programme['status']!=='publicly_documented'
                    || $programme['quantified_operational_capacity']!==null
                    || !in_array($programme['verification'],[
                        'independent_open_source_estimate_not_operational_intelligence',
                        'international_safeguards_reporting_with_limitations'
                    ],true)) {
                    throw new \InvalidArgumentException('Unsupported or unverified military claim');
                }
                self::sources($programme['sources']);
            }
            $this->entities[$record['id']]=$record;
        }
        foreach ($this->entities as $record) {
            if ($record['parent_id']!==null) {
                $parent=$this->entities[$record['parent_id']]??null;
                if ($parent===null || $parent['kind']!=='state'
                    || $parent['iso2']!==$record['iso2']) {
                    throw new \InvalidArgumentException('Authority must reference its state without becoming that state');
                }
            }
        }
        ksort($this->entities,SORT_STRING);
    }

    public static function fromJson(string $json):self
    {
        if (strlen($json)>250000) throw new \InvalidArgumentException('Oversized registry');
        $decoded=json_decode($json,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new \InvalidArgumentException('Invalid registry JSON');
        return new self($decoded);
    }

    public static function loadPinnedBaseline():self
    {
        $path=__DIR__.'/../data/2026-11-24-special-actors.json';
        $data=file_get_contents($path);
        if ($data===false) throw new \RuntimeException('Baseline not available');
        return self::fromJson($data);
    }

    /** @return array<string,mixed> */
    public function get(string $id):array
    {
        self::id($id);
        if (!isset($this->entities[$id])) throw new \DomainException('Unknown geopolitical actor');
        return $this->entities[$id];
    }

    /** @return list<array<string,mixed>> */
    public function all():array
    {
        return array_values($this->entities);
    }

    /**
     * Note: the Authority may negotiate administrative cooperation; it is
     * not assumed to have the same international treaty powers as a state.
     */
    public function canProposeInterstateTreaty(string $id):bool
    {
        return $this->get($id)['kind']==='state';
    }

    public function isHumanPlayable(string $id):bool
    {
        return $this->get($id)['controller']==='human_or_ai';
    }

    public function hash():string
    {
        $v=['version'=>$this->version,'observation_date'=>$this->observationDate,
            'entities'=>$this->entities];
        return hash('sha256',json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }

    private static function sources(mixed $sources):void
    {
        if (!is_array($sources) || !array_is_list($sources)
            || count($sources)<1 || count($sources)>12) {
            throw new \InvalidArgumentException('At least one official or documented source required');
        }
        foreach ($sources as $url) {
            if (!is_string($url) || strlen($url)>500
                || !str_starts_with($url,'https://')
                || filter_var($url,FILTER_VALIDATE_URL)===false) {
                throw new \InvalidArgumentException('Invalid source URL');
            }
        }
    }
    private static function exact(array $array,array $fields):void
    {
        $keys=array_keys($array);sort($keys);sort($fields);
        if ($keys!==$fields) throw new \InvalidArgumentException('Unexpected record fields');
    }
    private static function id(mixed $id):void
    {
        if (!is_string($id) || !preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D',$id)) {
            throw new \InvalidArgumentException('Invalid actor id');
        }
    }
    private static function iso(mixed $code):void
    {
        if (!is_string($code) || !preg_match('/^[A-Z]{2}$/D',$code)) {
            throw new \InvalidArgumentException('Invalid country code');
        }
    }
    private static function day(mixed $day):void
    {
        if (!is_string($day) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day)
            || ($date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day))===false
            || $date->format('Y-m-d')!==$day) {
            throw new \InvalidArgumentException('Invalid date');
        }
    }
}
