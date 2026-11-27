<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/AlphaWorlds.php';

/** Server-side clock identity for opt-in career economies, never a player principal. */
final class CareerEconomicClockPrincipal implements InstitutionalActorAuthorizer,EconomicPolicyPrincipal
{
    public function __construct(private readonly \PDO $db,private readonly string $world){}
    public function actorId():string{return 'career-clock-'.$this->world;}
    public function allows(string $world,string $actor,string $ability):bool{
        if($world!==$this->world)return false;
        $q=$this->db->prepare("SELECT 1 FROM wp_alpha_worlds w JOIN wp_career_ledgers l ON l.world_id=w.world_id WHERE w.world_id=? AND w.scenario='wp-career-v1' AND w.reference_bundle_sha IS NOT NULL");
        $q->execute([$world]);if($q->fetchColumn()===false)return false;
        if($actor==='FR')return in_array($ability,['read_institutions','advance_clock'],true);
        return $actor===$this->actorId()&&in_array($ability,['world.clock.read','world.clock.advance','organizations.read','organizations.clock','diplomacy.read','diplomacy.clock','economy.read','economy.clock'],true);
    }
    public function roleFor(string $world,string $country,string $action):string{throw new \DomainException('No executive authority');}
    public function legalApproval(string $world,string $state,string $instrument):bool{return false;}
}
final class CareerEconomicDomesticPrincipal implements PersistentDomesticPolicyAuthority
{
    public function __construct(private readonly \PDO $db,private readonly CareerEconomicClockPrincipal $p){}
    public function actorId():string{return $this->p->actorId();}
    public function allows(string $world,string $ability):bool{return in_array($ability,['domestic.read','domestic.clock'],true)&&$this->p->allows($world,$this->actorId(),'world.clock.advance');}
    public function enactedInstrument(string $world,string $policy):?string{return null;}
    public function clockDay(string $world):string{
        if(!$this->allows($world,'domestic.clock'))throw new \DomainException('Clock denied');
        $q=$this->db->prepare('SELECT pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$r=$q->fetchColumn();
        if(!is_string($r))throw new \DomainException('Missing pending clock day');return $r;
    }
}
/** Created only inside the verified career capability transaction. */
final class CareerEconomicProposalPrincipal implements PersistentDomesticPolicyAuthority
{
    public function __construct(private readonly \PDO $db,private readonly string $world,private readonly string $mandate,private readonly bool $automatic=false){}
    public function actorId():string{return ($this->automatic?'career-auto-':'career-').$this->mandate;}
    public function allows(string $world,string $ability):bool{
        return $this->db->inTransaction()&&$world===$this->world&&in_array($ability,['domestic.read','domestic.propose'],true);
    }
    public function enactedInstrument(string $world,string $policy):?string{return null;}
    public function clockDay(string $world):string{throw new \DomainException('No clock delegation');}
}
/** Server-side role obtained only from a verified active career mandate. */
final class CareerLegislativePrincipal implements InstitutionalActorAuthorizer{
    public function __construct(private readonly \PDO $db,private readonly string $world,private readonly string $mandate,private readonly string $role){}
    public function actorId():string{return 'career-'.$this->mandate;}
    public function allows(string $world,string $actor,string $ability):bool{
        return $this->db->inTransaction()&&$world===$this->world&&$actor==='FR'&&in_array($ability,['read_institutions','submit_bill'],true);
    }
    public function roleFor(string $world,string $country,string $action):string{
        if(!$this->allows($world,$country,$action)||$action!=='submit_bill')throw new \DomainException('No legislative authority');
        return $this->role;
    }
}
final class CareerEconomicWorld
{
    private static function json(array $x):string{return json_encode($x,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    public static function seed(\PDO $db,string $world,string $day,bool $expandedProgramme=false):void{
        CareerInstitutionLedger::check($db->inTransaction()&&$day===CareerScoreContract::START,'economic bootstrap requires opening transaction');
        $put=static function(string $sql,array $params)use($db):void{$q=$db->prepare($sql);$q->execute($params);};
        $bundle=GlobalReferenceBundle::published();$sha=(new PdoGlobalReferenceBundles($db))->install($bundle);
        $scenario=new AlphaScenario(AlphaScenario::INVESTITURE,$bundle,$sha);
        CareerInstitutionLedger::check($scenario->day===$day,'opening date mismatch');
        $roster=new PoliticalRoster($world,'FR',['prime_minister','economy','education']);
        $put('INSERT INTO wp_world_rosters(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)',[$world,'FR',self::json($roster->snapshot()),$roster->snapshotHash()]);
        $inst=new InstitutionalSimulation($world,'FR',$day);
        $inst->enableRevision();
        $put('INSERT INTO wp_institutional_states(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)',[$world,'FR',self::json($inst->snapshot()),$inst->hash()]);
        $dip=new DiplomaticSimulation($world,$day,$bundle->actors);
        $put('INSERT INTO wp_diplomatic_states(world_id,state_json,state_sha,actor_registry_sha,revision) VALUES(?,?,?,?,0)',[$world,self::json($dip->snapshot()),$dip->hash(),$bundle->actors->hash()]);
        $org=new OrganizationSimulation($world,$day,$bundle->actors,[]);$os=$org->snapshot();
        $put('INSERT INTO wp_organization_states(world_id,state_json,state_sha,actors_sha,charters_sha,revision) VALUES(?,?,?,?,?,0)',[$world,self::json($os),$org->hash(),$os['actors_sha'],$os['charters_sha']]);
        $econ=new WorldEconomicSimulation($world,$day,$bundle->actors,$bundle->references);
        $put('INSERT INTO wp_economic_states(world_id,state_json,state_sha,reference_sha,actor_registry_sha,revision) VALUES(?,?,?,?,?,0)',[$world,self::json($econ->snapshot()),$econ->hash(),$bundle->references->hash(),$bundle->actors->hash()]);
        // The opening macro/debt/demography/service/firm inputs are identical to
        // Investiture. Legacy automatic finance and legislative/trade bridges
        // stay off until new mandates can legally authorize their actions.
        $c=$scenario->domestic();
        $c['catalog']+=array_intersect_key(SocialProgramme::policies($c['macro_baseline']),SocialProgramme::target2027());
        if($expandedProgramme){require_once __DIR__.'/FrenchExpandedProgramme.php';$c['catalog']+=array_intersect_key(SocialProgramme::policies($c['macro_baseline']),array_fill_keys(FrenchExpandedProgramme::policyIds(),true));}
        foreach(['finance_autonomy','daily_legal_enabled','trade_feedback_enabled','eu_trade_enabled','eu_trade_v2','eu_trade_v3','eu_trade_v4'] as $flag)$c[$flag]=false;
        $dom=PdoDomesticPolicyStore::build($world,$c);
        $put('INSERT INTO wp_domestic_states(world_id,assumptions_json,assumptions_sha,state_json,state_sha,revision) VALUES(?,?,?,?,?,0)',[$world,self::json($c),$dom->assumptionsHash(),self::json($dom->snapshot()),$dom->hash()]);
        $put("UPDATE wp_alpha_worlds SET reference_bundle_sha=? WHERE world_id=? AND scenario='wp-career-v1'",[$sha,$world]);
        $put('UPDATE wp_world_clock SET domestic_enabled=1 WHERE world_id=?',[$world]);
        $put("INSERT INTO wp_world_clock_countries(world_id,country_code) VALUES(?,'FR')",[$world]);
    }
    public static function readLocked(\PDO $db,string $world,string $day):array{
        CareerInstitutionLedger::check($db->inTransaction(),'economic read requires fence');
        $q=$db->prepare("SELECT reference_bundle_sha FROM wp_alpha_worlds WHERE world_id=? AND scenario='wp-career-v1'");$q->execute([$world]);$sha=$q->fetchColumn();
        CareerInstitutionLedger::check(is_string($sha),'economic profile not enabled');
        $bundle=(new PdoGlobalReferenceBundles($db))->load($sha);$p=new CareerEconomicClockPrincipal($db,$world);
        $dom=(new PdoDomesticPolicyStore($db,new CareerEconomicDomesticPrincipal($db,$p)))->read($world);
        $econ=(new PdoEconomicStore($db,$bundle->actors,$bundle->references,$p))->read($world);
        CareerInstitutionLedger::check($dom['state']['day']===$day&&$econ['state']['day']===$day,'economic publication mismatch');
        return ['world_id'=>$world,'day'=>$day,'reference_bundle_sha'=>$sha,'domestic'=>$dom,'economy'=>$econ,'rankable'=>false];
    }
    /** Operator-only daily tick. Reuses existing recoverable multi-store coordinator. */
    public static function tick(callable $connect,string $world,string $target):array{
        CareerInstitutionLedger::id($world);CareerInstitutionLedger::day($target);
        $db=$connect();$q=$db->prepare("SELECT w.reference_bundle_sha,c.committed_day,c.pending_day FROM wp_alpha_worlds w JOIN wp_world_clock c ON c.world_id=w.world_id JOIN wp_career_ledgers l ON l.world_id=w.world_id WHERE w.world_id=? AND w.scenario='wp-career-v1'");
        $q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);
        CareerInstitutionLedger::check($r!==false&&is_string($r['reference_bundle_sha']),'not a career economic world');
        $bundle=(new PdoGlobalReferenceBundles($connect()))->load($r['reference_bundle_sha']);
        $p=new CareerEconomicClockPrincipal($connect(),$world);
        $dom=new PdoDomesticPolicyStore($connect(),new CareerEconomicDomesticPrincipal($connect(),$p));
        $inst=new PdoInstitutionalStore($connect(),$p);
        $clock=new WorldClockCoordinator($connect(),new PdoOrganizationStore($connect(),$bundle->actors,[],$p),
            new PdoDiplomaticStore($connect(),$bundle->actors,$p),new PdoEconomicStore($connect(),$bundle->actors,$bundle->references,$p),$p,$inst,['FR'],null,$dom,null);
        if($r['pending_day']!==null){CareerInstitutionLedger::check($r['pending_day']===$target,'another day pending');return $clock->resumePending($world)??throw new \DomainException('pending day disappeared');}
        if($r['committed_day']===$target)return $clock->read($world);
        CareerInstitutionLedger::check((new \DateTimeImmutable($r['committed_day']))->modify('+1 day')->format('Y-m-d')===$target,'daily tick required');
        // Expiry settlement is separate from the recoverable multi-store clock.
        // If the eligible roster is insufficient, refuse to progress rather than invent a winner.
        $guard=$db->prepare('SELECT state_json FROM wp_career_ledgers WHERE world_id=?');$guard->execute([$world]);
        $state=json_decode($guard->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
        $mid=$state['active']['president']??null;
        $day=$r['committed_day'];
        $opening=$mid===null&&$day===CareerScoreContract::START;
        $due=$mid!==null&&$state['mandates'][$mid]['expires']===$day;
        $missingAI=!isset($state['careers'][array_key_first(CareerInstitutionLedger::realAutonomousRoster($state))]);
        // Existing worlds acquire NPCs prospectively, never after the candidacy cutoff.
        if($opening||$due||($missingAI&&$mid!==null&&!$state['mandates'][$mid]['interim']&&$day<FrenchElectionCalendar::presidentialPoll($state['mandates'][$mid]['expires'])['candidacy_lock'])){
            $secret=getenv('WP_CAREER_EVIDENCE_KEY');CareerInstitutionLedger::check(is_string($secret)&&strlen($secret)>=32,'election signing key required');
            $store=new PdoCareerInstitutionStore($db,new CareerInstitutionEvidence($secret));
            if($missingAI&&!$due)$store->seedAutonomousCareers($world,$secret);
            if($opening||$due)$store->settleDuePresidency($world,$secret);
        }
        $secret=getenv('WP_CAREER_EVIDENCE_KEY');CareerInstitutionLedger::check(is_string($secret)&&strlen($secret)>=32,'career replay key required');
        $careerStore=new PdoCareerInstitutionStore($db,new CareerInstitutionEvidence($secret));
        $careerStore->settleAutomaticCabinet($world,$secret);
        $careerStore->settleProgrammeProposal($world);
        $current=$clock->read($world);return $clock->advance($world,$target,'career-day-'.$target,$current['etag']);
    }
}
