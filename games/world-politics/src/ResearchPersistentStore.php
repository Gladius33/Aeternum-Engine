<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ResearchPersistentModel.php';
require_once __DIR__.'/ResearchBilling.php';

/** Tenant ownership and entitlement are checked by ResearchQueue in the
 * caller transaction before entering this adapter. */
final class ResearchPersistentStore
{
    public function __construct(private readonly \PDO $db,private readonly string $key){}
    private function q(string $sql,array $p=[]):\PDOStatement{$q=$this->db->prepare($sql);$q->execute($p);return $q;}
    private function seal(array $p):string{return hash_hmac('sha256',ResearchExperiment::json($p),$this->key);}
    private static function decode(string $s):array{return json_decode($s,true,64,JSON_THROW_ON_ERROR);}
    private static function provenance(string $day,array $pin):array{
        return ['source_name'=>'World Politics Research persistent simulation','data_date'=>$day,'date_kind'=>'simulation_day',
            'classification'=>'synthetic_simulation_not_observed_data','model_manifest'=>$pin];
    }
    private function scope():void{if(!$this->db->inTransaction())throw new \LogicException('Research transaction required');}
    private function head(string $world,string $tenant,int $rev,string $pin,string $state,string $last):string{
        return $this->seal(['world'=>$world,'tenant'=>$tenant,'revision'=>$rev,'pin'=>$pin,'state_sha'=>$state,'last'=>$last]);
    }
    public function init(string $world,string $tenant):array{
        $this->scope();$old=$this->q('SELECT world_id FROM wp_research_simulations WHERE world_id=? AND tenant_id=? FOR UPDATE',[$world,$tenant])->fetchColumn();
        if($old!==false)return $this->read($world,$tenant);
        $c=ResearchPersistentModel::config();$pin=ResearchExperiment::json(ResearchPersistentModel::pin($c));$sim=ResearchPersistentModel::build($world,$c);
        $sha=$sim->hash();$last=str_repeat('0',64);
        $this->q('INSERT INTO wp_research_simulations(world_id,tenant_id,pin_json,state_sha,last_sha,head_signature) VALUES(?,?,?,?,?,?)',[$world,$tenant,$pin,$sha,$last,$this->head($world,$tenant,0,$pin,$sha,$last)]);
        return $this->read($world,$tenant);
    }
    private function replay(string $world,string $tenant):array{
        $this->scope();$h=$this->q('SELECT * FROM wp_research_simulations WHERE world_id=? AND tenant_id=? FOR UPDATE',[$world,$tenant])->fetch(\PDO::FETCH_ASSOC);
        if($h===false)throw new \DomainException('Simulation not initialized');
        $rev=(int)$h['revision'];
        if($rev<0||$rev>ResearchPersistentModel::MAX_EVENTS||!hash_equals($this->head($world,$tenant,$rev,$h['pin_json'],$h['state_sha'],$h['last_sha']),$h['head_signature']))throw new \DomainException('Research head signature');
        $c=ResearchPersistentModel::config();
        if($h['pin_json']!==ResearchExperiment::json(ResearchPersistentModel::pin($c)))throw new \DomainException('Pinned simulation edition unavailable');
        $sim=ResearchPersistentModel::build($world,$c);$last=str_repeat('0',64);$i=0;$rows=[];
        $events=$this->q('SELECT * FROM wp_research_simulation_events WHERE world_id=? ORDER BY revision LIMIT 65',[$world]);
        foreach($events as $e){
            $i++;$v=self::decode($e['event_json']);$a=$v['request'];
            if((int)$e['revision']!==$i||$i>$rev||$a['expected_revision']!==$i-1||$v['previous_sha']!==$last||$v['world']!==$world||$v['tenant']!==$tenant||$v['command']!==$e['command_sha']||$v['pin_sha']!==hash('sha256',$h['pin_json'])
                ||!hash_equals($this->seal($v),$e['signature_sha']))throw new \DomainException('Research journal signature');
            $transition=ResearchPersistentModel::step($sim,$c,$a);
            if($transition!==$v['transition']||$sim->hash()!==$v['state_sha'])throw new \DomainException('Research replay diverged');
            $last=ResearchExperiment::hash($v);$rows[]=['revision'=>$i,'transition'=>$transition,'event_sha'=>$last];
        }
        if($i!==$rev||$sim->hash()!==$h['state_sha']||$last!==$h['last_sha'])throw new \DomainException('Research projection mismatch');
        return [$h,$c,$sim,$rows];
    }
    public function read(string $world,string $tenant):array{
        [$h,$c,$sim]=$this->replay($world,$tenant);
        return ['world_id'=>$world,'version'=>ResearchPersistentModel::VERSION,'revision'=>(int)$h['revision'],'manifest'=>self::decode($h['pin_json']),
            'provenance'=>self::provenance($sim->snapshot()['day'],self::decode($h['pin_json'])),'state'=>ResearchPersistentModel::features($sim->snapshot()),'state_sha'=>$sim->hash(),'last_event_sha'=>$h['last_sha'],
            'allowed_policies'=>ResearchPersistentModel::policies($c),'maximum_days'=>ResearchPersistentModel::MAX_DAYS,'research_only'=>true,'official_score_eligible'=>false];
    }
    public function step(string $world,string $tenant,string $key,array $a):array{
        if(!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key))throw new \DomainException('Invalid idempotency key');
        $a=ResearchPersistentModel::normalize($a);$key=hash('sha256',$key);
        [$h,$c,$sim,$rows]=$this->replay($world,$tenant);$rev=(int)$h['revision'];
        $old=$this->q('SELECT event_json FROM wp_research_simulation_events WHERE world_id=? AND command_sha=?',[$world,$key])->fetchColumn();
        if(is_string($old)){
            $v=self::decode($old);if($v['request']!==$a)throw new \DomainException('Transition idempotency conflict');
            return ['revision'=>$a['expected_revision']+1,'transition'=>$v['transition'],'event_sha'=>ResearchExperiment::hash($v),'provenance'=>self::provenance($v['transition']['next_state']['day'],self::decode($h['pin_json']))];
        }
        if($a['expected_revision']!==$rev||$rev>=ResearchPersistentModel::MAX_EVENTS)throw new \DomainException('Stale or exhausted research revision');
        // Atomic compute charge before executing new work; rollback with any failure.
        ResearchBilling::simulation($this->db,$tenant,$world,$key,$a['days']);
        $transition=ResearchPersistentModel::step($sim,$c,$a);$rev++;
        $v=['world'=>$world,'tenant'=>$tenant,'command'=>$key,'pin_sha'=>hash('sha256',$h['pin_json']),'previous_sha'=>$h['last_sha'],'request'=>$a,'transition'=>$transition,'state_sha'=>$sim->hash()];
        $last=ResearchExperiment::hash($v);
        $this->q('INSERT INTO wp_research_simulation_events(world_id,revision,command_sha,event_json,signature_sha) VALUES(?,?,?,?,?)',[$world,$rev,$key,ResearchExperiment::json($v),$this->seal($v)]);
        $this->q('UPDATE wp_research_simulations SET revision=?,state_sha=?,last_sha=?,head_signature=? WHERE world_id=?',[$rev,$sim->hash(),$last,$this->head($world,$tenant,$rev,$h['pin_json'],$sim->hash(),$last),$world]);
        return ['revision'=>$rev,'transition'=>$transition,'event_sha'=>$last,'provenance'=>self::provenance($transition['next_state']['day'],self::decode($h['pin_json']))];
    }
    /** Pro feed: previously replay-validated signed transition evidence,
     * current opt-in only. No tenant IDs, account IDs or raw command IDs. */
    public function globalDataset(int $after,?int $through):array{
        $this->scope();
        if($after<0||($through!==null&&$through<$after))throw new \DomainException('Invalid transition cursor');
        $max=$through??(int)$this->q('SELECT COALESCE(MAX(seq),0) FROM wp_research_simulation_events')->fetchColumn();
        $raw=$this->q('SELECT e.*,s.tenant_id,s.pin_json,s.revision AS head_revision,s.state_sha,s.last_sha,s.head_signature
            FROM wp_research_simulation_events e JOIN wp_research_simulations s USING(world_id)
            JOIN wp_research_tenants t USING(tenant_id)
            WHERE t.share_dataset=1 AND e.seq>? AND e.seq<=? ORDER BY e.seq LIMIT 11',[$after,$max])->fetchAll(\PDO::FETCH_ASSOC);
        $out=[];
        foreach(array_slice($raw,0,10) as $e){
            $v=self::decode($e['event_json']);$revision=(int)$e['revision'];
            if(!hash_equals($this->head($e['world_id'],$e['tenant_id'],(int)$e['head_revision'],$e['pin_json'],$e['state_sha'],$e['last_sha']),$e['head_signature'])
                ||!hash_equals($this->seal($v),$e['signature_sha'])||$v['world']!==$e['world_id']||$v['tenant']!==$e['tenant_id']||$v['command']!==$e['command_sha']
                ||$v['pin_sha']!==hash('sha256',$e['pin_json'])||$v['request']['expected_revision']!==$revision-1||$revision>(int)$e['head_revision'])
                throw new \DomainException('Transition dataset evidence mismatch');
            $out[]=['seq'=>(int)$e['seq'],'world'=>hash_hmac('sha256','research-simulation-world|'.$e['world_id'],$this->key),
                'revision'=>$revision,'manifest'=>self::decode($e['pin_json']),'transition'=>$v['transition'],'provenance'=>self::provenance($v['transition']['next_state']['day'],self::decode($e['pin_json']))];
        }
        return ['version'=>ResearchPersistentModel::VERSION,'scope'=>'global_opted_in_research_transitions','through'=>$max,
            'next_after'=>count($raw)>10?$out[9]['seq']:null,'rows'=>$out,'synthetic_simulation'=>true];
    }
    public function dataset(string $world,string $tenant,int $after):array{
        if($after<0||$after>ResearchPersistentModel::MAX_EVENTS)throw new \DomainException('Invalid transition cursor');
        [$h,$c,$sim,$rows]=$this->replay($world,$tenant);$page=array_slice($rows,$after,10);foreach($page as &$row)$row['provenance']=self::provenance($row['transition']['next_state']['day'],self::decode($h['pin_json']));unset($row);
        return ['version'=>ResearchPersistentModel::VERSION,'world_id'=>$world,'through_revision'=>(int)$h['revision'],'manifest'=>self::decode($h['pin_json']),
            'next_after'=>$after+count($page)<count($rows)?$after+count($page):null,'rows'=>$page,'synthetic_simulation'=>true];
    }
}
