<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/WorldEconomicSimulation.php';

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

    /** Trusted administrative initialization only. */
    public function seed(WorldEconomicSimulation $simulation):void
    {
        $snapshot=$simulation->snapshot();
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
            $after=$sim->hash();
            $revision=(int)$row['revision']+1;
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
