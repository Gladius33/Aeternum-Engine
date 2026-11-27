<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** End a national control period, not the common world's clock.
 * The next certified officeholder is not modelled by this slice. */
final class UniverseContinuity
{
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
            if($slot['account_id']!==null||$successor===null||$successor['invested_on']!==$slot['closed_on']
                ||$campaign['start_day']!==$slot['closed_on']||$campaign['day']!==$day
                ||$day!==(new \DateTimeImmutable($slot['closed_on']))->modify('+1 day')->format('Y-m-d'))
                throw new \DomainException('Uncertified reopening');
            $q=$db->prepare("UPDATE wp_universe_slots SET closed_on=NULL,diplomacy_programme='balanced',domestic_programme='manual',last_human_domestic_day=NULL,last_human_diplomacy_day=NULL WHERE world_id=? AND country_code='FR'");
            $q->execute([$world]);return;
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
