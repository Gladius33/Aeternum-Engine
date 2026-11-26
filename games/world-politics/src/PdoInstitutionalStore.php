<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/InstitutionalSimulation.php';
require_once __DIR__.'/WorldPoliticsApi.php';
require_once __DIR__.'/WorldMutationFence.php';

/**
 * Session-derived institutional roles, NOT an HTTP header or JSON attribute.
 * A trusted implementation must verify actual control of the office in world.
 */
interface InstitutionalActorAuthorizer extends PoliticsAuthorizer
{
    public function roleFor(string $world,string $country,string $action):string;
}

/** Only a trusted simulation evidence service implements this extension. */
interface ComparativeInstitutionalAuthorizer extends InstitutionalActorAuthorizer
{
    public function verifiedAccountabilityEvidence(string $world,string $country,string $action,string $reference):array;
}

interface ElectoralInstitutionalAuthorizer extends InstitutionalActorAuthorizer
{
    public function verifiedFormationEvidence(string $world,string $country,string $reference):array;
}

interface RevisionInstitutionalAuthorizer extends InstitutionalActorAuthorizer
{
    public function verifiedRevisionEvidence(string $world,string $country,string $reference):array;
}

interface LegislativeInstitutionalAuthorizer extends InstitutionalActorAuthorizer
{
    public function verifiedLegislativeEvidence(string $world,string $country,string $reference):array;
}

/**
 * Durable institution aggregate. Independent from the character roster until
 * a combined world transaction / Aeternum admission root is implemented.
 *
 * Experimental only: no token parser or production-safe role connector yet.
 */
final class PdoInstitutionalStore
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly InstitutionalActorAuthorizer $auth
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    /** Server-verified identity used by the global institutional clock. */
    public function principalId():string
    {
        return $this->auth->actorId();
    }

    /** Trusted scenario bootstrap. Both roster and reviewed rules must exist. */
    public function seed(InstitutionalSimulation $simulation):void
    {
        $state=$simulation->snapshot();
 
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit(
                $this->pdo,$state['world_id'],$this->auth->actorId(),'bootstrap'
            );

        $this->pdo->prepare(
            'INSERT INTO wp_institutional_states(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)'
        )->execute([
            $state['world_id'],$state['country_code'],self::encode($state),$simulation->hash()
        ]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** @return array{state:array<string,mixed>,etag:string,revision:int} */
    public function read(string $world,string $country):array
    {
        $this->allow($world,$country,'read_institutions');
        $stmt=$this->pdo->prepare(
            'SELECT state_json,state_sha,revision FROM wp_institutional_states WHERE world_id=? AND country_code=?'
        );
        $stmt->execute([$world,$country]);
        $row=$stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('Institutional state missing');
        $sim=self::restore($row,$world,$country);
        return ['state'=>$sim->snapshot(),'etag'=>'"'.$sim->hash().'"','revision'=>(int)$row['revision']];
    }

    /**
     * Run a legal country-specific decision, actor role resolved only by auth.
     * @param array<string,mixed> $payload
     * @return array{status:int,result:array<string,mixed>,etag:string,revision:int}
     */
    public function decide(
        string $world,string $country,string $action,array $payload,
        string $idempotencyKey,string $ifMatch
    ):array {
        if (!in_array($action,['record_consultation','submit_bill','dissolve_lower_house','request_dissolution','request_cabinet_appointment','hold_election','accountability_file','accountability_vote','accountability_resolve','electoral_coalition','electoral_certify','revision_draft','revision_evidence','legislation_draft','legislation_evidence'],true)) {
            throw new \DomainException('Unimplemented institutional decision');
        }
        return $this->write($world,$country,$action,$payload,$idempotencyKey,$ifMatch,false);
    }

    /**
     * Clock advances require separate trusted, operator-level admission.
     * @return array{status:int,result:array<string,mixed>,etag:string,revision:int}
     */
    public function advance(
        string $world,string $country,string $targetDay,
        string $idempotencyKey,string $ifMatch
    ):array {
        return $this->write($world,$country,'advance_clock',['target_day'=>$targetDay],$idempotencyKey,$ifMatch,true);
    }

    /** @param array<string,mixed> $payload */
    private function write(
        string $world,string $country,string $action,array $payload,
        string $key,string $expected,bool $clock
    ):array {
        $this->allow($world,$country,$action);
        if (!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key)) {
            throw new \InvalidArgumentException('Invalid idempotency key');
        }
        $actor=$this->auth->actorId();
        if ($actor==='' || strlen($actor)>128 || preg_match('/[\x00-\x1f\x7f]/',$actor)) {
            throw new \InvalidArgumentException('Bad authenticated actor');
        }
        $role=$clock?'simulation_clock':$this->auth->roleFor($world,$country,$action);
        if (!is_string($role) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$role)) {
            throw new \DomainException('No verified institutional role');
        }
        $json=self::encode($payload);
        if (strlen($json)>8000) throw new \InvalidArgumentException('Oversized decision');
        $fingerprint=hash('sha256',$action."\n".$role."\n".$json);
        $receiptKey=hash('sha256',$action."\n".$key);
        // Internal IDs always come from server-supplied principal/command key.
        $commandId='a'.substr(hash('sha256',$world."\n".$country."\n".$actor."\n".$key),0,32);
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit(
                $this->pdo,$world,$actor,$action==='advance_clock' ? 'clock' : $action,$clock ? ($payload['target_day']??null) : null
            );
            $stmt=$this->pdo->prepare(
                'SELECT state_json,state_sha,revision FROM wp_institutional_states WHERE world_id=? AND country_code=? FOR UPDATE'
            );
            $stmt->execute([$world,$country]);
            $row=$stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('Institutional state missing');
            $this->allow($world,$country,$action);
            if ($this->auth->actorId()!==$actor
                || (!$clock && $this->auth->roleFor($world,$country,$action)!==$role)) {
                throw new \DomainException('Actor or institutional office revoked');
            }
            $retry=$this->pdo->prepare(
                'SELECT request_sha,response_json FROM wp_institutional_receipts WHERE world_id=? AND country_code=? AND actor_id=? AND receipt_key=?'
            );
            $retry->execute([$world,$country,$actor,$receiptKey]);
            $prior=$retry->fetch(\PDO::FETCH_ASSOC);
            if ($prior!==false) {
                if (!hash_equals($prior['request_sha'],$fingerprint)) {
                    throw new \DomainException('Conflicting idempotency key');
                }
                $this->pdo->commit();
                return json_decode($prior['response_json'],true,64,JSON_THROW_ON_ERROR);
            }
            $simulation=self::restore($row,$world,$country);
            $before=$simulation->hash();
            if (!hash_equals('"'.$before.'"',$expected)) {
                throw new \DomainException('Stale institutional precondition');
            }
            if($action==='legislation_draft'){
                $result=$simulation->legislation($role,$action,$payload,$commandId);
            }elseif($action==='legislation_evidence'){
                if(!$this->auth instanceof LegislativeInstitutionalAuthorizer||array_keys($payload)!==['evidence_id']
                    ||!is_string($payload['evidence_id'])||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$payload['evidence_id']))
                    throw new \DomainException('Verified legislative reference required');
                $e=$this->auth->verifiedLegislativeEvidence($world,$country,$payload['evidence_id']);
                if(($e['evidence_id']??null)!==$payload['evidence_id'])throw new \DomainException('Legislative evidence mismatch');
                $result=$simulation->legislation($role,$action,$e,$commandId);
            }elseif($action==='revision_draft'){
                $result=$simulation->revision($role,$action,$payload,$commandId);
            }elseif($action==='revision_evidence'){
                if(!$this->auth instanceof RevisionInstitutionalAuthorizer||array_keys($payload)!==['evidence_id']
                    ||!is_string($payload['evidence_id'])||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$payload['evidence_id']))
                    throw new \DomainException('Verified revision evidence reference required');
                $e=$this->auth->verifiedRevisionEvidence($world,$country,$payload['evidence_id']);
                if(($e['evidence_id']??null)!==$payload['evidence_id'])throw new \DomainException('Revision evidence identity mismatch');
                $result=$simulation->revision($role,$action,$e,$commandId);
            }elseif ($action==='electoral_coalition') {
                $result=$simulation->electoral($role,$action,$payload,$commandId);
            } elseif($action==='electoral_certify') {
                if(!$this->auth instanceof ElectoralInstitutionalAuthorizer||array_keys($payload)!==['evidence_id']
                    ||!is_string($payload['evidence_id'])||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$payload['evidence_id']))
                    throw new \DomainException('Verified formation reference required');
                $e=$this->auth->verifiedFormationEvidence($world,$country,$payload['evidence_id']);
                if(($e['evidence_id']??null)!==$payload['evidence_id'])throw new \DomainException('Formation evidence mismatch');
                $result=$simulation->electoral($role,$action,$e,$commandId);
            } elseif (str_starts_with($action,'accountability_')) {
                if (!$this->auth instanceof ComparativeInstitutionalAuthorizer
                    || array_keys($payload)!==['evidence_id'] || !is_string($payload['evidence_id'])
                    || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$payload['evidence_id']))
                    throw new \DomainException('Certified evidence reference required');
                $e=$this->auth->verifiedAccountabilityEvidence($world,$country,$action,$payload['evidence_id']);
                if (($e['evidence_id']??null)!==$payload['evidence_id'])throw new \DomainException('Evidence identity mismatch');
                $result=$simulation->accountability($role,$action,$e,$commandId);
            } else {
                $opinion=null;
                if($clock&&($simulation->snapshot()['revision_requests']??[])!==[]){
                    require_once __DIR__.'/PublicOpinion.php';
                    $q=$this->pdo->prepare('SELECT state_json,state_sha FROM wp_opinion_states WHERE world_id=?');$q->execute([$world]);$o=$q->fetch(\PDO::FETCH_ASSOC);
                    if($o===false)throw new \DomainException('Missing committed opinion for revision');
                    $x=PublicOpinion::restore(json_decode($o['state_json'],true,64,JSON_THROW_ON_ERROR));
                    $v=$x->snapshot();if(!hash_equals($o['state_sha'],$x->hash())||$v['world_id']!==$world||$v['day']!==$simulation->snapshot()['day'])throw new \DomainException('Invalid prior opinion state');
                    $opinion=['world_id'=>$world,'day'=>$v['day'],'approval'=>$v['approval'],'sha'=>$x->hash()];
                }
                $result=$clock ? $simulation->advanceTo($payload['target_day'],$opinion)
                    :$simulation->decide($role,$action,$payload,$commandId);
            }
            $after=$simulation->hash();
            $revision=(int)$row['revision']+1;
            $response=['status'=>$clock?200:202,'result'=>$result,
                'etag'=>'"'.$after.'"','revision'=>$revision];
            $this->pdo->prepare(
                'UPDATE wp_institutional_states SET state_json=?,state_sha=?,revision=? WHERE world_id=? AND country_code=?'
            )->execute([self::encode($simulation->snapshot()),$after,$revision,$world,$country]);
            $this->pdo->prepare(
                'INSERT INTO wp_institutional_journal(world_id,country_code,revision,actor_id,actor_role,operation,request_sha,before_sha,after_sha,result_json) VALUES(?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $world,$country,$revision,$actor,$role,$action,$fingerprint,$before,$after,self::encode($result)
            ]);
            $this->pdo->prepare(
                'INSERT INTO wp_institutional_receipts(world_id,country_code,actor_id,receipt_key,request_sha,response_json) VALUES(?,?,?,?,?,?)'
            )->execute([$world,$country,$actor,$receiptKey,$fingerprint,self::encode($response)]);
            $this->pdo->commit();
            return $response;
        } catch(\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string,mixed> $row */
    private static function restore(array $row,string $world,string $country):InstitutionalSimulation
    {
        $data=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \UnexpectedValueException('Invalid institutional JSON');
        $aggregate=InstitutionalSimulation::restore($data);
        $state=$aggregate->snapshot();
        if ($state['world_id']!==$world || $state['country_code']!==$country
            || !hash_equals($row['state_sha'],$aggregate->hash())) {
            throw new \UnexpectedValueException('Institutional snapshot scope/hash mismatch');
        }
        return $aggregate;
    }

    private function allow(string $world,string $country,string $action):void
    {
        if (!$this->auth->allows($world,$country,'read_institutions')
            || !$this->auth->allows($world,$country,$action)) {
            throw new \DomainException('Institutional command forbidden');
        }
    }
    private static function encode(mixed $value):string
    {
        return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}
