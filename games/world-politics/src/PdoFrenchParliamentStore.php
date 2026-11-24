<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

require_once __DIR__.'/FrenchParliament.php';
require_once __DIR__.'/PdoFrenchConstitutionalStore.php';
require_once __DIR__.'/WorldMutationFence.php';

/**
 * Server-derived authority. No client supplies its own group rights,
 * deputy mandate, coalition list or scenario voting coefficients.
 */
interface FrenchAssemblyAuthorizer
{
    public function actorId():string;
    public function allows(string $world,string $capability):bool;
    public function controlsDeputy(string $world,string $deputy):bool;
    /** Curated, immutable scenario policy assumptions for a verified law. */
    public function approvedMatter(string $world,string $matter):array;
    /** A signed/verified parliamentary motion roll, NOT JSON from a player. */
    public function verifiedMotionSignatories(string $world,string $motion):array;
    /** Groups in the scenario-certified coalition agreement. */
    public function verifiedCoalitionGroups(string $world,string $coalition):array;
}

/**
 * WP28 durable National Assembly aggregate, single-world InnoDB commit
 * per individual ballot / coalition / sealed signatories / roll call.
 *
 * Read-only link to constitutional state: the pinned sha is rechecked
 * immediately before finalizing, and checked again when applying the 49 vote.
 * The two aggregates are NOT an atomic global database transaction.
 */
final class PdoFrenchParliamentStore
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly FrenchAssemblyAuthorizer $authorizer,
        private readonly PdoFrenchConstitutionalStore $constitution
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }
    public function principalId():string
    {
        return $this->authorizer->actorId();
    }
    public function seed(FrenchParliament $parliament):void
    {
        $world=$parliament->snapshot()['world_id'];
        $this->check($world,'fr.assembly.bootstrap');
        $legal=$this->constitution->read($world);
        if ($legal['state']['assembly_members']!==$parliament->snapshot()['occupied_seats']) {
            throw new \DomainException('Parliament occupied seats disagree with constitutional Assembly composition');
        }
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit($this->pdo,$world,$this->principalId(),'bootstrap');
            $this->pdo->prepare(
                'INSERT INTO wp_fr_parliament_states
                 (world_id,state_json,state_sha,revision,constitutional_world_id)
                 VALUES(?,?,?,0,?)'
            )->execute([
                $world,self::json($parliament->snapshot()),
                $parliament->hash(),$world
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
        $this->check($world,'fr.assembly.read');
        $q=$this->pdo->prepare(
            'SELECT state_json,state_sha,revision,constitutional_world_id
             FROM wp_fr_parliament_states WHERE world_id=?'
        );
        $q->execute([$world]);
        $row=$q->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('French Assembly world absent');
        $model=$this->restore($row,$world);
        return [
            'state'=>$model->snapshot(),
            'etag'=>'"'.$model->hash().'"',
            'revision'=>(int)$row['revision']
        ];
    }

    /**
     * @param array<string,mixed> $request
     * @return array{status:int,result:array<string,mixed>,etag:string,revision:int}
     */
    public function execute(
        string $world,string $operation,array $request,
        string $idempotencyKey,string $ifMatch
    ):array {
        $fields=match($operation) {
            'open_matter'=>['matter_id'],
            'cast_ballot'=>['matter_id','deputy_id','choice'],
            'resolve'=>['matter_id'],
            'seal_signatures'=>['motion_id'],
            'coalition'=>['coalition_id','active'],
            default=>throw new \InvalidArgumentException('Unrecognized French Assembly command')
        };
        self::fields($request,$fields);
        self::id($world);
        if (!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$idempotencyKey)) {
            throw new \InvalidArgumentException('Invalid parliamentary command key');
        }
        $right=match($operation) {
            'open_matter'=>'fr.assembly.agenda',
            'cast_ballot'=>'fr.assembly.vote',
            'resolve'=>'fr.assembly.clerk',
            'seal_signatures'=>'fr.assembly.clerk',
            'coalition'=>'fr.assembly.coalition'
        };
        $this->check($world,$right);
        $actor=$this->principalId();
        $requestSha=hash('sha256',self::json([$operation,$request]));
        $receiptKey=hash('sha256',$operation."\n".$idempotencyKey);
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit($this->pdo,$world,$actor,$operation);
            $lock=$this->pdo->prepare(
                'SELECT state_json,state_sha,revision,constitutional_world_id
                 FROM wp_fr_parliament_states WHERE world_id=? FOR UPDATE'
            );
            $lock->execute([$world]);
            $row=$lock->fetch(\PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('French Assembly world absent');
            $this->check($world,$right);
            if ($this->principalId()!==$actor) {
                throw new \DomainException('Parliamentary authenticated principal changed');
            }
            $model=$this->restore($row,$world);
            $receipt=$this->pdo->prepare(
                'SELECT request_sha,response_json FROM wp_fr_parliament_receipts
                 WHERE world_id=? AND principal_id=? AND receipt_key=?'
            );
            $receipt->execute([$world,$actor,$receiptKey]);
            $previous=$receipt->fetch(\PDO::FETCH_ASSOC);
            if ($previous!==false) {
                if (!hash_equals($previous['request_sha'],$requestSha)) {
                    throw new \DomainException('Parliamentary command key used for different payload');
                }
                $this->checkFineGrained($world,$operation,$request);
                $result=json_decode($previous['response_json'],true,64,JSON_THROW_ON_ERROR);
                $this->pdo->commit();
                return $result;
            }
            $before=$model->hash();
            if (!hash_equals('"'.$before.'"',$ifMatch)) {
                throw new \DomainException('Stale parliamentary revision');
            }
            $this->checkFineGrained($world,$operation,$request);
            $constitutionalSha=null;
            if ($operation==='open_matter' || $operation==='resolve') {
                $constitutional=$this->constitution->read($world);
                $constitutionalSha=trim($constitutional['etag'],'"');
                $this->checkMatterConstitution(
                    $model,$operation,$request['matter_id'],
                    $constitutional['state'],$constitutionalSha
                );
            }
            $result=match($operation) {
                'open_matter'=>$this->openCurated($world,$model,$request['matter_id'],$constitutionalSha),
                'cast_ballot'=>$model->humanBallot(
                    $request['matter_id'],$request['deputy_id'],$request['choice']
                ),
                'resolve'=>$model->resolve($request['matter_id']),
                'seal_signatures'=>$model->signatures(
                    $request['motion_id'],
                    $this->authorizer->verifiedMotionSignatories($world,$request['motion_id'])
                ),
                'coalition'=>$model->coalition(
                    $request['coalition_id'],
                    $this->authorizer->verifiedCoalitionGroups($world,$request['coalition_id']),
                    $request['active']
                )
            };
            $after=$model->hash();
            if (hash_equals($before,$after)) {
                throw new \DomainException('Parliamentary action produced no new state');
            }
            $revision=(int)$row['revision']+1;
            $response=[
                'status'=>in_array($operation,['open_matter','coalition','seal_signatures'],true)?202:200,
                'result'=>$result,'etag'=>'"'.$after.'"','revision'=>$revision
            ];
            $this->pdo->prepare(
                'UPDATE wp_fr_parliament_states SET state_json=?,state_sha=?,revision=?
                 WHERE world_id=?'
            )->execute([self::json($model->snapshot()),$after,$revision,$world]);
            $this->pdo->prepare(
                'INSERT INTO wp_fr_parliament_journal
                 (world_id,revision,principal_id,operation,request_sha,
                  before_sha,after_sha,constitutional_sha,result_json)
                 VALUES(?,?,?,?,?,?,?,?,?)'
            )->execute([
                $world,$revision,$actor,$operation,$requestSha,
                $before,$after,$constitutionalSha,self::json($result)
            ]);
            $this->pdo->prepare(
                'INSERT INTO wp_fr_parliament_receipts
                 (world_id,principal_id,receipt_key,request_sha,response_json)
                 VALUES(?,?,?,?,?)'
            )->execute([
                $world,$actor,$receiptKey,$requestSha,self::json($response)
            ]);
            $this->pdo->commit();
            return $response;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** Trusted constitutional adapter reads the final recorded nominal tally. */
    public function certifiedResult(string $world,string $matterId,string $kind):array
    {
        $this->check($world,'fr.assembly.read');
        $read=$this->read($world);
        $model=FrenchParliament::restore($read['state']);
        $result=$model->result($matterId);
        if ($result['kind']!==$kind) {
            throw new \DomainException('Nominal roll call does not match constitutional legal procedure');
        }
        return $result;
    }

    /** @return list<string> */
    public function certifiedSignatories(string $world,string $motionId):array
    {
        $this->check($world,'fr.assembly.read');
        $read=$this->read($world);
        return FrenchParliament::restore($read['state'])->signatories($motionId);
    }

    private function openCurated(
        string $world,FrenchParliament $model,string $id,string $sha
    ):array {
        $plan=$this->authorizer->approvedMatter($world,$id);
        self::fields($plan,['kind','topic','public_pressure','concessions']);
        return $model->openMatter(
            $id,$plan['kind'],$plan['topic'],
            $plan['public_pressure'],$plan['concessions'],$sha
        );
    }

    private function checkMatterConstitution(
        FrenchParliament $model,string $operation,string $id,
        array $constitutional,string $sourceHash
    ):void {
        self::id($id);
        $agenda=$model->snapshot()['matters'][$id]??null;
        if ($operation==='open_matter') {
            $plan=$this->authorizer->approvedMatter(
                $model->snapshot()['world_id'],$id
            );
            self::fields($plan,['kind','topic','public_pressure','concessions']);
            $kind=$plan['kind'];
        } else {
            if (!is_array($agenda) || $agenda['status']!=='awaiting_ballots'
                || !hash_equals($agenda['source_constitution_sha'],$sourceHash)) {
                throw new \DomainException('Parliamentary ballot was prepared against a different constitutional state');
            }
            $kind=$agenda['kind'];
        }
        $pending=match($kind) {
            'confidence'=>($constitutional['confidence']['statement_id']??null)===$id
                && ($constitutional['confidence']['status']??null)==='awaiting_vote',
            'censure_49_2'=>($constitutional['spontaneous_motions'][$id]['status']??null)==='filed',
            'censure_49_3'=>($constitutional['motions'][$id]['status']??null)==='filed',
            default=>false
        };
        if (!$pending) {
            throw new \DomainException('Parliamentary matter lacks an actually pending constitutional act');
        }
        if ($constitutional['assembly_members']!==$model->snapshot()['occupied_seats']) {
            throw new \DomainException('Assembly member count differs from constitutional rulebook');
        }
    }

    /** @param array<string,mixed> $request */
    private function checkFineGrained(
        string $world,string $operation,array $request
    ):void {
        if ($operation==='cast_ballot' && (
            !$this->authorizer->controlsDeputy($world,$request['deputy_id'])
            || !$this->authorizer->allows($world,'fr.assembly.vote')
        )) {
            throw new \DomainException('Authenticated player does not control that deputy');
        }
    }

    private function restore(array $row,string $world):FrenchParliament
    {
        $state=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($state)
            || ($state['world_id']??null)!==$world
            || $row['constitutional_world_id']!==$world) {
            throw new \UnexpectedValueException('Foreign parliamentary world');
        }
        $model=FrenchParliament::restore($state);
        if (!hash_equals($model->hash(),$row['state_sha'])) {
            throw new \UnexpectedValueException('Parliamentary aggregate SHA is inconsistent');
        }
        return $model;
    }

    private function check(string $world,string $capability):void
    {
        self::id($world);
        $actor=$this->principalId();
        if ($actor==='' || strlen($actor)>128
            || preg_match('/[\x00-\x1f\x7f]/',$actor)
            || !$this->authorizer->allows($world,$capability)) {
            throw new \DomainException('Authenticated Assembly authority required');
        }
    }
    private static function fields(array $v,array $keys):void
    {
        $a=array_keys($v);sort($a,SORT_STRING);sort($keys,SORT_STRING);
        if ($a!==$keys) throw new \InvalidArgumentException('Unapproved parliamentary request fields');
    }
    private static function id(mixed $v):void
    {
        if (!is_string($v) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$v)) {
            throw new \InvalidArgumentException('Invalid parliamentary identifier');
        }
    }
    private static function json(mixed $v):string
    {
        return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
}
