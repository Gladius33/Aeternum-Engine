<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PublicOpinion.php';
require_once __DIR__.'/PdoDomesticPolicyStore.php';
require_once __DIR__.'/PdoInstitutionalStore.php';
require_once __DIR__.'/DiplomaticSimulation.php';

/** Clock-only aggregate. The coordinator owns checkpoint publication and recovery.
 * Source stores are read with their own PDO connections and verified replay.
 */
final class PdoPublicOpinionStore
{
    public function __construct(private readonly \PDO $db,private readonly DiplomaticPrincipal $principal,
        private readonly PdoDomesticPolicyStore $domestic,private readonly PdoInstitutionalStore $institutions) {}
    public function principalId():string{return $this->principal->actorId();}
    private function permit(string $world,string $ability):void
    {
        $actor=$this->principalId();
        if($actor===''||!$this->principal->allows($world,$actor,$ability)
            ||$this->domestic->principalId()!==$actor||$this->institutions->principalId()!==$actor)
            throw new \DomainException('Trusted opinion service required');
    }
    private function row(string $world,bool $lock=false):array
    {
        $q=$this->db->prepare('SELECT * FROM wp_opinion_states WHERE world_id=?'.($lock?' FOR UPDATE':''));
        $q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);
        if($r===false)throw new \DomainException('Opinion state missing');
        return $r;
    }
    private function restore(string $world,array $r):PublicOpinion
    {
        $x=PublicOpinion::restore(json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR));
        if($x->snapshot()['world_id']!==$world||!hash_equals($r['state_sha'],$x->hash()))throw new \UnexpectedValueException('Opinion scope or hash mismatch');
        return $x;
    }
    public function read(string $world):array
    {
        $this->permit($world,'world.clock.read');$r=$this->row($world);
        $x=$this->restore($world,$r);
        return ['state'=>$x->snapshot(),'etag'=>'"'.$x->hash().'"','revision'=>(int)$r['revision']];
    }
    public function execute(string $world,string $operation,array $payload,string $key,string $etag):array
    {
        $this->permit($world,'world.clock.advance');
        if($operation!=='clock'||array_keys($payload)!==['target_day']||!is_string($payload['target_day'])
            ||!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key)||!preg_match('/^"[a-f0-9]{64}"$/D',$etag))
            throw new \InvalidArgumentException('Invalid opinion clock request');
        $day=$payload['target_day'];$keySha=hash('sha256',$key);
        $this->db->beginTransaction();
        try{
            WorldMutationFence::admit($this->db,$world,$this->principalId(),'clock',$day);
            $q=$this->db->prepare('SELECT pending_day,opinion_enabled FROM wp_world_clock WHERE world_id=?');
            $q->execute([$world]);$clock=$q->fetch(\PDO::FETCH_ASSOC);
            if($clock===false||$clock['pending_day']!==$day||(int)$clock['opinion_enabled']!==1)throw new \DomainException('No pinned pending opinion day');
            $r=$this->row($world,true);$this->permit($world,'world.clock.advance');$x=$this->restore($world,$r);
            if($r['last_key_sha']!==null&&hash_equals($r['last_key_sha'],$keySha)){
                if($x->snapshot()['day']!==$day)throw new \DomainException('Opinion key conflict');
                $receipt=json_decode($r['last_receipt_json'],true,32,JSON_THROW_ON_ERROR);
                $this->db->commit();return $receipt;
            }
            if(!hash_equals('"'.$x->hash().'"',$etag))throw new \DomainException('Stale opinion precondition');
            $e=PublicOpinion::observe($this->domestic->read($world),$this->institutions->read($world,'FR'));
            if($e['day']!==$day)throw new \DomainException('Opinion sources must reach pending day first');
            $result=$x->advance($e);$rev=(int)$r['revision']+1;
            $receipt=['status'=>200,'result'=>$result,'etag'=>'"'.$x->hash().'"','revision'=>$rev];
            $q=$this->db->prepare('UPDATE wp_opinion_states SET state_json=?,state_sha=?,revision=?,last_key_sha=?,last_receipt_json=? WHERE world_id=?');
            $q->execute([PublicOpinion::json($x->snapshot()),$x->hash(),$rev,$keySha,PublicOpinion::json($receipt),$world]);
            $this->db->commit();return $receipt;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
