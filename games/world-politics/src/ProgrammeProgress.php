<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PoliticalRoster.php';
require_once __DIR__.'/CampaignLifecycle.php';
require_once __DIR__.'/PresidentialProgramme.php';

/** Exact identifier matching only, not semantic fulfilment or a prediction. */
final class ProgrammeProgress
{
    public const VERSION='wp92-programme-progress-v1';
    /** Member-visible selection and execution evidence from a coherent
     * committed managed universe. A legal instrument is not target delivery. */
    public static function managed(array $roster,array $d,array $i,array $org,array $catalog,string $profile):array
    {
        $r=PoliticalRoster::fromSnapshot($roster)->snapshot();
        if($r['world_id']!==$d['world_id']||$r['country_code']!=='FR'||$i['world_id']!==$d['world_id']||$i['country_code']!=='FR'
            ||$org['world_id']!==$d['world_id']||$i['day']!==$d['day']||$org['day']!==$d['day'])throw new \DomainException('Incoherent managed programme evidence');
        $person=$r['people'][CampaignLifecycle::PLAYER]??throw new \DomainException('Missing managed officeholder');
        $agenda=PresidentialProgramme::agenda($profile);$social=PresidentialProgramme::socialIds($profile)??[];
        $last=$d['days']===[]?[]:$d['days'][array_key_last($d['days'])];$active=$last['active_policies']??[];
        $measures=[];$groups=[];
        foreach($person['programme'] as $m){
            $id=$m['id'];$support=PresidentialProgramme::measureSupport($id);$map=$support['mapping'];$family=$map['family']??$map['field']??null;
            $available=false;$selected=false;$records=[];$state='no_matching_record';$outcome=null;
            if(SocialProgramme::isPolicy($id)){
                $available=SocialProgramme::assumption($id,$catalog,isset($d['social_economy_from']))!==null;
                $selected=in_array($id,$social,true);
                foreach($d['policies'] as $policy=>$v)if(SocialProgramme::objectiveId($policy)===$id){
                    $records[]=['policy_id'=>$policy,'record'=>$v,'active'=>in_array($policy,$active,true)];
                }
                if($records!==[]){
                    $states=array_column($records,'active');
                    if(in_array(true,$states,true))$state='active_latest_day';
                    else{
                        $v=$records[array_key_last($records)]['record'];
                        $state=$v['authorized_on']===null?($v['effective_on']<=$d['day']?'not_authorized_by_deadline':'proposed'):'legally_authorized_not_active';
                    }
                }
                if($id==='pf2022-philippe-poutou-4')$outcome=$org['nato_withdrawal']??null;
                if($id==='pf2022-nathalie-arthaud-3')$outcome=$last['public_equity']['collective']??null;
            }elseif($support['status']==='primary_constitutional_route'||($support['status']==='reviewed_objective'&&($map['field']??null)==='revision')){
                $available=isset($i['revision_requests'],$i['revision']);
                $selected=($agenda['revision']??null)===$map['value']&&($agenda['ratification']??null)===$map['ratification'];
                foreach($i['revision']['procedures']??[] as $key=>$v)if($v['template']===$map['value'])$records[]=['revision_id'=>$key,'record'=>$v,'request'=>$i['revision_requests'][$key]??null];
                if($records!==[])$state=$records[array_key_last($records)]['record']['status'];
                if($id==='pf2022-nicolas-dupont-aignan-4')$outcome=$i['civic_initiative']??null;
            }elseif($support['status']==='reviewed_objective'){
                $available=true;$selected=($agenda[$map['field']]??null)===$map['value'];
                // General executive objectives are reported separately by
                // the existing direct-action and institutional journals.
                $state='see_programme_journals';
            }
            if($available&&$family!==null)$groups[$family][]=$id;
            $measures[]=['measure_id'=>$id,'domain'=>$m['domain'],'proposal'=>$m['proposal'],'sources'=>$m['sources'],'support'=>$support['status'],
                'available_in_world'=>$available,'selected'=>$selected,'evidence_status'=>$state,'records'=>$records,'specific_outcome'=>$outcome,'target_delivery_certified'=>false];
        }
        return ['version'=>'wp114-managed-candidate-progress-v1','name'=>$person['name'],'kind'=>$person['kind'],'measures'=>$measures,
            'conflicting_selection_groups'=>array_filter($groups,fn($ids)=>count($ids)>1),'automatic_import'=>false,'exhaustive'=>false];
    }
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
                'semantic_fulfilment_verified'=>false,
                'automatic_execution_support'=>PresidentialProgramme::measureSupport($id)['status'],
                'execution_mapping'=>PresidentialProgramme::measureSupport($id)['mapping']];
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
            'historical_programme_curated'=>false,'historical_programme_partial'=>$person['kind']==='real'&&$person['programme']!==[],'semantic_fulfilment_verified'=>false,'score_rules_changed'=>false];
    }
}
