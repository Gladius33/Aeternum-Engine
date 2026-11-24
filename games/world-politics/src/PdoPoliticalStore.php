<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PoliticalRoster.php';
require_once __DIR__.'/WorldPoliticsApi.php';

/**
 * WP03: durable game-specific world aggregate and append-only audit journal.
 *
 * Experimental storage service, NOT a public HTTP adapter. The operator seeds
 * worlds using trusted scenario fixtures. The PoliticsAuthorizer must be bound
 * to a separately verified server-side session + world/office role; no claim
 * in the request JSON can confer privileges.
 */
final class PdoPoliticalStore
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly PoliticsAuthorizer $authorizer
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    /** Trusted administrative bootstrap only; reject existing worlds. */
    public function seed(PoliticalRoster $roster): void
    {
        $snapshot=$roster->snapshot();
        $statement=$this->pdo->prepare(
            'INSERT INTO wp_world_rosters(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)'
        );
        $statement->execute([
            $roster->worldId,$roster->countryCode,self::encode($snapshot),$roster->snapshotHash()
        ]);
    }

    /** @return array{state:array<string,mixed>,etag:string,revision:int} */
    public function read(string $world,string $country): array
    {
        $this->checkPermission($world,$country,'read');
        $query=$this->pdo->prepare(
            'SELECT state_json,state_sha,revision FROM wp_world_rosters WHERE world_id=? AND country_code=?'
        );
        $query->execute([$world,$country]);
        $row=$query->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('No accessible world');
        $roster=self::restore($row,$world,$country);
        return [
            'state'=>$roster->snapshot(),
            'etag'=>'"'.$roster->snapshotHash().'"',
            'revision'=>(int)$row['revision']
        ];
    }

    /**
     * Atomic, durable command admission, state mutation, journal and receipt.
     * A future REST transport should map the public operation to this trusted
     * allowlist and convert failures to structured problem+json responses.
     *
     * @param array<string,mixed> $payload
     * @return array{status:int,result:array<string,mixed>,etag:string,revision:int}
     */
    public function execute(
        string $world,
        string $country,
        string $operation,
        array $payload,
        string $idempotencyKey,
        string $ifMatch
    ): array {
        $abilities=[
            'create_fictional_person'=>'create_fictional_person',
            'initialize_scenario'=>'initialize_scenario',
            'nominate'=>'nominate',
            'resolve_nomination'=>'resolve_nomination',
        ];
        if (!isset($abilities[$operation])) throw new \InvalidArgumentException('Unapproved operation');
        $this->checkPermission($world,$country,$abilities[$operation]);
        if (!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$idempotencyKey)) {
            throw new \InvalidArgumentException('Invalid idempotency key');
        }
        $actor=$this->authorizer->actorId();
        if ($actor==='' || strlen($actor)>128 || preg_match('/[\x00-\x1f\x7f]/',$actor)) {
            throw new \InvalidArgumentException('Invalid authenticated actor');
        }
        $payloadJson=self::encode($payload);
        if (strlen($payloadJson)>16000) throw new \InvalidArgumentException('Oversized command');
        $fingerprint=hash('sha256',$operation."\n".$payloadJson);
        $receiptKey=hash('sha256',$operation."\n".$idempotencyKey);

        $this->pdo->beginTransaction();
        try {
            $query=$this->pdo->prepare(
                'SELECT state_json,state_sha,revision FROM wp_world_rosters WHERE world_id=? AND country_code=? FOR UPDATE'
            );
            $query->execute([$world,$country]);
            $row=$query->fetch(\PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('Missing world');

            // Re-evaluate admission under the country aggregate lock.
            $this->checkPermission($world,$country,$abilities[$operation]);
            if ($actor!==$this->authorizer->actorId()) {
                throw new \DomainException('Actor changed during transaction');
            }
            $prior=$this->pdo->prepare(
                'SELECT request_sha,status_code,result_json FROM wp_command_receipts WHERE world_id=? AND country_code=? AND actor_id=? AND receipt_key=?'
            );
            $prior->execute([$world,$country,$actor,$receiptKey]);
            $receipt=$prior->fetch(\PDO::FETCH_ASSOC);
            if ($receipt!==false) {
                if (!hash_equals($receipt['request_sha'],$fingerprint)) {
                    throw new \DomainException('Idempotency key payload conflict');
                }
                $this->pdo->commit();
                return json_decode($receipt['result_json'],true,64,JSON_THROW_ON_ERROR);
            }

            $roster=self::restore($row,$world,$country);
            $before=$roster->snapshotHash();
            if (!hash_equals('"'.$before.'"',$ifMatch)) {
                throw new \DomainException('Stale state precondition');
            }
            $result=$this->apply($roster,$operation,$payload,$country);
            $after=$roster->snapshotHash();
            $revision=(int)$row['revision']+1;
            $response=[
                'status'=>$operation==='create_fictional_person'?201:($operation==='nominate'?202:200),
                'result'=>$result,
                'etag'=>'"'.$after.'"',
                'revision'=>$revision,
            ];
            $write=$this->pdo->prepare(
                'UPDATE wp_world_rosters SET state_json=?,state_sha=?,revision=? WHERE world_id=? AND country_code=?'
            );
            $write->execute([self::encode($roster->snapshot()),$after,$revision,$world,$country]);
            $journal=$this->pdo->prepare(
                'INSERT INTO wp_world_events(world_id,country_code,revision,actor_id,operation,request_sha,before_sha,after_sha,result_json) VALUES(?,?,?,?,?,?,?,?,?)'
            );
            $journal->execute([
                $world,$country,$revision,$actor,$operation,$fingerprint,$before,$after,self::encode($result)
            ]);
            $receiptWrite=$this->pdo->prepare(
                'INSERT INTO wp_command_receipts(world_id,country_code,actor_id,receipt_key,request_sha,status_code,result_json) VALUES(?,?,?,?,?,?,?)'
            );
            $receiptWrite->execute([
                $world,$country,$actor,$receiptKey,$fingerprint,$response['status'],self::encode($response)
            ]);
            $this->pdo->commit();
            return $response;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function apply(PoliticalRoster $roster,string $operation,array $payload,string $country): array
    {
        switch ($operation) {
            case 'create_fictional_person':
                $person=PoliticalProfileTemplate::normalizeFictional($payload,$country);
                $roster->addPerson($person);
                return ['person_id'=>$person['id']];
            case 'initialize_scenario':
                self::exactFields($payload,['person_id']);
                if (!is_string($payload['person_id'])) throw new \InvalidArgumentException('Invalid person');
                $roster->setHeadOfState($payload['person_id']);
                return ['head_of_state'=>$payload['person_id']];
            case 'nominate':
                self::exactFields($payload,['office','person_id']);
                if (!is_string($payload['office']) || !is_string($payload['person_id'])) {
                    throw new \InvalidArgumentException('Invalid nomination');
                }
                $roster->nominate($payload['office'],$payload['person_id']);
                return ['office'=>$payload['office'],'person_id'=>$payload['person_id'],'state'=>'proposed'];
            case 'resolve_nomination':
                self::exactFields($payload,['office','accepted']);
                if (!is_string($payload['office']) || !is_bool($payload['accepted'])) {
                    throw new \InvalidArgumentException('Invalid resolution');
                }
                $roster->resolveNomination($payload['office'],$payload['accepted']);
                return ['office'=>$payload['office'],'state'=>$payload['accepted']?'accepted':'declined'];
        }
        throw new \InvalidArgumentException('Invalid operation');
    }

    /** @param array<string,mixed> $row */
    private static function restore(array $row,string $world,string $country): PoliticalRoster
    {
        $snapshot=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($snapshot)) throw new \UnexpectedValueException('Invalid persisted snapshot');
        $roster=PoliticalRoster::fromSnapshot($snapshot);
        if ($roster->worldId!==$world || $roster->countryCode!==$country
            || !hash_equals($row['state_sha'],$roster->snapshotHash())) {
            throw new \UnexpectedValueException('Persisted snapshot digest or scope mismatch');
        }
        return $roster;
    }

    private function checkPermission(string $world,string $country,string $ability): void
    {
        if (!$this->authorizer->allows($world,$country,'read')
            || !$this->authorizer->allows($world,$country,$ability)) {
            throw new \DomainException('Unauthorized world or action');
        }
    }

    /** @param array<string,mixed> $input @param list<string> $names */
    private static function exactFields(array $input,array $names): void
    {
        $keys=array_keys($input);
        sort($keys,SORT_STRING);
        sort($names,SORT_STRING);
        if ($keys!==$names) throw new \InvalidArgumentException('Unexpected request fields');
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
}
