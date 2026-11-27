<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/PdoCareerInstitutionStore.php';
use Aeternum\WorldPolitics\{CareerInstitutionLedger as L,CareerInstitutionEvidence,PdoCareerInstitutionStore};
if(getenv('WP_TEST_ISOLATED')!=='YES'||!str_contains(getenv('WP_TEST_DSN')?:'','dbname=wp_test;'))exit(2);
function dbck(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function dbno(callable $f):void{try{$f();}catch(DomainException $e){return;}throw new RuntimeException('expected rejection');}
$connect=fn()=>new PDO(getenv('WP_TEST_DSN'),getenv('WP_TEST_USER'),getenv('WP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db=$connect();$db2=$connect();$root=__DIR__.'/../games/world-politics/database/';
foreach(['007_world_clock.sql','013_world_mutation_fences.sql','019_wp30_alpha.sql','039_wp118_119_career.sql'] as $f)$db->exec(file_get_contents($root.$f));
$world='wp118-'.bin2hex(random_bytes(6));$a='acct-'.bin2hex(random_bytes(10));$b='acct-'.bin2hex(random_bytes(10));$token=bin2hex(random_bytes(32));$secret=random_bytes(32);$evidence=new CareerInstitutionEvidence($secret);$store=new PdoCareerInstitutionStore($db,$evidence);$other=new PdoCareerInstitutionStore($db2,$evidence);
$q=function(string $sql,array $args=[])use($db){$s=$db->prepare($sql);$s->execute($args);return $s;};
foreach([$a,$b] as $account)$q('INSERT INTO wp_accounts(account_id,username,password_hash) VALUES(?,?,?)',[$account,$account,'fixture']);
$q('INSERT INTO wp_alpha_worlds(world_id,owner_id,create_key,request_sha,label,scenario) VALUES(?,?,?,?,?,?)',[$world,$a,hash('sha256',$world),str_repeat('a',64),'Career fixture','fixture']);
foreach([$a,$b] as $account)$q('INSERT INTO wp_alpha_members(world_id,account_id,role) VALUES(?,?,?)',[$world,$account,'viewer']);
$q('INSERT INTO wp_world_clock(world_id,committed_day,revision) VALUES(?,?,0)',[$world,'2027-05-14']);
$q("INSERT INTO wp_world_mutation_fences(world_id,state,generation) VALUES(?,'idle',0)",[$world]);
$q('INSERT INTO wp_sessions(token_sha,account_id,expires_at) VALUES(?,?,?)',[hash('sha256',$token),$a,time()+3600]);
$store->seed($world,'career');
$event=fn($id,$op,$p)=>['world'=>$world,'country'=>'FR','day'=>'2027-05-14','world_revision'=>0,'command'=>$id,'actor'=>'institution','operation'=>$op,'payload'=>$p,'evidence_sha'=>str_repeat('b',64)];
$sig=fn($e)=>hash_hmac('sha256',L::json($e),$secret);
$one=$event('admit-a','admit',['career'=>'a','account'=>$a,'character'=>'char-a','mode'=>'opening']);
dbno(fn()=>$store->execute($one,str_repeat('0',64)));
$receipt=$store->execute($one,$sig($one));dbck($store->execute($one,$sig($one))===$receipt,'idempotent receipt');
$two=$event('admit-b','admit',['career'=>'b','account'=>$b,'character'=>'char-b','mode'=>'opening']);
$db->beginTransaction();$q('SELECT world_id FROM wp_world_mutation_fences WHERE world_id=? FOR UPDATE',[$world]);$db2->exec('SET SESSION innodb_lock_wait_timeout=1');
try{$other->execute($two,$sig($two));throw new RuntimeException('expected contention');}catch(PDOException $e){dbck((int)($e->errorInfo[1]??0)===1205,'lock timeout');}finally{$db->rollBack();}
$other->execute($two,$sig($two));
$grant=$event('grant-a','transition',['reason'=>'election','close'=>[],'grants'=>[['office'=>'president','career'=>'a','expires'=>'2032-05-14','interim'=>false]]]);
$store->execute($grant,$sig($grant));$s=$store->audit($world);$m=$s['active']['president'];
dbck($store->withCapability($world,$token,$m,1,'president.represent',fn()=>true),'session capability');
dbno(fn()=>$store->withCapability($world,$token,$m,2,'president.represent',fn()=>true));
$bad=$event('grant-b','transition',['reason'=>'election','close'=>[],'grants'=>[['office'=>'president','career'=>'b','expires'=>'2032-05-14','interim'=>false]]]);
dbno(fn()=>$other->execute($bad,$sig($bad)));dbck($store->audit($world)['sequence']===3,'rollback no event');
$replace=$event('succession-b','transition',['reason'=>'succession','close'=>['president'],'grants'=>[['office'=>'president','career'=>'b','expires'=>'2032-05-14','interim'=>false]]]);$other->execute($replace,$sig($replace));
dbno(fn()=>$store->withCapability($world,$token,$m,1,'president.represent',fn()=>true));
dbck($store->audit($world)['sequence']===4,'replay from second connection');
$q('UPDATE wp_career_active_offices SET generation=999 WHERE world_id=?',[$world]);dbno(fn()=>$store->audit($world));$q('UPDATE wp_career_active_offices SET generation=2 WHERE world_id=?',[$world]);
dbck($store->audit($world)['sequence']===4,'tamper detected and restored');
foreach(['wp_career_active_offices','wp_career_memberships','wp_career_events','wp_career_ledgers','wp_world_mutation_fences','wp_world_clock','wp_alpha_members','wp_alpha_worlds'] as $table)$q("DELETE FROM $table WHERE world_id=?",[$world]);
$q('DELETE FROM wp_sessions WHERE account_id=?',[$a]);foreach([$a,$b] as $account)$q('DELETE FROM wp_accounts WHERE account_id=?',[$account]);
echo "PASS WP118–119 MariaDB: fence contention, rollback, replay, session grants, revocation and projection tamper detection\n";
