<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/PdoDomesticPolicyStore.php';
require_once __DIR__.'/../games/world-politics/src/LocalLegislationClerk.php';
require_once __DIR__.'/../games/world-politics/src/PoliticalRoster.php';
use Aeternum\WorldPolitics\{PdoDomesticPolicyStore as D,PersistentDomesticPolicyAuthority,InstitutionalSimulation as I,PdoInstitutionalStore as S,InstitutionalActorAuthorizer,LocalLegislationClerk,PoliticalRoster,FinanceBudget as F,DomesticPolicySimulation};
if(getenv('WP_TEST_ISOLATED')!=='YES'||!str_contains(getenv('WP_DB_DSN')?:'','wp_test'))exit(2);
function ck71db(bool $b,string $s):void{if(!$b)throw new RuntimeException('WP71DB FAIL '.$s);echo "PASS WP71DB: $s\n";}
$connect=fn()=>new PDO(getenv('WP_DB_DSN'),getenv('WP_DB_USER'),getenv('WP_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db=$connect();$world='wp71-finance-proof-db';$start='2027-12-30';$policy='budget-2028-defense-increase';
$roster=new PoliticalRoster($world,'FR',['prime_minister']);
$db->prepare('INSERT INTO wp_world_rosters(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)')
    ->execute([$world,'FR',json_encode($roster->snapshot(),JSON_THROW_ON_ERROR),$roster->snapshotHash()]);
$inst=new I($world,'FR',$start);$inst->enableElectoral(\Aeternum\WorldPolitics\ParliamentaryLifecycle::electionConfig($start));$inst->enableLegislation(true,true,true,true,true,true,true,2028);
$ia=new class($world) implements InstitutionalActorAuthorizer{
    public function __construct(private readonly string $w){}
    public function actorId():string{return 'wp71-db-clock';}
    public function allows(string $world,string $country,string $ability):bool{return $world===$this->w&&$country==='FR'&&in_array($ability,['read_institutions','advance_clock'],true);}
    public function roleFor(string $world,string $country,string $action):string{throw new DomainException('No decision authority');}
};
$is=new S($db,$ia);$is->seed($inst);
$da=new class($world) implements PersistentDomesticPolicyAuthority{
    public string $day='2027-12-31';
    public function __construct(private readonly string $w){}
    public function actorId():string{return 'wp71-db-domestic';}
    public function allows(string $world,string $ability):bool{return $world===$this->w&&in_array($ability,['domestic.admin','domestic.read','domestic.propose','domestic.clock'],true);}
    public function enactedInstrument(string $world,string $policy):?string{return null;}
    public function clockDay(string $world):string{return $this->day;}
};
$baseline=10000000000;
$cfg=['start_day'=>$start,'currency'=>'EUR','opening_balance'=>100000000000,'baseline_revenue'=>$baseline,'baseline_expense'=>$baseline,
    'cohorts'=>['workers'=>400,'retired'=>300],'catalog'=>F::policies(2028),'daily_legal_enabled'=>true];
$ds=new D($connect(),$da);$ds->seed($world,$cfg);$opening=$ds->read($world);
$proposal=$ds->execute($world,'propose',['policy_id'=>$policy,'effective_day'=>'2028-01-01'],'wp71-db-proposal-01',$opening['etag']);
ck71db($proposal['status']===202&&$proposal['result']['policy']['authorized_on']===null,'proposed budget has no authority');
$certify=function(string $kind,array $data,string $id)use($connect,$world,$start):array{
    $e=['world_id'=>$world,'country'=>'FR','day'=>$start,'evidence_id'=>$id,'bill_id'=>null,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
    $s=new S($connect(),new LocalLegislationClerk($e));$read=$s->read($world,'FR');
    return $s->decide($world,'FR','legislation_evidence',['evidence_id'=>$id],'wp71-db-'.hash('sha256',$id),$read['etag']);
};
$f=['policy_id'=>$policy,'prime_minister'=>'pm-initial','finance_minister'=>'civic-economy','government_project_certified'=>true];
$certify('finance_file',$f,'budget');$sha=F::digest($policy,2028);
$v=['bill_id'=>'budget','text_sha'=>$sha,'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0];
$certify('finance_assembly',$v,'assembly');$certify('finance_transfer',['bill_id'=>'budget','prime_minister'=>'pm-initial','transmission_certified'=>true],'transfer');
$certify('finance_senate',$v,'senate');$certify('finance_final',['bill_id'=>'budget','text_sha'=>$sha,'route'=>'identical','navette_certified'=>true,'yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0],'final');
$certify('finance_review',['bill_id'=>'budget','text_sha'=>$sha,'constitutional'=>true,'decision_certified'=>true],'review');
$certify('finance_publish',['bill_id'=>'budget','text_sha'=>$sha,'president'=>'campaign-president','promulgation_certified'=>true,'publication_certified'=>true],'publish');
$law=$is->read($world,'FR');
ck71db(F::proof($law['state']['legislation']['finance'],$policy,$sha,'2028-01-01')!==null,'published law persisted with exact year and digest');
$is->advance($world,'FR','2027-12-31','wp71-db-inst-tick-01',$law['etag']);
$payload=['target_day'=>'2027-12-31'];$key='wp71-db-dom-tick-01';
$db->exec("CREATE TRIGGER wp71_db_fail_receipt BEFORE INSERT ON wp_domestic_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated finance rollback'");
$failed=false;
try{$ds->execute($world,'clock',$payload,$key,$proposal['etag']);}catch(PDOException $e){$failed=true;}
finally{$db->exec('DROP TRIGGER wp71_db_fail_receipt');}
$q=$db->prepare('SELECT COUNT(*) FROM wp_domestic_enactments WHERE world_id=?');$q->execute([$world]);
ck71db($failed&&(int)$q->fetchColumn()===0&&$ds->read($world)['state']['policies'][$policy]['authorized_on']===null,'legal proof and fiscal publication rollback together');
$dec=$ds->execute($world,'clock',$payload,$key,$proposal['etag']);
$reopen=new D($connect(),$da);$state=$reopen->read($world);
ck71db($dec['result']['expense']===$baseline&&$state['state']['policies'][$policy]['authorized_on']===$start,'authorized law not spent before effective date');
$next=$is->read($world,'FR');$is->advance($world,'FR','2028-01-01','wp71-db-inst-tick-02',$next['etag']);$da->day='2028-01-01';
$jan=$reopen->execute($world,'clock',['target_day'=>$da->day],'wp71-db-dom-tick-02',$dec['etag']);
$delta=F::catalog(2028)[$policy]['expense_delta'];
ck71db($jan['result']['expense']===$baseline+$delta&&in_array($policy,$jan['result']['active_policies'],true),'2028 fiscal delta starts on effective date');
ck71db($reopen->execute($world,'clock',$payload,$key,$proposal['etag'])===$dec,'exact historical receipt replay');
$q->execute([$world]);ck71db((int)$q->fetchColumn()===1,'one legal instrument, no duplicate booking');
$state=$reopen->read($world);
$pure=new DomesticPolicySimulation($world,$start,'EUR',$cfg['opening_balance'],$baseline,$baseline,$cfg['cohorts'],$cfg['catalog'],null,null,null,null,false,false,false,null,false,false,true);
$pure->restore($state['state'],trim($state['etag'],'"'));
$sum=$delta;
for($i=1;$i<=366;$i++){
    $da->day=(new DateTimeImmutable('2028-01-01'))->modify('+'.$i.' days')->format('Y-m-d');
    $r=$pure->advance($da,$da->day);$sum+=$r['expense']-$baseline;
}
ck71db($da->day==='2029-01-01'&&$r['expense']===$baseline&&!in_array($policy,$r['active_policies'],true)&&$sum===$delta*366,'366-day appropriation expires, no extra daily charge');
