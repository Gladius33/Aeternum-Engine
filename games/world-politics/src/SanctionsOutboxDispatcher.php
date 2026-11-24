<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoEconomicStore.php';

/**
 * WP16 — trusted, at-least-once bridge from the transactional diplomatic
 * outbox to the durable economic proposal store.
 *
 * Source event commit and economic commit are separate MariaDB transactions.
 * If the worker crashes between them, the next delivery reuses the same
 * economic idempotency key and therefore cannot create a duplicate policy.
 *
 * The bridge creates only a PROPOSAL. Lawful country approval and the
 * simulation clock remain independent, mandatory gates.
 */
final class SanctionsOutboxDispatcher
{
    public function __construct(
        private readonly \PDO $diplomaticPdo,
        private readonly PdoEconomicStore $economy,
        private readonly EconomicPolicyPrincipal $service
    ) {
        $this->diplomaticPdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->diplomaticPdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    /**
     * @param array{effect_bps:int,low_bps:int,high_bps:int,effective_on:string,kind:string} $assumptions
     * @return array{source_event_id:string,policy_id:string,status:string,revision:int}
     */
    public function deliver(string $world,string $eventId,array $assumptions):array
    {
        self::id($world);self::id($eventId);
        if (!$this->service->allows(
            $world,$this->service->actorId(),'economy.bridge_dispatch'
        ) || $this->economy->principalId()!==$this->service->actorId()) {
            throw new \DomainException('Only the paired trusted bridge worker can dispatch');
        }
        self::fields($assumptions,[
            'effect_bps','low_bps','high_bps','effective_on','kind'
        ]);
        if ($assumptions['kind']!=='scenario_assumption'
            || !is_int($assumptions['effect_bps'])
            || !is_int($assumptions['low_bps'])
            || !is_int($assumptions['high_bps'])
            || !is_string($assumptions['effective_on'])
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$assumptions['effective_on'])
            || ($day=\DateTimeImmutable::createFromFormat(
                '!Y-m-d',$assumptions['effective_on']
            ))===false
            || $day->format('Y-m-d')!==$assumptions['effective_on']) {
            throw new \InvalidArgumentException('Scenario designer must supply explicit bounded assumptions');
        }
        foreach (['effect_bps','low_bps','high_bps'] as $key) {
            if ($assumptions[$key]<0 || $assumptions[$key]>10000) {
                throw new \InvalidArgumentException('Assumption basis points out of range');
            }
        }
        if ($assumptions['low_bps']>$assumptions['effect_bps']
            || $assumptions['effect_bps']>$assumptions['high_bps']) {
            throw new \InvalidArgumentException('Unordered assumption bounds');
        }
        $assumptionsSha=hash('sha256',self::json($assumptions));

        $read=$this->diplomaticPdo->prepare(
            'SELECT o.*, j.after_sha AS journal_after_sha, j.actor_id AS journal_actor_id, j.operation AS journal_operation, j.result_json AS journal_result_json
             FROM wp_diplomatic_outbox o
             INNER JOIN wp_diplomatic_journal j
             ON j.world_id=o.world_id AND j.revision=o.source_revision
             WHERE o.world_id=? AND o.event_id=?'
        );
        $read->execute([$world,$eventId]);
        $row=$read->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('No authenticated pending diplomatic source event');

        if (!hash_equals($row['payload_sha'],hash('sha256',$row['payload_json']))
            || !hash_equals($row['source_state_sha'],$row['journal_after_sha'])
            || $row['source_actor_id']!==$row['journal_actor_id']
            || $row['journal_operation']!=='propose') {
            throw new \UnexpectedValueException('Diplomatic outbox or journal proof mismatch');
        }
        $envelope=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);
        $eventResult=json_decode($row['journal_result_json'],true,32,JSON_THROW_ON_ERROR);
        self::fields($envelope,[
            'version','world_id','event_id','from','to','action',
            'status','source_revision','source_state_sha'
        ]);
        if ($envelope['version']!=='wp16-sanctions-proposal-1'
            || $envelope['world_id']!==$world
            || $envelope['event_id']!==$eventId
            || $envelope['source_revision']!==(int)$row['source_revision']
            || $envelope['source_state_sha']!==$row['source_state_sha']
            || $envelope['action']!=='sanctions_proposal'
            || $envelope['status']!=='pending_domestic_approval'
            || ($eventResult['id']??null)!==$eventId
            || ($eventResult['status']??null)!=='pending_domestic_approval'
            || ($eventResult['action']??null)!=='sanctions_proposal'
            || !is_string($envelope['from'])
            || !is_string($envelope['to'])) {
            throw new \UnexpectedValueException('Source event is not an authorized sanctions proposal');
        }

        // Re-evaluate the sender's grant even when returning old receipts.
        $this->economy->assertBridgeCountryGrant($world,$envelope['from']);

        if ($row['status']==='delivered') {
            if (!is_string($row['assumptions_sha'])
                || !hash_equals($row['assumptions_sha'],$assumptionsSha)
                || !is_string($row['economic_policy_id'])
                || $row['economic_revision']===null) {
                throw new \DomainException('Previously delivered event uses incompatible policy assumptions');
            }
            return [
                'source_event_id'=>$eventId,
                'policy_id'=>$row['economic_policy_id'],
                'status'=>'delivered',
                'revision'=>(int)$row['economic_revision']
            ];
        }
        if ($row['status']!=='pending') {
            throw new \DomainException('Unknown outbox state');
        }

        // A deterministic receipt key permits safe delivery retries even
        // after economy committed while the outbox still says pending.
        $key=self::receiptKey($world,$eventId);
        $economyState=$this->economy->read($world);
        $proposal=$this->economy->execute($world,'propose',[
            'from'=>$envelope['from'],'to'=>$envelope['to'],
            'effect_bps'=>$assumptions['effect_bps'],
            'low_bps'=>$assumptions['low_bps'],
            'high_bps'=>$assumptions['high_bps'],
            'effective_on'=>$assumptions['effective_on']
        ],$key,$economyState['etag']);
        if ($proposal['status']!==202
            || ($proposal['result']['status']??null)!=='proposed') {
            throw new \UnexpectedValueException('Bridge must not authorize economic policy');
        }
        $policyId=$proposal['result']['policy_id'];

        $this->diplomaticPdo->beginTransaction();
        try {
            $update=$this->diplomaticPdo->prepare(
                "UPDATE wp_diplomatic_outbox
                 SET status='delivered',assumptions_sha=?,economic_policy_id=?,
                     economic_revision=?,delivered_at=UTC_TIMESTAMP()
                 WHERE world_id=? AND event_id=? AND status='pending' AND payload_sha=?"
            );
            $update->execute([
                $assumptionsSha,$policyId,$proposal['revision'],
                $world,$eventId,$row['payload_sha']
            ]);
            if ($update->rowCount()!==1) {
                $other=$this->diplomaticPdo->prepare(
                    'SELECT status,assumptions_sha,economic_policy_id,economic_revision
                     FROM wp_diplomatic_outbox WHERE world_id=? AND event_id=? FOR UPDATE'
                );
                $other->execute([$world,$eventId]);
                $current=$other->fetch(\PDO::FETCH_ASSOC);
                if ($current===false || $current['status']!=='delivered'
                    || !hash_equals($current['assumptions_sha']??'',$assumptionsSha)
                    || $current['economic_policy_id']!==$policyId) {
                    throw new \DomainException('Concurrent outbox acknowledgment conflict');
                }
            }
            $this->diplomaticPdo->commit();
        } catch (\Throwable $error) {
            if ($this->diplomaticPdo->inTransaction()) $this->diplomaticPdo->rollBack();
            throw $error;
        }
        return [
            'source_event_id'=>$eventId,
            'policy_id'=>$policyId,
            'status'=>'delivered',
            'revision'=>$proposal['revision']
        ];
    }

    /** Stable economic command receipt key, for crash/retry recovery. */
    public static function receiptKey(string $world,string $eventId):string
    {
        self::id($world);self::id($eventId);
        return 'bridge-'.substr(hash('sha256',
            $world."\n".$eventId."\neconomic_proposal"
        ),0,32);
    }

    /** @param array<string,mixed> $given @param list<string> $expected */
    private static function fields(array $given,array $expected):void
    {
        $actual=array_keys($given);sort($actual,SORT_STRING);sort($expected,SORT_STRING);
        if ($actual!==$expected) throw new \InvalidArgumentException('Unknown outbox envelope fields');
    }

    private static function id(string $value):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$value)) {
            throw new \InvalidArgumentException('Invalid world or event identifier');
        }
    }

    private static function json(mixed $value):string
    {
        return json_encode(
            $value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
        );
    }
}
