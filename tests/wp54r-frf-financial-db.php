<?php
declare(strict_types=1);
require __DIR__.'/../games/world-politics/src/PdoDomesticPolicyStore.php';
require __DIR__.'/../games/world-politics/src/EuropeanSpillovers.php';
use Aeternum\WorldPolitics\{PdoDomesticPolicyStore as Store,PersistentDomesticPolicyAuthority,EuropeanWithdrawal as E,EuropeanTradeFeedback as T,EuropeanSpillovers as S,FrancCommittedBudget as C,FrenchMacroBaseline as B,SovereignDebtLedger as L};
function ckDB(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);echo "PASS FRF DB: $m\n";}
function noDB(callable $f):void{try{$f();}catch(DomainException|InvalidArgumentException $e){return;}throw new RuntimeException('Invalid action accepted');}
if (getenv('WP_TEST_ISOLATED')!=='YES' || !str_contains(getenv('WP_TEST_DSN')?:'','wp_test')) {
    fwrite(STDERR,"WP54r requires isolated wp_test database\n");exit(2);
}
function wp29b(bool $ok,string $label):void {
    if (!$ok) throw new RuntimeException('FAIL WP54r: '.$label);
    echo "PASS WP54r: $label\n";
}
function reject29b(callable $fn,string $label,string $class=DomainException::class):void {
    try {$fn();} catch (Throwable $e) {
        wp29b($e instanceof $class,$label.' ('.get_class($e).')');
        return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}
$connect=static fn():PDO=>new PDO(getenv('WP_TEST_DSN'),getenv('WP_TEST_USER'),getenv('WP_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
$pdo=$connect();
// This test runs FIRST in the isolated MariaDB job; no previous test owns these tables.
$tables=['wp_domestic_receipts','wp_domestic_journal','wp_domestic_states',
    'wp_world_mutation_fences','wp_world_clock_steps','wp_world_clock'];
foreach($tables as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
foreach(['007_world_clock.sql','013_world_mutation_fences.sql','017_wp29b_domestic_policy.sql'] as $file) {
    $sql=preg_replace('/^\s*--[^\n]*$/m','',file_get_contents(__DIR__.'/../games/world-politics/database/'.$file));
    foreach(explode(';',$sql) as $stmt) if (trim($stmt)!=='') $pdo->exec(trim($stmt));
}
$d='2026-11-25';$start='2026-12-01';$sha=str_repeat('a',64);
$e=E::request(E::initial($d,true,true,true,true,true,true,true,true,true,false,false,true),$d);
$terms=['effective_on'=>$start,'instrument_sha'=>$sha,'reserve_purchase_authorized'=>true,'commit_daily'=>true,'sector_integration'=>true,'financial_integration'=>true];
noDB(fn()=>E::certify($e,$d,'franc_native_budget',$terms));
$e=E::certify($e,$d,'notification',['national_decision_sha'=>$sha,'notification_sha'=>$sha]);
$e=E::certify($e,$d,'agreement',['effective_on'=>$start,'parliament_consent'=>true,'council_qualified_majority'=>true,'france_agrees'=>true,'agreement_sha'=>$sha]);
$e=E::certify($e,$d,'currency_terms',['effective_on'=>$start,'units_per_eur'=>2,'instrument_sha'=>$sha,'contracts'=>[['id'=>'bond','debtor'=>'fr_treasury','creditor'=>'fr_banks','kind'=>'sovereign_bond','principal_eur_minor'=>100,'governing_law'=>'fr','treatment'=>'convert_certified','evidence_sha'=>$sha]]]);
$e=E::certify($e,$d,'franc_rule',['effective_on'=>$start,'opening_base_franc_minor'=>1000000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_bank_terms',['effective_on'=>$start,'opening_reserve_allocation_franc_minor'=>100000,'minimum_capital_bps'=>1000,'minimum_liquidity_bps'=>2000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_bank_crisis_terms',['effective_on'=>$start,'facility_pool_franc_minor'=>100000,'guarantee_pool_franc_minor'=>100000,'collateral_haircut_bps'=>2000,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_fx_terms',['effective_on'=>$start,'official_eur_minor'=>100,'counterparty_eur_minor'=>100,'official_franc_pool_minor'=>500,'counterparty_franc_pool_minor'=>500,'minimum_official_eur_minor'=>50,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_fiscal',['effective_on'=>$start,'converted_opening_cohorts'=>['legacy-state-french_credit_institutions'],'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_fx_order',['effective_on'=>$start,'kind'=>'sell_eur','eur_minor'=>20,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_funding',['effective_on'=>'2026-12-02','spending_requested_franc_minor'=>40,'repayment_requested_franc_minor'=>0,'instrument_sha'=>$sha]);
$e=E::certify($e,$d,'franc_native_budget',$terms);
noDB(fn()=>E::certify($e,$d,'franc_native_budget',$terms));
$rev=E::certify($e,$d,'revocation',['national_decision_sha'=>$sha,'notice_sha'=>$sha,'unequivocal_unconditional'=>true]);
ckDB(!isset($rev['franc_native_budget_terms'])&&E::restore($rev)===$rev,'revocation clears terms and replay is exact');


$world='frf-db-test';$b=B::load();$model=L::load($b,$d,true);
$catalog=['unused'=>['revenue_delta'=>0,'expense_delta'=>0,'pressure_delta'=>['workers'=>0],'duration_days'=>1,'concessions'=>['workers'=>0],'concession_daily_cost'=>0,'production_delta_bps'=>0,'employment_delta_bps'=>0]];
$config=['start_day'=>$d,'currency'=>'EUR','opening_balance'=>$b['assumed_opening_cash'],'baseline_revenue'=>$b['daily_revenue'],'baseline_expense'=>$b['daily_primary_expense'],'cohorts'=>['workers'=>400],'catalog'=>$catalog,'macro_baseline'=>$b,'debt_model'=>$model,'eu_trade_enabled'=>true,'eu_trade_v2'=>true,'eu_trade_v3'=>true,'eu_trade_v4'=>true];
$a=new class implements PersistentDomesticPolicyAuthority{
    public string $day='2026-11-25';
    public function actorId():string{return 'verified-frf-worker';}
    public function allows(string $world,string $ability):bool{return $world==='frf-db-test'&&in_array($ability,['domestic.admin','domestic.read','domestic.clock'],true);}
    public function enactedInstrument(string $world,string $policy):?string{return null;}
    public function clockDay(string $world):string{return $this->day;}
};
$store=new Store($pdo,$a);$store->seed($world,$config);
$org=fn($s)=>['state'=>['world_id'=>$world,'day'=>$s['day'],'eu_withdrawal'=>$s],'etag'=>'"'.$sha.'"'];
$payload=function(string $day,array $read)use(&$e,$a,$org,$world){$e=E::advance($e,$day);$a->day=$day;return ['target_day'=>$day,'eu_trade_observation'=>json_encode(T::observe($org($e),$world,$day,T::VERSION_V4,$read),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)];};
$run=function(string $day)use($store,$world,$payload){$read=$store->read($world);return $store->execute($world,'clock',$payload($day,$read),'clock-frf-'.$day,$read['etag']);};
for($i=26;$i<=30;$i++)$run('2026-11-'.$i);
$before=$store->read($world);$command=$payload($start,$before);
$stale=$command;$source=json_decode($command['eu_trade_observation'],true,64,JSON_THROW_ON_ERROR);$source['prior_domestic_sha']=str_repeat('0',64);$stale['eu_trade_observation']=json_encode($source,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
noDB(fn()=>$store->execute($world,'clock',$stale,'stale-frf-source',$before['etag']));
ckDB($store->read($world)===$before,'stale native source does not mutate state');
$pdo->exec("CREATE TRIGGER wp54r_inject_failure BEFORE INSERT ON wp_domestic_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FRF injected receipt failure'");
try{
    $failed=false;try{$store->execute($world,'clock',$command,'clock-frf-first',$before['etag']);}catch(PDOException $ex){$failed=true;}
    ckDB($failed,'receipt failure injected');
}finally{$pdo->exec('DROP TRIGGER wp54r_inject_failure');}
ckDB($store->read($world)===$before&&(int)$pdo->query("SELECT COUNT(*) FROM wp_domestic_journal WHERE world_id='frf-db-test'")->fetchColumn()===5,'native state and journal rollback');
$first=$store->execute($world,'clock',$command,'clock-frf-first',$before['etag']);
$reopen=new Store($connect(),$a);
ckDB($reopen->execute($world,'clock',$command,'clock-frf-first',$before['etag'])===$first,'native idempotent receipt survives reconnect');
$e=E::certify($e,$start,'currency_mark',['effective_on'=>'2026-12-02','value_index_bps'=>8000,'instrument_sha'=>$sha]);
$second=$run('2026-12-02');$read=$reopen->read($world);
$report=C::project($read['state']);
ckDB($report['financial_audit']['audited_days']===2&&$report['latest']['closing_state']===$second['result']['frf_committed_budget']['closing_state']&&$report['latest']['bank_balance']['balance_difference_frf_minor']===0,'persisted bank and Treasury reconciliation after reconnect');
ckDB(S::project($org($e),$read)['franc_native_budget']===$report,'same report from committed organization and domestic states');
$j=$pdo->query("SELECT before_sha,after_sha FROM wp_domestic_journal WHERE world_id='frf-db-test' ORDER BY revision")->fetchAll(PDO::FETCH_ASSOC);
ckDB(count($j)===7&&$j[6]['after_sha']===trim($read['etag'],'"'),'native journal chain final hash');
for($i=1;$i<count($j);$i++)ckDB($j[$i-1]['after_sha']===$j[$i]['before_sha'],'continuous journal link '.$i);
$facility=['currency'=>'GBP','effective_on'=>'2026-12-03','maturity_on'=>'2026-12-04','instrument_sha'=>$sha,'principal_minor'=>10000,'opening_reserve_minor'=>9000,'minimum_reserve_minor'=>1000,'coupon_bps'=>0,'eur_minor_per_10000_units'=>12000];
$e=E::certify($e,'2026-12-02','franc_external_facility',$facility);
$third=$run('2026-12-03');$fourth=$run('2026-12-04');$newRead=(new Store($connect(),$a))->read($world);
$fx=C::project($newRead['state']);
ckDB($fx['latest']['external_finance']['facilities']['GBP']['principal_outstanding_minor']===2000&&$fx['latest']['external_finance']['facilities']['GBP']['reserve_minor']===1000,'native foreign facility settlement persists in MariaDB');
ckDB($fx['financial_audit']['audited_days']===4&&S::project($org($e),$newRead)['franc_native_budget']===$fx,'foreign journal and report replay agree after reconnect');
$quote=['currency'=>'GBP','effective_on'=>'2026-12-05','instrument_sha'=>$sha,'kind'=>'mark','quantity_minor'=>0,'counterparty_eur_minor'=>0,'counterparty_foreign_minor'=>0,'eur_minor_per_10000_units'=>10000];
$e=E::certify($e,'2026-12-04','franc_external_market_order',$quote);
$fifth=$run('2026-12-05');$f=C::project((new Store($connect(),$a))->read($world)['state']);
ckDB($f['latest']['external_finance']['facilities']['GBP']['current_cross_rate']===10000&&$f['latest']['external_finance']['fx_revaluation_frf_minor']<0,'certified cross rate revaluation persists in MariaDB');
ckDB($f['bank_equity_bridge']['days']===5,'bank equity bridge reconciles after persisted revaluation');
foreach($tables as $table)$pdo->exec('DROP TABLE IF EXISTS '.$table);
