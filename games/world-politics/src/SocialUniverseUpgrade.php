<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PdoDomesticPolicyStore.php';
require_once __DIR__.'/InstitutionalSimulation.php';
require_once __DIR__.'/WorldMutationFence.php';
require_once __DIR__.'/UniverseScore.php';

/** Explicit offline maintenance: preserve all past days and legal histories,
 * retain prior hashes in journals, switch only on the next simulated day.
 * No automatic destructive rewrite at login or at server startup. */
final class SocialUniverseUpgrade
{
    public const VERSION='wp114k-social-upgrade-v1';
    private static function json(array $v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    public static function prepare(array $cfg,array $dom,string $domSha,string $assumptionsSha,array $inst,string $instSha):array
    {
        $world=$dom['world_id'];$day=$dom['day'];
        $old=PdoDomesticPolicyStore::build($world,$cfg);
        if(!hash_equals($old->assumptionsHash(),$assumptionsSha))throw new \DomainException('Old assumption digest mismatch');
        $old->restore($dom,$domSha);
        $legal=InstitutionalSimulation::restore($inst);
        if($inst['world_id']!==$world||$inst['country_code']!=='FR'||$inst['day']!==$day||!hash_equals($legal->hash(),$instSha))throw new \DomainException('Old legal source mismatch');
        $pinned=isset($cfg['social_economy_from']);
        if($pinned){
            if(!isset($inst['legislation']['social_programme']))throw new \DomainException('Inconsistent social model pin');
            if(array_diff_key(PrimaryManifestoPolicy::definitions()+SocialProgramme::target2027(),$cfg['catalog'])===[])return ['changed'=>false,'day'=>$day];
        }
        if(!isset($cfg['finance_autonomy'],$cfg['population_model'],$cfg['services_model'],$cfg['macro_baseline'],$inst['legislation']['national_service']))throw new \DomainException('Unsupported legacy world');
        $cfg['catalog']+=SocialProgramme::policies($cfg['macro_baseline']);
        $effective=(new \DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
        if(!$pinned)$cfg['social_economy_from']=$effective;
        $next=PdoDomesticPolicyStore::build($world,$cfg);
        // Rebuild in canonical field order; the new assumption hash is explicit.
        $v=$next->snapshot();
        foreach($v as $k=>$value)if(array_key_exists($k,$dom)&&$k!=='assumptions_sha')$v[$k]=$dom[$k];
        $hash=hash('sha256',json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
        $next->restore($v,$hash);
        if($next->snapshot()['days']!==$dom['days'])throw new \DomainException('Historical rows would change');
        $inst['legislation']['social_programme']=SocialProgramme::VERSION;
        $newInst=InstitutionalSimulation::restore($inst);
        return ['changed'=>true,'day'=>$day,'effective_on'=>$effective,'config'=>$cfg,
            'domestic'=>$v,'domestic_sha'=>$hash,'assumptions_sha'=>$next->assumptionsHash(),
            'institutional'=>$inst,'institutional_sha'=>$newInst->hash()];
    }
    public static function run(\PDO $db,int $no,bool $apply=false):array
    {
        if($no<1||$db->inTransaction())throw new \InvalidArgumentException('Independent maintenance transaction required');
        $db->beginTransaction();
        try{
            $q=$db->prepare('SELECT world_id FROM wp_universes WHERE universe_no=?');$q->execute([$no]);$w=$q->fetchColumn();
            if(!is_string($w))throw new \DomainException('Unknown universe');
            $f=WorldMutationFence::lockCoordinator($db,$w);
            $q=$db->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=? FOR UPDATE');$q->execute([$w]);$c=$q->fetch(\PDO::FETCH_ASSOC);
            if($f['state']!=='idle'||$c===false||$c['pending_day']!==null)throw new \DomainException('World clock must be idle');
            $q=$db->prepare('SELECT * FROM wp_domestic_states WHERE world_id=? FOR UPDATE');$q->execute([$w]);$d=$q->fetch(\PDO::FETCH_ASSOC);
            $q=$db->prepare("SELECT * FROM wp_institutional_states WHERE world_id=? AND country_code='FR' FOR UPDATE");$q->execute([$w]);$i=$q->fetch(\PDO::FETCH_ASSOC);
            if($d===false||$i===false)throw new \DomainException('Missing world domains');
            $dom=json_decode($d['state_json'],true,64,JSON_THROW_ON_ERROR);
            if($dom['day']!==$c['committed_day'])throw new \DomainException('Uncommitted domestic source');
            $r=self::prepare(json_decode($d['assumptions_json'],true,64,JSON_THROW_ON_ERROR),$dom,$d['state_sha'],$d['assumptions_sha'],json_decode($i['state_json'],true,64,JSON_THROW_ON_ERROR),$i['state_sha']);
            $report=['version'=>self::VERSION,'universe_no'=>$no,'day'=>$r['day'],'changed'=>$r['changed'],'applied'=>false];
            if(!$r['changed']){$db->rollBack();return $report;}
            // Pending proofs were signed under the prior assumptions; do
            // not reinterpret them or silently replace historical evidence.
            $q=$db->prepare('SELECT policy_id FROM wp_domestic_enactments WHERE world_id=?');$q->execute([$w]);
            foreach($q->fetchAll(\PDO::FETCH_COLUMN) as $id)if(($dom['policies'][$id]['authorized_on']??null)===null)throw new \DomainException('Pending legal proof must be resolved first');
            $report+=['effective_on'=>$r['effective_on'],'old_assumptions_sha'=>$d['assumptions_sha'],'new_assumptions_sha'=>$r['assumptions_sha'],
                'old_domestic_sha'=>$d['state_sha'],'new_domestic_sha'=>$r['domestic_sha'],'old_institutional_sha'=>$i['state_sha'],'new_institutional_sha'=>$r['institutional_sha']];
            // Preserve closed rankings. Active control keeps its tenure,
            // score and opening baseline, but is reclassified into a
            // transition-specific cohort (old rules + new config + switch day).
            $q=$db->prepare("SELECT cohort_sha,day FROM wp_universe_income_checkpoints WHERE world_id=? AND country_code='FR' FOR UPDATE");
            $q->execute([$w]);$checkpoint=$q->fetch(\PDO::FETCH_ASSOC);
            if($checkpoint!==false){
                if($checkpoint['day']!==$r['day'])throw new \DomainException('Stale score checkpoint');
                $new=CampaignScore::digest(['version'=>self::VERSION,'prior_cohort'=>$checkpoint['cohort_sha'],
                    'new_config'=>CampaignScore::digest($r['config']),'effective_on'=>$r['effective_on']]);
                $report['old_score_cohort']=$checkpoint['cohort_sha'];$report['new_score_cohort']=$new;
            }
            if(!$apply){$db->rollBack();return $report;}
            $dr=(int)$d['revision']+1;$ir=(int)$i['revision']+1;
            $q=$db->prepare('UPDATE wp_domestic_states SET assumptions_json=?,assumptions_sha=?,state_json=?,state_sha=?,revision=? WHERE world_id=?');
            $q->execute([self::json($r['config']),$r['assumptions_sha'],self::json($r['domestic']),$r['domestic_sha'],$dr,$w]);
            $q=$db->prepare("UPDATE wp_institutional_states SET state_json=?,state_sha=?,revision=? WHERE world_id=? AND country_code='FR'");
            $q->execute([self::json($r['institutional']),$r['institutional_sha'],$ir,$w]);
            if($checkpoint!==false){
                $q=$db->prepare("UPDATE wp_universe_period_scores s JOIN wp_universe_control_periods p USING(world_id,country_code,generation)
                    SET s.cohort_sha=? WHERE s.world_id=? AND s.country_code='FR' AND p.left_on IS NULL AND s.cohort_sha=?");
                $q->execute([$report['new_score_cohort'],$w,$report['old_score_cohort']]);
                $q=$db->prepare("UPDATE wp_universe_income_checkpoints SET cohort_sha=? WHERE world_id=? AND country_code='FR'");
                $q->execute([$report['new_score_cohort'],$w]);
            }
            $report['applied']=true;$json=self::json($report);$request=self::json(['operation'=>self::VERSION,'day'=>$r['day'],'world_id'=>$w]);$sha=hash('sha256',$request);
            $q=$db->prepare("INSERT INTO wp_domestic_journal(world_id,revision,principal_id,operation,request_sha,request_json,before_sha,after_sha,result_json) VALUES(?,?,'social-maintenance','model_upgrade',?,?,?,?,?)");
            $q->execute([$w,$dr,$sha,$request,$d['state_sha'],$r['domestic_sha'],$json]);
            $q=$db->prepare("INSERT INTO wp_institutional_journal(world_id,country_code,revision,actor_id,actor_role,operation,request_sha,before_sha,after_sha,result_json) VALUES(?,'FR',?,'social-maintenance','maintenance','model_upgrade',?,?,?,?)");
            $q->execute([$w,$ir,$sha,$i['state_sha'],$r['institutional_sha'],$json]);
            $db->commit();return $report;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
