<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/UniverseIdentityReference.php';
require_once __DIR__.'/PoliticalRoster.php';

/** End a national control period, not the common world's clock.
 * The next certified officeholder is not modelled by this slice. */
final class UniverseContinuity
{
    /** New identity-roster worlds only: a national slot is the current
     * executive control period, not lifetime ownership of a country. */
    public static function publishComparative(\PDO $db,string $world,string $country,string $beforeDay,string $day):void{
        if(!$db->inTransaction()||$country==='FR'||!in_array($country,ComparativeGovernment::COUNTRIES,true)||$day!==(new \DateTimeImmutable($beforeDay))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Invalid comparative publication scope');
        $q=$db->prepare('SELECT roster_version FROM wp_universes WHERE world_id=?');$q->execute([$world]);
        if(!UniverseIdentityReference::isDocumentary((string)$q->fetchColumn()))return;
        $q=$db->prepare('SELECT state_json,state_sha FROM wp_institutional_states WHERE world_id=? AND country_code=? FOR UPDATE');$q->execute([$world,$country]);$r=$q->fetch(\PDO::FETCH_ASSOC);
        if($r===false)throw new \DomainException('Missing comparative publication source');
        $inst=InstitutionalSimulation::restore(json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR));$s=$inst->snapshot();$g=$s['accountability'];
        if(!hash_equals($r['state_sha'],$inst->hash())||$s['world_id']!==$world||$s['country_code']!==$country||$s['day']!==$day)throw new \DomainException('Invalid comparative publication source');
        $old=new ComparativeGovernment($world,$country,$g['start_day'],$g['initial_incumbent'],$g['seats']);
        foreach($g['history'] as $event){
            $proof=$event['evidence'];$on=substr($proof['at']??$proof['day'],0,10);
            if($on>$beforeDay)break;
            $old->advance($on);
            if($event['action']==='certified_electoral_formation')$old->installCertifiedFormation($proof,$event['formation'],$event['formation_poll']??null);
            elseif($event['action']==='us_presidential_inauguration')$old->installUsPresidential($proof);
            else $old->apply($event['action'],$event['role'],$proof);
        }
        $old->advance($beforeDay);$prior=$old->snapshot();
        $vacant=static fn(array $v):bool=>in_array($v['status'],['caretaker','election_pending','removed_successor_pending'],true);
        $q=$db->prepare('SELECT account_id,generation,closed_on FROM wp_universe_slots WHERE world_id=? AND country_code=? FOR UPDATE');$q->execute([$world,$country]);$slot=$q->fetch(\PDO::FETCH_ASSOC);
        if($slot===false)throw new \DomainException('Missing comparative slot');
        $wasVacant=$vacant($prior);$nowVacant=$vacant($g);
        $changed=$prior['incumbent']!==$g['incumbent']||$wasVacant!==$nowVacant;
        // Handle a previously certified closure at the current committed day
        // as well as ordinary election formation during the pending tick.
        if(!$changed&&($slot['closed_on']!==null)===$nowVacant)return;
        if($slot['account_id']!==null&&($changed||$nowVacant)){
            $q=$db->prepare('UPDATE wp_universe_control_periods SET left_on=? WHERE world_id=? AND country_code=? AND generation=? AND left_on IS NULL');$q->execute([$day,$world,$country,$slot['generation']]);
            if($q->rowCount()!==1)throw new \DomainException('Missing comparative control period');
            $q=$db->prepare('DELETE FROM wp_alpha_members WHERE world_id=? AND account_id=?');$q->execute([$world,$slot['account_id']]);
        }
        $closed=$nowVacant?($slot['closed_on']??$day):null;
        $q=$db->prepare("UPDATE wp_universe_slots SET account_id=NULL,closed_on=?,diplomacy_programme='balanced',last_human_diplomacy_day=NULL,last_human_domestic_day=NULL,programme_set_on=NULL WHERE world_id=? AND country_code=?");
        $q->execute([$closed,$world,$country]);
    }
    public static function publish(\PDO $db,string $world,?array $campaign,string $day):void
    {
        if(!$db->inTransaction()||$campaign===null||$campaign['world_id']!==$world)throw new \DomainException('Invalid managed campaign');
        $q=$db->prepare('SELECT 1 FROM wp_universes WHERE world_id=?');$q->execute([$world]);
        if($q->fetchColumn()===false)throw new \DomainException('Managed continuity on a private world');
        if($campaign['status']!=='game_over'){
            $q=$db->prepare("SELECT account_id,closed_on FROM wp_universe_slots WHERE world_id=? AND country_code='FR' FOR UPDATE");
            $q->execute([$world]);$slot=$q->fetch(\PDO::FETCH_ASSOC);
            if($slot===false)throw new \DomainException('Missing French slot');
            if($slot['closed_on']===null)return;
            $successor=$campaign['managed_successor']??null;
            $invested=$successor['invested_on']??null;$vacancy=$successor['vacancy_since']??null;
            $validStart=$vacancy===null?$invested===$slot['closed_on']:($vacancy===$slot['closed_on']
                &&$invested===(new \DateTimeImmutable($vacancy))->modify('+36 days')->format('Y-m-d'));
            if($slot['account_id']!==null||$successor===null||!$validStart
                ||$campaign['start_day']!==$invested||$campaign['day']!==$day
                ||$day!==(new \DateTimeImmutable($invested))->modify('+1 day')->format('Y-m-d'))
                throw new \DomainException('Uncertified reopening');
            $q=$db->prepare('SELECT roster_version FROM wp_universes WHERE world_id=?');$q->execute([$world]);
            $version=(string)$q->fetchColumn();$programme='governance';$programmeDay=null;
            if(UniverseIdentityReference::isBound($version)){
                $q=$db->prepare("SELECT state_json,state_sha,revision FROM wp_world_rosters WHERE world_id=? AND country_code='FR' FOR UPDATE");$q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);
                if($r===false)throw new \DomainException('Missing successor roster');
                $state=json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR);$roster=PoliticalRoster::fromSnapshot($state);
                if(!hash_equals($r['state_sha'],$roster->snapshotHash())||$state['world_id']!==$world||$state['country_code']!=='FR')throw new \DomainException('Invalid successor roster');
                $previous=$state['people']['campaign-president']??throw new \DomainException('Missing incumbent identity');
                $cast=UniverseIdentityReference::frenchChallengers($world,$successor['ordinal']-1,$previous['name']);
                $person=$cast[$successor['elected_as']]??throw new \DomainException('Unmapped electoral alias');
                if($version===UniverseIdentityReference::PROGRAMME_ROSTER){$programme=UniverseIdentityReference::documentedProgramme($person);$programmeDay=$day;}
                $state['people']['campaign-president']=$person;$new=PoliticalRoster::fromSnapshot($state);
                $after=$new->snapshotHash();$revision=(int)$r['revision']+1;
                $json=static fn(array $v):string=>json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                $q=$db->prepare("UPDATE wp_world_rosters SET state_json=?,state_sha=?,revision=? WHERE world_id=? AND country_code='FR'");
                $q->execute([$json($new->snapshot()),$after,$revision,$world]);
                $q=$db->prepare("INSERT INTO wp_world_events(world_id,country_code,revision,actor_id,operation,request_sha,before_sha,after_sha,result_json) VALUES(?,'FR',?,'scenario-election','successor_identity_binding',?,?,?,?)");
                $q->execute([$world,$revision,hash('sha256',$json($successor)),$r['state_sha'],$after,$json(['identity'=>$person,'previous'=>$previous,'successor'=>$successor,'classification'=>'alternate_history_casting'])]);
            }
            $q=$db->prepare("UPDATE wp_universe_slots SET closed_on=NULL,diplomacy_programme='balanced',domestic_programme=?,programme_set_on=?,last_human_domestic_day=NULL,last_human_diplomacy_day=NULL WHERE world_id=? AND country_code='FR'");
            $q->execute([$programme,$programmeDay,$world]);return;
        }
        $end=$campaign['ended_on'];
        if(!is_string($end)||$end>$day)throw new \DomainException('Invalid campaign end');
        $q=$db->prepare("SELECT account_id,generation,closed_on FROM wp_universe_slots WHERE world_id=? AND country_code='FR' FOR UPDATE");
        $q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);
        if($r===false)throw new \DomainException('Missing French slot');
        if($r['closed_on']!==null){
            if($r['closed_on']!==$end||$r['account_id']!==null)throw new \DomainException('Frozen slot mismatch');
            return;
        }
        if($end!==$day)throw new \DomainException('Missing earlier closure');
        if($r['account_id']!==null){
            $q=$db->prepare("UPDATE wp_universe_control_periods SET left_on=? WHERE world_id=? AND country_code='FR' AND generation=? AND left_on IS NULL");
            $q->execute([$end,$world,$r['generation']]);
            if($q->rowCount()!==1)throw new \DomainException('Missing active French period');
            $q=$db->prepare('DELETE FROM wp_alpha_members WHERE world_id=? AND account_id=?');$q->execute([$world,$r['account_id']]);
        }
        $q=$db->prepare("UPDATE wp_universe_slots SET account_id=NULL,closed_on=?,last_human_diplomacy_day=NULL WHERE world_id=? AND country_code='FR'");
        $q->execute([$end,$world]);
    }
}
