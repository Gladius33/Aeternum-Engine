<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/PdoDiplomaticStore.php';
require_once __DIR__.'/PdoOrganizationStore.php';
require_once __DIR__.'/PdoEconomicStore.php';
require_once __DIR__.'/PdoInstitutionalStore.php';
require_once __DIR__.'/PdoFrenchConstitutionalStore.php';
require_once __DIR__.'/WorldMutationFence.php';

/**
 * WP17/WP18: recoverable WORLD clock for diplomacy, organizations,
 * the economy and an optional pinned roster of national institutions.
 *
 * - A single durable pending intent precedes all subsystem writes.
 * - Every subsystem clocks through its existing server-granted command API.
 * - A crash after any subsystem commit can be resumed from the pending intent.
 * - Only when all three reach target is committed_day published.
 * - This is a *saga*, NOT an atomic global database transaction.
 *
 * Each injected store MUST own a different PDO connection from coordinator.
 * All stores are provisioned with the same trusted scheduler actor ID.
 */
final class WorldClockCoordinator
{
    private const STEPS=['organizations','diplomacy','economy'];

    /** @var list<string> */
    private readonly array $countries;
    private readonly ?PdoInstitutionalStore $institutions;
    private readonly ?PdoFrenchConstitutionalStore $franceParliament;

    public function __construct(
        private readonly \PDO $pdo,
        private readonly PdoOrganizationStore $organizations,
        private readonly PdoDiplomaticStore $diplomacy,
        private readonly PdoEconomicStore $economy,
        private readonly DiplomaticPrincipal $principal,
        ?PdoInstitutionalStore $institutions=null,
        array $countries=[],
        ?PdoFrenchConstitutionalStore $franceParliament=null
    ) {
        if (!array_is_list($countries) || count($countries)>193
            || count(array_unique($countries))!==count($countries)) {
            throw new \InvalidArgumentException('Invalid national institution clock roster');
        }
        foreach ($countries as $country) {
            if (!is_string($country)
                || !preg_match('/^[A-Z]{2}$/D',$country)
                || !InstitutionalRulebook::hasRules($country)) {
                throw new \DomainException('National clock needs an explicitly reviewed country rulebook');
            }
        }
        if ($countries!==[] && $institutions===null) {
            throw new \InvalidArgumentException('Country clocks require institutional store');
        }
        sort($countries,SORT_STRING);
        if ($franceParliament!==null && !in_array('FR',$countries,true)) {
            throw new \DomainException(
                'French parliamentary calendar requires FR among pinned institutional countries'
            );
        }
        $this->countries=$countries;
        $this->institutions=$institutions;
        $this->franceParliament=$franceParliament;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }

    /**
     * Trusted bootstrap; validates all three simulation days beforehand.
     * This is not a public endpoint or a player action.
     */
    public function seed(string $world,string $startingDay):void
    {
        self::id($world);self::day($startingDay);
        $this->trust($world,'world.clock.bootstrap');
        $days=$this->componentStates($world);
        foreach ($days as $name=>$record) {
            if ($record['day']!==$startingDay) {
                throw new \DomainException('New world has mismatched '.$name.' starting date');
            }
        }
        $this->assertCountryDaysAt($world,$startingDay);
        $this->assertFranceDayAt($world,$startingDay,false);
        $this->pdo->beginTransaction();
        try {
            $q=$this->pdo->prepare(
                'INSERT INTO wp_world_clock(world_id,committed_day,revision) VALUES(?,?,0)'
            );
            $q->execute([$world,$startingDay]);
            WorldMutationFence::seed($this->pdo,$world);
            $roster=$this->pdo->prepare(
                'INSERT INTO wp_world_clock_countries(world_id,country_code) VALUES(?,?)'
            );
            foreach ($this->countries as $code) {
                $roster->execute([$world,$code]);
            }
            if ($this->franceParliament!==null) {
                $this->pdo->prepare(
                    "INSERT INTO wp_world_clock_fr_parliament
                     (world_id,country_code,timezone_id)
                     VALUES(?,'FR','Europe/Paris')"
                )->execute([$world]);
            }
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /**
     * Non-privileged game clients may read this progress through a trusted
     * API facade, but they cannot request a tick. In-progress state stays
     * visible as "advancing" until all three domains finish.
     *
     * @return array{state:array<string,mixed>,etag:string}
     */
    public function read(string $world):array
    {
        self::id($world);
        $this->trust($world,'world.clock.read');
        $row=$this->row($world,false);
        if ($row===null) throw new \DomainException('No world clock');
        $this->assertCountryRoster($world);
        $this->assertFranceRoster($world);
        $state=$this->publicState($row);
        return ['state'=>$state,'etag'=>self::etag($row)];
    }

    /**
     * @return array{status:int,result:array<string,mixed>,etag:string}
     */
    public function advance(
        string $world,string $targetDay,string $idempotencyKey,string $ifMatch
    ):array {
        self::id($world);self::day($targetDay);
        if (!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$idempotencyKey)) {
            throw new \InvalidArgumentException('Invalid world tick idempotency key');
        }
        $this->trust($world,'world.clock.advance');
        $key=$this->commandKey($world,$idempotencyKey);
        $this->pdo->beginTransaction();
        try {
            // Order: fence row FIRST, then authoritative world-clock row.
            // This serializes the transition intent with every admitted
            // player, autonomous worker and government command.
            $fence=WorldMutationFence::lockCoordinator($this->pdo,$world);
            $row=$this->row($world,true);
            if ($row===null) throw new \DomainException('No world clock');
            $this->assertCountryRoster($world);
            $this->assertFranceRoster($world);

            // Revocations must be checked even when replaying a receipt.
            $this->trust($world,'world.clock.advance');
            if ($row['pending_key']===null
                && $row['last_command_key']!==null
                && hash_equals($row['last_command_key'],$key)) {
                if ($fence['state']!=='idle') {
                    throw new \DomainException('Completed world clock receipt has an active write fence');
                }
                if ($row['last_target_day']!==$targetDay) {
                    throw new \DomainException('Idempotency key reused with another target date');
                }
                $result=json_decode($row['last_receipt_json'],true,32,JSON_THROW_ON_ERROR);
                $this->pdo->commit();
                return $result;
            }

            if ($row['pending_key']!==null) {
                if (!hash_equals($row['pending_key'],$key)
                    || $row['pending_day']!==$targetDay) {
                    throw new \DomainException('Another world tick is awaiting recovery');
                }
                WorldMutationFence::begin(
                    $this->pdo,$world,$fence,$key,$targetDay,
                    $this->principal->actorId()
                );
                // A retry does not need the old, now-stale If-Match value.
                $this->pdo->commit();
            } else {
                if ($fence['state']!=='idle') {
                    throw new \DomainException('Write fence and world clock transition disagree');
                }
                if (!hash_equals(self::etag($row),$ifMatch)) {
                    throw new \DomainException('Stale world clock precondition');
                }
                if ($targetDay<=$row['committed_day']) {
                    throw new \DomainException('Game clock must advance strictly forward');
                }
                $start=new \DateTimeImmutable($row['committed_day'],new \DateTimeZone('UTC'));
                $target=new \DateTimeImmutable($targetDay,new \DateTimeZone('UTC'));
                if ($start->diff($target)->days>366) {
                    throw new \InvalidArgumentException('A world tick may not exceed 366 days');
                }
                $this->assertDomainsAt($world,$row['committed_day']);
                $this->assertCountryDaysAt($world,$row['committed_day']);
                $this->assertFranceDayAt($world,$row['committed_day'],false);

                // Both records transition in a single transaction. A crash
                // can never publish pending game time without fencing writers.
                WorldMutationFence::begin(
                    $this->pdo,$world,$fence,$key,$targetDay,
                    $this->principal->actorId()
                );
                $update=$this->pdo->prepare(
                    'UPDATE wp_world_clock SET pending_day=?,pending_key=? WHERE world_id=?'
                );
                $update->execute([$targetDay,$key,$world]);
                $this->pdo->commit();
            }
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        // Each step can independently commit in its own PDO transaction.
        // This method may throw at any step without losing pending intent.
        foreach (self::STEPS as $step) {
            $this->completeStep($world,$targetDay,$key,$step);
            if ($step==='organizations') {
                foreach ($this->countries as $country) {
                    $this->completeCountry($world,$targetDay,$key,$country);
                    if ($country==='FR' && $this->franceParliament!==null) {
                        $this->completeFranceParliament($world,$targetDay,$key);
                    }
                }
            }
        }

        $this->pdo->beginTransaction();
        try {
            $fence=WorldMutationFence::lockCoordinator($this->pdo,$world);
            $row=$this->row($world,true);
            if ($row===null || $row['pending_key']!==$key
                || $row['pending_day']!==$targetDay) {
                throw new \DomainException('World clock intent changed before publication');
            }
            $this->trust($world,'world.clock.advance');
            $this->assertDomainsAt($world,$targetDay);
            $this->assertCountryDaysAt($world,$targetDay);
            $this->assertFranceDayAt($world,$targetDay,true);
            if ($this->franceParliament!==null
                && !$this->franceStepComplete($world,$key)) {
                throw new \DomainException('French constitutional parliamentary checkpoint missing');
            }
            $progress=$this->completedSteps($world,$key);
            if ($this->completedCountryNames($world,$key)!==$this->countries) {
                throw new \DomainException('National institutions must reach the same day before publication');
            }
            if (count($progress)!==count(self::STEPS)) {
                throw new \DomainException('All domains must be checkpointed before publication');
            }

            $revision=(int)$row['revision']+1;
            $result=[
                'status'=>200,
                'result'=>[
                    'world_id'=>$world,
                    'committed_day'=>$targetDay,
                    'revision'=>$revision,
                    'completed_subsystems'=>self::STEPS,
                    'countries'=>$this->countries,
                    'france_parliament_enabled'=>$this->franceParliament!==null,
                    'coherent'=>true
                ],
                'etag'=>self::etagFrom($world,$targetDay,null,$revision)
            ];
            $update=$this->pdo->prepare(
                'UPDATE wp_world_clock
                 SET committed_day=?,pending_day=NULL,pending_key=NULL,
                     revision=?,last_command_key=?,last_target_day=?,
                     last_receipt_json=? WHERE world_id=?'
            );
            $update->execute([
                $targetDay,$revision,$key,$targetDay,self::json($result),$world
            ]);
            WorldMutationFence::finish(
                $this->pdo,$world,$fence,$key,$targetDay,
                $this->principal->actorId()
            );
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** @return array<string,array{day:string,etag:string}> */
    private function componentStates(string $world):array
    {
        $result=[];
        foreach (self::STEPS as $step) {
            $store=$this->store($step);
            $record=$store->read($world);
            $day=$record['state']['day']??null;
            if (!is_string($day)) {
                throw new \UnexpectedValueException('Domain has no canonical game date: '.$step);
            }
            self::day($day);
            $result[$step]=['day'=>$day,'etag'=>$record['etag']];
        }
        return $result;
    }

    private function assertDomainsAt(string $world,string $day):void
    {
        foreach ($this->componentStates($world) as $step=>$state) {
            if ($state['day']!==$day) {
                throw new \DomainException(
                    'World clock domain not at required date: '.$step
                );
            }
        }
    }

    private function completeStep(
        string $world,string $targetDay,string $key,string $step
    ):void {
        $this->pdo->beginTransaction();
        try {
            $row=$this->row($world,true);
            if ($row===null || $row['pending_day']!==$targetDay
                || $row['pending_key']!==$key) {
                throw new \DomainException('World tick cannot advance without matching durable intent');
            }
            $this->trust($world,'world.clock.advance');
            $store=$this->store($step);
            $current=$store->read($world);
            $day=$current['state']['day']??null;
            if ($day!==$row['committed_day'] && $day!==$targetDay) {
                throw new \DomainException('Externally modified domain time: '.$step);
            }
            $existing=$this->stepRow($world,$key,$step);
            if ($existing!==null) {
                if ($day!==$targetDay
                    || !hash_equals($existing['after_sha'],trim($current['etag'],'"'))) {
                    throw new \UnexpectedValueException('Previously completed step was changed: '.$step);
                }
                $this->pdo->commit();
                return;
            }

            $recovered=$day===$targetDay;
            if (!$recovered) {
                $receipt=$store->execute(
                    $world,'clock',['target_day'=>$targetDay],
                    self::stepKey($world,$targetDay,$step),
                    $current['etag']
                );
                if ($receipt['status']!==200
                    || ($receipt['result']['day']??null)!==$targetDay) {
                    throw new \UnexpectedValueException('Domain clock did not confirm target day');
                }
            }
            $final=$store->read($world);
            if (($final['state']['day']??null)!==$targetDay) {
                throw new \UnexpectedValueException('Domain did not commit target day');
            }
            $insert=$this->pdo->prepare(
                'INSERT INTO wp_world_clock_steps(world_id,command_key,subsystem,before_day,after_day,after_sha,recovered) VALUES(?,?,?,?,?,?,?)'
            );
            $insert->execute([
                $world,$key,$step,$row['committed_day'],$targetDay,
                trim($final['etag'],'"'),$recovered?1:0
            ]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /**
     * The national roster is part of immutable world configuration. A
     * restarted worker cannot silently omit an inconvenient country.
     */
    private function assertCountryRoster(string $world):void
    {
        $q=$this->pdo->prepare(
            'SELECT country_code FROM wp_world_clock_countries WHERE world_id=? ORDER BY country_code'
        );
        $q->execute([$world]);
        $stored=$q->fetchAll(\PDO::FETCH_COLUMN,0);
        if ($stored!==$this->countries) {
            throw new \DomainException('National institution roster differs from pinned world configuration');
        }
    }

    private function assertCountryDaysAt(string $world,string $date):void
    {
        if ($this->countries===[]) return;
        if ($this->institutions===null) {
            throw new \DomainException('National institution store is missing');
        }
        foreach ($this->countries as $country) {
            $row=$this->institutions->read($world,$country);
            if (($row['state']['day']??null)!==$date) {
                throw new \DomainException('Country institution time differs: '.$country);
            }
        }
    }

    /** @return list<string> */
    private function completedCountryNames(string $world,string $commandKey):array
    {
        $q=$this->pdo->prepare(
            'SELECT country_code FROM wp_world_clock_country_steps WHERE world_id=? AND command_key=? ORDER BY country_code'
        );
        $q->execute([$world,$commandKey]);
        return $q->fetchAll(\PDO::FETCH_COLUMN,0);
    }

    private function completeCountry(
        string $world,string $targetDay,string $key,string $country
    ):void {
        if ($this->institutions===null) {
            throw new \DomainException('National institution store missing');
        }
        $this->pdo->beginTransaction();
        try {
            $row=$this->row($world,true);
            if ($row===null || $row['pending_day']!==$targetDay
                || $row['pending_key']!==$key) {
                throw new \DomainException('Country event has no matching world tick');
            }
            $this->assertCountryRoster($world);
            $this->trust($world,'world.clock.advance');
            $current=$this->institutions->read($world,$country);
            $day=$current['state']['day']??null;
            if ($day!==$row['committed_day'] && $day!==$targetDay) {
                throw new \DomainException('Country institution externally advanced: '.$country);
            }
            $check=$this->pdo->prepare(
                'SELECT after_sha FROM wp_world_clock_country_steps
                 WHERE world_id=? AND command_key=? AND country_code=?'
            );
            $check->execute([$world,$key,$country]);
            $done=$check->fetch(\PDO::FETCH_ASSOC);
            if ($done!==false) {
                if ($day!==$targetDay
                    || !hash_equals($done['after_sha'],trim($current['etag'],'"'))) {
                    throw new \UnexpectedValueException('Recorded country institution checkpoint differs');
                }
                $this->pdo->commit();
                return;
            }
            $recovered=$day===$targetDay;
            if (!$recovered) {
                $receipt=$this->institutions->advance(
                    $world,$country,$targetDay,
                    self::stepKey($world,$targetDay,'institution-'.$country),
                    $current['etag']
                );
                if ($receipt['status']!==200
                    || ($receipt['result']['day']??null)!==$targetDay) {
                    throw new \UnexpectedValueException('Country clock failed its effective date');
                }
            }
            $final=$this->institutions->read($world,$country);
            if (($final['state']['day']??null)!==$targetDay) {
                throw new \UnexpectedValueException('Country clock not at target date');
            }
            $q=$this->pdo->prepare(
                'INSERT INTO wp_world_clock_country_steps
                 (world_id,country_code,command_key,before_day,after_day,after_sha,recovered)
                 VALUES(?,?,?,?,?,?,?)'
            );
            $q->execute([
                $world,$country,$key,$row['committed_day'],$targetDay,
                trim($final['etag'],'"'),$recovered?1:0
            ]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /**
     * A separate world-configuration row pins the France constitutional
     * calendar. A restarted coordinator may not silently omit it.
     */
    private function assertFranceRoster(string $world):void
    {
        $q=$this->pdo->prepare(
            'SELECT country_code,timezone_id
             FROM wp_world_clock_fr_parliament WHERE world_id=?'
        );
        $q->execute([$world]);
        $saved=$q->fetch(\PDO::FETCH_ASSOC);
        if (($saved!==false)!==($this->franceParliament!==null)) {
            throw new \DomainException(
                'French constitutional clock configuration differs from pinned world'
            );
        }
        if ($saved!==false
            && ($saved['country_code']!=='FR'
                || $saved['timezone_id']!=='Europe/Paris')) {
            throw new \UnexpectedValueException('Unsupported French clock timezone or country');
        }
    }

    private static function frenchEndInstant(string $day):string
    {
        self::day($day);
        return (new \DateTimeImmutable(
            $day,new \DateTimeZone('Europe/Paris')
        ))->setTime(23,59,59)->format('Y-m-d\TH:i:sP');
    }

    private static function frenchDay(int $epoch):string
    {
        if ($epoch<0 || $epoch>4102444800) {
            throw new \UnexpectedValueException('Out-of-range French constitutional time');
        }
        return (new \DateTimeImmutable('@'.$epoch))
            ->setTimezone(new \DateTimeZone('Europe/Paris'))
            ->format('Y-m-d');
    }

    private function assertFranceDayAt(
        string $world,string $day,bool $requireEndOfDay
    ):void {
        if ($this->franceParliament===null) return;
        $read=$this->franceParliament->read($world);
        $epoch=$read['state']['now']??null;
        if (!is_int($epoch) || self::frenchDay($epoch)!==$day) {
            throw new \DomainException(
                'French parliamentary calendar differs from authoritative game day'
            );
        }
        if ($requireEndOfDay
            && $epoch!==FrenchConstitutionalCrisis::instant(
                self::frenchEndInstant($day)
            )) {
            throw new \DomainException(
                'French parliament has not finished its end-of-day calendar work'
            );
        }
    }

    private function franceStepComplete(string $world,string $key):bool
    {
        $q=$this->pdo->prepare(
            'SELECT 1 FROM wp_world_clock_fr_parliament_steps
             WHERE world_id=? AND command_key=?'
        );
        $q->execute([$world,$key]);
        return $q->fetchColumn()!==false;
    }

    /**
     * Authorized, Paris-local parliamentary end-of-day tick with durable
     * recovery after constitution committed but the checkpoint did not.
     * Each store owns its own PDO transaction.
     */
    private function completeFranceParliament(
        string $world,string $targetDay,string $key
    ):void {
        if ($this->franceParliament===null) {
            throw new \DomainException('French parliamentary store is not configured');
        }
        $targetStamp=self::frenchEndInstant($targetDay);
        $targetEpoch=FrenchConstitutionalCrisis::instant($targetStamp);
        $this->pdo->beginTransaction();
        try {
            $row=$this->row($world,true);
            if ($row===null || $row['pending_day']!==$targetDay
                || $row['pending_key']!==$key) {
                throw new \DomainException('Missing matching parliamentary world tick intent');
            }
            $this->assertFranceRoster($world);
            $this->trust($world,'world.clock.advance');
            $source=$this->franceParliament->read($world);
            $now=$source['state']['now']??null;
            if (!is_int($now) || $now>$targetEpoch) {
                throw new \DomainException('French parliamentary time is after requested day');
            }
            $sourceDay=self::frenchDay($now);
            if ($sourceDay!==$row['committed_day']
                && $sourceDay!==$targetDay) {
                throw new \DomainException('French parliamentary clock externally advanced');
            }
            $saved=$this->pdo->prepare(
                'SELECT after_instant,after_sha
                 FROM wp_world_clock_fr_parliament_steps
                 WHERE world_id=? AND command_key=?'
            );
            $saved->execute([$world,$key]);
            $checkpoint=$saved->fetch(\PDO::FETCH_ASSOC);
            if ($checkpoint!==false) {
                if ((int)$checkpoint['after_instant']!==$targetEpoch
                    || $now!==$targetEpoch
                    || !hash_equals(
                        $checkpoint['after_sha'],trim($source['etag'],'"')
                    )) {
                    throw new \UnexpectedValueException(
                        'French parliamentary checkpoint no longer matches legal state'
                    );
                }
                $this->pdo->commit();
                return;
            }

            $recovered=$now===$targetEpoch;
            if (!$recovered) {
                // The trusted French principal must independently certify
                // the same clock instant; a request body cannot choose it.
                $response=$this->franceParliament->execute(
                    $world,'clock',['at'=>$targetStamp],
                    self::stepKey($world,$targetDay,'france-parliament'),
                    $source['etag']
                );
                if ($response['status']!==200
                    || ($response['result']['now']??null)!==$targetEpoch) {
                    throw new \UnexpectedValueException(
                        'French parliamentary domain did not complete the target instant'
                    );
                }
            }
            $after=$this->franceParliament->read($world);
            if (($after['state']['now']??null)!==$targetEpoch) {
                throw new \UnexpectedValueException('French parliamentary day not closed');
            }
            $write=$this->pdo->prepare(
                'INSERT INTO wp_world_clock_fr_parliament_steps
                 (world_id,command_key,before_day,after_day,
                  after_instant,after_sha,recovered)
                 VALUES(?,?,?,?,?,?,?)'
            );
            $write->execute([
                $world,$key,$row['committed_day'],$targetDay,
                $targetEpoch,trim($after['etag'],'"'),$recovered?1:0
            ]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function store(string $step):object
    {
        return match($step) {
            'organizations'=>$this->organizations,
            'diplomacy'=>$this->diplomacy,
            'economy'=>$this->economy,
            default=>throw new \InvalidArgumentException('Unknown world clock subsystem')
        };
    }

    private function trust(string $world,string $ability):void
    {
        $principalId=$this->principal->actorId();
        if ($principalId==='' || strlen($principalId)>128
            || !$this->principal->allows($world,$principalId,$ability)) {
            throw new \DomainException('Trusted world clock service required');
        }
        // A non-verified client cannot forward a tick using other
        // principals' privileged domain stores.
        if ($this->organizations->principalId()!==$principalId
            || $this->diplomacy->principalId()!==$principalId
            || $this->economy->principalId()!==$principalId
            || ($this->institutions!==null
                && $this->institutions->principalId()!==$principalId)
            || ($this->franceParliament!==null
                && $this->franceParliament->principalId()!==$principalId)) {
            throw new \DomainException('Mismatch between clock and verified domain principals');
        }
    }

    /** @return array<string,mixed>|null */
    private function row(string $world,bool $lock):?array
    {
        $sql='SELECT * FROM wp_world_clock WHERE world_id=?'.($lock?' FOR UPDATE':'');
        $stmt=$this->pdo->prepare($sql);
        $stmt->execute([$world]);
        $row=$stmt->fetch(\PDO::FETCH_ASSOC);
        return $row===false?null:$row;
    }

    /** @return array<string,mixed>|null */
    private function stepRow(string $world,string $key,string $step):?array
    {
        $stmt=$this->pdo->prepare(
            'SELECT * FROM wp_world_clock_steps WHERE world_id=? AND command_key=? AND subsystem=?'
        );
        $stmt->execute([$world,$key,$step]);
        $row=$stmt->fetch(\PDO::FETCH_ASSOC);
        return $row===false?null:$row;
    }

    /** @return list<string> */
    private function completedSteps(string $world,string $key):array
    {
        $q=$this->pdo->prepare(
            'SELECT subsystem FROM wp_world_clock_steps WHERE world_id=? AND command_key=?'
        );
        $q->execute([$world,$key]);
        $steps=$q->fetchAll(\PDO::FETCH_COLUMN,0);
        sort($steps,SORT_STRING);
        $expected=self::STEPS;sort($expected,SORT_STRING);
        if ($steps!==$expected) return [];
        return $steps;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function publicState(array $row):array
    {
        $pending=$row['pending_day']!==null;
        $completed=$pending?$this->completedStepNames(
            $row['world_id'],$row['pending_key']
        ):[];
        return [
            'world_id'=>$row['world_id'],
            'committed_day'=>$row['committed_day'],
            'pending_day'=>$row['pending_day'],
            'status'=>$pending?'advancing':'coherent',
            'revision'=>(int)$row['revision'],
            'completed_subsystems'=>$completed,
            'countries'=>$this->countries,
            'completed_country_clocks'=>$pending
                ?$this->completedCountryNames($row['world_id'],$row['pending_key']):[],
            'france_parliament_enabled'=>$this->franceParliament!==null,
            'france_parliament_completed'=>$pending
                && $this->franceParliament!==null
                && $this->franceStepComplete($row['world_id'],$row['pending_key']),
            'coherent'=>!$pending
        ];
    }

    /** @return list<string> */
    private function completedStepNames(string $world,string $key):array
    {
        $q=$this->pdo->prepare(
            'SELECT subsystem FROM wp_world_clock_steps WHERE world_id=? AND command_key=?'
        );
        $q->execute([$world,$key]);
        $names=$q->fetchAll(\PDO::FETCH_COLUMN,0);
        sort($names,SORT_STRING);
        return $names;
    }

    /** @param array<string,mixed> $row */
    private static function etag(array $row):string
    {
        return self::etagFrom(
            $row['world_id'],$row['committed_day'],
            $row['pending_day'],(int)$row['revision']
        );
    }

    private static function etagFrom(
        string $world,string $committed,?string $pending,int $revision
    ):string {
        return '"'.hash('sha256',self::json([
            'world'=>$world,'committed'=>$committed,
            'pending'=>$pending,'revision'=>$revision
        ])).'"';
    }

    private function commandKey(string $world,string $rawKey):string
    {
        return hash('sha256',
            $world."\n".$this->principal->actorId()."\n".$rawKey
        );
    }

    private static function stepKey(string $world,string $day,string $step):string
    {
        return 'tick-'.substr(hash('sha256',
            $world."\n".$day."\n".$step."\nwp17"
        ),0,32);
    }

    private static function id(string $id):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)) {
            throw new \InvalidArgumentException('Invalid world identifier');
        }
    }

    private static function day(string $day):void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day)
            || ($date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day))===false
            || $date->format('Y-m-d')!==$day) {
            throw new \InvalidArgumentException('Invalid world game date');
        }
    }

    private static function json(mixed $value):string
    {
        return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
}
