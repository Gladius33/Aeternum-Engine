<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CampaignLifecycle.php';
require_once __DIR__.'/HouseholdPurchasingPowerProxy.php';

/** Alpha gameplay score, never a claim of empirically calibrated RDB/UC.
 * Inputs come exclusively from trusted, committed world snapshots. */
final class CampaignScore
{
    public const VERSION='wp77-score-alpha-v1';
    public const SCENARIO='fr-investiture-2027-v1';
    public const START='2027-05-14';
    public const LEAGUE='experimental-alpha';
    public const RULES_RELEASE='wp78-alpha-rules-v1';
    public static function json(array $a):string
    {return json_encode($a,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    public static function digest(array $a):string{return hash('sha256',self::json($a));}
    public static function cohort(string $bundleSha,array $config,array $dip):string
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$bundleSha)||($config['start_day']??null)!==self::START)
            throw new \DomainException('Invalid score cohort source');
        $pins=[];
        foreach(['household_reference','candidate_reference','defense_reference'] as $k){
            $h=$dip[$k]['sha']??null;
            if(!is_string($h)||!preg_match('/^[a-f0-9]{64}$/D',$h))throw new \DomainException('Missing ranking reference pin');
            $pins[$k]=$h;
        }
        return self::digest(['scenario'=>self::SCENARIO,'start'=>self::START,'rules'=>self::RULES_RELEASE,
            'score'=>self::VERSION,'income_model'=>HouseholdPurchasingPowerProxy::VERSION,'difficulty'=>'standard',
            'bundle_sha'=>$bundleSha,'domestic_config_sha'=>self::digest($config),'references'=>$pins]);
    }
    /** 1000 points per Gregorian mean year, 100 points per percentage point
     * of purchasing power gain (= one point per basis point).
     * Integer floor and nonnegative total, no floating arithmetic. */
    public static function points(int $days,int $gainBps):array
    {
        if($days<0||$days>7305||$gainBps< -10000||$gainBps>1000000)throw new \DomainException('Score outside supported bounds');
        $tenure=intdiv($days*10000000,3652425);
        return ['tenure_points'=>$tenure,'purchasing_power_points'=>$gainBps,'score'=>max(0,$tenure+$gainBps)];
    }
    public static function report(array $domestic,array $diplomatic,array $opinion):array
    {
        $d=$domestic['state'];$p=$diplomatic['state'];$o=$opinion['state'];$c=$o['campaign']??null;
        if(!is_array($c)||$c['start_day']!==self::START||$c['world_id']!==$d['world_id']||$o['world_id']!==$d['world_id']
            ||$p['world_id']!==$d['world_id']||$c['day']!==$d['day']||$o['day']!==$d['day']||$p['day']!==$d['day']
            ||$c['player_character']!==CampaignLifecycle::PLAYER||$c['office']!=='head_of_state'||$c['country']!=='FR'
            ||!in_array($c['status'],['active','game_over'],true))throw new \DomainException('Incoherent campaign score scope');
        $terminal=$c['status']==='game_over';
        if($c['office_retained']!==!$terminal||($terminal?$c['ended_on']!==$c['day']:$c['ended_on']!==null))
            throw new \DomainException('Inconsistent campaign terminal state');
        $t=CampaignLifecycle::tenure($c);$days=$t['elapsed_office_days'];
        if($days>7305||count($d['days'])!==$days||count($o['history'])!==$days)
            throw new \DomainException('Score needs complete committed tenure history');
        if($days>0&&($d['days'][0]['day']!=='2027-05-15'||$o['history'][$days-1]['source']['day']!==$d['day']))
            throw new \DomainException('Score history begins or ends at wrong date');
        $h=HouseholdPurchasingPowerProxy::report($domestic,$diplomatic);
        if($h['window_days']!==min(365,$days))throw new \DomainException('Incorrect score averaging window');
        // Equal weight for the two residual-income sensitivity variants.
        // Do not select whichever variant awards more points.
        $means=$h['rolling_means_bps'];
        $index=$means===null?10000:intdiv($means['constant_nominal_residual']+$means['price_indexed_residual'],2);
        $gain=$index-10000;
        $sha=trim($opinion['etag'],'"');
        if(!preg_match('/^[a-f0-9]{64}$/D',$sha))throw new \DomainException('Unverified opinion source digest');
        return ['version'=>self::VERSION,'league'=>self::LEAGUE,'rules_release'=>self::RULES_RELEASE,
            'world_id'=>$d['world_id'],'scenario'=>self::SCENARIO,'difficulty'=>'standard','start_day'=>self::START,'day'=>$d['day'],
            'elapsed_office_days'=>$days,'terminal'=>$terminal,'ended_on'=>$c['ended_on'],'end_reason'=>$c['reason'],
            'constitutional_status'=>$t['constitutional_status'],'recognition_status'=>$t['recognition_status'],
            'window_days'=>$h['window_days'],'complete_365_day_window'=>$h['complete_365_day_window'],
            'opening_index_bps'=>10000,'income_proxy_index_bps'=>$index,'income_proxy_gain_bps'=>$gain,
            'income_proxy_cases_bps'=>$means,'income_model'=>HouseholdPurchasingPowerProxy::VERSION,
            ...self::points($days,$gain),'source_hashes'=>$h['source_hashes']+['opinion'=>$sha],
            'rankable_in_alpha'=>true,'official_ranking_eligible'=>false,'calibrated_real_rdb_per_uc'=>false,
            'early_window_provisional'=>$days<365,'formula'=>'max(0,floor(1000*T/365.2425)+G_bps)',
            'assumptions'=>['equal weight residual variants','available committed days up to 365, no fabricated prior history',
                'population proxy for UC, not calibrated household accounts']];
    }
}
