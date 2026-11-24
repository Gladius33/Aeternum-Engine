<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

require_once __DIR__.'/FrenchSittingCalendar.php';
require_once __DIR__.'/PdoFrenchConstitutionalStore.php';
require_once __DIR__.'/WorldMutationFence.php';

/** Only trusted parliamentary scenario configuration can approve a ballot slot. */
interface FrenchSittingAuthority extends FrenchConstitutionalPrincipal
{
    public function verifiedCensureVoteAt(string $world,string $motionId):string;
}

/**
 * A persistent, source-labelled Assembly calendar with independently
 * authenticated Conference-of-Presidents decision to schedule each vote.
 * This is a separate aggregate; no false assertion of cross-store atomicity.
 */
final class PdoFrenchSittingCalendarStore
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly FrenchSittingAuthority $authority
    ) {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    public function principalId():string {return $this->authority->actorId();}

    public function seed(FrenchSittingCalendar $calendar):void
    {
 
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit(
                $this->pdo,$world,$this->authority->actorId(),'bootstrap'
            );
       $world=$calendar->snapshot()['world_id'];
        $this->check($world,'fr.calendar.bootstrap');
        $q=$this->pdo->prepare(
            'INSERT INTO wp_fr_sitting_calendars
             (world_id,state_json,state_sha,revision) VALUES(?,?,?,0)'
        );
        $q->execute([$world,self::json($calendar->snapshot()),$calendar->hash()]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** @return array{state:array<string,mixed>,etag:string,revision:int} */
    public function read(string $world):array
    {
        $this->check($world,'fr.calendar.read');
        $q=$this->pdo->prepare(
            'SELECT state_json,state_sha,revision FROM wp_fr_sitting_calendars WHERE world_id=?'
        );
        $q->execute([$world]);
        $row=$q->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('No reviewed parliamentary sitting calendar');
        $calendar=$this->restore($row,$world);
        return [
            'state'=>$calendar->snapshot(),
            'etag'=>'"'.$calendar->hash().'"',
            'revision'=>(int)$row['revision']
        ];
    }

    /**
     * Register an official or scenario-reviewed Conference vote slot only
     * after matching a genuinely filed constitutional motion.
     * The time never comes from client JSON.
     */
    public function schedule(
        string $world,string $motionId,
        PdoFrenchConstitutionalStore $constitution
    ):array {
        $this->check($world,'fr.calendar.schedule');
        self::id($motionId);
        if ($constitution->principalId()!==$this->authority->actorId()) {
            throw new \DomainException('Parliamentary clerk identity mismatch');
        }
        // Already stored legal facts are read outside this calendar write;
        // no in-flight constitutional mutation may be accepted concurrently.
        $legal=$constitution->read($world)['state'];
        $motion=$legal['motions'][$motionId]
            ??$legal['spontaneous_motions'][$motionId]
            ??null;
        if ($motion===null || ($motion['status']??null)!=='filed') {
            throw new \DomainException('Only an actually filed censure can be scheduled');
        }
        $time=$this->authority->verifiedCensureVoteAt($world,$motionId);
        $this->pdo->beginTransaction();
        try {
            WorldMutationFence::admit(
                $this->pdo,$world,$this->authority->actorId(),'schedule',null
            );
            $this->check($world,'fr.calendar.schedule');
            $q=$this->pdo->prepare(
                'SELECT state_json,state_sha,revision
                 FROM wp_fr_sitting_calendars WHERE world_id=? FOR UPDATE'
            );
            $q->execute([$world]);
            $row=$q->fetch(\PDO::FETCH_ASSOC);
            if ($row===false) throw new \DomainException('No reviewed parliamentary sitting calendar');
            $calendar=$this->restore($row,$world);
            $before=$calendar->hash();
            $slot=$calendar->schedule(
                $motionId,$motion['filed_at'],$motion['earliest_vote_at'],$time
            );
            if ($calendar->hash()===$before) {
                $this->pdo->commit();
                return $slot;
            }
            $after=$calendar->hash();
            $revision=(int)$row['revision']+1;
            $w=$this->pdo->prepare(
                'UPDATE wp_fr_sitting_calendars
                 SET state_json=?,state_sha=?,revision=? WHERE world_id=?'
            );
            $w->execute([
                self::json($calendar->snapshot()),$after,$revision,$world
            ]);
            $j=$this->pdo->prepare(
                'INSERT INTO wp_fr_sitting_journal
                 (world_id,revision,motion_id,scheduled_epoch,actor_id,
                  before_sha,after_sha) VALUES(?,?,?,?,?,?,?)'
            );
            $j->execute([
                $world,$revision,$motionId,$slot['vote_at'],
                $this->authority->actorId(),$before,$after
            ]);
            $this->pdo->commit();
            return $slot;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function assertClock(string $world,array $legal,int $target):void
    {
        $calendar=$this->model($world);
        $calendar->assertClockMayAdvance($legal,$target);
    }

    public function assertVote(string $world,array $legal,string $id,int $instant):void
    {
        $calendar=$this->model($world);
        $calendar->assertVoteAllowed($legal,$id,$instant);
    }

    private function model(string $world):FrenchSittingCalendar
    {
        $this->check($world,'fr.calendar.read');
        $q=$this->pdo->prepare(
            'SELECT state_json,state_sha,revision
             FROM wp_fr_sitting_calendars WHERE world_id=?'
        );
        $q->execute([$world]);
        $row=$q->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('No pinned parliamentary calendar');
        return $this->restore($row,$world);
    }

    private function restore(array $row,string $world):FrenchSittingCalendar
    {
        $snapshot=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);
        if (!is_array($snapshot) || ($snapshot['world_id']??null)!==$world) {
            throw new \UnexpectedValueException('Foreign French sitting calendar');
        }
        $calendar=FrenchSittingCalendar::restore($snapshot);
        if (!hash_equals($row['state_sha'],$calendar->hash())) {
            throw new \UnexpectedValueException('Tampered sitting schedule SHA');
        }
        return $calendar;
    }

    private function check(string $world,string $ability):void
    {
        self::id($world);
        if ($this->authority->actorId()===''
            || !$this->authority->allows($world,$ability)) {
            throw new \DomainException('Verified parliamentary sitting authority required');
        }
    }
    private static function id(string $id):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)) {
            throw new \InvalidArgumentException('Invalid world or censure identifier');
        }
    }
    private static function json(mixed $payload):string
    {
        return json_encode(
            $payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
        );
    }
}
