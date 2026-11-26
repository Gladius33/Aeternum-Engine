<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/AlphaWorlds.php';

/** Offline audit authority, never mounted in HTTP. Read grants only. */
final class ReleaseAuditPrincipal implements InstitutionalActorAuthorizer,EconomicPolicyPrincipal
{
    public function __construct(private readonly string $world){}
    public function actorId():string{return 'local-release-audit';}
    public function allows(string $world,string $actor,string $ability):bool
    {return $world===$this->world&&(($actor===$this->actorId()&&in_array($ability,['diplomacy.read','world.clock.read','organizations.read','economy.read'],true))||($ability==='read_institutions'&&preg_match('/^[A-Z]{2}$/D',$actor)===1));}
    public function roleFor(string $world,string $country,string $action):string{throw new \DomainException('Audit cannot decide');}
    public function legalApproval(string $world,string $state,string $instrument):bool{return false;}
}
final class ReleaseAuditDomestic implements PersistentDomesticPolicyAuthority
{
    public function __construct(private readonly string $world){}
    public function actorId():string{return 'local-release-audit';}
    public function allows(string $world,string $ability):bool{return $world===$this->world&&$ability==='domestic.read';}
    public function enactedInstrument(string $world,string $policy):?string{return null;}
    public function clockDay(string $world):string{throw new \DomainException('Audit cannot advance');}
}
/** A consistent read-only snapshot of one ranked campaign. No repairs,
 * no password/session access, no mutation and no public endpoint. */
final class ReleaseAudit
{
    public static function inspect(\PDO $db,string $world):array
    {
        if(!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$world)||$db->inTransaction())throw new \InvalidArgumentException('Invalid audit scope');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT w.scenario,w.reference_bundle_sha,c.* FROM wp_alpha_worlds w JOIN wp_world_clock c ON c.world_id=w.world_id WHERE w.world_id=?');
            $q->execute([$world]);$clock=$q->fetch(\PDO::FETCH_ASSOC);
            if($clock===false)throw new \DomainException('Unknown audit world');
            if($clock['pending_day']!==null)throw new \DomainException('Pending checkpoint; recover before qualification');
            if($clock['scenario']!==CampaignScore::SCENARIO||(int)$clock['score_enabled']!==1)throw new \DomainException('World not in ranked alpha scope');
            $bundle=(new PdoGlobalReferenceBundles($db))->load($clock['reference_bundle_sha']);
            $scenario=new AlphaScenario($clock['scenario'],$bundle,$clock['reference_bundle_sha']);
            $principal=new ReleaseAuditPrincipal($world);
            $ds=new PdoDomesticPolicyStore($db,new ReleaseAuditDomestic($world));
            $is=new PdoInstitutionalStore($db,$principal);
            $dom=$ds->read($world);
            $dip=(new PdoDiplomaticStore($db,$scenario->actors,$principal))->read($world);
            $op=(new PdoPublicOpinionStore($db,$principal,$ds,$is))->read($world);
            $org=(new PdoOrganizationStore($db,$scenario->actors,$scenario->charters,$principal))->read($world);
            $econ=(new PdoEconomicStore($db,$scenario->actors,$scenario->references,$principal))->read($world);
            $date=$clock['committed_day'];
            foreach([$dom,$dip,$op,$org,$econ] as $r)if($r['state']['day']!==$date)throw new \DomainException('Audit source day mismatch');
            $q=$db->prepare('SELECT country_code FROM wp_world_clock_countries WHERE world_id=? ORDER BY country_code');$q->execute([$world]);
            if($q->fetchAll(\PDO::FETCH_COLUMN)!==$scenario->countries)throw new \DomainException('Audit country roster mismatch');
            foreach($scenario->countries as $country)if($is->read($world,$country)['state']['day']!==$date)throw new \DomainException('Audit country day mismatch');
            $saved=(new PdoCampaignScores($db))->read($world);
            if($saved===null||$saved['world_advancing'])throw new \DomainException('Missing committed score');
            $expected=CampaignScore::report($dom,$dip,$op);
            // Same-day proposals may legitimately change source snapshot hashes
            // after publication. The committed daily rows and opinion cannot.
            $q=$db->prepare('SELECT assumptions_json FROM wp_domestic_states WHERE world_id=?');$q->execute([$world]);$cfg=json_decode($q->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
            $cohort=CampaignScore::cohort($clock['reference_bundle_sha'],$cfg,$dip['state']);
            if(!hash_equals($saved['cohort_sha'],$cohort)||$saved['source_hashes']['opinion']!==trim($op['etag'],'"'))
                throw new \DomainException('Audit score provenance mismatch');
            if($saved['elapsed_office_days']>0){
                $last=$op['state']['history'][array_key_last($op['state']['history'])]['source'];
                if($saved['source_hashes']['domestic']!==$last['domestic_sha'])throw new \DomainException('Audit historical domestic provenance mismatch');
            }
            unset($expected['source_hashes']);$actual=array_intersect_key($saved,$expected);
            if($actual!==$expected)throw new \DomainException('Recomputed score differs');
            $report=['world_id'=>$world,'day'=>$date,'score'=>$saved['score'],'revision'=>$saved['clock_revision'],
                'countries_checked'=>count($scenario->countries),'domains_checked'=>['domestic','diplomacy','opinion','organizations','economy','institutions'],'cohort_sha'=>$cohort,'status'=>'verified_ranked_alpha_score',
                'official_ranking_eligible'=>false,'scope'=>'six_clock_domains_and_score_not_full_security_audit'];
            $db->commit();return $report;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
