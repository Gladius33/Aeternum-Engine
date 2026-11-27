<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/DomesticPolicySimulation.php';
require_once __DIR__.'/WorldMutationFence.php';
require_once __DIR__.'/DailyLegalActivation.php';
require_once __DIR__.'/UniverseAutonomy.php';

interface PersistentDomesticPolicyAuthority extends DomesticPolicyAuthority
{
    public function actorId(): string;
}

/**
 * WP29: durable domestic policy aggregate. Server-curated configuration,
 * revalidated world/actor permissions, optimistic ETag, transactionally
 * paired state, journal and receipts. No HTTP transport.
 * Clock-managed worlds require an explicitly pinned domestic subsystem.
 */
final class PdoDomesticPolicyStore
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly PersistentDomesticPolicyAuthority $principal
    ) {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function principalId(): string { return $this->principal->actorId(); }

    /** Trusted scenario bootstrap, never a player command. */
    public function seed(string $world, array $config): void
    {
        $actor = $this->permit($world,'domestic.admin');
        $sim = self::build($world,$config);
        $this->transaction(function () use ($world,$actor,$config,$sim): void {
            $this->admit($world,$actor,'bootstrap','domestic.admin');
            $q = $this->pdo->prepare('INSERT INTO wp_domestic_states
                (world_id,assumptions_json,assumptions_sha,state_json,state_sha,revision)
                VALUES(?,?,?,?,?,0)');
            $q->execute([$world,self::json($config),$sim->assumptionsHash(),self::json($sim->snapshot()),$sim->hash()]);
        });
    }

    public function read(string $world): array
    {
        $actor = $this->permit($world,'domestic.read');
        $row = $this->row($world,false);
        $sim = $this->restore($world,$row);
        $this->sameActor($world,'domestic.read',$actor);
        return ['state'=>$sim->snapshot(),'etag'=>'"'.$sim->hash().'"','revision'=>(int)$row['revision']];
    }

    /**
     * Only a trusted controller can call the clock or enact operations;
     * neither dates of authority nor proof hashes nor assumptions originate
     * from client JSON. An idempotent replay returns the original receipt.
     */
    public function execute(string $world,string $operation,array $payload,string $key,string $ifMatch): array
    {
        $ability = match($operation) {
            'propose'=>'domestic.propose','enact'=>'domestic.enact','clock'=>'domestic.clock','crisis'=>'domestic.crisis','corporation'=>'domestic.corporation',
            default=>throw new \InvalidArgumentException('Unknown domestic operation')
        };
        $actor = $this->permit($world,$ability);
        $this->sameActor($world,'domestic.read',$actor);
        self::fields($payload,match($operation) {
            'propose'=>['policy_id','effective_day'],'enact'=>['policy_id'],'clock'=>['target_day',...(array_key_exists('trade_observation',$payload)?['trade_observation']:[]),...(array_key_exists('eu_trade_observation',$payload)?['eu_trade_observation']:[])],'crisis'=>['action'],'corporation'=>['action']
        });
        foreach ($payload as $field=>$value) {
            if (!is_string($value) || strlen($value)>($field==='eu_trade_observation'?8192:($field==='trade_observation'?2048:64))) throw new \InvalidArgumentException('Invalid command value');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key)
            || !preg_match('/^"[a-f0-9]{64}"$/D',$ifMatch)) {
            throw new \InvalidArgumentException('Invalid receipt key or strong ETag');
        }
        ksort($payload,SORT_STRING);
        $request = self::json(['operation'=>$operation,'payload'=>$payload]);
        $requestSha = hash('sha256',$request);
        $receiptKey = hash('sha256',$key);

        return $this->transaction(function () use ($world,$operation,$payload,$ifMatch,$ability,$actor,$request,$requestSha,$receiptKey): array {
            $this->admit($world,$actor,$operation==='clock'?'clock':'domestic_'.$operation,$ability,$payload['target_day']??null);
            $row = $this->row($world,true);
            $this->sameActor($world,$ability,$actor);
            $this->sameActor($world,'domestic.read',$actor);
            $sim = $this->restore($world,$row);

            $q = $this->pdo->prepare('SELECT request_sha,response_json FROM wp_domestic_receipts
                WHERE world_id=? AND principal_id=? AND receipt_key=?');
            $q->execute([$world,$actor,$receiptKey]);
            $receipt = $q->fetch(\PDO::FETCH_ASSOC);
            if ($receipt!==false) {
                if (!hash_equals($receipt['request_sha'],$requestSha)) throw new \DomainException('Conflicting domestic idempotency key');
                return self::decode($receipt['response_json']);
            }
            $before = $sim->hash();
            if (!hash_equals('"'.$before.'"',$ifMatch)) throw new \DomainException('Stale domestic state');
            if($operation==='clock'&&isset($payload['eu_trade_observation'])){
                $source=self::decode($payload['eu_trade_observation']);
                if(isset($source['frf_committed_observation'])&&!hash_equals($before,$source['prior_domestic_sha']??''))throw new \DomainException('Stale native budget source');
            }
            if($operation==='clock'&&isset($sim->snapshot()['daily_legal_version']))DailyLegalActivation::apply($this->pdo,$sim,$world,$payload['target_day'],$actor);
            if($operation==='clock')UniverseAutonomy::proposeDomestic($this->pdo,$sim,$world,$actor,$payload['target_day']);
            $result = match($operation) {
                'propose'=>$this->propose($sim,$payload),
                'enact'=>$this->enact($sim,$payload),
                'clock'=>$sim->advance($this->principal,$payload['target_day'],isset($payload['trade_observation'])?self::decode($payload['trade_observation']):null,isset($payload['eu_trade_observation'])?self::decode($payload['eu_trade_observation']):null),'crisis'=>$sim->crisisDecision($this->principal,$payload['action']),'corporation'=>$sim->corporationDecision($this->principal,$payload['action'])
            };
            $this->sameActor($world,$ability,$actor);
            $this->sameActor($world,'domestic.read',$actor);
            if(in_array($operation,['propose','crisis','corporation'],true))UniverseAutonomy::markDomestic($this->pdo,$world,$actor,$sim->snapshot()['day']);
            $after = $sim->hash();
            $revision = (int)$row['revision']+1;
            $response = ['status'=>$operation==='propose'?202:200,'result'=>$result,
                'etag'=>'"'.$after.'"','revision'=>$revision];
            $q = $this->pdo->prepare('UPDATE wp_domestic_states SET state_json=?,state_sha=?,revision=? WHERE world_id=?');
            $q->execute([self::json($sim->snapshot()),$after,$revision,$world]);
            if ($q->rowCount()!==1) throw new \UnexpectedValueException('Domestic state update lost');
            $q = $this->pdo->prepare('INSERT INTO wp_domestic_journal
                (world_id,revision,principal_id,operation,request_sha,request_json,before_sha,after_sha,result_json)
                VALUES(?,?,?,?,?,?,?,?,?)');
            $q->execute([$world,$revision,$actor,$operation,$requestSha,$request,$before,$after,self::json($result)]);
            $q = $this->pdo->prepare('INSERT INTO wp_domestic_receipts
                (world_id,principal_id,receipt_key,request_sha,response_json) VALUES(?,?,?,?,?)');
            $q->execute([$world,$actor,$receiptKey,$requestSha,self::json($response)]);
            return $response;
        });
    }

    private function propose(DomesticPolicySimulation $sim,array $payload): array
    {
        $id=$payload['policy_id'];
        $sim->propose($this->principal,$id,$payload['effective_day']);
        return ['policy_id'=>$id,'policy'=>$sim->snapshot()['policies'][$id]];
    }

    private function enact(DomesticPolicySimulation $sim,array $payload): array
    {
        $id=$payload['policy_id'];
        $sim->authorize($this->principal,$id);
        return ['policy_id'=>$id,'policy'=>$sim->snapshot()['policies'][$id]];
    }

    private function admit(string $world,string $actor,string $operation,string $ability,?string $target=null): void
    {
        WorldMutationFence::admit($this->pdo,$world,$actor,$operation,$target);
        $this->sameActor($world,$ability,$actor);
        // The fence is held. Do not lock the coordinator row for a clock
        // step: the coordinator holds it on its separate PDO connection.
        $q=$this->pdo->prepare('SELECT * FROM wp_world_clock WHERE world_id=?'.($operation==='bootstrap'?' FOR UPDATE':''));
        $q->execute([$world]);
        $clock=$q->fetch(\PDO::FETCH_ASSOC);
        if ($clock===false) return;
        if ($operation==='bootstrap' || (int)($clock['domestic_enabled']??0)!==1) {
            throw new \DomainException('Domestic clock integration not enabled');
        }
        if ($operation==='clock' && ($clock['pending_day']===null || $clock['pending_day']!==$target)) {
            throw new \DomainException('Coordinated domestic clock requires matching pending tick');
        }
        if ($operation==='domestic_enact' && !($this->principal instanceof VerifiedDomesticPolicyAuthority)) {
            throw new \DomainException('Coordinated enactment requires verified legislative registry');
        }
    }

    private function row(string $world,bool $lock): array
    {
        $q=$this->pdo->prepare('SELECT assumptions_json,assumptions_sha,state_json,state_sha,revision
            FROM wp_domestic_states WHERE world_id=?'.($lock?' FOR UPDATE':''));
        $q->execute([$world]);
        $row=$q->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new \DomainException('No domestic policy world');
        return $row;
    }

    private function restore(string $world,array $row): DomesticPolicySimulation
    {
        $sim=self::build($world,self::decode($row['assumptions_json']));
        if (!hash_equals($row['assumptions_sha'],$sim->assumptionsHash())) {
            throw new \UnexpectedValueException('Pinned domestic assumptions changed');
        }
        $sim->restore(self::decode($row['state_json']),$row['state_sha']);
        return $sim;
    }

    private static function build(string $world,array $config): DomesticPolicySimulation
    {
        self::fields($config,['start_day','currency','opening_balance','baseline_revenue',
            'baseline_expense','cohorts','catalog',...(array_key_exists('macro_baseline',$config)?['macro_baseline']:[]),...(array_key_exists('debt_model',$config)?['debt_model']:[]),...(array_key_exists('population_model',$config)?['population_model']:[]),...(array_key_exists('services_model',$config)?['services_model']:[]),...(array_key_exists('corporations_enabled',$config)?['corporations_enabled']:[]),...(array_key_exists('wage_feedback_enabled',$config)?['wage_feedback_enabled']:[]),...(array_key_exists('trade_feedback_enabled',$config)?['trade_feedback_enabled']:[]),...(array_key_exists('firm_model',$config)?['firm_model']:[]),...(array_key_exists('public_equity_enabled',$config)?['public_equity_enabled']:[]),...(array_key_exists('industrial_enabled',$config)?['industrial_enabled']:[]),...(array_key_exists('daily_legal_enabled',$config)?['daily_legal_enabled']:[]),...(array_key_exists('eu_trade_enabled',$config)?['eu_trade_enabled']:[]),...(array_key_exists('eu_trade_v2',$config)?['eu_trade_v2']:[]),...(array_key_exists('eu_trade_v3',$config)?['eu_trade_v3']:[]),...(array_key_exists('eu_trade_v4',$config)?['eu_trade_v4']:[]),...(array_key_exists('finance_autonomy',$config)?['finance_autonomy']:[])]);
        if (!is_string($config['start_day']) || !is_string($config['currency'])
            || !is_int($config['opening_balance']) || !is_int($config['baseline_revenue'])
            || !is_int($config['baseline_expense']) || !is_array($config['cohorts']) || !is_array($config['catalog'])) {
            throw new \InvalidArgumentException('Invalid domestic configuration types');
        }
        return new DomesticPolicySimulation($world,$config['start_day'],$config['currency'],
            $config['opening_balance'],$config['baseline_revenue'],$config['baseline_expense'],
            $config['cohorts'],$config['catalog'],$config['macro_baseline']??null,$config['debt_model']??null,$config['population_model']??null,$config['services_model']??null,$config['corporations_enabled']??false,$config['wage_feedback_enabled']??false,$config['trade_feedback_enabled']??false,$config['firm_model']??null,$config['public_equity_enabled']??false,$config['industrial_enabled']??false,$config['daily_legal_enabled']??false,$config['eu_trade_enabled']??false,$config['eu_trade_v2']??false,$config['eu_trade_v3']??false,$config['eu_trade_v4']??false,$config['finance_autonomy']??false);
    }

    private function permit(string $world,string $ability): string
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$world)) throw new \InvalidArgumentException('Invalid domestic world');
        $actor=$this->principal->actorId();
        if (!preg_match('/^[A-Za-z0-9_.:@-]{1,128}$/D',$actor)
            || !$this->principal->allows($world,$ability)) throw new \DomainException('Unverified domestic permission');
        return $actor;
    }

    private function sameActor(string $world,string $ability,string $actor): void
    {
        if ($this->permit($world,$ability)!==$actor) throw new \DomainException('Domestic principal changed');
    }

    private function transaction(callable $work): mixed
    {
        if ($this->pdo->inTransaction()) throw new \LogicException('Domestic store requires its own transaction');
        $this->pdo->beginTransaction();
        try {
            $result=$work();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private static function fields(array $value,array $expected): void
    {
        $keys=array_keys($value);sort($keys,SORT_STRING);sort($expected,SORT_STRING);
        if ($keys!==$expected) throw new \InvalidArgumentException('Unexpected domestic fields');
    }

    private static function decode(string $value): array
    {
        $decoded=json_decode($value,true,64,JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new \UnexpectedValueException('Invalid domestic JSON');
        return $decoded;
    }

    private static function json(array $value): string
    {
        return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    }
}
