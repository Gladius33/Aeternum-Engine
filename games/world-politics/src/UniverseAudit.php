<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/UniverseScore.php';
require_once __DIR__.'/UniverseLobby.php';

/** Read-only integrity qualification for one managed universe; no secrets,
 * no automatic repair and no assertion of economic calibration. */
final class UniverseAudit
{
    public static function inspect(\PDO $db,int $no):array
    {
        if($no<1||$db->inTransaction())throw new \InvalidArgumentException('Independent audit transaction required');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('SET TRANSACTION READ ONLY');
        $db->beginTransaction();
        try{
            $q=$db->prepare('SELECT u.world_id,u.roster_version,c.committed_day,c.pending_day FROM wp_universes u JOIN wp_world_clock c ON c.world_id=u.world_id WHERE u.universe_no=?');
            $q->execute([$no]);$w=$q->fetch(\PDO::FETCH_ASSOC);
            if($w===false)throw new \DomainException('Unknown universe');
            if($w['pending_day']!==null)throw new \DomainException('Audit requires coherent committed day');
            $world=$w['world_id'];$day=$w['committed_day'];
            $q=$db->prepare('SELECT country_code,account_id,generation,closed_on FROM wp_universe_slots WHERE world_id=? ORDER BY country_code');
            $q->execute([$world]);$slots=$q->fetchAll(\PDO::FETCH_ASSOC);
            if(array_column($slots,'country_code')!==UniverseLobby::roster($w['roster_version']))throw new \DomainException('Unexpected pilot roster');
            $q=$db->prepare('SELECT p.*,s.opening_index_bps,s.latest_index_bps,s.through_day,s.elapsed_control_days,s.gain_bps,s.score FROM wp_universe_control_periods p LEFT JOIN wp_universe_period_scores s USING(world_id,country_code,generation) WHERE p.world_id=? ORDER BY p.country_code,p.generation LIMIT 10001');
            $q->execute([$world]);$periods=$q->fetchAll(\PDO::FETCH_ASSOC);
            if(count($periods)>10000)throw new \DomainException('Audit page limit exceeded');
            $active=[];$countScores=0;
            foreach($periods as $r){
                if($r['joined_on']>$day||($r['left_on']!==null&&($r['left_on']<$r['joined_on']||$r['left_on']>$day)))throw new \DomainException('Invalid control interval');
                if($r['left_on']===null){
                    if(isset($active[$r['country_code']]))throw new \DomainException('Multiple active periods');
                    $active[$r['country_code']]=$r;
                }
                if($r['score']!==null){
                    if($r['country_code']!=='FR')throw new \DomainException('Unqualified foreign income score');
                    UniverseScore::assertPeriod($r+['committed_day'=>$day]);++$countScores;
                }
            }
            $members=[];$q=$db->prepare('SELECT account_id,role FROM wp_alpha_members WHERE world_id=?');
            $q->execute([$world]);foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $m)$members[$m['account_id']]=$m['role'];
            $humans=0;
            foreach($slots as $slot){
                $r=$active[$slot['country_code']]??null;
                if($slot['closed_on']!==null&&($slot['closed_on']>$day||$slot['account_id']!==null))throw new \DomainException('Invalid closed slot');
                if($slot['account_id']===null){if($r!==null)throw new \DomainException('Orphan active period');continue;}
                if($r===null||$r['account_id']!==$slot['account_id']||(int)$r['generation']!==(int)$slot['generation']
                    ||($members[$slot['account_id']]??null)!=='viewer')throw new \DomainException('Slot membership mismatch');
                unset($members[$slot['account_id']]);++$humans;
            }
            if($members!==[])throw new \DomainException('Orphan universe member');
            $q=$db->prepare('SELECT decision_day,programme,policy_id,effective_day FROM wp_universe_programme_actions WHERE world_id=? ORDER BY decision_day LIMIT 10001');
            $q->execute([$world]);$actions=$q->fetchAll(\PDO::FETCH_ASSOC);
            if(count($actions)>10000)throw new \DomainException('Programme audit page limit exceeded');
            foreach($actions as $a)if($a['decision_day']>=$day||!PresidentialProgramme::validAction($a))throw new \DomainException('Invalid programme journal');
            $q=$db->prepare('SELECT mandate_start,programme,decision_day,action_id,outcome FROM wp_universe_programme_direct WHERE world_id=? ORDER BY decision_day LIMIT 10001');
            $q->execute([$world]);$direct=$q->fetchAll(\PDO::FETCH_ASSOC);
            if(count($direct)>10000)throw new \DomainException('Direct programme audit page limit exceeded');
            $q=$db->prepare('SELECT state_json,state_sha FROM wp_domestic_states WHERE world_id=?');$q->execute([$world]);$dom=$q->fetch(\PDO::FETCH_ASSOC);
            if($dom===false||!hash_equals($dom['state_sha'],hash('sha256',$dom['state_json'])))throw new \DomainException('Missing domestic audit source');
            $events=json_decode($dom['state_json'],true,64,JSON_THROW_ON_ERROR)['ukraine_aid_decisions']??[];
            foreach($direct as $r){
                if($r['decision_day']<$r['mandate_start']||$r['decision_day']>=$day||PresidentialProgramme::aidAction($r['programme'])!==$r['action_id']
                    ||!in_array($r['outcome'],['applied','already_matching','human_override'],true))throw new \DomainException('Invalid direct programme decision');
                if($r['outcome']==='applied'&&!in_array(['day'=>$r['decision_day'],'action'=>$r['action_id']],$events,true))throw new \DomainException('Missing direct decision evidence');
            }
            $db->commit();
            return ['universe_no'=>$no,'committed_day'=>$day,'slots'=>count($slots),'human_slots'=>$humans,
                'control_periods'=>count($periods),'recomputed_scores'=>$countScores,'integrity'=>'passed',
                'official_ranking_eligible'=>false,'qualification'=>'read_only_relational_and_arithmetic'];
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
