<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/InstitutionalRulebook.php';

/**
 * A scenario's country universe and human-playability are DIFFERENT concerns.
 * Only trusted, source-verified imports may add country records.
 *
 * This class intentionally does NOT pretend that its starter selection is
 * the complete 193-member UN country dataset.
 */
final class CountryCatalog
{
    /** @var list<string> */
    public const EU_2026 = [
        'AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI','FR','GR','HR','HU',
        'IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK',
    ];
    /**
     * Configurable initial strategic choices, NOT an official ranking.
     * Members outside the EU and Switzerland only.
     * @var list<string>
     */
    public const STRATEGIC_STARTER = [
        'AU','BR','CA','CN','GB','ID','IN','JP','KR','MX','RU','SA','TR','US','ZA'
    ];

    /** Non-human geopolitical actors, regardless of strategic selection. */
    public const PERMANENTLY_AUTOMATED = ['IL','IR','PS'];

    /** @var array<string,array<string,mixed>> */
    private array $countries = [];
    /** @var array<string,true> */
    private array $playableTargets = [];

    /**
     * @param list<array{iso2:string,name:string,un_member:bool,as_of:string,source:string}> $records
     * @param list<string> $strategic
     */
    public function __construct(array $records, array $strategic = self::STRATEGIC_STARTER)
    {
        foreach ($strategic as $candidate) {
            if (in_array($candidate,self::PERMANENTLY_AUTOMATED,true)) {
                throw new \InvalidArgumentException('Non-playable country cannot be selected as a strategic human faction');
            }
        }
        $eligible = array_merge(self::EU_2026,['CH'],$strategic);
        if (count($eligible)!==count(array_unique($eligible))) {
            throw new \InvalidArgumentException('Strategic country list duplicates mandatory playable targets');
        }
        foreach ($eligible as $code) {
            self::checkCode($code);
            $this->playableTargets[$code] = true;
        }
        if (!array_is_list($records)) throw new \InvalidArgumentException('Invalid reference dataset');
        foreach ($records as $record) {
            $keys = array_keys($record);
            sort($keys);
            $expected = ['as_of','iso2','name','source','un_member'];
            sort($expected);
            if ($keys !== $expected) throw new \InvalidArgumentException('Invalid country record');
            self::checkCode($record['iso2']);
            if (isset($this->countries[$record['iso2']])) throw new \DomainException('Duplicate country');
            if (!is_string($record['name']) || trim($record['name'])==='' || strlen($record['name'])>160) {
                throw new \InvalidArgumentException('Invalid country name');
            }
            if (!is_bool($record['un_member'])) throw new \InvalidArgumentException('Unverified UN membership');
            if (!is_string($record['source']) || !str_starts_with($record['source'],'https://')
                || filter_var($record['source'], FILTER_VALIDATE_URL) === false) {
                throw new \InvalidArgumentException('Country record needs a trusted HTTPS provenance link');
            }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d',$record['as_of']);
            if ($date===false || $date->format('Y-m-d')!==$record['as_of']) {
                throw new \InvalidArgumentException('Invalid source observation date');
            }
            $this->countries[$record['iso2']] = $record;
        }
        ksort($this->countries,SORT_STRING);
    }

    /** @return array<string,mixed> */
    public function country(string $code):array
    {
        self::checkCode($code);
        if (!isset($this->countries[$code])) throw new \DomainException('Country not imported');
        $record = $this->countries[$code];
        return $record + [
            'eligible_for_human_play'=>isset($this->playableTargets[$code])
                && !in_array($code,self::PERMANENTLY_AUTOMATED,true),
            'control_policy'=>in_array($code,self::PERMANENTLY_AUTOMATED,true) ? 'ai_only'
                : (isset($this->playableTargets[$code]) ? 'human_or_ai' : 'ai_only'),
            'un_observer_status'=>$code==='PS' && !$record['un_member']
                ? 'non_member_observer_state' : null,
            // No country's policy is invented just to enable the selection.
            'institution_rules_ready'=>InstitutionalRulebook::hasRules($code),
            // A constitutional pilot is not a completed playable campaign.
            'human_play_ready'=>false,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function all():array
    {
        $out=[];
        foreach(array_keys($this->countries) as $code) $out[]=$this->country($code);
        return $out;
    }

    public function unMemberCount():int
    {
        return count(array_filter($this->countries,static fn(array $r):bool=>$r['un_member']));
    }

    /**
     * Call this release gate AFTER importing UN's official 193-member dataset.
     * Do not manufacture records from ISO 3166 (which also lists territories).
     */
    public function assertGlobalCoverage():void
    {
        if ($this->unMemberCount()!==193) {
            throw new \DomainException('Scenario lacks the 193 documented UN member countries');
        }
        // 193 full UN members plus Palestine as a separate observer-state actor.
        // Their presence must be verified from sources, never inferred from ISO.
        if (!isset($this->countries['PS']) || $this->countries['PS']['un_member']!==false) {
            throw new \DomainException('Palestine must exist separately as a non-member observer state');
        }
        foreach (['IL','IR'] as $code) {
            if (!isset($this->countries[$code]) || !$this->countries[$code]['un_member']) {
                throw new \DomainException('A UN member geopolitical actor is missing: '.$code);
            }
        }
        foreach (array_keys($this->playableTargets) as $target) {
            if (!isset($this->countries[$target])) {
                throw new \DomainException('Missing required human-play target '.$target);
            }
        }
    }

    public static function checkCode(mixed $code):void
    {
        if (!is_string($code) || !preg_match('/^[A-Z]{2}$/D',$code)) {
            throw new \InvalidArgumentException('Invalid country ISO2');
        }
    }
}
