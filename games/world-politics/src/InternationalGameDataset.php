<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Closed aggregate projection of one committed foreign-fiscal world day.
 * Only public country codes, fixed numeric fields and documentary metadata.
 * A world/day envelope remains the unit of storage and withdrawal. */
final class InternationalGameDataset
{
    public const VERSION='wp130-international-game-aggregate-v1';
    private const COUNTRIES=['AT','AU','BE','BG','BR','CA','CH','CN','CY','CZ','DE','DK','EE','ES','FI','GB','GR','HR','HU','ID','IE','IN','IT','JP','KR','LT','LU','LV','MT','MX','NL','PL','PT','RO','RU','SA','SE','SI','SK','TR','US','ZA'];
    private const FEATURES=['gross_debt','cash','credit_remaining','interest_arrears','principal_arrears','primary_arrears',
        'revenue_received','primary_paid','interest_paid','principal_repaid','borrowing_received','overall_cash_balance',
        'real_output_index','price_index','indicative_real_wage_index','unemployment_scaled','nominal_disposable_income','real_disposable_income','purchasing_power_index','trade_gap_bps','known_trade_exposure_bps','unknown_trade_counterparts'];
    private static function source(array $s):array{
        return ['source_name'=>$s['source_name'],'source_url'=>$s['source_url'],'published_on'=>$s['published_on'],'data_period'=>$s['data_period']];
    }
    public static function project(array $r,string $day):?array{
        $c=$r['config'];$s=$r['state'];$edition=$c['version'];
        if(!in_array($edition,['wp130-normalized-fiscal-world-v3','wp130-normalized-fiscal-world-v4'],true))return null;
        if($s['day']!==$day||array_keys($c['countries'])!==self::COUNTRIES||array_keys($s['countries'])!==self::COUNTRIES)throw new \DomainException('International aggregate scope');
        $first=$c['countries']['AT']['opening_forecast'];
        $macro=self::source($first['macro_source']);$fiscal=self::source($first['fiscal_source']);
        $out=['version'=>self::VERSION,'classification'=>'synthetic_game_simulation','data_date'=>$day,'date_kind'=>'simulation_day',
            'model_version'=>$edition,'source_name'=>'World Politics international macro-fiscal simulation',
            'fiscal_unit'=>'normalized_reference_GDP_units','index_base'=>1000000000,'household_unit'=>'normalized_opening_household_income_units',
            'unemployment_unit'=>'percent_times_1000000','forecast_reference_blob'=>$first['reference_blob'],
            'source_state_sha256'=>$r['state_sha'],'config_sha256'=>$r['config_sha'],
            'forecast_sources'=>['macro'=>$macro,'fiscal'=>$fiscal],'assumptions_calibrated'=>false,
            'country_rows'=>[]];
        foreach(self::COUNTRIES as $cc){
            $cfg=$c['countries'][$cc];$opening=$cfg['opening_forecast'];$state=$s['countries'][$cc];$last=$s['last_flows'][$cc];$m=$s['macro'][$cc];$hh=$s['households'][$cc];$t=$m['trade'];$flow=$last['executed_flows'];
            if($state['day']!==$day||$t['data_date']!==$day||$opening['reference_blob']!==$first['reference_blob']||self::source($opening['macro_source'])!==$macro||self::source($opening['fiscal_source'])!==$fiscal)throw new \DomainException('International aggregate day or source mismatch');
            $g=$cfg['gdp_reference'];
            $gdp=($g['kind']??null)==='observed'?['source_name'=>str_contains($g['source_url'],'worldbank.org')?'World Bank — published GDP reference':null,'source_url'=>$g['source_url'],'data_year'=>$g['year'],'value_usd'=>$g['value'],'quality'=>$g['quality']]:null;
            $out['country_rows'][]=['country'=>$cc,'features'=>[
                'gross_debt'=>$state['stocks']['gross_debt'],'cash'=>$state['stocks']['cash'],'credit_remaining'=>$state['credit_remaining'],
                'interest_arrears'=>$state['arrears']['interest'],'principal_arrears'=>$state['arrears']['principal'],'primary_arrears'=>$state['arrears']['primary'],
                'revenue_received'=>$flow['tax_received']+$flow['other_received'],'primary_paid'=>$flow['primary_paid'],'interest_paid'=>$flow['interest_paid'],
                'principal_repaid'=>$flow['principal_repaid'],'borrowing_received'=>$flow['borrowing_received'],'overall_cash_balance'=>$last['overall_cash_balance'],
                'real_output_index'=>$m['real_output_index'],'price_index'=>$m['price_index'],'indicative_real_wage_index'=>$m['indicative_real_wage_index'],
                'unemployment_scaled'=>$m['unemployment_scaled'],'nominal_disposable_income'=>$hh['nominal_disposable_income']??null,
                'real_disposable_income'=>$hh['real_disposable_income']??null,'purchasing_power_index'=>$hh['purchasing_power_index'],
                'trade_gap_bps'=>$m['trade_gap_bps'],'known_trade_exposure_bps'=>$t['known_exposure_bps'],'unknown_trade_counterparts'=>$t['unknown_counterpart_count']],
                'trade_coverage_complete'=>$t['coverage_complete'],'household_available'=>$hh['available'],
                'trade_economic_sha256'=>$t['economic_sha256'],'gdp_reference'=>$gdp];
        }
        self::verify($out,$day);return $out;
    }
    private static function sha(mixed $x,int $n=64):bool{return is_string($x)&&strlen($x)===$n&&ctype_xdigit($x)&&strtolower($x)===$x;}
    private static function url(mixed $x):bool{return is_string($x)&&strlen($x)<=512&&str_starts_with($x,'https://')&&filter_var($x,FILTER_VALIDATE_URL)!==false;}
    public static function verify(array $p,string $day):void{
        if(array_keys($p)!==['version','classification','data_date','date_kind','model_version','source_name','fiscal_unit','index_base','household_unit','unemployment_unit','forecast_reference_blob','source_state_sha256','config_sha256','forecast_sources','assumptions_calibrated','country_rows']
            ||$p['version']!==self::VERSION||$p['classification']!=='synthetic_game_simulation'||$p['data_date']!==$day||$p['date_kind']!=='simulation_day'
            ||!in_array($p['model_version'],['wp130-normalized-fiscal-world-v3','wp130-normalized-fiscal-world-v4'],true)||$p['source_name']!=='World Politics international macro-fiscal simulation'
            ||$p['fiscal_unit']!=='normalized_reference_GDP_units'||$p['index_base']!==1000000000||$p['household_unit']!=='normalized_opening_household_income_units'
            ||$p['unemployment_unit']!=='percent_times_1000000'||$p['assumptions_calibrated']!==false||!self::sha($p['forecast_reference_blob'],40)||!self::sha($p['source_state_sha256'])||!self::sha($p['config_sha256'])
            ||!is_array($p['forecast_sources'])||array_keys($p['forecast_sources'])!==['macro','fiscal']
            ||!is_array($p['country_rows'])||!array_is_list($p['country_rows'])||array_column($p['country_rows'],'country')!==self::COUNTRIES)
            throw new \DomainException('International aggregate contract');
        foreach($p['forecast_sources'] as $kind=>$source){
            if(!is_array($source)||array_keys($source)!==['source_name','source_url','published_on','data_period']||!is_string($source['source_name'])||strlen($source['source_name'])>128||!self::url($source['source_url'])
                ||!is_string($source['published_on'])||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$source['published_on'])||$source['data_period']!=='2026–2027')
                throw new \DomainException('International forecast provenance');
        }
        foreach($p['country_rows'] as $r){
            if(array_keys($r)!==['country','features','trade_coverage_complete','household_available','trade_economic_sha256','gdp_reference']
                ||!is_array($r['features'])||array_keys($r['features'])!==self::FEATURES||!is_bool($r['trade_coverage_complete'])||!is_bool($r['household_available'])||!self::sha($r['trade_economic_sha256']))
                throw new \DomainException('International feature contract');
            $nullable=['unemployment_scaled','nominal_disposable_income','real_disposable_income','purchasing_power_index','known_trade_exposure_bps'];
            foreach($r['features'] as $k=>$v){
                if($v===null&&in_array($k,$nullable,true))continue;
                if(!is_int($v)||abs($v)>100000000000000000||($v<0&&!in_array($k,['overall_cash_balance','trade_gap_bps'],true)))throw new \DomainException('International feature bounds');
            }
            foreach(['nominal_disposable_income','real_disposable_income','purchasing_power_index'] as $k)
                if(($r['features'][$k]!==null)!==$r['household_available'])throw new \DomainException('International household missingness');
            if($r['trade_coverage_complete']&&($r['features']['known_trade_exposure_bps']===null||$r['features']['unknown_trade_counterparts']!==0))throw new \DomainException('International trade missingness');
            $g=$r['gdp_reference'];if($g!==null&&(!is_array($g)||array_keys($g)!==['source_name','source_url','data_year','value_usd','quality']
                ||!in_array($g['source_name'],[null,'World Bank — published GDP reference'],true)||!self::url($g['source_url'])||!is_int($g['data_year'])||$g['data_year']<1900||$g['data_year']>2026||!is_int($g['value_usd'])||$g['value_usd']<0||!in_array($g['quality'],['published','estimated'],true)))throw new \DomainException('GDP lineage contract');
        }
    }
}
