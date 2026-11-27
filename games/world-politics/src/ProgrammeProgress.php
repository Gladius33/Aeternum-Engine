<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PoliticalRoster.php';
require_once __DIR__.'/CampaignLifecycle.php';

/** Exact identifier matching only, not semantic fulfilment or a prediction. */
final class ProgrammeProgress
{
    public const VERSION='wp92-programme-progress-v1';
    public static function report(array $roster,array $domestic):array
    {
        $r=PoliticalRoster::fromSnapshot($roster['state'])->snapshot();
        $d=$domestic['state'];$person=$r['people'][CampaignLifecycle::PLAYER]??null;
        if($r['world_id']!==$d['world_id']||$r['country_code']!=='FR'||$person===null)
            throw new \DomainException('Incoherent programme sources');
        $ids=array_fill_keys(array_column($person['programme'],'id'),true);
        $observed=[];
        foreach($d['days'] as $day){
            foreach($day['active_policies'] as $id){
                if(!isset($ids[$id]))continue;
                if(!isset($observed[$id]))$observed[$id]=['first_active_day'=>$day['day'],'last_active_day'=>$day['day'],'active_days'=>0];
                $observed[$id]['last_active_day']=$day['day'];$observed[$id]['active_days']++;
            }
        }
        $rows=[];$counts=['no_matching_record'=>0,'proposed'=>0,'authorized_not_observed'=>0,'active_latest_day'=>0,'previously_active'=>0];
        foreach($person['programme'] as $measure){
            $id=$measure['id'];$p=$d['policies'][$id]??null;$o=$observed[$id]??null;
            if($o!==null&&($p===null||$p['authorized_on']===null))throw new \DomainException('Observed programme policy lacks authorization');
            $status=match(true){
                $p===null=>'no_matching_record',
                $p['authorized_on']===null=>'proposed',
                $o===null=>'authorized_not_observed',
                $o['last_active_day']===$d['day']=>'active_latest_day',
                default=>'previously_active'
            };
            $counts[$status]++;
            $rows[]=['measure_id'=>$id,'domain'=>$measure['domain'],'proposal'=>$measure['proposal'],'status'=>$status,
                'matching_policy_id'=>$p===null?null:$id,'policy_record'=>$p,'observed_activity'=>$o,
                'semantic_fulfilment_verified'=>false];
        }
        $hashes=[];
        foreach(['roster'=>$roster,'domestic'=>$domestic] as $k=>$snapshot){
            $h=trim($snapshot['etag'],'"');
            if(!preg_match('/^[a-f0-9]{64}$/D',$h))throw new \DomainException('Invalid programme provenance');
            $hashes[$k]=$h;
        }
        return ['version'=>self::VERSION,'world_id'=>$d['world_id'],'day'=>$d['day'],'candidate_id'=>CampaignLifecycle::PLAYER,
            'candidate_kind'=>$person['kind'],'source_hashes'=>$hashes,'measures'=>$rows,'counts'=>$counts,
            'matching_rule'=>'exact_measure_id_equals_recorded_domestic_policy_id',
            'historical_programme_curated'=>false,'semantic_fulfilment_verified'=>false,'score_rules_changed'=>false];
    }
}
