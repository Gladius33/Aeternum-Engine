<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/UniverseLobby.php';

/** Derived, reproducible experimental projection. It is neither a fiscal
 * ledger nor observed household income. No new mandate or score authority. */
final class NationalEconomicScenario{
    public const VERSION='wp130-consumption-trade-scenario-v1';
    private const BLOB='fc951675740ba80b5b6e0b5df8a29ce969f4698f';
    public static function pack():array{
        static $p=null;if($p!==null)return $p;
        $raw=file_get_contents(__DIR__.'/../data/wp130-national-economy-reference-v1.json');
        if(!is_string($raw)||!hash_equals(self::BLOB,sha1('blob '.strlen($raw)."\0".$raw)))throw new \DomainException('Unpinned national economic reference');
        $d=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        $codes=array_keys($d['countries']);sort($codes);
        if($d['version']!=='wp130-national-economy-reference-v1'||$codes!==array_values(array_diff(UniverseLobby::DIPLOMATIC_COUNTRIES,['FR']))||count($d['series'])!==52)
            throw new \DomainException('National economic reference coverage');
        return $p=$d;
    }
    public static function profile(string $cc,string $day):?array{
        $p=self::pack();$c=$p['countries'][$cc]??null;if($c===null)return null;
        $observations=[];
        foreach($c['observations'] as $id=>[$year,$value]){
            $m=$p['series'][$id];$observations[$m['metric']]=['value'=>$value,'year'=>$year,'indicator'=>$id,'unit'=>$m['unit'],
                'kind'=>$value===null?'missing':'published_historical_or_estimated','source_url'=>$m['source_url'],'publisher'=>$m['publisher']];
        }
        $next=new \DateTimeImmutable($c['renewal']['expected_renewal_month'].'-01');
        // Keep the projected month, not an invented election day.
        while($next->format('Y-m')<substr($day,0,7))$next=$next->modify('+'.$c['renewal']['term_years'].' years');
        return ['reference_as_of'=>$p['as_of'],'reference_blob'=>self::BLOB,'government_mode'=>$c['government_mode'],'electoral_reference'=>$c['electoral_reference'],
            'next_ordinary_chamber_renewal_month'=>$next->format('Y-m'),'renewal_classification'=>'reference_or_scenario_month_not_certified_result',
            'renewal_rule'=>$c['renewal'],'observations'=>$observations,'consumption_history'=>$c['consumption_history']];
    }
    /** The last two historical consumption levels anchor a bounded linear
     * extrapolation from investiture; the starting index is exactly 10000.
     * Trade pressure is a separate relative stress scenario, not a causal
     * estimate. Largest cumulative loss per counterpart avoids duplication. */
    public static function project(array $profile,string $actor,string $day,?array $gdp,array $economy):array{
        $start=new \DateTimeImmutable('2027-05-14');$now=new \DateTimeImmutable($day);
        $elapsed=(int)$start->diff($now)->format('%r%a');
        if($elapsed<0||$elapsed>30000||$economy['day']!==$day)throw new \DomainException('Economic scenario date mismatch');
        $h=$profile['consumption_history'];$last=$h[count($h)-1]??null;$prior=$h[count($h)-2]??null;
        $trend=null;$index=null;
        if($prior!==null&&$last!==null&&$prior['value']>0&&$last['year']>$prior['year']){
            $trend=(int)round(10000*($last['value']/$prior['value']-1)/($last['year']-$prior['year']));
            $trend=max(-500,min(500,$trend));$index=max(1000,min(30000,10000+intdiv($trend*$elapsed,365)));
        }
        $loss=[];$missing=[];
        foreach($economy['projections'] as $id=>$v){
            $policy=$economy['policies'][$id]??null;
            if($policy===null||$policy['status']!=='effective')throw new \DomainException('Invalid effective economic policy');
            if($policy['from']!==$actor&&$policy['to']!==$actor)continue;
            $peer=$policy['from']===$actor?$policy['to']:$policy['from'];
            if($v['effective_on']>$day)throw new \DomainException('Future effective impact');
            if($v['central_usd']===null){$missing[$peer]=true;continue;}
            if(!is_int($v['central_usd'])||$v['central_usd']<0)throw new \DomainException('Invalid trade projection amount');
            $age=(int)(new \DateTimeImmutable($v['effective_on']))->diff($now)->format('%a');
            $weighted=intdiv($v['central_usd']*min(365,$age+1),365);
            $loss[$peer]=max($loss[$peer]??0,$weighted);
        }
        $stress=null;
        if(($gdp['kind']??null)==='observed'&&$gdp['value']>0&&$missing===[]){
            // 0.25 is an explicitly fictional pass-through coefficient.
            $stress=(int)min(2500,round(2500*array_sum($loss)/$gdp['value']));
        }
        return ['version'=>self::VERSION,'classification'=>'experimental_consumption_stress_not_household_income',
            'day'=>$day,'opening_index_bps'=>10000,'historical_annual_trend_bps'=>$trend,'baseline_index_bps'=>$index,
            'trade_stress_bps'=>$stress,'scenario_index_bps'=>$index===null||$stress===null?null:max(1000,$index-$stress),
            'trade_coverage_complete_for_effective_policies'=>$missing===[],'missing_trade_counterpart_count'=>count($missing),
            'not_a_forecast'=>true,'rankable'=>false];
    }
}
