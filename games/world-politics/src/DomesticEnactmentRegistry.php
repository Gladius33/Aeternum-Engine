<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoDomesticPolicyStore.php';
require_once __DIR__.'/PdoFrenchConstitutionalStore.php';

/** Server-only simulated final-act publisher; no REST publication endpoint. */
final class DomesticEnactmentRegistry
{
    public function __construct(private readonly \PDO $pdo,
        private readonly PersistentDomesticPolicyAuthority $publisher,
        private readonly ?PdoFrenchConstitutionalStore $constitution=null)
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    /**
     * Final publication is a separate trusted scenario act, not an automatic
     * consequence of an Assembly vote. This is NOT a full French legislative
     * procedure: the final text and review digest are curated server evidence.
     * No power to create real-world statutes is implied.
     */
    public function publish(string $world,string $policy,string $route,string $finalTextSha,string $reviewSha):string
    {
        foreach ([$world,$policy] as $id) {
            if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)) throw new \InvalidArgumentException('Invalid enactment id');
        }
        foreach ([$finalTextSha,$reviewSha] as $sha) {
            if (!preg_match('/^[a-f0-9]{64}$/D',$sha)) throw new \InvalidArgumentException('Invalid final evidence');
        }
        if (!in_array($route,['fr_49_3','curated_final_act'],true)) throw new \InvalidArgumentException('Unknown legal route');
        $actor=$this->publisher->actorId();
        $this->permit($world,$actor);
        if ($this->pdo->inTransaction()) throw new \LogicException('Registry requires independent transaction');
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit($this->pdo,$world,$actor,'domestic_publication');
            $this->permit($world,$actor);
            $q=$this->pdo->prepare('SELECT state_json,state_sha,assumptions_sha FROM wp_domestic_states WHERE world_id=? FOR UPDATE');
            $q->execute([$world]);$row=$q->fetch(\PDO::FETCH_ASSOC);
            if ($row===false || !hash_equals($row['state_sha'],hash('sha256',$row['state_json']))) throw new \DomainException('Unverified domestic state');
            $state=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);
            $p=$state['policies'][$policy]??null;
            if ($state['world_id']!==$world || $state['assumptions_sha']!==$row['assumptions_sha']
                || $p===null || $p['authorized_on']!==null || $state['day']>=$p['effective_on']) {
                throw new \DomainException('No timely pending domestic proposal');
            }
            $source=null;
            if ($route==='fr_49_3') {
                if ($this->constitution===null || $this->constitution->principalId()!==$actor)
                    throw new \DomainException('Verified constitutional reader required');
                $legal=$this->constitution->read($world);
                $last=null;
                foreach ($legal['state']['history'] as $entry) {
                    if ($entry['bill_id']===$policy) $last=$entry;
                }
                if (($last['assembly_adoption']??null)!==true
                    || ($legal['state']['current_bill']['bill_id']??null)===$policy)
                    throw new \DomainException('No completed Assembly adoption for this bill');
                $source=trim($legal['etag'],'"');
            } elseif ($this->constitution!==null) {
                // Cannot use the generic route to bypass a configured French procedure.
                throw new \DomainException('French constitutional scenario requires reviewed route');
            }
            $record=['world_id'=>$world,'policy_id'=>$policy,'route'=>$route,
                'classification'=>'simulated_final_act','published_on'=>$state['day'],
                'effective_on'=>$p['effective_on'],'assumptions_sha'=>$state['assumptions_sha'],
                'proposal_sha'=>$row['state_sha'],'constitutional_sha'=>$source,
                'final_text_sha'=>$finalTextSha,'review_sha'=>$reviewSha,'publisher'=>$actor];
            $json=json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            $sha=hash('sha256',$json);
            $this->permit($world,$actor);
            $q=$this->pdo->prepare('INSERT INTO wp_domestic_enactments(world_id,policy_id,record_json,record_sha) VALUES(?,?,?,?)');
            $q->execute([$world,$policy,$json,$sha]);$this->pdo->commit();return $sha;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Called from server-side authority, never from a player-supplied proof hash. */
    public function proof(string $world,string $policy):?string
    {
        $q=$this->pdo->prepare('SELECT e.record_json,e.record_sha,s.assumptions_sha,s.state_json,s.state_sha
            FROM wp_domestic_enactments e JOIN wp_domestic_states s ON s.world_id=e.world_id
            WHERE e.world_id=? AND e.policy_id=?');
        $q->execute([$world,$policy]);$r=$q->fetch(\PDO::FETCH_ASSOC);
        if ($r===false) return null;
        if (!hash_equals($r['record_sha'],hash('sha256',$r['record_json']))
            || !hash_equals($r['state_sha'],hash('sha256',$r['state_json']))) throw new \UnexpectedValueException('Corrupt legislative proof');
        $e=json_decode($r['record_json'],true,64,JSON_THROW_ON_ERROR);
        $s=json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR);
        if ($e['world_id']!==$world || $s['world_id']!==$world || $e['policy_id']!==$policy
            || $e['classification']!=='simulated_final_act' || $e['assumptions_sha']!==$r['assumptions_sha']
            || $e['effective_on']!==($s['policies'][$policy]['effective_on']??null)
            || $e['published_on']>$s['day']) throw new \UnexpectedValueException('Legislative proof scope mismatch');
        return $r['record_sha'];
    }

    private function permit(string $world,string $actor):void
    {
        if (!preg_match('/^[A-Za-z0-9_.:@-]{1,128}$/D',$actor) || $actor!==$this->publisher->actorId()
            || !$this->publisher->allows($world,'domestic.publish')) throw new \DomainException('Verified publication authority required');
    }
}

/** Explicitly binds the policy store's enactment verification to persisted final acts. */
final class VerifiedDomesticPolicyAuthority implements PersistentDomesticPolicyAuthority
{
    public function __construct(private readonly PersistentDomesticPolicyAuthority $base,
        private readonly DomesticEnactmentRegistry $registry) {}
    public function actorId():string{return $this->base->actorId();}
    public function allows(string $world,string $ability):bool{return $this->base->allows($world,$ability);}
    public function clockDay(string $world):string{return $this->base->clockDay($world);}
    public function enactedInstrument(string $world,string $policy):?string {
        return $this->allows($world,'domestic.enact')?$this->registry->proof($world,$policy):null;
    }
}
