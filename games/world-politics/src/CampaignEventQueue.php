<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PublicOpinion.php';
require_once __DIR__.'/WorldMutationFence.php';

/** Trusted game-scenario event publisher. Not an HTTP endpoint. */
final class CampaignEventQueue
{
    public function __construct(private readonly \PDO $db) {}
    public function readDay(string $world,string $day):?array
    {
        $q=$this->db->prepare('SELECT event_json,event_sha FROM wp_campaign_events WHERE world_id=? AND event_day=?');
        $q->execute([$world,$day]);$r=$q->fetch(\PDO::FETCH_ASSOC);
        if($r===false)return null;
        $e=self::decode($r);
        if($e['world_id']!==$world||$e['day']!==$day)throw new \UnexpectedValueException('Campaign event scope mismatch');
        return $e;
    }
    private static function decode(array $r):array
    {
        if(!hash_equals($r['event_sha'],hash('sha256',$r['event_json'])))throw new \UnexpectedValueException('Campaign event checksum mismatch');
        $e=json_decode($r['event_json'],true,16,JSON_THROW_ON_ERROR);CampaignLifecycle::validate($e);return $e;
    }
    /** Operator or trusted narrative simulation worker only. Immutable insert. */
    public function publish(array $e):void
    {
        CampaignLifecycle::validate($e);$world=$e['world_id'];
        $this->db->beginTransaction();
        try{
            WorldMutationFence::admit($this->db,$world,'campaign-narrative-service','campaign_publish');
            $q=$this->db->prepare('SELECT committed_day,pending_day,campaign_enabled,game_over FROM wp_world_clock WHERE world_id=? FOR UPDATE');
            $q->execute([$world]);$clock=$q->fetch(\PDO::FETCH_ASSOC);
            if($clock===false||(int)$clock['campaign_enabled']!==1||(int)$clock['game_over']===1||$clock['pending_day']!==null)
                throw new \DomainException('Campaign is not writable');
            $q=$this->db->prepare('SELECT state_json,state_sha FROM wp_opinion_states WHERE world_id=? FOR UPDATE');$q->execute([$world]);
            $r=$q->fetch(\PDO::FETCH_ASSOC);
            if($r===false)throw new \DomainException('Campaign opinion state missing');
            $op=PublicOpinion::restore(json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR));
            $s=$op->snapshot();
            if(!hash_equals($r['state_sha'],$op->hash())||$s['world_id']!==$world||$s['day']!==$clock['committed_day']||!isset($s['campaign']))
                throw new \UnexpectedValueException('Campaign snapshot mismatch');
            if(isset($s['presidency'])&&in_array($e['type'],['presidential_election_lost','presidential_mandate_ended'],true))
                throw new \DomainException('Presidential calendar owns electoral outcomes');
            $q=$this->db->prepare('SELECT event_json,event_sha FROM wp_campaign_events WHERE world_id=? AND event_day>? ORDER BY event_day');
            $q->execute([$world,$clock['committed_day']]);$future=[];
            foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $row)$future[]=self::decode($row);
            if(count($future)>=3650)throw new \DomainException('Campaign event limit');
            CampaignLifecycle::validateQueue($s['campaign'],[...$future,$e]);
            self::insert($this->db,$e);
            $this->db->commit();
        }catch(\Throwable $ex){if($this->db->inTransaction())$this->db->rollBack();throw $ex;}
    }
    /** Bootstrap-only insertion into the same atomic creation transaction. */
    public static function insert(\PDO $db,array $e):void
    {
        if(!$db->inTransaction())throw new \LogicException('Event insert requires transaction');
        CampaignLifecycle::validate($e);$json=PublicOpinion::json($e);
        $q=$db->prepare('INSERT INTO wp_campaign_events(world_id,event_day,event_id,event_json,event_sha) VALUES(?,?,?,?,?)');
        $q->execute([$e['world_id'],$e['day'],$e['id'],$json,hash('sha256',$json)]);
    }
}
