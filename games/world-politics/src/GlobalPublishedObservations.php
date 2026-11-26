<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/EconomicReferenceRegistry.php';

/** Published table transcription, not a synthetic World Bank API response.
 * This dated pack is immutable for existing worlds. */
final class GlobalPublishedObservations
{
    public const VERSION='wp54r-global-published-tables-v1';
    public const TRADE_LICENSE='French public statistical facts; source attribution; EUR converted to USD at ECB 2025 annual average';
    public static function load():array
    {
        $raw=file_get_contents(__DIR__.'/../data/2026-11-25-global-published-tables-v1.json');
        if($raw===false)throw new \DomainException('Missing published global tables');
        $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!==self::VERSION||$p['as_of']!=='2026-11-25'||$p['wdi_vintage']!=='2025-12-15'||$p['year']!==2024||count($p['population_thousands'])!==194||count($p['gdp_usd_millions'])!==55||$p['usd_per_eur_2025_basis_points']!==11300)throw new \DomainException('Unexpected global published table');
        foreach($p['sources'] as $url)if(!is_string($url)||!str_starts_with($url,'https://')||!filter_var($url,FILTER_VALIDATE_URL))throw new \DomainException('Invalid global source');
        return $p;
    }
    public static function digest():string
    {return hash('sha256',json_encode(self::load(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));}
    public static function records(GeopoliticalActorRegistry $actors):array
    {
        $p=self::load();$rows=[];
        foreach(['population_thousands'=>['population_total','persons',1000,'population'],'gdp_usd_millions'=>['gdp_current_usd','USD',1000000,'gdp']] as $key=>[$metric,$unit,$scale,$source]){
            foreach($p[$key] as $iso=>$value){
                if(!preg_match('/^[A-Z]{2}$/D',$iso)||!is_int($value)||$value<=0||$value>100000000)throw new \DomainException('Invalid WDI table value');
                $id='state_'.strtolower($iso);$actor=$actors->get($id);
                if($actor['kind']!=='state'||$actor['iso2']!==$iso)throw new \DomainException('Unexpected WDI actor');
                $rows[]=['actor_id'=>$id,'partner_id'=>null,'metric'=>$metric,'value'=>$value*$scale,'unit'=>$unit,'year'=>2024,
                    'kind'=>'observed','quality'=>'estimated','source_url'=>$p['sources'][$source],'license'=>'CC BY 4.0; World Bank WDI dataset terms; rounded published table'];
            }
        }
        // French reporting only; the symmetric record denotes the same turnover,
        // not a separate observation of the partner's customs books.
        foreach($p['goods_trade_2025_eur_millions'] as $iso=>$v){
            $id='state_'.strtolower($iso);$actors->get($id);
            if(!isset($p['sources'][$v['source']])||!is_int($v['exports'])||!is_int($v['imports'])||min($v['exports'],$v['imports'])<0||max($v['exports'],$v['imports'])>1000000)throw new \DomainException('Invalid goods trade');
            $usd=intdiv(($v['exports']+$v['imports'])*1000000*$p['usd_per_eur_2025_basis_points'],10000);
            foreach([['state_fr',$id],[$id,'state_fr']] as [$from,$to])$rows[]=['actor_id'=>$from,'partner_id'=>$to,'metric'=>'bilateral_trade_usd','value'=>$usd,'unit'=>'USD','year'=>2025,
                'kind'=>'observed','quality'=>'estimated','source_url'=>$p['sources'][$v['source']],'license'=>self::TRADE_LICENSE];
        }
        return (new EconomicReferenceRegistry($p['as_of'],$rows,$actors))->all();
    }
    public static function summary():array
    {
        $p=self::load();
        return ['version'=>self::VERSION,'source_sha'=>self::digest(),'wdi_vintage'=>$p['wdi_vintage'],'observation_year'=>2024,'sources'=>$p['sources'],'notes'=>$p['notes'],
            'goods_trade_2025_eur_millions'=>$p['goods_trade_2025_eur_millions'],'usd_per_eur_2025_basis_points'=>$p['usd_per_eur_2025_basis_points'],
            'organizations_reference_only'=>$p['organizations'],'not_current_forecasts'=>true,'alliance_rules_executable'=>false];
    }
}
