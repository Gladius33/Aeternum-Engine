<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ResearchModels.php';

/** Closed deterministic experiment catalogue. No world IDs, arbitrary code,
 * network calls, live political commands, or access to gameplay storage. */
final class ResearchExperiment
{
    public const VERSION='wp123-experiment-v1';
    public const TYPES=['programme-coverage','policy-capacity','working-hours','household-sensitivity','household-backtest','fiscal-path','family-transfer','countries'];
    public static function json(array $x):string{return json_encode($x,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    public static function hash(array $x):string{return hash('sha256',self::json($x));}
    public static function pin():array{
        $files=['src/ResearchExperiment.php','src/ResearchModels.php','src/HouseholdIncomeReference.php',
            'src/HouseholdHistoricalBacktest.php','src/HouseholdAccount.php','src/ProgrammeSourceInventory.php',
            'data/2026-11-26-household-income-v1.json','data/wp114-direct-source-inventory.json','data/2026-11-25-un-members-v1.json'];
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
            'countries'=>ResearchModels::countries($p)
        };
        $out=['version'=>self::VERSION,'request'=>$r,'manifest'=>$current,'result'=>$result,
            'research_only'=>true,'runtime_authority'=>false,'official_score_eligible'=>false];
        $out['result_sha256']=self::hash($out);return $out;
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
