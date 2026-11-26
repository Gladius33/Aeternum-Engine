<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** WP51: aggregate cohort demography, no individual people or forecasts.
 * Every simulated person is counted once; raw published rounding is preserved.
 */
final class FrenchDemography
{
    public const VERSION='wp51-demography-v1';
    private const AGES=['00-14','15-19','20-24','25-29','30-34','35-39','40-44','45-49','50-54','55-59','60-64','65-69','70-74','75+'];
    private const SEXES=['female','male'];
    private const MORTALITY=[1,1,2,2,3,4,6,10,16,25,40,65,100,350];
    private const MIGRATION=[12,8,12,16,16,12,8,6,4,3,2,1,0,0];
    private static function split(int $total,array $weights):array
    {
        $den=array_sum($weights);
        if($total<0||$total>100000000||$den<=0||$den>50000000000)throw new \DomainException('Population allocation bounds');
        $out=[];$cum=0;$prior=0;
        foreach($weights as $key=>$w){
            if(!is_int($w)||$w<0)throw new \DomainException('Invalid cohort weight');
            $cum+=$w;
            // Products bounded by 5e18 within int64.
            $n=intdiv($total*$cum,$den);$out[$key]=$n-$prior;$prior=$n;
        }
        return $out;
    }
    private static function ratio(int $n,int $d):?int{return $d===0?null:intdiv($n*10000,$d);}
    public static function load(string $start,int $u0,bool $project=false):array
    {
        return self::build(json_decode(file_get_contents(__DIR__.'/../data/2026-11-25-fr-demography-v1.json'),true,32,JSON_THROW_ON_ERROR),$start,$u0,$project);
    }
    public static function build(array $p,string $start,int $u0,bool $project=false):array
    {
        if($u0<0||$u0>3000||($p['schema']??null)!=='wp51-fr-demography-v1'||($p['country']??null)!=='FR'||(($p['as_of']??null)!==$start&&!($start==='2027-05-14'&&($p['as_of']??null)==='2026-11-25'))
            ||!is_array($p['observations']??null)||count($p['observations'])!==51)
            throw new \DomainException('Invalid demography pack');
        $rows=[];
        foreach($p['observations'] as $o){
            $src=$p['sources'][$o['source_id']??'']??null;
            if(!is_array($src)||!is_string($src['url']??null)||!str_starts_with($src['url'],'https://www.insee.fr/')
                ||!is_string($src['published_on']??null)||$src['published_on']>$start||($o['kind']??null)!=='observed'
                ||!is_string($o['metric']??null)||!is_string($o['period']??null)||!is_int($o['value']??null))
                throw new \DomainException('Invalid demographic observation');
            $k=$o['metric'].'/'.$o['period'].'/'.($o['sex']??'').'/'.($o['age_group']??'');
            if(isset($rows[$k]))throw new \DomainException('Duplicate demographic observation');
            $rows[$k]=$o;
        }
        $get=function(string $metric,string $period,string $unit,string $sex='',string $age='')use($rows):int{
            $r=$rows[$metric.'/'.$period.'/'.$sex.'/'.$age]??throw new \DomainException('Missing demographic anchor');
            if($r['unit']!==$unit||abs($r['value'])>100000000)throw new \DomainException('Demography unit or magnitude');
            return $r['value'];
        };
        $opening=[];$raw=[];$totals=[];
        foreach([...self::SEXES,'all'] as $sex){
            $totals[$sex]=$get('population_total','2026-01-01','persons',$sex);
            foreach(self::AGES as $g)$raw[$sex][$g]=$get('population_age_sex','2026-01-01','persons',$sex,$g);
            if(abs(array_sum($raw[$sex])-$totals[$sex])>10000)throw new \DomainException('Excessive rounded residual');
            if($sex!=='all')foreach(self::split($totals[$sex],$raw[$sex]) as $g=>$v)$opening[$sex.'/'.$g]=$v;
        }
        if(array_sum($opening)!==$totals['all']||$totals['all']<=0)throw new \DomainException('Population total mismatch');
        foreach(self::AGES as $g)if(abs($raw['female'][$g]+$raw['male'][$g]-$raw['all'][$g])>2000)
            throw new \DomainException('Age group rounding mismatch');
        $births=$get('live_births','2025','persons');$deaths=$get('deaths','2025','persons');
        $migration=$get('net_migration','2025','persons');
        if($births<=0||$deaths<=0||$migration<0||$births-$deaths!==$get('natural_balance','2025','persons'))
            throw new \DomainException('Demographic balance mismatch');
        $projection=[];
        if($project){
            if($start!=='2027-05-14')throw new \DomainException('Population projection requires investiture');
            $days=(int)(new \DateTimeImmutable('2026-01-01'))->diff(new \DateTimeImmutable($start))->format('%a');
            $delta=intdiv(($births-$deaths+$migration)*$days,365);
            $opening=self::split($totals['all']+$delta,$opening);
            $projection=['opening_population_projection'=>['method'=>'constant 2025 physical net flows and fixed cohort shares','days'=>$days,'observed_population'=>$totals['all'],'net_change'=>$delta,'projected_population'=>array_sum($opening),'classification'=>'scenario_projection_not_observation']];
        }
        return ['version'=>self::VERSION,'classification'=>'fictional_cohort_model_observed_anchors',...$projection,
            'source_pack'=>$p,'source_sha'=>hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),
            'start_day'=>$start,...($start!==$p['as_of']?['opening_anchor_status'=>$project?'projected_from_historical_physical_flows':'historical_observations_carried_forward_as_scenario_assumptions','opening_anchor_vintage'=>$p['as_of']]:[]),'population_observation_day'=>'2026-01-01','opening_cohorts'=>$opening,
            'opening_indicators'=>self::indicators($opening),'annual_births'=>$births,'annual_deaths'=>$deaths,'annual_net_migration'=>$migration,
            'fertility_observed_hundredths'=>$get('fertility_rate','2025','hundredths_children_per_woman'),
            'statistical_adjustment_not_physical_flow'=>$get('statistical_adjustment','2025','persons'),
            'initial_unemployment_bps'=>$u0,'model_year_days'=>365,'mortality_relative_weights'=>self::MORTALITY,'migration_weights'=>self::MIGRATION,
            'female_birth_share_bps'=>4900,'working_age_output_sensitivity_bps'=>5000,
            'coefficients'=>'fictional_uncalibrated','net_migration_only'=>true];
    }
    public static function assertValid(array $c,string $start,int $u0):void
    {if(!isset($c['source_pack'])||self::build($c['source_pack'],$start,$u0,isset($c['opening_population_projection']))!==$c)throw new \DomainException('Demographic assumptions mismatch');}
    public static function indicators(array $a):array
    {
        $children=0;$working=0;$older=0;$women=0;$female=0;$male=0;$school=0;
        foreach(self::SEXES as $sex)foreach(self::AGES as $i=>$g){
            $v=$a[$sex.'/'.$g];if(!is_int($v)||$v<0)throw new \DomainException('Negative demographic cohort');
            if($i<2)$children+=$v;elseif($i<11)$working+=$v;else $older+=$v;
            if($i===0||$i===1)$school+=$v;
            if($sex==='female'){$female+=$v;if($i>=1&&$i<=7)$women+=$v;}else $male+=$v;
        }
        $total=$children+$working+$older;
        if($total<=0||$total>100000000||$total!==$female+$male)throw new \DomainException('Demographic population bounds');
        return ['population'=>$total,'female'=>$female,'male'=>$male,'under_20'=>$children,'age_20_64'=>$working,'age_65_plus'=>$older,
            'female_15_49'=>$women,'education_age_proxy'=>$school,
            'old_age_dependency_bps'=>self::ratio($older,$working),'total_dependency_bps'=>self::ratio($children+$older,$working),
            'age_65_plus_share_bps'=>self::ratio($older,$total)];
    }
    /** Lagged working-age population proxy only, not observed employment.
     * Does not change old scenarios; no immediate conversion of minors into jobs.
     */
    public static function outputAdjustment(array $c,?array $previous):int
    {
        $opening=$c['opening_indicators']['age_20_64'];
        $n=$previous['population']['indicators']['age_20_64']??$opening;
        return max(-500,min(500,intdiv(($n-$opening)*$c['working_age_output_sensitivity_bps'],$opening)));
    }
    public static function step(array $c,?array $previous,array $macro):array
    {
        $p=$previous['population']??null;$a=$p['cohorts']??$c['opening_cohorts'];
        $before=self::indicators($a);$base=$c['opening_indicators'];
        $carry=$p['flow_remainder']??['births'=>0,'deaths'=>0,'net_migration'=>0];
        $agingCarry=$p['aging_remainder']??array_fill_keys(array_keys($a),0);
        $annualBirth=intdiv($c['annual_births']*$before['female_15_49'],$base['female_15_49']);
        $annualDeath=intdiv($c['annual_deaths']*$before['population'],$base['population']);
        // Net migration responds only to prior committed macro conditions.
        // Bounded scenario sensitivity; no claim of causal empirical fit.
        $u=$previous['macro']['unemployment_bps']??null;
        $u0=$c['initial_unemployment_bps']??null; // baseline set separately below
        $migrationFactor=$u===null||$u0===null?10000:max(5000,min(15000,10000-($u-$u0)*3));
        $annualMigration=intdiv($c['annual_net_migration']*$migrationFactor,10000);
        $flows=[];
        foreach(['births'=>$annualBirth,'deaths'=>$annualDeath,'net_migration'=>$annualMigration] as $k=>$v){
            $n=$v+$carry[$k];$flows[$k]=intdiv($n,365);$carry[$k]=$n%365;
        }
        $dw=[];$mw=[];
        foreach(self::SEXES as $sex)foreach(self::AGES as $i=>$g){
            $k=$sex.'/'.$g;$dw[$k]=$a[$k]*$c['mortality_relative_weights'][$i];
            $mw[$k]=$c['migration_weights'][$i];
        }
        $dead=self::split($flows['deaths'],$dw);
        foreach($dead as $k=>$v){if($v>$a[$k])throw new \DomainException('Mortality exceeds cohort');$a[$k]-=$v;}
        $moves=[];$aged=0;
        // Simultaneous adjacent age-band transfers, not single-age microdata.
        foreach(self::SEXES as $sex)foreach(self::AGES as $i=>$g){
            $k=$sex.'/'.$g;
            if($i===count(self::AGES)-1){$moves[$k]=0;continue;}
            $den=365*($i===0?15:5);$n=$a[$k]+$agingCarry[$k];
            $moves[$k]=intdiv($n,$den);$agingCarry[$k]=$n%$den;
            if($moves[$k]>$a[$k])throw new \DomainException('Aging exceeds cohort');
        }
        foreach(self::SEXES as $sex)foreach(self::AGES as $i=>$g){
            $k=$sex.'/'.$g;if($i===count(self::AGES)-1)continue;
            $a[$k]-=$moves[$k];$a[$sex.'/'.self::AGES[$i+1]]+=$moves[$k];$aged+=$moves[$k];
        }
        $born=self::split($flows['births'],['female/00-14'=>$c['female_birth_share_bps'],'male/00-14'=>10000-$c['female_birth_share_bps']]);
        foreach($born as $k=>$v)$a[$k]+=$v;
        foreach(self::split($flows['net_migration'],$mw) as $k=>$v)$a[$k]+=$v;
        $after=self::indicators($a);
        if($after['population']!==$before['population']+$flows['births']-$flows['deaths']+$flows['net_migration'])
            throw new \DomainException('Demographic stock flow mismatch');
        $cumulative=$p['cumulative']??['births'=>0,'deaths'=>0,'net_migration'=>0];
        foreach($flows as $k=>$v)$cumulative[$k]+=$v;
        return ['version'=>self::VERSION,'opening_population'=>$before['population'],'cohorts'=>$a,'indicators'=>$after,
            'flows'=>$flows,'natural_balance'=>$flows['births']-$flows['deaths'],'aging_transfers'=>$aged,
            'flow_remainder'=>$carry,'aging_remainder'=>$agingCarry,'cumulative'=>$cumulative,
            'migration_factor_bps'=>$migrationFactor,'lagged_output_adjustment_bps'=>self::outputAdjustment($c,$previous),
            'daily_nominal_gdp_per_capita_minor'=>intdiv($macro['nominal_gdp'],$after['population'])];
    }
    public static function project(array $dom):array
    {
        $c=$dom['population_model'];$days=[];$months=[];$years=[];
        foreach($dom['days'] as $r){
            $p=$r['population'];$day=$r['day'];$days[]=['day'=>$day,'opening_population'=>$p['opening_population'],
                'population'=>$p['indicators']['population'],...$p['flows'],'natural_balance'=>$p['natural_balance'],
                'old_age_dependency_bps'=>$p['indicators']['old_age_dependency_bps']];
            foreach(['month','year'] as $type){
                $key=substr($day,0,$type==='month'?7:4);$b=$type==='month'?($months[$key]??null):($years[$key]??null);
                $b??=['period'=>$key,'first_day'=>$day,'last_day'=>$day,'days'=>0,'opening_population'=>$p['opening_population'],
                    'closing_population'=>$p['opening_population'],'births'=>0,'deaths'=>0,'net_migration'=>0,'population_change'=>0];
                $b['last_day']=$day;$b['days']++;$b['closing_population']=$p['indicators']['population'];
                foreach($p['flows'] as $k=>$v)$b[$k]+=$v;
                $b['population_change']=$b['closing_population']-$b['opening_population'];
                if($type==='month')$months[$key]=$b;else $years[$key]=$b;
            }
        }
        $last=$dom['days']===[]?null:$dom['days'][array_key_last($dom['days'])]['population'];
        return ['version'=>self::VERSION,'world_id'=>$dom['world_id'],'day'=>$dom['day'],'classification'=>'fictional_demography_observed_anchors',
            'source_sha'=>$c['source_sha'],'reference'=>$c['source_pack'],'assumptions'=>array_diff_key($c,['source_pack'=>true]),
            'cohorts'=>$last['cohorts']??$c['opening_cohorts'],'indicators'=>$last['indicators']??$c['opening_indicators'],
            'latest'=>$last,'days'=>$days,'months'=>array_values($months),'years'=>array_values($years),'not_a_forecast'=>true];
    }
}
