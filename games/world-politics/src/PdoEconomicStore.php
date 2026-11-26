<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/WorldEconomicSimulation.php';
require_once __DIR__.'/WorldMutationFence.php';

/**
 * Durable, world-scoped economic policy decisions.
 * A country-specific legal approval is a trusted server capability,
 * never a Boolean claimed by the mobile/web request.
 *
 * This is an internal application adapter, not a public HTTP service.
 */
final class PdoEconomicStore
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly GeopoliticalActorRegistry $actors,
        private readonly EconomicReferenceRegistry $references,
        private readonly EconomicPolicyPrincipal $principal
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    public function principalId():string
    {
        return $this->principal->actorId();
    }

    /** Verify delegated country authority before dispatch or receipt replay. */
    public function assertBridgeCountryGrant(string $world,string $actor):void
    {
        $this->readAllowed($world);
        $reference=$this->actors->get($actor);
        if ($reference['kind']!=='state'
            || !$this->principal->allows($world,$actor,'economy.propose')
            || ($reference['controller']==='ai_only'
                && !$this->principal->allows($world,$actor,'economy.ai_controller'))) {
            throw new \DomainException('Bridge is not delegated to issue this country proposal');
        }
    }

    /** Trusted administrative initialization only. */
    public function seed(WorldEconomicSimulation $simulation):void
    {
        $snapshot=$simulation->snapshot();
 
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit(
                $this->pdo,$snapshot['world_id'],$this->principal->actorId(),'bootstrap'
            );

        $verified=WorldEconomicSimulation::fromSnapshot(
            $snapshot,$this->actors,$this->references
        );
        $insert=$this->pdo->prepare(
            'INSERT INTO wp_economic_states(world_id,state_json,state_sha,reference_sha,actor_registry_sha,revision) VALUES(?,?,?,?,?,0)'
        );
        $insert->execute([
            $snapshot['world_id'],self::json($snapshot),$verified->hash(),
            $this->references->hash(),$this->actors->hash()
        ]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** @return array{state:array<string,mixed>,etag:string,revision:int} */
    public function read(string $world):array
    {
        $this->readAllowed($world);
        $q=$this->pdo->prepare(
            'SELECT state_json,state_sha,reference_sha,actor_registry_sha,revision FROM wp_economic_states WHERE world_id=?'
        );
        $q->execute([$world]);
        $record=$q->fetch(\PDO::FETCH_ASSOC);
        if ($record===false) throw new \DomainException('No economic world');
        $sim=$this->restore($record,$world);
        return [
            'state'=>$sim->snapshot(),
            'etag'=>'"'.$sim->hash().'"',
            'revision'=>(int)$record['revision']
        ];
    }

    /** @return array{reference_sha:string,as_of:string,indicators:list<array<string,mixed>>} */
    public function indicators(string $world):array
    {
        $this->readAllowed($world);
        // Verify the world actually exists and remains pinned to these data.
        $this->read($world);
        return [
            'reference_sha'=>$this->references->hash(),
            'as_of'=>$this->references->asOf,
            'indicators'=>$this->references->all()
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,result:array<string,mixed>,etag:string,revision:int}
     */
    public function execute(
        string $world,string $operation,array $payload,
        string $idempotencyKey,string $ifMatch
    ):array {
        self::id($world);
        if (!in_array($operation,['propose','authorize','clock'],true)) {
            throw new \InvalidArgumentException('Unknown economic operation');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$idempotencyKey)) {
            throw new \InvalidArgumentException('Invalid idempotency key');
        }
        if (array_is_list($payload) && $payload!==[]) {
            throw new \InvalidArgumentException('JSON object required');
        }
        $actor=$this->principal->actorId();
        if (!is_string($actor) || $actor==='' || strlen($actor)>128
            || preg_match('/[\x00-\x1f\x7f]/',$actor)) {
            throw new \InvalidArgumentException('Unverified principal');
        }
        $body=self::json($payload);
        if (strlen($body)>16000) throw new \InvalidArgumentException('Command body too large');
        $requestSha=hash('sha256',$operation."\n".$body);
        $receiptKey=hash('sha256',$operation."\n".$idempotencyKey);
        $commandId='eco-'.substr(hash('sha256',
            $world."\n".$actor."\n".$operation."\n".$idempotencyKey
        ),0,32);

        $this->readAllowed($world);
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit(
                $this->pdo,$world,$actor,$operation,$operation==='clock' ? ($payload['target_day']??null) : null
            );
            $lock=$this->pdo->prepare(
                'SELECT state_json,state_sha,reference_sha,actor_registry_sha,revision FROM wp_economic_states WHERE world_id=? FOR UPDATE'
            );
            $lock->execute([$world]);
            $row=$lock->fetch(\PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('No economic world');
            $this->readAllowed($world);
            if ($actor!==$this->principal->actorId()) {
                throw new \DomainException('Principal changed during command');
            }
            // Restore state before checking authority, including on
            // idempotency replay: legal powers may have been revoked.
            $sim=$this->restore($row,$world);
            $this->authorize($sim,$world,$operation,$payload);
            $old=$this->pdo->prepare(
                'SELECT request_sha,response_json FROM wp_economic_receipts WHERE world_id=? AND actor_id=? AND receipt_key=?'
            );
            $old->execute([$world,$actor,$receiptKey]);
            $receipt=$old->fetch(\PDO::FETCH_ASSOC);
            if ($receipt!==false) {
                if (!hash_equals($receipt['request_sha'],$requestSha)) {
                    throw new \DomainException('Conflicting idempotency key');
                }
                $previous=json_decode($receipt['response_json'],true,64,JSON_THROW_ON_ERROR);
                $this->pdo->commit();
                return $previous;
            }

            $before=$sim->hash();
            if (!hash_equals('"'.$before.'"',$ifMatch)) {
                throw new \DomainException('Stale economic state');
            }
            $result=$this->apply($sim,$operation,$payload,$commandId);
            $revision=(int)$row['revision']+1;
            if($operation==='clock'&&isset($sim->snapshot()['daily_bridge']))$result['bridged_proposals']=$this->bridgeDaily($sim,$world,$payload['target_day'],$revision);
            $after=$sim->hash();
            $response=[
                'status'=>$operation==='propose'?202:200,
                'result'=>$result,'etag'=>'"'.$after.'"',
                'revision'=>$revision
            ];
            $w=$this->pdo->prepare(
                'UPDATE wp_economic_states SET state_json=?,state_sha=?,revision=? WHERE world_id=?'
            );
            $w->execute([self::json($sim->snapshot()),$after,$revision,$world]);
            $j=$this->pdo->prepare(
                'INSERT INTO wp_economic_journal(world_id,revision,actor_id,operation,request_sha,before_sha,after_sha,result_json) VALUES(?,?,?,?,?,?,?,?)'
            );
            $j->execute([
                $world,$revision,$actor,$operation,$requestSha,$before,$after,self::json($result)
            ]);
            $r=$this->pdo->prepare(
                'INSERT INTO wp_economic_receipts(world_id,actor_id,receipt_key,request_sha,response_json) VALUES(?,?,?,?,?)'
            );
            $r->execute([$world,$actor,$receiptKey,$requestSha,self::json($response)]);
            $this->pdo->commit();
            return $response;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** Atomic with the economic clock, journal and receipt. The diplomatic
     * source is already committed and protected by the world write fence.
     * This grants no authorization, nor does it change trade or GDP. */
    private function bridgeDaily(WorldEconomicSimulation $sim,string $world,string $day,int $revision):array
    {
        $q=$this->pdo->prepare('SELECT state_json,state_sha,actor_registry_sha FROM wp_diplomatic_states WHERE world_id=?');
        $q->execute([$world]);$d=$q->fetch(\PDO::FETCH_ASSOC);
        if($d===false||!hash_equals($this->actors->hash(),$d['actor_registry_sha']))throw new \DomainException('Missing pinned diplomatic bridge source');
        $dip=DiplomaticSimulation::fromSnapshot(json_decode($d['state_json'],true,64,JSON_THROW_ON_ERROR),$this->actors);
        $state=$dip->snapshot();
        if(!hash_equals($d['state_sha'],$dip->hash())||$state['world_id']!==$world||$state['day']!==$day)throw new \DomainException('Diplomatic bridge source day or hash mismatch');
        $q=$this->pdo->prepare("SELECT o.*,j.after_sha AS journal_after_sha,j.actor_id AS journal_actor_id,j.operation AS journal_operation,j.result_json AS journal_result_json
            FROM wp_diplomatic_outbox o LEFT JOIN wp_diplomatic_journal j ON j.world_id=o.world_id AND j.revision=o.source_revision
            WHERE o.world_id=? AND o.status='pending' ORDER BY o.source_revision,o.event_id LIMIT 32 FOR UPDATE");
        $q->execute([$world]);$out=[];
        foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $row){
            $id=$row['event_id'];$source=$state['proposals'][$id]??null;
            $env=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);
            $jr=json_decode($row['journal_result_json']??'null',true,32,JSON_THROW_ON_ERROR);
            if(!is_array($source)||!is_array($env)||!is_array($jr)
                ||!hash_equals($row['payload_sha'],hash('sha256',$row['payload_json']))
                ||!is_string($row['journal_after_sha'])||!hash_equals($row['source_state_sha'],$row['journal_after_sha'])
                ||$row['source_actor_id']!==$row['journal_actor_id']||$row['journal_operation']!=='propose'
                ||$env['version']!=='wp16-sanctions-proposal-1'||$env['world_id']!==$world||$env['event_id']!==$id
                ||$env['source_revision']!==(int)$row['source_revision']||$env['source_state_sha']!==$row['source_state_sha']
                ||$env['action']!=='sanctions_proposal'||$env['status']!=='pending_domestic_approval'
                ||$jr['id']!==$id||$jr['action']!=='sanctions_proposal'||$jr['status']!=='pending_domestic_approval'
                ||$source['action']!=='sanctions_proposal'||$source['status']!=='pending_domestic_approval'
                ||$source['from']!==$env['from']||$source['to']!==$env['to'])throw new \DomainException('Unverified diplomatic outbox source');
            // Unsupported participants remain in the outbox for explicit review.
            if($source['from']!=='state_fr'||$this->actors->get($source['to'])['kind']!=='state')continue;
            $assumptions=['effect_bps'=>500,'low_bps'=>100,'high_bps'=>1000,
                'effective_on'=>(new \DateTimeImmutable($day))->modify('+14 days')->format('Y-m-d'),'kind'=>'scenario_assumption'];
            $bot=new class($world) implements EconomicPolicyPrincipal{
                public function __construct(private readonly string $world){}
                public function actorId():string{return 'fictional-sanctions-bridge';}
                public function allows(string $w,string $a,string $p):bool{return $w===$this->world&&$a==='state_fr'&&$p==='economy.propose';}
                public function legalApproval(string $w,string $s,string $i):bool{return false;}
            };
            $policyId='daily-bridge-'.substr(hash('sha256',$world.'|'.$id),0,32);
            $proposal=$sim->proposeTradeRestriction($bot,$source['from'],$source['to'],$assumptions['effect_bps'],$assumptions['low_bps'],$assumptions['high_bps'],$assumptions['effective_on'],$policyId);
            if($proposal['status']!=='proposed'||$proposal['binding_effect']!==false)throw new \DomainException('Bridge must not authorize policy');
            $update=$this->pdo->prepare("UPDATE wp_diplomatic_outbox SET status='delivered',assumptions_sha=?,economic_policy_id=?,economic_revision=?,delivered_at=UTC_TIMESTAMP() WHERE world_id=? AND event_id=? AND status='pending'");
            $update->execute([hash('sha256',self::json($assumptions)),$policyId,$revision,$world,$id]);
            if($update->rowCount()!==1)throw new \DomainException('Bridge acknowledgment conflict');
            $out[]=['source_event_id'=>$id,'policy_id'=>$policyId,'status'=>'proposed','binding_effect'=>false];
        }
        return $out;
    }

    /** @param array<string,mixed> $payload */
    private function authorize(
        WorldEconomicSimulation $sim,string $world,string $operation,array $payload
    ):void {
        $fields=match($operation) {
            'propose'=>['from','to','effect_bps','low_bps','high_bps','effective_on'],
            'authorize'=>['policy_id'],
            'clock'=>['target_day']
        };
        self::fields($payload,$fields);
        if ($operation==='clock') {
            if (!$this->principal->allows($world,$this->principal->actorId(),'economy.clock')) {
                throw new \DomainException('Trusted simulation scheduler required');
            }
            return;
        }
        if ($operation==='propose') {
            $actor=$payload['from'];
            $ability='economy.propose';
        } else {
            $id=$payload['policy_id'];
            if (!is_string($id) || !isset($sim->snapshot()['policies'][$id])) {
                throw new \DomainException('No such policy in this world');
            }
            $actor=$sim->snapshot()['policies'][$id]['from'];
            $ability='economy.approve';
        }
        if (!is_string($actor)) throw new \InvalidArgumentException('Invalid sovereign actor');
        $reference=$this->actors->get($actor);
        if (!$this->principal->allows($world,$actor,$ability)
            || ($reference['controller']==='ai_only'
                && !$this->principal->allows($world,$actor,'economy.ai_controller'))) {
            throw new \DomainException('Unverified economic policy authority');
        }
        if ($operation==='authorize'
            && !$this->principal->legalApproval($world,$actor,'trade_restriction')) {
            throw new \DomainException('Country legal procedure not satisfied');
        }
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function apply(
        WorldEconomicSimulation $sim,string $operation,array $payload,string $id
    ):array {
        return match($operation) {
            'propose'=>$sim->proposeTradeRestriction(
                $this->principal,self::str($payload['from']),self::str($payload['to']),
                self::integer($payload['effect_bps']),
                self::integer($payload['low_bps']),
                self::integer($payload['high_bps']),
                self::str($payload['effective_on']),$id
            ),
            'authorize'=>$sim->authorizeRestriction(
                $this->principal,self::str($payload['policy_id']),$id
            ),
            'clock'=>$sim->advanceTo(
                $this->principal,self::str($payload['target_day']),$id
            ),
        };
    }

    /** @param array<string,mixed> $row */
    private function restore(array $row,string $world):WorldEconomicSimulation
    {
        if (!hash_equals($this->references->hash(),$row['reference_sha'])
            || !hash_equals($this->actors->hash(),$row['actor_registry_sha'])) {
            throw new \DomainException('Pinned economic sources differ from initialized scenario');
        }
        $state=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['world_id']??null)!==$world) {
            throw new \UnexpectedValueException('Incorrect world in persisted economic snapshot');
        }
        $sim=WorldEconomicSimulation::fromSnapshot(
            $state,$this->actors,$this->references
        );
        if (!hash_equals($row['state_sha'],$sim->hash())) {
            throw new \UnexpectedValueException('Persisted economic snapshot failed checksum validation');
        }
        return $sim;
    }

    private function readAllowed(string $world):void
    {
        self::id($world);
        if (!$this->principal->allows($world,$this->principal->actorId(),'economy.read')) {
            throw new \DomainException('No world read permission');
        }
    }
    /** @param array<string,mixed> $payload @param list<string> $expected */
    private static function fields(array $payload,array $expected):void
    {
        $keys=array_keys($payload);sort($keys);sort($expected);
        if ($keys!==$expected) throw new \InvalidArgumentException('Unexpected policy fields');
    }
    private static function id(string $id):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)) {
            throw new \InvalidArgumentException('Invalid economic world id');
        }
    }
    private static function str(mixed $value):string
    {
        if (!is_string($value)) throw new \InvalidArgumentException('Expected string');
        return $value;
    }
    private static function integer(mixed $value):int
    {
        if (!is_int($value)) throw new \InvalidArgumentException('Expected integer basis points');
        return $value;
    }
    private static function json(mixed $value):string
    {
        return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}
