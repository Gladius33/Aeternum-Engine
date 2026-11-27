<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

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
    public static function policies(\PDO $db,string $world,string $committedDay):?array
    {
        if(!self::managed($db,$world))return null;
        if(!$db->inTransaction())throw new \LogicException('Clock policy requires fenced transaction');
        $q=$db->prepare('SELECT country_code,account_id,diplomacy_programme,last_human_diplomacy_day,closed_on FROM wp_universe_slots WHERE world_id=? ORDER BY country_code');
        $q->execute([$world]);$out=[];
        foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $r){
            if(!in_array($r['diplomacy_programme'],['cooperative','balanced','cautious'],true))throw new \DomainException('Invalid programme');
            $out['state_'.strtolower($r['country_code'])]=['programme'=>$r['diplomacy_programme'],
                'succession_pending'=>$r['closed_on']!==null,'human_action_this_day'=>$r['account_id']!==null&&$r['last_human_diplomacy_day']===$committedDay];
        }
        if($out===[])throw new \DomainException('Managed universe without slots');
        return $out;
    }
}
