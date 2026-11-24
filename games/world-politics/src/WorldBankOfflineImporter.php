<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/EconomicReferenceRegistry.php';

/**
 * WP14: offline, source-attributed, pagination-complete WDI importer.
 *
 * This is NOT a live HTTP downloader or a license autodetector.
 * An administrator must fetch official World Bank API v2 responses,
 * verify the series-specific right to redistribute, pin every page,
 * and pass an explicit approval manifest to this importer.
 *
 * Third-party data can have different rights. A page with missing
 * country/year codes is skipped with diagnostics, never guessed.
 */
final class WorldBankOfflineImporter
{
    private const WDI_METRICS=[
        'SP.POP.TOTL'=>['metric'=>'population_total','unit'=>'persons'],
        'NY.GDP.MKTP.CD'=>['metric'=>'gdp_current_usd','unit'=>'USD']
    ];

    /**
     * @param list<string> $pages Complete archived JSON pages of ONE WDI series
     * @param array<string,mixed> $rights e.g.
     *  {license,license_url,reviewed_at,redistribution_approved}
     * @return array{
     *  registry:EconomicReferenceRegistry,
     *  manifest:array<string,mixed>,
     *  diagnostics:list<array<string,mixed>>
     * }
     */
    public static function import(
        array $pages,string $series,string $asOf,
        GeopoliticalActorRegistry $actors,array $rights
    ):array {
        if (!isset(self::WDI_METRICS[$series])) {
            throw new \InvalidArgumentException('Series is not yet mapped to a safe metric');
        }
        self::rights($rights,$asOf);
        if (!array_is_list($pages) || $pages===[] || count($pages)>100) {
            throw new \InvalidArgumentException('Missing or oversized API response pages');
        }
        $seenPages=[];$expectedPages=null;$reportedTotal=null;
        $records=[];$diagnostics=[];
        foreach ($pages as $json) {
            if (!is_string($json) || strlen($json)>12000000) {
                throw new \InvalidArgumentException('A WDI response page is invalid or too large');
            }
            $data=json_decode($json,true,32,JSON_THROW_ON_ERROR);
            if (!is_array($data) || count($data)!==2 || !array_is_list($data)
                || !is_array($data[0]) || !is_array($data[1])
                || !array_is_list($data[1])) {
                throw new \InvalidArgumentException('WDI API v2 must return [pagination, rows]');
            }
            $meta=$data[0];$rows=$data[1];
            $page=self::whole($meta['page']??null);
            $pagesCount=self::whole($meta['pages']??null);
            $total=self::whole($meta['total']??null);
            if ($pagesCount<1 || $pagesCount>100 || $page<1 || $page>$pagesCount
                || $total<0 || $total>50000) {
                throw new \InvalidArgumentException('Unexpected WDI pagination');
            }
            if ($expectedPages!==null
                && ($expectedPages!==$pagesCount || $reportedTotal!==$total)) {
                throw new \InvalidArgumentException('Contradictory WDI pagination metadata');
            }
            if (isset($seenPages[$page])) {
                throw new \DomainException('Duplicated WDI page');
            }
            $seenPages[$page]=true;
            $expectedPages=$pagesCount;$reportedTotal=$total;
            foreach ($rows as $row) {
                if (!is_array($row) || !is_array($row['indicator']??null)
                    || ($row['indicator']['id']??null)!==$series
                    || !is_array($row['country']??null)) {
                    throw new \InvalidArgumentException('Wrong indicator or malformed WDI row');
                }
                $country=$row['country']['id']??null;
                $year=$row['date']??null;
                if (!is_string($country) || !preg_match('/^[A-Z]{2}$/D',$country)) {
                    $diagnostics[]=['code'=>'skipped_aggregate_or_non_iso2','country'=>$country];
                    continue;
                }
                try {
                    $actor=$actors->get('state_'.strtolower($country));
                } catch (\DomainException $e) {
                    $diagnostics[]=['code'=>'country_not_in_curated_scenario','country'=>$country];
                    continue;
                }
                if ($actor['kind']!=='state' || $actor['iso2']!==$country) {
                    $diagnostics[]=['code'=>'not_sovereign_country_actor','country'=>$country];
                    continue;
                }
                if (!is_string($year) || !preg_match('/^\d{4}$/D',$year)) {
                    $diagnostics[]=['code'=>'invalid_year','country'=>$country];
                    continue;
                }
                $yearNumber=(int)$year;
                if ($yearNumber<1900 || $yearNumber>(int)substr($asOf,0,4)) {
                    $diagnostics[]=['code'=>'outside_historical_snapshot','country'=>$country,'year'=>$year];
                    continue;
                }
                $value=$row['value']??null;
                if ($value===null) {
                    $kind='missing';$quality='missing';$normalized=null;
                    $source=null;$license='not_applicable';
                } else {
                    $normalized=self::quantity($value,$series);
                    $kind='observed';
                    $quality='estimated';
                    $source='https://api.worldbank.org/v2/country/'.$country
                        .'/indicator/'.$series.'?date='.$year;
                    $license=$rights['license'];
                }
                $records[]=[
                    'actor_id'=>'state_'.strtolower($country),'partner_id'=>null,
                    'metric'=>self::WDI_METRICS[$series]['metric'],
                    'unit'=>self::WDI_METRICS[$series]['unit'],
                    'value'=>$normalized,'year'=>$yearNumber,
                    'kind'=>$kind,'quality'=>$quality,
                    'source_url'=>$source,'license'=>$license
                ];
            }
        }
        if (count($seenPages)!==$expectedPages
            || count($seenPages)!==count($pages)
            || count($records)>50000) {
            throw new \DomainException('Cannot import an incomplete WDI page set');
        }
        if ($reportedTotal!==null
            && $reportedTotal!==count($records)+count($diagnostics)) {
            throw new \DomainException('WDI page totals do not match imported and skipped rows');
        }
        $registry=new EconomicReferenceRegistry($asOf,$records,$actors);
        return [
            'registry'=>$registry,
            'manifest'=>[
                'provider'=>'World Bank API v2',
                'series'=>$series,
                'as_of'=>$asOf,
                'license'=>$rights['license'],
                'license_url'=>$rights['license_url'],
                'license_reviewed_at'=>$rights['reviewed_at'],
                'redistribution_approved'=>true,
                'api_pages'=>$expectedPages,
                'reported_rows'=>$reportedTotal,
                'imported_rows'=>$registry->count(),
                'skipped_rows'=>count($diagnostics),
                'snapshot_sha'=>$registry->hash(),
                'source_url'=>'https://api.worldbank.org/v2/country/all/indicator/'.$series
            ],
            'diagnostics'=>$diagnostics
        ];
    }

    private static function rights(array $rights,string $asOf):void
    {
        $required=['license','license_url','reviewed_at','redistribution_approved'];
        $fields=array_keys($rights);sort($fields);sort($required);
        if ($fields!==$required || $rights['redistribution_approved']!==true
            || !is_string($rights['license']) || trim($rights['license'])===''
            || !is_string($rights['license_url'])
            || !str_starts_with($rights['license_url'],'https://')
            || filter_var($rights['license_url'],FILTER_VALIDATE_URL)===false
            || !is_string($rights['reviewed_at'])
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$rights['reviewed_at'])
            || ($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$rights['reviewed_at']))===false
            || $d->format('Y-m-d')!==$rights['reviewed_at']
            || $rights['reviewed_at']>$asOf) {
            throw new \DomainException('Series-specific redistribution and date review required');
        }
    }

    private static function whole(mixed $number):int
    {
        if (is_int($number)) return $number;
        if (is_string($number) && preg_match('/^\d{1,8}$/D',$number)) return (int)$number;
        throw new \InvalidArgumentException('Invalid pagination integer');
    }

    private static function quantity(mixed $value,string $series):int
    {
        if (!is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException('WDI numeric value must be a JSON number');
        }
        if (!is_finite((float)$value) || $value<0 || $value>1000000000000000) {
            throw new \InvalidArgumentException('Unsupported WDI value magnitude');
        }
        // All economic metric models in this milestone use integer units.
        // Explicitly round external floating observations to nearest unit.
        // The record is marked estimated, never labeled an exact measurement.
        return (int)round($value,0,PHP_ROUND_HALF_UP);
    }
}
