<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ResearchModels.php';
require_once __DIR__.'/NationalReferenceFallback.php';
require_once __DIR__.'/NationalInvestitureForecast.php';

/** Closed deterministic experiment catalogue. No world IDs, arbitrary code,
 * network calls, live political commands, or access to gameplay storage. */
final class ResearchExperiment
{
    public const VERSION='wp123-experiment-v1';
    public const TYPES=['programme-coverage','policy-capacity','working-hours','household-sensitivity','household-backtest','fiscal-path','family-transfer','countries','national-indicators'];
    public static function json(array $x):string{return json_encode($x,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    public static function hash(array $x):string{return hash('sha256',self::json($x));}
    public static function pin():array{
        $files=['src/ResearchExperiment.php','src/ResearchModels.php','src/HouseholdIncomeReference.php',
            'src/HouseholdHistoricalBacktest.php','src/HouseholdAccount.php','src/ProgrammeSourceInventory.php',
            'data/2026-11-26-household-income-v1.json','data/wp114-direct-source-inventory.json','data/2026-11-25-un-members-v1.json',
            'src/NationalReferenceFallback.php','data/wp130-national-economy-reference-v1.json',
            'data/wp130-historical-resource-rents-staging-v1.json','data/wp130-historical-social-services-staging-v1.json',
            'data/wp130-multisource-gap-supplement-v1.json','data/wp130-historical-economic-gap-supplement-v1.json','data/wp130-fiscal-debt-gap-supplement-v1.json','data/wp130-final-coverage-supplement-v1.json','data/wp130-archival-national-completion-v1.json','data/wp130-archival-debt-extension-v1.json','data/wp130-older-debt-archives-v1.json','data/wp130-1960s-debt-archives-v1.json','src/NationalInvestitureForecast.php','data/wp130-investiture-macro-forecast-v1.json'];
        $pins=[];foreach($files as $f){$h=hash_file('sha256',__DIR__.'/../'.$f);if($h===false)throw new \DomainException('Missing experiment dependency');$pins[$f]=$h;}
        return ['version'=>self::VERSION,'php'=>PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,'files'=>$pins];
    }
    public static function normalize(array $request):array{
        $keys=array_keys($request);sort($keys);
        if($keys!==['parameters','type']||!in_array($request['type'],self::TYPES,true)||!is_array($request['parameters']))
            throw new \DomainException('Invalid experiment');
        $p=$request['parameters'];ksort($p,SORT_STRING);
        foreach($p as $k=>$v)if(!is_string($k)||(!is_int($v)&&!is_string($v)))throw new \DomainException('Invalid parameter type');
        return ['type'=>$request['type'],'parameters'=>$p];
    }
    public static function run(array $request,?array $pin=null):array{
        $r=self::normalize($request);$current=self::pin();
        if($pin!==null&&self::hash($pin)!==self::hash($current))throw new \DomainException('Experiment edition changed');
        $p=$r['parameters'];$type=$r['type'];
        if(in_array($type,['programme-coverage','household-backtest'],true)&&$p!==[])throw new \DomainException('No parameters expected');
        $result=match($type){
            'programme-coverage'=>ResearchModels::coverage(),
            'policy-capacity'=>ResearchModels::policy($p),
            'working-hours'=>ResearchModels::hours($p),
            'household-sensitivity'=>ResearchModels::income($p),
            'household-backtest'=>ResearchModels::calibration(),
            'fiscal-path'=>ResearchModels::fiscal($p),
            'family-transfer'=>ResearchModels::family($p),
            'countries'=>ResearchModels::countries($p),
            'national-indicators'=>self::nationalIndicators($p)
        };
        $provenance=self::provenance($type,$result);
        $result=self::attachRowProvenance($type,$result,$provenance);
        $out=['version'=>self::VERSION,'request'=>$r,'manifest'=>$current,'result'=>$result,'provenance'=>$provenance,
            'research_only'=>true,'runtime_authority'=>false,'official_score_eligible'=>false];
        $out['result_sha256']=self::hash($out);return $out;
    }

    private static function nationalIndicators(array $p):array{
        if(array_keys($p)!==['country']||!is_string($p['country'])||!preg_match('/^[A-Z]{2}$/D',$p['country']))
            throw new \DomainException('Invalid national indicator parameters');
        $ref=NationalReferenceFallback::country($p['country']);$rows=[];
        foreach($ref['observations'] as $metric=>$row)$rows[]=['country'=>$p['country'],'metric'=>$metric]+$row;
        unset($ref['observations']);
        return $ref+['rows'=>$rows,'opening_forecast'=>NationalInvestitureForecast::opening($p['country'])];
    }
    /** Each returned row has provenance; a reference catalogue retains individual
     * per-indicator source and observation year rather than a common date. */
    private static function attachRowProvenance(string $type,array $result,array $meta):array{
        if($type==='national-indicators')return $result;
        if($type==='household-backtest'){
            foreach($result['backtest']['rows'] as &$row)$row['provenance']=[
                'source_name'=>'INSEE — annual national accounts and World Politics accounting model',
                'source_url'=>$result['backtest']['source'],'data_year'=>$row['year'],
                'published_on'=>$result['backtest']['vintage'],'classification'=>'observed_inputs_and_conditional_model_outputs'];
            unset($row);return $result;
        }
        if(isset($result['rows'])){
            foreach($result['rows'] as $i=>&$row){
                if($type==='programme-coverage')$row['provenance']=$meta['sources'][$i];
                elseif($type==='countries')$row['provenance']=[
                    'source_name'=>'United Nations','data_date'=>$result['as_of'],'date_kind'=>'reference_snapshot',
                    'sources'=>$meta['sources']];
                else $row['provenance']=['source_name'=>'World Politics Research model','data_date'=>null,
                    'date_kind'=>'conditional_scenario_no_calendar_date','inputs'=>$meta['sources']];
            }
            unset($row);
        }
        return $result;
    }
    /** Descriptive metadata is inside the hashed report, not added after
     * signing. User assumptions never acquire an external source or date. */
    private static function provenance(string $type,array $result):array{
        $sources=[];$kind='user_supplied_assumptions_and_model';
        if($type==='national-indicators'){
            $kind='mixed_documentary_reference_and_opening_estimate';
            foreach($result['rows'] as $row)$sources[]=['metric'=>$row['metric'],'source_name'=>$row['source_name'],
                'source_url'=>$row['source_url'],'data_year'=>$row['data_year'],'retrieved_on'=>$row['retrieved_on'],
                'quality'=>$row['quality'],'secondary_source_url'=>$row['secondary_source_url'],'source_indicator'=>$row['source_indicator']??$row['indicator'],'data_period'=>$row['data_period']??null];
            foreach(['macro_source','fiscal_source','nominal_gdp_source'] as $k){$src=$result['opening_forecast'][$k]??null;if($src!==null)$sources[]=['source_name'=>$src['source_name'],'source_url'=>$src['source_url'],'published_on'=>$src['published_on']??null,'data_period'=>$src['data_period'],'classification'=>'published_forecast_input'];}
        }elseif($type==='countries'){
            $kind='documentary_reference';
            foreach($result['sources'] as $url)$sources[]=['source_name'=>str_contains($url,'unstats')?'United Nations Statistics Division':'United Nations',
                'source_url'=>$url,'data_date'=>$result['as_of'],'date_kind'=>'reference_snapshot','retrieved_on'=>$result['as_of']];
        }elseif($type==='programme-coverage'){
            $kind='documentary_reference';
            $inventory=ProgrammeSourceInventory::report();
            foreach($result['rows'] as $r)$sources[]=['source_name'=>$inventory['people'][$r['person']]['name'].' — published political project',
                'source_url'=>$r['source'],'data_date'=>null,'date_kind'=>'publication_date_unknown','retrieved_on'=>$inventory['observed_on']];
        }elseif($type==='household-backtest'){
            $kind='derived_historical_benchmark';
            $pack=HouseholdIncomeReference::pin()['pack'];
            foreach($pack['sources'] as $id=>$s)$sources[]=['source_name'=>'INSEE — '.$id,'source_url'=>$s['url'],
                'data_date'=>$s['published_on'],'date_kind'=>'publication_date','retrieved_on'=>$pack['retrieved_on'],
                'observation_period'=>$id==='annual'?'2020–2025':($id==='quarterly'?'2025-Q3–2026-Q2':null)];
        }else{
            $sources[]=['source_name'=>'Research API caller','source_url'=>null,'data_date'=>null,'date_kind'=>'not_supplied','retrieved_on'=>null];
        }
        return ['schema'=>'wp-research-provenance-v1','classification'=>$kind,'sources'=>$sources,
            'model_name'=>'World Politics Research','model_version'=>ResearchModels::VERSION,'not_an_observed_forecast'=>true];
    }
    public static function csv(array $report):string{
        $rows=$report['result']['rows']??$report['result']['backtest']['rows']??null;
        if(!is_array($rows)||$rows===[])throw new \DomainException('No tabular result');
        $keys=[];foreach($rows as $row)foreach(array_keys($row) as $k)if(!in_array($k,$keys,true))$keys[]=$k;
        $f=fopen('php://temp','w+');
        if($f===false)throw new \RuntimeException('CSV unavailable');
        fputcsv($f,$keys,',','"','');
        foreach($rows as $row){
            $values=[];foreach($keys as $key){
                $v=$row[$key]??null;
                if(is_array($v))$v=self::json($v);
                if(is_bool($v))$v=$v?'true':'false';
                if(is_string($v)&&preg_match('/^[=+@\\-\\t\\r]/',$v))$v="'".$v;
                $values[]=$v;
            }
            fputcsv($f,$values,',','"','');
        }
        rewind($f);$out=stream_get_contents($f);fclose($f);
        if($out===false||strlen($out)>262144)throw new \DomainException('CSV bound');return $out;
    }
}
