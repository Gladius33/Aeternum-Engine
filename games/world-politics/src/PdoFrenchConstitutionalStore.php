<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/FrenchConstitutionalCrisis.php';
require_once __DIR__.'/WorldMutationFence.php';

/**
 * WP21 — durable France-specific constitutional event log / REST command store.
 *
 * Authorization, Council minutes, motion signatories and secret roll-call
 * votes are server-derived from a trusted FrenchConstitutionalPrincipal,
 * never trusted from mobile or web request bodies.
 */
final class PdoFrenchConstitutionalStore
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly FrenchConstitutionalPrincipal $principal,
        private readonly ?PdoFrenchSittingCalendarStore $sittingCalendar=null
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    public function hasSittingCalendar():bool
    {
        return $this->sittingCalendar!==null;
    }

    public function principalId():string
    {
        return $this->principal->actorId();
    }

    public function seed(FrenchConstitutionalCrisis $sim):void
    {
        $state=$sim->snapshot();
        $this->assertCalendarIdentity($state['world_id']);
        $this->pdo->prepare(
            'INSERT INTO wp_fr_constitutional_states
            (world_id,state_json,state_sha,revision,sitting_calendar_required)
             VALUES(?,?,?,0,?)'
        )->execute([
            $state['world_id'],self::json($state),$sim->hash(),
            $this->sittingCalendar===null?0:1
        ]);
    }

    /** @return array{state:array<string,mixed>,etag:string,revision:int} */
    public function read(string $world):array
    {
        $this->check($world,'fr.constitution.read');
        $q=$this->pdo->prepare(
            'SELECT state_json,state_sha,revision,sitting_calendar_required FROM wp_fr_constitutional_states WHERE world_id=?'
        );
        $q->execute([$world]);
        $row=$q->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('French constitutional world absent');
        $sim=$this->restore($row,$world);
        $this->assertCalendarConfiguration($row);
        $this->assertCalendarIdentity($world);
        return [
            'state'=>$sim->snapshot(),
            'etag'=>'"'.$sim->hash().'"',
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
        self::id($world);
        $fields=match($operation) {
            'engage'=>['bill_id','category','at'],
            'file_motion'=>['motion_id','at'],
            'vote_motion'=>['motion_id','at'],
            'engage_confidence'=>['statement_id','kind','at'],
            'vote_confidence'=>['statement_id','at'],
            'file_spontaneous'=>['motion_id','at'],
            'vote_spontaneous'=>['motion_id','at'],
            'clock'=>['at'],
            'session'=>['session_id','at'],
            default=>throw new \InvalidArgumentException('Unsupported French constitutional command')
        };
        self::fields($request,$fields);
        if (!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$idempotencyKey)) {
            throw new \InvalidArgumentException('Invalid constitutional idempotency key');
        }
        foreach ($request as $value) {
            if (!is_string($value) || strlen($value)>200) {
                throw new \InvalidArgumentException('Constitutional inputs must be bounded strings');
            }
        }
        $right=match($operation) {
            'engage'=>'fr.49_3.engage',
            'file_motion'=>'fr.censure.file',
            'vote_motion'=>'fr.censure.vote',
            'engage_confidence'=>'fr.49_1.engage',
            'vote_confidence'=>'fr.49_1.vote',
            'file_spontaneous'=>'fr.49_2.file',
            'vote_spontaneous'=>'fr.49_2.vote',
            'clock'=>'fr.constitution.clock',
            'session'=>'fr.constitution.session'
        };
        $this->check($world,'fr.constitution.read');
        $this->check($world,$right);
        if (in_array($operation,[
            'engage_confidence','vote_confidence',
            'file_spontaneous','vote_spontaneous'
        ],true) && !$this->principal instanceof FrenchParliamentaryPrincipal) {
            throw new \DomainException('Authenticated parliamentary clerk required');
        }
        $principalId=$this->principal->actorId();
        if ($principalId==='' || strlen($principalId)>128
            || preg_match('/[\x00-\x1f\x7f]/',$principalId)) {
            throw new \DomainException('Invalid trusted service principal');
        }
        $this->checkInstant($world,$operation,$request['at']);
        ksort($request,SORT_STRING);
        $sha=hash('sha256',self::json([$operation,$request]));
        $keyHash=hash('sha256',$operation."\n".$idempotencyKey);
        $commandId='fr-'.substr(hash('sha256',
            $world."\n".$principalId."\n".$operation."\n".$idempotencyKey
        ),0,32);
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit(
                $this->pdo,$world,$principalId,$operation,$operation==='clock' ? (new \DateTimeImmutable('@'.FrenchConstitutionalCrisis::instant($request['at'])))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d') : null
            );
            $read=$this->pdo->prepare(
                'SELECT state_json,state_sha,revision,sitting_calendar_required FROM wp_fr_constitutional_states WHERE world_id=? FOR UPDATE'
            );
            $read->execute([$world]);
            $row=$read->fetch(\PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('French constitutional world absent');
            $this->check($world,'fr.constitution.read');
            $this->check($world,$right);
            $this->checkInstant($world,$operation,$request['at']);
            if ($this->principal->actorId()!==$principalId) {
                throw new \DomainException('Constitutional identity changed during transaction');
            }
            $sim=$this->restore($row,$world);
            $this->assertCalendarConfiguration($row);
            $this->assertCalendarIdentity($world);
            $retry=$this->pdo->prepare(
                'SELECT request_sha,response_json FROM wp_fr_constitutional_receipts
                 WHERE world_id=? AND actor_id=? AND receipt_key=?'
            );
            $retry->execute([$world,$principalId,$keyHash]);
            $prior=$retry->fetch(\PDO::FETCH_ASSOC);
            if ($prior!==false) {
                if (!hash_equals($prior['request_sha'],$sha)) {
                    throw new \DomainException('Conflicting constitutional receipt');
                }
                $receipt=json_decode($prior['response_json'],true,64,JSON_THROW_ON_ERROR);
                $this->pdo->commit();
                return $receipt;
            }
            $before=$sim->hash();
            if (!hash_equals('"'.$before.'"',$ifMatch)) {
                throw new \DomainException('Stale French constitutional ETag');
            }
            $this->checkSittingAgenda($world,$sim,$operation,$request);
            $result=match($operation) {
                'engage'=>$sim->engage(
                    $this->principal,$request['bill_id'],$request['category'],
                    $request['at'],$commandId
                ),
                'file_motion'=>$sim->fileMotion(
                    $this->principal,$request['motion_id'],$request['at'],$commandId
                ),
                'vote_motion'=>$sim->vote(
                    $this->principal,$request['motion_id'],$request['at'],$commandId
                ),
                'engage_confidence'=>$sim->engageConfidence(
                    $this->parliamentClerk(),$request['statement_id'],
                    $request['kind'],$request['at'],$commandId
                ),
                'vote_confidence'=>$sim->voteConfidence(
                    $this->parliamentClerk(),$request['statement_id'],
                    $request['at'],$commandId
                ),
                'file_spontaneous'=>$sim->fileSpontaneousMotion(
                    $this->parliamentClerk(),$request['motion_id'],
                    $request['at'],$commandId
                ),
                'vote_spontaneous'=>$sim->voteSpontaneousMotion(
                    $this->parliamentClerk(),$request['motion_id'],
                    $request['at'],$commandId
                ),
                'clock'=>$sim->advance(
                    $this->principal,$request['at'],$commandId
                ),
                'session'=>$sim->openSession(
                    $this->principal,$request['session_id'],$request['at'],$commandId
                ),
            };
            $after=$sim->hash();
            $revision=(int)$row['revision']+1;
            $receipt=[
                'status'=>in_array($operation,[
                    'engage','file_motion','engage_confidence','file_spontaneous'
                ],true)?202:200,
                'result'=>$result,'etag'=>'"'.$after.'"','revision'=>$revision
            ];
            $write=$this->pdo->prepare(
                'UPDATE wp_fr_constitutional_states
                 SET state_json=?,state_sha=?,revision=? WHERE world_id=?'
            );
            $write->execute([self::json($sim->snapshot()),$after,$revision,$world]);
            $journal=$this->pdo->prepare(
                'INSERT INTO wp_fr_constitutional_journal
                 (world_id,revision,actor_id,operation,request_sha,before_sha,after_sha,result_json)
                 VALUES(?,?,?,?,?,?,?,?)'
            );
            $journal->execute([
                $world,$revision,$principalId,$operation,$sha,$before,$after,self::json($result)
            ]);
            $receipts=$this->pdo->prepare(
                'INSERT INTO wp_fr_constitutional_receipts
                 (world_id,actor_id,receipt_key,request_sha,response_json) VALUES(?,?,?,?,?)'
            );
            $receipts->execute([
                $world,$principalId,$keyHash,$sha,self::json($receipt)
            ]);
            $this->pdo->commit();
            return $receipt;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function restore(array $row,string $world):FrenchConstitutionalCrisis
    {
        $snapshot=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($snapshot) || ($snapshot['world_id']??null)!==$world) {
            throw new \UnexpectedValueException('Foreign constitutional state detected');
        }
        $sim=FrenchConstitutionalCrisis::restore($snapshot);
        if (!hash_equals($row['state_sha'],$sim->hash())) {
            throw new \UnexpectedValueException('French constitutional snapshot hash does not match');
        }
        return $sim;
    }

    private function assertCalendarConfiguration(array $row):void
    {
        if (!array_key_exists('sitting_calendar_required',$row)
            || !in_array((int)$row['sitting_calendar_required'],[0,1],true)
            || ((int)$row['sitting_calendar_required']===1)
               !==($this->sittingCalendar!==null)) {
            throw new \DomainException(
                'French constitutional calendar differs from pinned world configuration'
            );
        }
    }

    private function assertCalendarIdentity(string $world):void
    {
        if ($this->sittingCalendar===null) return;
        if ($this->sittingCalendar->principalId()!==$this->principal->actorId()) {
            throw new \DomainException('French sitting calendar principal mismatch');
        }
        // Also ensure that the approved schedule exists and its SHA is valid.
        $this->sittingCalendar->read($world);
    }

    /** @param array<string,mixed> $request */
    private function checkSittingAgenda(
        string $world,FrenchConstitutionalCrisis $constitution,
        string $operation,array $request
    ):void {
        if ($this->sittingCalendar===null) return;
        $snapshot=$constitution->snapshot();
        if ($operation==='clock') {
            $this->sittingCalendar->assertClock(
                $world,$snapshot,FrenchConstitutionalCrisis::instant($request['at'])
            );
        } elseif ($operation==='vote_motion' || $operation==='vote_spontaneous') {
            $this->sittingCalendar->assertVote(
                $world,$snapshot,$request['motion_id'],
                FrenchConstitutionalCrisis::instant($request['at'])
            );
        }
    }

    private function parliamentClerk():FrenchParliamentaryPrincipal
    {
        if (!$this->principal instanceof FrenchParliamentaryPrincipal) {
            throw new \DomainException('Authenticated parliamentary clerk required');
        }
        return $this->principal;
    }

    private function checkInstant(string $world,string $operation,string $claimed):void
    {
        $verified=$this->principal->verifiedInstant($world,$operation);
        if (FrenchConstitutionalCrisis::instant($claimed)
            !==FrenchConstitutionalCrisis::instant($verified)) {
            throw new \DomainException('Unverified constitutional simulation time');
        }
    }

    private function check(string $world,string $ability):void
    {
        self::id($world);
        if (!$this->principal->allows($world,$ability)) {
            throw new \DomainException('Constitutional permission denied');
        }
    }

    /** @param array<string,mixed> $request @param list<string> $expected */
    private static function fields(array $request,array $expected):void
    {
        $got=array_keys($request);
        sort($got,SORT_STRING);sort($expected,SORT_STRING);
        if ($got!==$expected) throw new \InvalidArgumentException('Unrecognized constitutional command fields');
    }

    private static function id(string $world):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$world)) {
            throw new \InvalidArgumentException('Invalid French constitutional world');
        }
    }

    private static function json(mixed $data):string
    {
        return json_encode(
            $data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR
        );
    }
}
