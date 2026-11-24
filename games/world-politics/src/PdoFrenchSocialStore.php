<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/FrenchSocialDynamics.php';
require_once __DIR__.'/PdoFrenchConstitutionalStore.php';
require_once __DIR__.'/WorldMutationFence.php';

/**
 * WP24: Source-pinned social effects, transactional and server-driven.
 *
 * The web/mobile API cannot submit demographic coefficients, thresholds,
 * trade or trust assumptions. Those are curated once by an authenticated
 * scenario administrator and pinned to the world and bill ID.
 *
 * Cross-domain constitutional snapshot reference is verified but NOT
 * global-transactional with the social aggregate. The stored source hash
 * ensures each assessment is tied to the specific immutable legal state
 * used, not to whatever legal state exists at a later day.
 */
final class PdoFrenchSocialStore
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly PdoFrenchConstitutionalStore $constitution,
        private readonly FrenchConstitutionalPrincipal $principal
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    /**
     * Trusted scenario bootstrap. Configurations indexed by bill_id:
     * { impacts: sector->int, concessions: sector->int,
     *   context: dimension->int, provenance: fictional_scenario_curated }.
     * All configurations become immutable for the lifetime of a world.
     *
     * @param array<string,array<string,mixed>> $configs
     */
    public function seed(FrenchSocialDynamics $social,array $configs):void
    {
        $world=$social->snapshot()['world_id'];
        $this->authorize($world,'fr.social.admin');
        $this->assertSamePrincipal();
        $this->constitution->read($world);
        if ($configs===[] || count($configs)>500) {
            throw new \InvalidArgumentException('At least one curated law scenario required');
        }
        $this->pdo->beginTransaction();
        try {
            $snapshot=$social->snapshot();
            if ($snapshot['history']!==[]) {
                throw new \DomainException('Seed must start without historical social reports');
            }
            $sql=$this->pdo->prepare(
                'INSERT INTO wp_fr_social_states
                 (world_id,state_json,state_sha,cohort_sha,revision) VALUES(?,?,?,?,0)'
            );
            $sql->execute([
                $world,self::json($snapshot),$social->hash(),$social->cohortHash()
            ]);
            $q=$this->pdo->prepare(
                'INSERT INTO wp_fr_social_assumptions
                 (world_id,bill_id,assumption_json,assumption_sha) VALUES(?,?,?,?)'
            );
            ksort($configs,SORT_STRING);
            foreach ($configs as $bill=>$config) {
                self::id($bill);
                $normalized=self::config($config);
                $serialized=self::json($normalized);
                $q->execute([
                    $world,$bill,$serialized,hash('sha256',$serialized)
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** @return array{state:array<string,mixed>,etag:string,revision:int} */
    public function read(string $world):array
    {
        $this->authorize($world,'fr.social.read');
        $this->assertSamePrincipal();
        $q=$this->pdo->prepare(
            'SELECT state_json,state_sha,cohort_sha,revision
             FROM wp_fr_social_states WHERE world_id=?'
        );
        $q->execute([$world]);
        $row=$q->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('French social world absent');
        $sim=$this->restore($row,$world);
        return [
            'state'=>$sim->snapshot(),
            'etag'=>'"'.$sim->hash().'"',
            'revision'=>(int)$row['revision']
        ];
    }

    /**
     * Compute a law-specific assessment with server-approved assumptions.
     * @return array{status:int,result:array<string,mixed>,etag:string,revision:int}
     */
    public function assess(
        string $world,string $bill,string $key,string $ifMatch
    ):array {
        self::id($world);self::id($bill);
        if (!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key)) {
            throw new \InvalidArgumentException('Invalid social receipt key');
        }
        $this->authorize($world,'fr.social.assess');
        $this->authorize($world,'fr.social.read');
        $this->assertSamePrincipal();
        $principalId=$this->principal->actorId();
        if (!is_string($principalId) || $principalId===''
            || strlen($principalId)>128
            || preg_match('/[\x00-\x1f\x7f]/',$principalId)) {
            throw new \DomainException('Unverified social service identity');
        }
        $hash=hash('sha256','assess49_3'."\n".$bill);
        $receipt=hash('sha256','assess49_3'."\n".$key);

        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit(
                $this->pdo,$world,$principalId,'assess',null
            );
            $lock=$this->pdo->prepare(
                'SELECT state_json,state_sha,cohort_sha,revision
                 FROM wp_fr_social_states WHERE world_id=? FOR UPDATE'
            );
            $lock->execute([$world]);
            $row=$lock->fetch(\PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('French social world absent');
            $this->authorize($world,'fr.social.assess');
            $this->assertSamePrincipal();
            if ($principalId!==$this->principal->actorId()) {
                throw new \DomainException('Social principal identity changed during transaction');
            }
            $sim=$this->restore($row,$world);
            $old=$this->pdo->prepare(
                'SELECT request_sha,response_json FROM wp_fr_social_receipts
                 WHERE world_id=? AND principal_id=? AND receipt_key=?'
            );
            $old->execute([$world,$principalId,$receipt]);
            $earlier=$old->fetch(\PDO::FETCH_ASSOC);
            if ($earlier!==false) {
                if (!hash_equals($earlier['request_sha'],$hash)) {
                    throw new \DomainException('Social receipt key reused for another law');
                }
                $response=json_decode($earlier['response_json'],true,64,JSON_THROW_ON_ERROR);
                $this->pdo->commit();
                return $response;
            }
            $before=$sim->hash();
            if (!hash_equals('"'.$before.'"',$ifMatch)) {
                throw new \DomainException('Stale social assessment ETag');
            }
            $configRow=$this->pdo->prepare(
                'SELECT assumption_json,assumption_sha
                 FROM wp_fr_social_assumptions WHERE world_id=? AND bill_id=?'
            );
            $configRow->execute([$world,$bill]);
            $approved=$configRow->fetch(\PDO::FETCH_ASSOC);
            if ($approved===false) {
                throw new \DomainException('No approved social scenario for that law');
            }
            if (!hash_equals(
                $approved['assumption_sha'],
                hash('sha256',$approved['assumption_json'])
            )) {
                throw new \UnexpectedValueException('Social assumptions failed checksum verification');
            }
            $config=self::config(json_decode(
                $approved['assumption_json'],true,64,JSON_THROW_ON_ERROR
            ));
            $constState=$this->constitution->read($world);
            $legal=FrenchConstitutionalCrisis::restore($constState['state']);
            $report=$sim->assess49_3(
                $legal,$bill,$config['impacts'],
                $config['concessions'],$config['context']
            );
            if ($report['source_constitution_hash']!==$legal->hash()
                || $report['classification']!==
                    'scenario_assumptions_not_survey_or_forecast') {
                throw new \UnexpectedValueException('Inconsistent constitutional social report');
            }
            $after=$sim->hash();
            $revision=(int)$row['revision']+1;
            $response=[
                'status'=>202,'result'=>$report,
                'etag'=>'"'.$after.'"','revision'=>$revision
            ];
            $write=$this->pdo->prepare(
                'UPDATE wp_fr_social_states
                 SET state_json=?,state_sha=?,revision=? WHERE world_id=?'
            );
            $write->execute([self::json($sim->snapshot()),$after,$revision,$world]);
            $j=$this->pdo->prepare(
                'INSERT INTO wp_fr_social_journal
                 (world_id,revision,principal_id,operation,request_sha,
                  before_sha,after_sha,constitution_sha,assumptions_sha,result_json)
                 VALUES(?,?,?,?,?,?,?,?,?,?)'
            );
            $j->execute([
                $world,$revision,$principalId,'assess49_3',$hash,
                $before,$after,$legal->hash(),$approved['assumption_sha'],
                self::json($report)
            ]);
            $saved=$this->pdo->prepare(
                'INSERT INTO wp_fr_social_receipts
                 (world_id,principal_id,receipt_key,request_sha,response_json)
                 VALUES(?,?,?,?,?)'
            );
            $saved->execute([
                $world,$principalId,$receipt,$hash,self::json($response)
            ]);
            $this->pdo->commit();
            return $response;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** @param array<string,mixed> $row */
    private function restore(array $row,string $world):FrenchSocialDynamics
    {
        $snapshot=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($snapshot) || ($snapshot['world_id']??null)!==$world) {
            throw new \UnexpectedValueException('Social archive world mismatch');
        }
        $sim=FrenchSocialDynamics::restore($snapshot);
        if (!hash_equals($row['state_sha'],$sim->hash())
            || !hash_equals($row['cohort_sha'],$sim->cohortHash())) {
            throw new \UnexpectedValueException('Immutable social baseline or checksum changed');
        }
        return $sim;
    }

    private function assertSamePrincipal():void
    {
        if ($this->principal->actorId()!==$this->constitution->principalId()) {
            throw new \DomainException('Social worker cannot borrow another principal legal rights');
        }
    }

    private function authorize(string $world,string $ability):void
    {
        self::id($world);
        if (!$this->principal->allows($world,$ability)) {
            throw new \DomainException('No verified social permission');
        }
    }

    /** @param array<string,mixed> $given */
    private static function config(array $given):array
    {
        $keys=array_keys($given);sort($keys,SORT_STRING);
        if ($keys!==['concessions','context','impacts','provenance']
            || $given['provenance']!=='fictional_scenario_curated'
            || !is_array($given['impacts'])
            || !is_array($given['concessions'])
            || !is_array($given['context'])) {
            throw new \InvalidArgumentException('Unapproved social assumptions format');
        }
        foreach(['impacts','concessions','context'] as $section) {
            ksort($given[$section],SORT_STRING);
        }
        return $given;
    }

    private static function id(string $value):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$value)) {
            throw new \InvalidArgumentException('Invalid world or social law ID');
        }
    }

    private static function json(mixed $data):string
    {
        return json_encode(
            $data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR
        );
    }
}
