<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PresidentialProgramme.php';

/** DB-derived fallback choices. Accepted human diplomacy commands suppress
 * replacement diplomacy for the next tick, not login or polling. */
final class UniverseAutonomy
{
    private static function managed(\PDO $db,string $world):bool
    {
        try{$q=$db->prepare('SELECT 1 FROM wp_universes WHERE world_id=?');$q->execute([$world]);return $q->fetchColumn()!==false;}
        catch(\PDOException $e){if($e->getCode()==='42S02'&&(int)($e->errorInfo[1]??0)===1146)return false;throw $e;}
    }
    public static function markDiplomacy(\PDO $db,string $world,string $actor,string $day):void
    {
        if(!preg_match('/^acct-[a-f0-9]{32}$/D',$actor)||!self::managed($db,$world))return;
        if(!$db->inTransaction())throw new \LogicException('Diplomacy mark requires command transaction');
        $q=$db->prepare('UPDATE wp_universe_slots SET last_human_diplomacy_day=? WHERE world_id=? AND account_id=?');
        $q->execute([$day,$world,$actor]);
    }
    public static function markDomestic(\PDO $db,string $world,string $actor,string $day):void
    {
        if(!preg_match('/^acct-[a-f0-9]{32}$/D',$actor)||!self::managed($db,$world))return;
        if(!$db->inTransaction())throw new \LogicException('Domestic mark requires command transaction');
        $q=$db->prepare("UPDATE wp_universe_slots SET last_human_domestic_day=? WHERE world_id=? AND country_code='FR' AND account_id=? AND closed_on IS NULL");
        $q->execute([$day,$world,$actor]);
    }
    /** Fenced, opt-in proposal only; never enactment or legal signature. */
    public static function proposeDomestic(\PDO $db,DomesticPolicySimulation $sim,string $world,string $actor,string $target):void
    {
        if(!self::managed($db,$world))return;
        if(!$db->inTransaction()||$actor!=='alpha-clock-'.$world)throw new \LogicException('Managed clock required');
        $s=$sim->snapshot();$day=$s['day'];
        if($s['world_id']!==$world||$target!==(new \DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Domestic autonomy scope');
        $q=$db->prepare("SELECT domestic_programme,last_human_domestic_day,closed_on,generation,programme_set_on FROM wp_universe_slots WHERE world_id=? AND country_code='FR' FOR UPDATE");
        $q->execute([$world]);$slot=$q->fetch(\PDO::FETCH_ASSOC);
        if($slot===false)throw new \DomainException('Missing domestic slot');
        if($slot['closed_on']!==null||$slot['last_human_domestic_day']===$day)return;
        if(self::directAid($db,$sim,$world,$day,$slot)||self::directIndustry($db,$sim,$world,$day,$slot))return;
        $next=PresidentialProgramme::nextProposal($slot['domestic_programme'],$day,$s['policies'],isset($s['social_economy_from']));
        if($next===null)return;
        $policy=$next['policy_id'];$effective=$next['effective_day'];
        $authority=new class($world) implements DomesticPolicyAuthority{
            public function __construct(private readonly string $world){}
            public function allows(string $world,string $ability):bool{return $world===$this->world&&$ability==='domestic.propose';}
            public function enactedInstrument(string $world,string $policy):?string{return null;}
            public function clockDay(string $world):string{throw new \DomainException('No clock delegation');}
        };
        $sim->propose($authority,$policy,$effective);
        $q=$db->prepare('INSERT INTO wp_universe_programme_actions(world_id,decision_day,generation,programme,policy_id,effective_day) VALUES(?,?,?,?,?,?)');
        $q->execute([$world,$day,$slot['generation'],$slot['domestic_programme'],$policy,$effective]);
    }
    /** Direct discretionary aid objective is a once-per-mandate/profile
     * decision, not a standing order that overwrites later human choices.
     * The existing aid model preserves prior legal obligations. */
    private static function directAid(\PDO $db,DomesticPolicySimulation $sim,string $world,string $day,array $slot):bool
    {
        $action=PresidentialProgramme::aidAction($slot['domestic_programme']);
        if($action===null)return false;
        $s=$sim->snapshot();
        $q=$db->prepare('SELECT state_json,state_sha FROM wp_opinion_states WHERE world_id=?');$q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);
        if($r===false)throw new \DomainException('Missing mandate source');
        require_once __DIR__.'/PublicOpinion.php';
        $o=PublicOpinion::restore(json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR));$os=$o->snapshot();
        if(!hash_equals($r['state_sha'],$o->hash())||$os['day']!==$day||$os['world_id']!==$world||$os['campaign']['status']!=='active')
            throw new \DomainException('Direct programme mandate mismatch');
        $mandate=$os['campaign']['start_day'];
        $q=$db->prepare('SELECT 1 FROM wp_universe_programme_direct WHERE world_id=? AND mandate_start=? AND programme=? AND action_id=?');
        $q->execute([$world,$mandate,$slot['domestic_programme'],$action]);
        if($q->fetchColumn()!==false)return false;
        $events=$s['ukraine_aid_decisions']??[];
        $last=$events===[]?null:$events[array_key_last($events)];
        $current=$last===null?10000:UkraineAidPolicy::ACTIONS[$last['action']];
        $desired=UkraineAidPolicy::ACTIONS[$action];
        $set=$slot['programme_set_on']??$mandate;
        $result='already_matching';
        if($current!==$desired){
            if($last!==null&&$last['day']>=$set)$result='human_override';
            else{
                $principal=new class($world) implements DomesticPolicyAuthority{
                    public function __construct(private readonly string $w){}
                    public function allows(string $world,string $ability):bool{return $world===$this->w&&$ability==='domestic.corporation';}
                    public function enactedInstrument(string $world,string $policy):?string{return null;}
                    public function clockDay(string $world):string{throw new \DomainException('No clock delegation');}
                };
                $sim->corporationDecision($principal,$action);$result='applied';
            }
        }
        $q=$db->prepare('INSERT INTO wp_universe_programme_direct(world_id,mandate_start,programme,decision_day,generation,action_id,outcome) VALUES(?,?,?,?,?,?,?)');
        $q->execute([$world,$mandate,$slot['domestic_programme'],$day,$slot['generation'],$action,$result]);
        return true;
    }
    private static function mandate(\PDO $db,string $world,string $day):string
    {
        require_once __DIR__.'/PublicOpinion.php';
        $q=$db->prepare('SELECT state_json,state_sha FROM wp_opinion_states WHERE world_id=?');$q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);
        if($r===false)throw new \DomainException('Missing objective mandate');
        $o=PublicOpinion::restore(json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR));$s=$o->snapshot();
        if(!hash_equals($r['state_sha'],$o->hash())||$s['day']!==$day||$s['world_id']!==$world||$s['campaign']['status']!=='active')
            throw new \DomainException('Invalid objective mandate');
        return $s['campaign']['start_day'];
    }
    private static function directIndustry(\PDO $db,DomesticPolicySimulation $sim,string $world,string $day,array $slot):bool
    {
        $a=PresidentialProgramme::agenda($slot['domestic_programme']);
        if(($a['industry']??'none')==='none')return false;
        $mandate=self::mandate($db,$world,$day);$s=$sim->snapshot();
        $q=$db->prepare("SELECT 1 FROM wp_universe_programme_direct WHERE world_id=? AND mandate_start=? AND programme=? AND action_id IN ('start-industrial-pilot','pause-industrial-pilot','resume-industrial-pilot','industry-no-change')");
        $q->execute([$world,$mandate,$slot['domestic_programme']]);if($q->fetchColumn()!==false)return false;
        $mode=$s['days']===[]?'not_started':$s['days'][array_key_last($s['days'])]['industrial']['mode'];
        $action=PresidentialProgramme::industryAction($slot['domestic_programme'],$mode);
        $result='already_matching';
        $events=array_values(array_filter($s['corporation_decisions'],fn($e)=>IndustrialCapital::isAction($e['action'])));
        $last=$events===[]?null:$events[array_key_last($events)];
        if($action!==null){
            if($last!==null&&$last['day']>=($slot['programme_set_on']??$mandate))$result='human_override';
            else{
                $principal=new class($world) implements DomesticPolicyAuthority{
                    public function __construct(private readonly string $w){}
                    public function allows(string $world,string $ability):bool{return $world===$this->w&&$ability==='domestic.corporation';}
                    public function enactedInstrument(string $world,string $policy):?string{return null;}
                    public function clockDay(string $world):string{throw new \DomainException('No clock delegation');}
                };
                $sim->corporationDecision($principal,$action);$result='applied';
            }
        }
        $q=$db->prepare('INSERT INTO wp_universe_programme_direct(world_id,mandate_start,programme,decision_day,generation,action_id,outcome) VALUES(?,?,?,?,?,?,?)');
        $q->execute([$world,$mandate,$slot['domestic_programme'],$day,$slot['generation'],$action??'industry-no-change',$result]);
        return true;
    }
    /** A single negotiated amendment request for the explicitly fictional
     * bilateral civil commitment; no unilateral debt erasure. */
    public static function commitmentObjective(\PDO $db,DiplomaticSimulation $sim,string $world,string $actor,string $target):?array
    {
        if(!self::managed($db,$world))return null;
        if(!$db->inTransaction()||$actor!=='alpha-clock-'.$world)throw new \LogicException('Managed diplomatic clock required');
        $s=$sim->snapshot();$day=$s['day'];
        if($s['world_id']!==$world||$target!==(new \DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Commitment scope');
        $q=$db->prepare("SELECT domestic_programme,closed_on,last_human_diplomacy_day,programme_set_on FROM wp_universe_slots WHERE world_id=? AND country_code='FR' FOR UPDATE");
        $q->execute([$world]);$slot=$q->fetch(\PDO::FETCH_ASSOC);
        if($slot===false||$slot['closed_on']!==null||$slot['last_human_diplomacy_day']===$day)return null;
        $action=PresidentialProgramme::commitmentAction($slot['domestic_programme']);if($action===null)return null;
        $mandate=self::mandate($db,$world,$day);$id='ua-civil-fixture';$ledger=$s['commitments'];
        $command=PresidentialProgramme::objectiveId($world,$mandate,'commitment',$id);
        if(isset($ledger['commands'][$command]))return null;
        // Do not supersede a more recent explicit human decision.
        foreach($ledger['commands'] as $c)if($c['id']===$id&&$c['day']>=($slot['programme_set_on']??$mandate))return null;
        $r=$ledger['records'][$id];
        if($r['pending']!==null||!in_array('future',array_column($r['installments'],'status'),true))return null;
        $principal=new class($world) implements DiplomaticPrincipal{
            public function __construct(private readonly string $w){}
            public function actorId():string{return 'fictional-programme-commitment';}
            public function allows(string $world,string $actor,string $ability):bool{return $world===$this->w&&$actor==='state_fr'&&$ability==='diplomacy.commitment';}
        };
        return ['id'=>$command,'result'=>$sim->commitmentDecision($principal,$id,$action,$command)];
    }
    /** Use only durable, matching proposals from the current mandate and
     * current selected executable profile. Called by the fenced national
     * clock before the ordinary independent institutional progression. */
    public static function advanceInstitutions(\PDO $db,InstitutionalSimulation $sim,string $world,string $actor,string $target):?array
    {
        if(!self::managed($db,$world))return null;
        if(!$db->inTransaction()||$actor!=='alpha-clock-'.$world)throw new \LogicException('Managed clock required');
        $s=$sim->snapshot();$day=$s['day'];
        if($s['world_id']!==$world||$s['country_code']!=='FR'||$target!==(new \DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Institution programme scope');
        $q=$db->prepare("SELECT domestic_programme,last_human_domestic_day,closed_on FROM wp_universe_slots WHERE world_id=? AND country_code='FR' FOR UPDATE");
        $q->execute([$world]);$slot=$q->fetch(\PDO::FETCH_ASSOC);
        if($slot===false)throw new \DomainException('Missing French slot');
        if($slot['closed_on']!==null||$slot['last_human_domestic_day']===$day||!PresidentialProgramme::executable($slot['domestic_programme']))return null;
        $q=$db->prepare('SELECT state_json,state_sha FROM wp_opinion_states WHERE world_id=?');$q->execute([$world]);$op=$q->fetch(\PDO::FETCH_ASSOC);
        if($op===false)throw new \DomainException('Missing mandate source');
        $o=json_decode($op['state_json'],true,64,JSON_THROW_ON_ERROR);
        if(!hash_equals($op['state_sha'],hash('sha256',json_encode($o,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)))
            ||$o['world_id']!==$world||$o['day']!==$day||$o['campaign']['status']!=='active')throw new \DomainException('Programme mandate mismatch');
        $objective=PresidentialProgramme::institutionalObjective($sim,$slot['domestic_programme'],$o['campaign']['start_day']);
        if($objective!==null)return $objective;
        $q=$db->prepare('SELECT state_json,state_sha FROM wp_domestic_states WHERE world_id=?');$q->execute([$world]);$dom=$q->fetch(\PDO::FETCH_ASSOC);
        if($dom===false)throw new \DomainException('Missing proposal source');
        $d=json_decode($dom['state_json'],true,64,JSON_THROW_ON_ERROR);
        if(!hash_equals($dom['state_sha'],hash('sha256',json_encode($d,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)))||$d['world_id']!==$world||$d['day']!==$day)
            throw new \DomainException('Programme proposal source mismatch');
        $q=$db->prepare('SELECT decision_day,programme,policy_id,effective_day FROM wp_universe_programme_actions WHERE world_id=? AND decision_day>=? AND decision_day<? AND effective_day>? ORDER BY decision_day LIMIT 128');
        $q->execute([$world,$o['campaign']['start_day'],$day,$day]);
        foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $a){
            if(!PresidentialProgramme::executable($a['programme'])||!PresidentialProgramme::permits($slot['domestic_programme'],$a['policy_id']))continue;
            $proposal=$d['policies'][$a['policy_id']]??null;
            if($proposal===null)throw new \DomainException('Missing programme proposal');
            $result=PresidentialProgramme::advanceOne($sim,$a,$proposal);
            if($result!==null)return $result;
        }
        return null;
    }
    public static function policies(\PDO $db,string $world,string $committedDay):?array
    {
        if(!self::managed($db,$world))return null;
        if(!$db->inTransaction())throw new \LogicException('Clock policy requires fenced transaction');
        $q=$db->prepare('SELECT s.country_code,s.account_id,s.diplomacy_programme,s.last_human_diplomacy_day,s.closed_on,u.roster_version FROM wp_universe_slots s JOIN wp_universes u USING(world_id) WHERE s.world_id=? ORDER BY s.country_code');
        $q->execute([$world]);$out=[];
        foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $r){
            if(!in_array($r['diplomacy_programme'],['cooperative','balanced','cautious'],true))throw new \DomainException('Invalid programme');
            $out['state_'.strtolower($r['country_code'])]=['programme'=>$r['diplomacy_programme'],
                'succession_pending'=>$r['closed_on']!==null,'human_action_this_day'=>$r['account_id']!==null&&$r['last_human_diplomacy_day']===$committedDay,
                ...(in_array($r['roster_version'],['wp111-diplomatic-roster-v2','wp119-identity-roster-v1'],true)?['initiative_enabled'=>true]:[])];
        }
        if($out===[])throw new \DomainException('Managed universe without slots');
        return $out;
    }
}
