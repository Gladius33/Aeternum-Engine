<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/OrganizationSimulation.php';
require_once __DIR__.'/AutonomousOrganizationPlanner.php';
require_once __DIR__.'/WorldMutationFence.php';

/**
 * WP10: country-owned and AI decisions both go through a durable,
 * serializable organization-world command boundary.
 *
 * Not an HTTP server. The injected principal MUST come from trusted server
 * authentication; the client never supplies its own identity or office.
 */
final class PdoOrganizationStore
{
    /**
     * @param list<array<string,mixed>> $charters Trusted, version-pinned documents
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly GeopoliticalActorRegistry $actors,
        private readonly array $charters,
        private readonly DiplomaticPrincipal $principal
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    /** Administrative scenario bootstrap, never exposed to public REST. */
    /** Identity bound server-side; never derived from request JSON. */
    public function principalId():string
    {
        return $this->principal->actorId();
    }

    public function seed(OrganizationSimulation $sim):void
    {
        $state=$sim->snapshot();
 
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit(
                $this->pdo,$state['world_id'],$this->principal->actorId(),'bootstrap'
            );

        $validated=OrganizationSimulation::fromSnapshot($state,$this->actors,$this->charters);
        $stmt=$this->pdo->prepare(
            'INSERT INTO wp_organization_states(world_id,state_json,state_sha,actors_sha,charters_sha,revision) VALUES(?,?,?,?,?,0)'
        );
        $stmt->execute([
            $state['world_id'],self::json($state),$validated->hash(),
            $state['actors_sha'],$state['charters_sha']
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
        $this->canRead($world);
        $stmt=$this->pdo->prepare(
            'SELECT state_json,state_sha,actors_sha,charters_sha,revision FROM wp_organization_states WHERE world_id=?'
        );
        $stmt->execute([$world]);
        $row=$stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('Missing organization world');
        $sim=$this->restore($row,$world);
        return [
            'state'=>$sim->snapshot(),'etag'=>'"'.$sim->hash().'"',
            'revision'=>(int)$row['revision']
        ];
    }

    /**
     * Atomic command, snapshot, audit event, durable idempotency receipt.
     *
     * @param array<string,mixed> $payload
     * @return array{status:int,result:array<string,mixed>,etag:string,revision:int}
     */
    public function execute(
        string $world,string $operation,array $payload,
        string $idempotencyKey,string $ifMatch
    ):array {
        if (!in_array($operation,['apply','vote','formalize','withdraw','clock','ai_vote','eu_intent','eu_evidence'],true)) {
            throw new \InvalidArgumentException('Unrecognized organization operation');
        }
        self::identifier($world);
        if (!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$idempotencyKey)) {
            throw new \InvalidArgumentException('Invalid idempotency key');
        }
        if (array_is_list($payload) && $payload!==[]) {
            throw new \InvalidArgumentException('Expected JSON object');
        }
        $actorId=$this->principal->actorId();
        if (!is_string($actorId) || $actorId==='' || strlen($actorId)>128
            || preg_match('/[\x00-\x1f\x7f]/',$actorId)) {
            throw new \InvalidArgumentException('Invalid authenticated principal');
        }
        $input=self::json($payload);
        if (strlen($input)>16000) {
            throw new \InvalidArgumentException('Organization payload too large');
        }
        $requestSha=hash('sha256',$operation."\n".$input);
        $receiptKey=hash('sha256',$operation."\n".$idempotencyKey);
        $commandId='op-'.substr(hash('sha256',
            $world."\n".$actorId."\n".$operation."\n".$idempotencyKey
        ),0,32);

        $this->canRead($world);
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit(
                $this->pdo,$world,$actorId,$operation,$operation==='clock' ? ($payload['target_day']??null) : null
            );
            $locked=$this->pdo->prepare(
                'SELECT state_json,state_sha,actors_sha,charters_sha,revision FROM wp_organization_states WHERE world_id=? FOR UPDATE'
            );
            $locked->execute([$world]);
            $row=$locked->fetch(\PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('Missing organization world');
            $this->canRead($world);
            if ($actorId!==$this->principal->actorId()) {
                throw new \DomainException('Authenticated principal changed during transaction');
            }
            // Validate and re-authorize BEFORE returning any old receipt:
            // revoked human/AI actors cannot retrieve prior accepted commands.
            $this->authorize($world,$operation,$payload);
            $prior=$this->pdo->prepare(
                'SELECT request_sha,response_json FROM wp_organization_receipts WHERE world_id=? AND actor_id=? AND receipt_key=?'
            );
            $prior->execute([$world,$actorId,$receiptKey]);
            $old=$prior->fetch(\PDO::FETCH_ASSOC);
            if ($old!==false) {
                if (!hash_equals($old['request_sha'],$requestSha)) {
                    throw new \DomainException('Conflicting idempotency payload');
                }
                $response=json_decode($old['response_json'],true,64,JSON_THROW_ON_ERROR);
                $this->pdo->commit();
                return $response;
            }

            $sim=$this->restore($row,$world);
            $before=$sim->hash();
            if (!hash_equals('"'.$before.'"',$ifMatch)) {
                throw new \DomainException('Stale state precondition');
            }
            $result=$this->apply($sim,$operation,$payload,$commandId);
            $after=$sim->hash();
            $revision=(int)$row['revision']+1;
            $response=[
                'status'=>in_array($operation,['apply','withdraw','eu_intent'],true)?202:200,
                'result'=>$result,'etag'=>'"'.$after.'"','revision'=>$revision
            ];
            $update=$this->pdo->prepare(
                'UPDATE wp_organization_states SET state_json=?,state_sha=?,revision=? WHERE world_id=?'
            );
            $update->execute([self::json($sim->snapshot()),$after,$revision,$world]);
            $journal=$this->pdo->prepare(
                'INSERT INTO wp_organization_journal(world_id,revision,actor_id,operation,request_sha,before_sha,after_sha,result_json) VALUES(?,?,?,?,?,?,?,?)'
            );
            $journal->execute([
                $world,$revision,$actorId,$operation,$requestSha,
                $before,$after,self::json($result)
            ]);
            $receipt=$this->pdo->prepare(
                'INSERT INTO wp_organization_receipts(world_id,actor_id,receipt_key,request_sha,response_json) VALUES(?,?,?,?,?)'
            );
            $receipt->execute([
                $world,$actorId,$receiptKey,$requestSha,self::json($response)
            ]);
            $this->pdo->commit();
            return $response;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string,mixed> $payload */
    private function authorize(string $world,string $operation,array $payload):void
    {
        $fields=match ($operation) {
            'apply'=>['organization','applicant'],
            'vote'=>['organization','voter','application_id','approve'],
            'withdraw'=>['organization','actor'],
            'formalize'=>['organization','application_id'],
            'clock'=>['target_day'],
            'ai_vote'=>['voter','policy'],
            'eu_intent'=>[], 'eu_evidence'=>['kind','data'],
        };
        self::fields($payload,$fields);
        if($operation==='eu_intent'){
            if(!$this->principal->allows($world,'state_fr','organizations.eu_intent'))throw new \DomainException('Actor not authorized for this organization action');
            return;
        }
        if($operation==='eu_evidence'){
            if(!$this->principal->allows($world,$this->principal->actorId(),'organizations.eu_certify'))throw new \DomainException('Trusted treaty service required');
            if(!is_string($payload['kind'])||!is_array($payload['data']))throw new \InvalidArgumentException('Invalid EU evidence');return;
        }
        if ($operation==='ai_vote') {
            if (!is_string($payload['voter']) || !is_string($payload['policy'])) {
                throw new \InvalidArgumentException('Invalid AI voting input');
            }
            $entity=$this->actors->get($payload['voter']);
            if ($entity['controller']!=='ai_only'
                || !$this->principal->allows($world,$this->principal->actorId(),'organizations.ai_scheduler')
                || !$this->principal->allows($world,$payload['voter'],'organizations.vote')
                || !$this->principal->allows($world,$payload['voter'],'organizations.ai_controller')) {
                throw new \DomainException('Trusted AI voting service required');
            }
            return;
        }
        if ($operation==='formalize' || $operation==='clock') {
            $ability=$operation==='formalize'?'organizations.formalize':'organizations.clock';
            if (!$this->principal->allows($world,$this->principal->actorId(),$ability)) {
                throw new \DomainException('Trusted treaty service required');
            }
            return;
        }
        $actor=match ($operation) {
            'apply'=>$payload['applicant'],
            'vote'=>$payload['voter'],
            'withdraw'=>$payload['actor'],
        };
        if (!is_string($actor)) throw new \InvalidArgumentException('Invalid organization actor');
        $reference=$this->actors->get($actor);
        $ability='organizations.'.$operation;
        if (!$this->principal->allows($world,$actor,$ability)
            || ($reference['controller']==='ai_only'
                && !$this->principal->allows($world,$actor,'organizations.ai_controller'))) {
            throw new \DomainException('Actor not authorized for this organization action');
        }
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function apply(
        OrganizationSimulation $sim,string $operation,array $payload,string $commandId
    ):array {
        if ($operation==='vote' || $operation==='formalize') {
            $appId=self::str($payload['application_id']);
            $org=self::str($payload['organization']);
            $application=$sim->snapshot()['applications'][$appId]??null;
            if (!is_array($application) || $application['organization']!==$org) {
                throw new \DomainException('Application belongs to another organization');
            }
        }
        return match ($operation) {
            'apply'=>$sim->apply($this->principal,
                self::str($payload['organization']),self::str($payload['applicant']),$commandId),
            'vote'=>$sim->vote($this->principal,
                self::str($payload['voter']),self::str($payload['application_id']),
                self::bool($payload['approve']),$commandId),
            'formalize'=>$sim->formalize($this->principal,
                self::str($payload['application_id']),$commandId),
            'withdraw'=>$sim->requestWithdrawal($this->principal,
                self::str($payload['organization']),self::str($payload['actor']),$commandId),
            'clock'=>$sim->advanceTo($this->principal,
                self::str($payload['target_day']),$commandId),
            'eu_intent'=>$sim->euIntent($this->principal,$commandId),
            'eu_evidence'=>$sim->euEvidence($this->principal,$payload['kind'],$payload['data'],$commandId),
            'ai_vote'=>[
                'voter'=>self::str($payload['voter']),
                'decisions'=>(new AutonomousOrganizationPlanner())->execute(
                    $sim,$this->actors,$this->principal,
                    self::str($payload['voter']),self::str($payload['policy'])
                ),
            ],
        };
    }

    /** @param array<string,mixed> $row */
    private function restore(array $row,string $world):OrganizationSimulation
    {
        $state=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['world_id']??null)!==$world) {
            throw new \UnexpectedValueException('Persisted world mismatch');
        }
        if (!hash_equals($this->actors->hash(),$row['actors_sha'])
            || !is_string($state['charters_sha'])
            || !hash_equals($state['charters_sha'],$row['charters_sha'])) {
            throw new \DomainException('Pinned ruleset or registry changed');
        }
        $sim=OrganizationSimulation::fromSnapshot($state,$this->actors,$this->charters);
        if (!hash_equals($row['state_sha'],$sim->hash())) {
            throw new \UnexpectedValueException('Persisted organization state modified');
        }
        return $sim;
    }

    private function canRead(string $world):void
    {
        self::identifier($world);
        if (!$this->principal->allows($world,$this->principal->actorId(),'organizations.read')) {
            throw new \DomainException('No world membership');
        }
    }

    /** @param array<string,mixed> $record @param list<string> $expected */
    private static function fields(array $record,array $expected):void
    {
        $actual=array_keys($record);sort($actual);sort($expected);
        if ($actual!==$expected) throw new \InvalidArgumentException('Unexpected action fields');
    }
    private static function identifier(string $id):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)) {
            throw new \InvalidArgumentException('Invalid world id');
        }
    }
    private static function str(mixed $value):string
    {
        if (!is_string($value)) throw new \InvalidArgumentException('Expected text input');
        return $value;
    }
    private static function bool(mixed $value):bool
    {
        if (!is_bool($value)) throw new \InvalidArgumentException('Expected boolean vote');
        return $value;
    }
    private static function json(mixed $value):string
    {
        return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}
