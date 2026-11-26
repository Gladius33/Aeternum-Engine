<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/AlphaWorlds.php';

/** Scenario ownership is not a grant of legal office. No constitutional signing, enactment or AI privileges. */
final class AlphaPrincipal implements InstitutionalActorAuthorizer,EconomicPolicyPrincipal
{
    public function __construct(private readonly AlphaSession $session,private readonly string $world,
        private readonly bool $scheduler=false,private readonly array $countries=['FR'],private readonly bool $electoral=false,private readonly bool $revision=false,private readonly bool $legislation=false) {}
    public function actorId():string {return $this->scheduler?'alpha-clock-'.$this->world:$this->session->id;}
    public function allows(string $world,string $actor,string $ability):bool
    {
        if ($world!==$this->world || ($role=$this->session->role($world))===null) return false;
        if ($this->scheduler) {
            if (in_array($actor,$this->countries,true)) return in_array($ability,['read_institutions','advance_clock'],true)
                && ($ability!=='advance_clock'||$role==='owner');
            if ($actor!==$this->actorId()) return false;
            return in_array($ability,['world.clock.read','organizations.read','diplomacy.read','economy.read'],true)
                || ($role==='owner' && in_array($ability,['world.clock.advance','organizations.clock','diplomacy.clock','economy.clock'],true));
        }
        if ($actor!=='FR' && in_array($actor,$this->countries,true)) return $ability==='read_institutions';
        if ($actor==='FR') {
            if($this->legislation&&$role==='owner'&&$ability==='legislation_draft')return true;
            if($this->revision&&$role==='owner'&&in_array($ability,['revision_draft','request_dissolution','request_cabinet_appointment'],true))return true;
            if($this->electoral&&$role==='owner'&&$ability==='electoral_coalition')return true;
            return in_array($ability,['read','read_institutions'],true)
                || ($role==='owner' && in_array($ability,['create_fictional_person','initialize_scenario'],true));
        }
        if ($actor===$this->actorId()) return in_array($ability,['diplomacy.read','organizations.read','economy.read'],true);
        return $role==='owner' && $actor==='state_fr' && in_array($ability,['diplomacy.propose','diplomacy.respond','organizations.eu_intent'],true);
    }
    public function roleFor(string $world,string $country,string $action):string {
        if($this->legislation&&!$this->scheduler&&$country==='FR'&&$action==='legislation_draft'&&$this->allows($world,$country,$action))return 'scenario_bill_author';
        if($this->revision&&!$this->scheduler&&$country==='FR'&&$action==='revision_draft'&&$this->allows($world,$country,$action))return 'scenario_revision_author';
        if($this->revision&&!$this->scheduler&&$country==='FR'&&in_array($action,['request_dissolution','request_cabinet_appointment'],true)&&$this->allows($world,$country,$action))return 'scenario_president_requester';
        if($this->electoral&&!$this->scheduler&&$country==='FR'&&$action==='electoral_coalition'&&$this->allows($world,$country,$action))return 'scenario_negotiator';
        throw new \DomainException('No legal office delegated');
    }
    public function legalApproval(string $world,string $state,string $instrument):bool{return false;}
}

final class AlphaDomesticPrincipal implements PersistentDomesticPolicyAuthority
{
    public function __construct(private readonly AlphaSession $session,private readonly string $world,
        private readonly \PDO $db,private readonly bool $scheduler=false) {}
    public function actorId():string{return $this->scheduler?'alpha-clock-'.$this->world:$this->session->id;}
    public function allows(string $world,string $ability):bool {
        if ($world!==$this->world || ($role=$this->session->role($world))===null) return false;
        return $ability==='domestic.read' || ($role==='owner' && in_array($ability,$this->scheduler?['domestic.clock']:['domestic.propose','domestic.crisis','domestic.corporation'],true));
    }
    public function enactedInstrument(string $world,string $policy):?string{return null;}
    public function clockDay(string $world):string {
        if (!$this->scheduler || !$this->allows($world,'domestic.clock')) throw new \DomainException('Clock denied');
        $q=$this->db->prepare('SELECT pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);
        $day=$q->fetchColumn();if (!is_string($day)) throw new \DomainException('No certified pending day');return $day;
    }
}
