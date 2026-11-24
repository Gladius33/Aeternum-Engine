<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/DiplomaticSimulation.php';

/**
 * WP08: global, transactionally serialized, server-authorized diplomacy.
 *
 * NOT a network front controller. A trusted gateway must bind the principal
 * to a verified login or an isolated AI worker service account.
 */
final class PdoDiplomaticStore
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly GeopoliticalActorRegistry $actors,
        private readonly DiplomaticPrincipal $principal
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    public function registry():GeopoliticalActorRegistry
    {
        return $this->actors;
    }

    /** Trusted scenario bootstrap, not a player command. */
    public function seed(DiplomaticSimulation $sim):void
    {
        $snapshot=$sim->snapshot();
        if (!hash_equals($snapshot['actors_sha'],$this->actors->hash())) {
            throw new \DomainException('Pinned geopolitical registry mismatch');
        }
        $stmt=$this->pdo->prepare(
            'INSERT INTO wp_diplomatic_states(world_id,state_json,state_sha,actor_registry_sha,revision) VALUES (?,?,?,?,0)'
        );
        $stmt->execute([
            $snapshot['world_id'],self::json($snapshot),$sim->hash(),$this->actors->hash()
        ]);
    }

    /** @return array{state:array<string,mixed>,etag:string,revision:int} */
    public function read(string $world):array
    {
        $this->admitRead($world);
        $stmt=$this->pdo->prepare(
            'SELECT state_json,state_sha,actor_registry_sha,revision FROM wp_diplomatic_states WHERE world_id=?'
        );
        $stmt->execute([$world]);
        $row=$stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('Unknown world');
        $aggregate=$this->rehydrate($row,$world);
        return [
            'state'=>$aggregate->snapshot(),
            'etag'=>'"'.$aggregate->hash().'"',
            'revision'=>(int)$row['revision'],
        ];
    }

    /**
     * Durable, idempotent command. All writes happen under the global world
     * row lock; the journal, receipts, and snapshot share one transaction.
     *
     * @param array<string,mixed> $payload
     * @return array{status:int,result:array<string,mixed>,etag:string,revision:int}
     */
    public function execute(
        string $world,string $operation,array $payload,
        string $idempotencyKey,string $ifMatch
    ):array {
        if (!in_array($operation,['propose','respond','ai_decide','clock'],true)) {
            throw new \InvalidArgumentException('Unapproved diplomatic operation');
        }
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$world)
            || !preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$idempotencyKey)) {
            throw new \InvalidArgumentException('Invalid world or idempotency key');
        }
        if (array_is_list($payload) && $payload!==[]) {
            throw new \InvalidArgumentException('JSON object required');
        }
        $actor=$this->principal->actorId();
        if ($actor==='' || strlen($actor)>128
            || preg_match('/[\x00-\x1f\x7f]/',$actor)) {
            throw new \InvalidArgumentException('Invalid authenticated principal');
        }
        $data=self::json($payload);
        if (strlen($data)>16000) throw new \InvalidArgumentException('Oversized diplomatic command');
        $requestSha=hash('sha256',$operation."\n".$data);
        $receiptKey=hash('sha256',$operation."\n".$idempotencyKey);
        $commandId='cmd-'.substr(hash('sha256',
            $world."\n".$actor."\n".$operation."\n".$idempotencyKey
        ),0,32);

        $this->admitRead($world);
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare(
                'SELECT state_json,state_sha,actor_registry_sha,revision FROM wp_diplomatic_states WHERE world_id=? FOR UPDATE'
            );
            $stmt->execute([$world]);
            $row=$stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('Unknown world');

            $this->admitRead($world);
            if ($this->principal->actorId()!==$actor) {
                throw new \DomainException('Principal changed inside transaction');
            }

            $old=$this->pdo->prepare(
                'SELECT request_sha,response_json FROM wp_diplomatic_receipts WHERE world_id=? AND actor_id=? AND receipt_key=?'
            );
            $old->execute([$world,$actor,$receiptKey]);
            $receipt=$old->fetch(\PDO::FETCH_ASSOC);
            if ($receipt!==false) {
                if (!hash_equals($receipt['request_sha'],$requestSha)) {
                    throw new \DomainException('Conflicting idempotency payload');
                }
                // Permission revocation must also prevent replay of receipts.
                $this->checkCommandAuthority($world,$operation,$payload);
                $this->pdo->commit();
                return json_decode($receipt['response_json'],true,64,JSON_THROW_ON_ERROR);
            }

            $sim=$this->rehydrate($row,$world);
            if (!hash_equals('"'.$sim->hash().'"',$ifMatch)) {
                throw new \DomainException('Stale state precondition');
            }
            $this->checkCommandAuthority($world,$operation,$payload);
            $before=$sim->hash();
            $result=$this->apply($sim,$operation,$payload,$commandId);
            $after=$sim->hash();
            $revision=(int)$row['revision']+1;
            $response=[
                'status'=>$operation==='propose'?202:200,
                'result'=>$result,
                'etag'=>'"'.$after.'"',
                'revision'=>$revision
            ];

            $updated=$this->pdo->prepare(
                'UPDATE wp_diplomatic_states SET state_json=?,state_sha=?,revision=? WHERE world_id=?'
            );
            $updated->execute([self::json($sim->snapshot()),$after,$revision,$world]);
            $journal=$this->pdo->prepare(
                'INSERT INTO wp_diplomatic_journal(world_id,revision,actor_id,operation,request_sha,before_sha,after_sha,result_json) VALUES(?,?,?,?,?,?,?,?)'
            );
            $journal->execute([$world,$revision,$actor,$operation,$requestSha,$before,$after,self::json($result)]);
            $writeReceipt=$this->pdo->prepare(
                'INSERT INTO wp_diplomatic_receipts(world_id,actor_id,receipt_key,request_sha,response_json) VALUES(?,?,?,?,?)'
            );
            $writeReceipt->execute([$world,$actor,$receiptKey,$requestSha,self::json($response)]);

            $this->pdo->commit();
            return $response;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function admitRead(string $world):void
    {
        if (!$this->principal->allows($world,$this->principal->actorId(),'diplomacy.read')) {
            throw new \DomainException('No verified world access');
        }
    }

    /** @param array<string,mixed> $payload */
    private function checkCommandAuthority(string $world,string $operation,array $payload):void
    {
        if ($operation==='clock') {
            if (!$this->principal->allows($world,$this->principal->actorId(),'diplomacy.clock')) {
                throw new \DomainException('Trusted clock required');
            }
            return;
        }
        if ($operation==='ai_decide') {
            if (!isset($payload['responder']) || !is_string($payload['responder'])
                || !$this->principal->allows($world,$this->principal->actorId(),'diplomacy.ai_scheduler')) {
                throw new \DomainException('Trusted AI scheduler required');
            }
            $this->actors->get($payload['responder']);
            if ($this->actors->isHumanPlayable($payload['responder'])
                || !$this->principal->allows($world,$payload['responder'],'diplomacy.respond')
                || !$this->principal->allows($world,$payload['responder'],'diplomacy.ai_controller')) {
                throw new \DomainException('Trusted AI scheduler required');
            }
            return;
        }
        $entityId=$operation==='propose' ? ($payload['from']??null) : ($payload['responder']??null);
        $ability=$operation==='propose'?'diplomacy.propose':'diplomacy.respond';
        if (!is_string($entityId)) throw new \InvalidArgumentException('Missing diplomatic actor field');
        $entity=$this->actors->get($entityId);
        if (!$this->principal->allows($world,$entityId,$ability)
            || ($entity['controller']==='ai_only'
                && !$this->principal->allows($world,$entityId,'diplomacy.ai_controller'))) {
            throw new \DomainException('Actor has no verified authority over this diplomatic participant');
        }
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function apply(DiplomaticSimulation $sim,string $operation,array $payload,string $commandId):array
    {
        switch ($operation) {
            case 'propose':
                self::fields($payload,['from','to','action']);
                foreach(['from','to','action'] as $key) if (!is_string($payload[$key])) {
                    throw new \InvalidArgumentException('Invalid diplomatic identifier');
                }
                return $sim->propose($this->principal,$payload['from'],$payload['to'],$payload['action'],$commandId);
            case 'respond':
                self::fields($payload,['responder','proposal_id','accepted']);
                if (!is_string($payload['responder']) || !is_string($payload['proposal_id'])
                    || !is_bool($payload['accepted'])) {
                    throw new \InvalidArgumentException('Invalid response fields');
                }
                return $sim->respond($this->principal,$payload['responder'],
                    $payload['proposal_id'],$payload['accepted'],$commandId);
            case 'ai_decide':
                self::fields($payload,['responder','policy']);
                if (!is_string($payload['responder']) || !is_string($payload['policy'])) {
                    throw new \InvalidArgumentException('Invalid AI target');
                }
                // The AI only decides for explicitly non-playable entities.
                if ($this->actors->isHumanPlayable($payload['responder'])) {
                    throw new \DomainException('AI-only scheduler cannot decide for human-playable state');
                }
                $planner=new AutonomousDiplomacyPlanner();
                $outcomes=$planner->execute($sim,$this->principal,$payload['responder'],$payload['policy']);
                return ['responder'=>$payload['responder'],'decisions'=>$outcomes];
            case 'clock':
                self::fields($payload,['target_day']);
                if (!is_string($payload['target_day'])) {
                    throw new \InvalidArgumentException('Invalid game date');
                }
                return $sim->advanceTo($payload['target_day']);
        }
        throw new \InvalidArgumentException('Unknown operation');
    }

    /** @param array<string,mixed> $row */
    private function rehydrate(array $row,string $world):DiplomaticSimulation
    {
        if (!hash_equals($this->actors->hash(),$row['actor_registry_sha'])) {
            throw new \DomainException('Pinned world actor dataset differs from runtime registry');
        }
        $state=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['world_id']??null)!==$world) {
            throw new \UnexpectedValueException('Wrong persisted world scope');
        }
        $sim=DiplomaticSimulation::fromSnapshot($state,$this->actors);
        if (!hash_equals($row['state_sha'],$sim->hash())) {
            throw new \UnexpectedValueException('Diplomatic snapshot tampering detected');
        }
        return $sim;
    }

    /** @param array<string,mixed> $data @param list<string> $expected */
    private static function fields(array $data,array $expected):void
    {
        $found=array_keys($data);
        sort($found,SORT_STRING);sort($expected,SORT_STRING);
        if ($found!==$expected) throw new \InvalidArgumentException('Unexpected command fields');
    }

    private static function json(mixed $data):string
    {
        return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}
