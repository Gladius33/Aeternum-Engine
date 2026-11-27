<?php
declare(strict_types=1);
if(getenv('WP_TEST_ISOLATED')!=='YES'||!str_contains(getenv('WP_DB_DSN')?:'','dbname=wp_test;'))exit(2);
function ck(bool $b,string $m):void{if(!$b)throw new RuntimeException('WP118 HTTP '.$m);}
function req(string $method,string $path,mixed $body=null,?string $token=null,string $key='career-http-creation-001',?string $etag=null):array{
    $h=['Content-Type: application/json','Idempotency-Key: '.$key];if($token!==null)$h[]='Authorization: Bearer '.$token;if($etag!==null)$h[]='If-Match: '.$etag;
    $opts=['method'=>$method,'header'=>implode("\r\n",$h),'ignore_errors'=>true,'timeout'=>20];
    if($body!==null)$opts['content']=json_encode($body,JSON_THROW_ON_ERROR);
    $raw=file_get_contents('http://127.0.0.1:18730/api/v1'.$path,false,stream_context_create(['http'=>$opts]));
    if($raw===false)throw new RuntimeException('HTTP transport failed');
    return ['status'=>(int)explode(' ',$http_response_header[0])[1],'body'=>json_decode($raw,true,64,JSON_THROW_ON_ERROR)];
}
$pdo=new PDO(getenv('WP_DB_DSN'),getenv('WP_DB_USER'),getenv('WP_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$exec=function($sql,$args)use($pdo){$q=$pdo->prepare($sql);$q->execute($args);};
$ids=[];$tokens=[];foreach(range(1,3) as $i){$id='acct-'.bin2hex(random_bytes(10));$tok=bin2hex(random_bytes(32));$ids[]=$id;$tokens[]=$tok;
$exec('INSERT INTO wp_accounts(account_id,username,password_hash) VALUES(?,?,?)',[$id,$id,'fixture']);$exec('INSERT INTO wp_sessions(token_sha,account_id,expires_at) VALUES(?,?,?)',[hash('sha256',$tok),$id,time()+3600]);}
[$a,$b,$c]=$tokens;
ck(req('POST','/career-worlds',['label'=>'Career'])['status']===401,'anonymous denied');
$n=req('POST','/career-worlds',['label'=>'Career'],$a);ck($n['status']===201,'create');
$w=$n['body']['world_id'];$p='/career-worlds/'.$w;
ck(req('POST','/career-worlds',['label'=>'Career'],$a)===$n,'idempotent creation');
ck(req('POST','/career-worlds',['label'=>'Changed'],$a)['status']===409,'creation conflict');
$read=req('GET',$p.'/career',null,$a);ck($read['status']===200&&$read['body']['career']!==null&&$read['body']['mandates']===[]&&!$read['body']['rankable'],'no automatic executive mandate');
ck(req('GET',$p.'/career',null,$b)['status']===404,'cross account denied');
ck(req('POST',$p.'/members',['account_id'=>$ids[1]],$a)['status']===200,'invite member');
ck(req('POST',$p.'/members',['account_id'=>$ids[2]],$b)['status']===409,'viewer cannot invite');
ck(req('GET',$p.'/career',null,$b)['body']['career']===null,'invitation not admission');
ck(req('POST',$p.'/admission',['reference_id'=>'campaign2027-jean-luc-melenchon'],$b)['status']===409,'incomplete real programme blocked');
$ad=req('POST',$p.'/admission',(object)[],$b);ck($ad['status']===200&&$ad['body']['career']!==null&&$ad['body']['mandates']===[],'member career admitted');
ck(req('POST',$p.'/admission',(object)[],$b)===$ad,'admission replay');
ck(req('POST',$p.'/admission',['office'=>'president'],$b)['status']===422,'office injection denied');
ck(req('POST',$p.'/transition',['office'=>'president'],$a)['status']===404,'no public signing');
ck(req('POST','/worlds/'.$w.'/domestic/proposals',['policy_id'=>'education-reform'],$a)['status']===404,'no legacy owner bypass');
ck(req('POST','/worlds/'.$w.'/clock/advances',['target_day'=>'2027-05-15'],$a)['status']===404,'no legacy clock bypass');
ck(req('GET',$p.'/career',null,$c)['status']===404,'uninvited account denied');
require_once __DIR__.'/../games/world-politics/src/PdoCareerInstitutionStore.php';
$secret=getenv('WP_CAREER_EVIDENCE_KEY');$cs=new \Aeternum\WorldPolitics\PdoCareerInstitutionStore($pdo,new \Aeternum\WorldPolitics\CareerInstitutionEvidence($secret));
$ca=$read['body']['career']['id'];$cb=$ad['body']['career']['id'];
$bp=['ballot'=>['registered'=>1000,'first'=>['votes'=>[$ca=>600,$cb=>300],'blank_invalid'=>20],'second'=>null],'first_round_day'=>'2027-05-14','second_round_day'=>'2027-05-14'];
$br=$cs->certifyBallot($w,$bp,'opening-ballot-fixture-001',$secret);
ck($cs->certifyBallot($w,$bp,'opening-ballot-fixture-001',$secret)===$br,'ballot replay');
$presRead=req('GET',$p.'/career',null,$a)['body'];
$pres=$presRead['mandates'][0];
ck($presRead['score_progress']['status']==='evidence_pending'&&$presRead['score_progress']['score']===null&&$presRead['score_progress']['series'][0]['tenure_days']===0,'opening presidency progress remains unranked');
ck(req('GET',$p.'/career',null,$b)['body']['score_progress']['status']==='pre_mandate','own career progress only');
$act=['kind'=>'appoint_pm','mandate'=>$pres['id'],'generation'=>$pres['generation'],'target'=>$cb,'portfolio'=>null,'proposal'=>null];
ck(req('POST',$p.'/cabinet',$act,$b,'cabinet-denied-001')['status']===409,'other account cannot appoint');
$ok=req('POST',$p.'/cabinet',$act,$a,'cabinet-appoint-001');ck($ok['status']===200,'authenticated president appoints PM');
ck(req('POST',$p.'/cabinet',$act,$a,'cabinet-appoint-001')===$ok,'cabinet receipt replay');
$pm=req('GET',$p.'/career',null,$b)['body']['mandates'][0];
ck($pm['office']==='prime-minister','PM grant published');
$invite=req('POST',$p.'/members',['account_id'=>$ids[2]],$a);ck($invite['status']===200,'third career invited');
$cc=req('POST',$p.'/admission',(object)[],$c)['body']['career']['id'];
$proposal=['kind'=>'propose_minister','mandate'=>$pm['id'],'generation'=>$pm['generation'],'target'=>$cc,'portfolio'=>'health','proposal'=>null];
ck(req('POST',$p.'/cabinet',$proposal,$b,'cabinet-proposal-001')['status']===200,'PM proposes minister');
$pid=req('GET',$p.'/career',null,$a)['body']['appointment_proposals'][0]['id'];
$confirm=['kind'=>'confirm_minister','mandate'=>$pres['id'],'generation'=>$pres['generation'],'target'=>null,'portfolio'=>null,'proposal'=>$pid];
ck(req('POST',$p.'/cabinet',$confirm,$b,'cabinet-confirm-denied')['status']===409,'PM cannot confirm own proposal');
ck(req('POST',$p.'/cabinet',$confirm,$a,'cabinet-confirm-001')['status']===200,'president confirms proposal');
ck(req('GET',$p.'/career',null,$c)['body']['mandates'][0]['office']==='minister-health','minister mandate published');
$resign=['kind'=>'resign_pm','mandate'=>$pm['id'],'generation'=>$pm['generation'],'target'=>null,'portfolio'=>null,'proposal'=>null];
ck(req('POST',$p.'/cabinet',$resign,$b,'cabinet-resign-001')['status']===200,'PM resignation');
ck(req('GET',$p.'/career',null,$c)['body']['mandates']===[],'cabinet revocation atomic');
foreach(['wp_career_active_offices','wp_career_memberships','wp_career_events','wp_career_ledgers','wp_world_mutation_fences','wp_world_clock','wp_alpha_members','wp_alpha_worlds'] as $t)$exec("DELETE FROM $t WHERE world_id=?",[$w]);
foreach($ids as $id){$exec('DELETE FROM wp_sessions WHERE account_id=?',[$id]);$exec('DELETE FROM wp_accounts WHERE account_id=?',[$id]);}
// A second world uses the pinned 2027 economic models, with no v1 score.
require_once __DIR__.'/../games/world-politics/src/CareerEconomicWorld.php';
$eid='acct-'.bin2hex(random_bytes(10));$etoken=bin2hex(random_bytes(32));
$exec('INSERT INTO wp_accounts(account_id,username,password_hash) VALUES(?,?,?)',[$eid,$eid,'fixture']);
$exec('INSERT INTO wp_sessions(token_sha,account_id,expires_at) VALUES(?,?,?)',[hash('sha256',$etoken),$eid,time()+3600]);
$en=req('POST','/career-worlds',['label'=>'Career economic regression','profile'=>'economic'],$etoken);
ck($en['status']===201,'economic profile create');
$ew=$en['body']['world_id'];$ep='/career-worlds/'.$ew;
$before=req('GET',$ep.'/economy',null,$etoken);
ck($before['status']===200&&$before['body']['day']==='2027-05-14'&&!$before['body']['rankable'],'economic opening date and no rank');
$connect=fn()=>new PDO(getenv('WP_DB_DSN'),getenv('WP_DB_USER'),getenv('WP_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
// Admit two careers before the opening fictional election, appoint the
// government, then exercise its actual domestic proposal capability.
$e2='acct-'.bin2hex(random_bytes(10));$t2=bin2hex(random_bytes(32));
$exec('INSERT INTO wp_accounts(account_id,username,password_hash) VALUES(?,?,?)',[$e2,$e2,'fixture']);
$exec('INSERT INTO wp_sessions(token_sha,account_id,expires_at) VALUES(?,?,?)',[hash('sha256',$t2),$e2,time()+3600]);
ck(req('POST',$ep.'/members',['account_id'=>$e2],$etoken)['status']===200,'economic member invited');
$eac=req('GET',$ep.'/career',null,$etoken)['body']['career']['id'];
$fiction=['name'=>'Camille Simulation','measure_ids'=>['administration-report']];
$ebc=req('POST',$ep.'/admission',['fictional'=>$fiction],$t2)['body']['career']['id'];
ck(req('GET',$ep.'/career',null,$t2)['body']['career']['fictional_profile']['name']==='Camille Simulation','named fictional profile persisted');
$ebp=['ballot'=>['registered'=>1000,'first'=>['votes'=>[$eac=>600,$ebc=>300],'blank_invalid'=>20],'second'=>null],'first_round_day'=>'2027-05-14','second_round_day'=>'2027-05-14'];
$cs->certifyBallot($ew,$ebp,'economic-ballot-fixture-001',$secret);
$em=req('GET',$ep.'/career',null,$etoken)['body']['mandates'][0];
$eact=['kind'=>'appoint_pm','mandate'=>$em['id'],'generation'=>$em['generation'],'target'=>$ebc,'portfolio'=>null,'proposal'=>null];
ck(req('POST',$ep.'/cabinet',$eact,$etoken,'economic-pm-001')['status']===200,'economic PM appointed');
$epm=req('GET',$ep.'/career',null,$t2)['body']['mandates'][0];
$inst=req('GET',$ep.'/institutions',null,$t2);ck($inst['status']===200,'read institution ETag');
$bill=['mandate'=>$epm['id'],'generation'=>$epm['generation'],'title'=>'Fictional education bill'];
ck(req('POST',$ep.'/bills',$bill,$etoken,'bill-denied-001',$inst['body']['etag'])['status']===409,'president cannot spoof PM legislative power');
$bf=req('POST',$ep.'/bills',$bill,$t2,'bill-filed-001',$inst['body']['etag']);ck($bf['status']===202&&$bf['body']['status']==='filed','PM files bill in existing institution');
ck(req('POST',$ep.'/bills',$bill,$t2,'bill-filed-001',$inst['body']['etag'])===$bf,'bill idempotent replay');
$policy=['mandate'=>$epm['id'],'generation'=>$epm['generation'],'policy_id'=>'education-reform','effective_day'=>'2027-05-15'];
$tag=$before['body']['domestic']['etag'];
ck(req('POST',$ep.'/policy-proposals',$policy,$etoken,'policy-denied-001',$tag)['status']===409,'president cannot spoof PM policy authority');
$pr=req('POST',$ep.'/policy-proposals',$policy,$t2,'policy-propose-001',$tag);
ck($pr['status']===202,'PM proposal written to existing domestic journal');
ck(req('POST',$ep.'/policy-proposals',$policy,$t2,'policy-propose-001',$tag)===$pr,'domestic receipt replay');
$tag2=req('GET',$ep.'/economy',null,$etoken)['body']['domestic']['etag'];
$newPolicy=['mandate'=>$epm['id'],'generation'=>$epm['generation'],'policy_id'=>'pf2027-dlf-production-tax','effective_day'=>'2027-10-01'];
ck(req('POST',$ep.'/policy-proposals',$newPolicy,$etoken,'policy-denied-002',$tag2)['status']===409,'president cannot borrow programme proposal powers');
$newResult=req('POST',$ep.'/policy-proposals',$newPolicy,$t2,'policy-propose-002',$tag2);
ck($newResult['status']===202,'reviewed 2027 economic measure allowed for PM');

\Aeternum\WorldPolitics\CareerEconomicWorld::tick($connect,$ew,'2027-05-15');
$after=req('GET',$ep.'/economy',null,$etoken);
ck($after['status']===200&&$after['body']['day']==='2027-05-15'&&$after['body']['domestic']['state']['day']==='2027-05-15'&&count($after['body']['domestic']['state']['days'])===1,'coordinated economic day');
ck($after['body']['domestic']['state']['days'][0]['active_policies']===[],'unlegislated policy remains inert');
$preview=$after['body']['score_preview'];
ck(!$preview['rankable']&&!$preview['official_eligible']&&count($preview['series'])===1,'own experimental series only');
$ps=$preview['series'][0];
ck($ps['status']==='provisional_preview'&&$ps['tenure_days']===1&&$ps['credit_days']===0
    &&$ps['baseline_index_bps']===10000&&$ps['window_days']===1&&is_int($ps['score']),'real committed post-accession score calculation');
$pmView=req('GET',$ep.'/economy',null,$t2);
ck($pmView['status']===200&&$pmView['body']['score_preview']['series']===[],'PM never receives another career score');
\Aeternum\WorldPolitics\CareerEconomicWorld::tick($connect,$ew,'2027-05-15');
ck(req('GET',$ep.'/economy',null,$etoken)===$after,'no duplicate accrual');
$participation=req('GET',$ep.'/career',null,$etoken)['body']['score_progress'];
ck($participation['participation_evidence_through']==='2027-05-15'&&$participation['series'][0]['verified_credit_days']===0
    &&$participation['series'][0]['tenure_days']===1&&$participation['series'][0]['tenure_participation_verified']&&$participation['score']===null,'clock publishes participation atomically even without daily human action');

ck(!isset($after['body']['domestic']['state']['policies']['administration-report']),'human activity suppressed fallback');
\Aeternum\WorldPolitics\CareerEconomicWorld::tick($connect,$ew,'2027-05-16');
$auto=req('GET',$ep.'/economy',null,$etoken);
ck(isset($auto['body']['domestic']['state']['policies']['administration-report'])&&$auto['body']['domestic']['state']['policies']['administration-report']['authorized_on']===null,'inactive PM programme creates inert proposal');
\Aeternum\WorldPolitics\CareerEconomicWorld::tick($connect,$ew,'2027-05-16');
ck(req('GET',$ep.'/economy',null,$etoken)===$auto,'fallback replay stable');
ck(req('POST','/worlds/'.$ew.'/clock/advances',['target_day'=>'2027-05-16'],$etoken)['status']===404,'no player clock grant');
echo "PASS WP118 economic profile, committed-day recovery, no duplicate economic accrual\n";
$fn=req('POST','/career-worlds',['label'=>'German career','country'=>'DE'],$etoken,'career-de-creation-001');
ck($fn['status']===201,'German opposition career creation');
$fw=$fn['body']['world_id'];$fv=req('GET','/career-worlds/'.$fw.'/career',null,$etoken);
ck($fv['status']===200&&$fv['body']['country']==='DE'&&$fv['body']['mandates']===[],'German country pinned, no French powers');
$fd=req('GET','/career-worlds/'.$fw.'/characters',null,$etoken);
ck($fd['status']===200&&count($fd['body']['characters'])===6,'German documentary office and party roster');
foreach($fd['body']['characters'] as $person)ck(!$person['playable']&&$person['controller']==='ai','programme unavailable despite scenario identity');
$fi=req('GET','/career-worlds/'.$fw.'/institutions',null,$etoken);
ck($fi['status']===200&&$fi['body']['state']['accountability']['incumbent']==='real-de-friedrich-merz','documentary alternate-history opening');
ck($fi['body']['state']['foreign_recurring']['version']==='wp119-career-foreign-recurring-v1'&&$fi['body']['state']['electoral']['config']['parties']['cdu-csu']['leader']==='real-de-friedrich-merz','career election and incumbent share canonical identity');
$openingOffice=$fv['body']['occupied_offices'][0];
ck($openingOffice['office']==='federal-chancellor'&&$openingOffice['controller']==='ai','opening AI mandate, not player authority');
$common=['world_id'=>$fw,'country'=>'DE','motion_id'=>'motion-career-de','procedure_certified'=>true];
$cs->applyForeignEvidence($fw,'accountability_file',$common+['evidence_id'=>'de-file','at'=>'2027-05-14T00:00:00Z','chamber'=>'bundestag','signatures'=>30,'candidate'=>'real-de-johann-wadephul','kind'=>'no_confidence'],$secret);
$cs->tickForeignInstitution($fw,'2027-05-15');
$cs->tickForeignInstitution($fw,'2027-05-16');
$cs->applyForeignEvidence($fw,'accountability_vote',$common+['evidence_id'=>'de-vote','at'=>'2027-05-16T00:00:00Z','chamber'=>'bundestag','present'=>100,'yes'=>60,'no'=>40,'abstain'=>0],$secret);
$resolveProof=$common+['evidence_id'=>'de-resolve','at'=>'2027-05-16T01:00:00Z','outcome'=>'appoint_successor','successor'=>'real-de-johann-wadephul'];
$receiptForeign=$cs->applyForeignEvidence($fw,'accountability_resolve',$resolveProof,$secret);
ck($cs->applyForeignEvidence($fw,'accountability_resolve',$resolveProof,$secret)===$receiptForeign,'foreign evidence receipt replay');
try{$badProof=$resolveProof;$badProof['successor']='real-de-friedrich-merz';$cs->applyForeignEvidence($fw,'accountability_resolve',$badProof,$secret);throw new RuntimeException('foreign evidence conflict accepted');}catch(DomainException $e){}
$cs->tickForeignInstitution($fw,'2027-05-17');
ck($cs->applyForeignEvidence($fw,'accountability_resolve',$resolveProof,$secret)===$receiptForeign,'foreign receipt survives next committed day');
$afterForeign=req('GET','/career-worlds/'.$fw.'/career',null,$etoken);
ck($afterForeign['body']['occupied_offices'][0]['identity']['person_reference']==='person-de-johann-wadephul'&&$afterForeign['body']['mandates']===[],'certified successor identity projected atomically');

$frd=req('GET',$ep.'/characters',null,$etoken);
ck($frd['status']===200&&count($frd['body']['characters'])===22,'French directory');

ck(req('POST','/career-worlds',['label'=>'Unknown','country'=>'CH'],$etoken,'career-ch-creation-001')['status']===409,'unimplemented rules fail closed');
echo "PASS WP118 HTTP career sandbox, invitations, admission, replay, principal and legacy isolation\n";

// WP119 public career pool: a single state/clock, multiple human careers,
// no owner-derived mandate, immediate spare, late admission without reset.
require_once __DIR__.'/../games/world-politics/src/CareerUniverseScheduler.php';
$public=req('POST','/career-worlds/join',['fictional'=>['name'=>'Public career one']],$etoken,'public-career-join-01');
ck($public['status']===201&&!$public['body']['office_granted_by_join']&&!$public['body']['state_reset'],'public career admission');
$pw=$public['body']['world_id'];$pp='/career-worlds/'.$pw;
ck(req('POST','/career-worlds/join',['fictional'=>['name'=>'Public career one']],$etoken,'public-career-join-01')===$public,'public join receipt replay');
ck(req('POST','/career-worlds/join',['fictional'=>['name'=>'Different name']],$etoken,'public-career-join-01')['status']===409,'public receipt conflict');
ck(req('POST','/career-worlds/join',['world_id'=>$ew],$etoken,'private-world-public-01')['status']===409,'private career not publicly joinable');
$dir=req('GET','/career-worlds/public',null,$etoken)['body'];
ck(count($dir['universes'])===1&&$dir['universes'][0]['vacant']===7&&$dir['universes'][0]['is_member'],'public directory omits private worlds');
ck(req('POST',$pp.'/members',['account_id'=>$eid],$etoken)['status']===409,'public membership never grants owner role');
ck(req('GET',$pp.'/career',null,$etoken)['body']['mandates']===[],'joining confers no office');
$exec('UPDATE wp_career_public_worlds SET next_tick_epoch=UNIX_TIMESTAMP()-1 WHERE world_id=?',[$pw]);
$sch=new \Aeternum\WorldPolitics\CareerUniverseScheduler($connect);
ck($sch->tick($pw)['day']==='2027-05-15','one human public world ticks autonomously');
ck($sch->tick($pw)['status']==='not_due','public clock cannot double tick');
$shared=req('GET',$pp.'/economy',null,$etoken);
$public2=req('POST','/career-worlds/join',['world_id'=>$pw],$t2,'public-career-join-02');
ck($public2['status']===201&&$public2['body']['world_id']===$pw&&$public2['body']['career_id']!==$public['body']['career_id']
    &&$public2['body']['handover_day']==='2027-05-15','second human enters same committed world');
$lateEconomic=req('GET',$pp.'/economy',null,$t2);
ck($lateEconomic['status']===200&&$lateEconomic['body']['score_preview']['series']===[],'late join owns no presidential score');
unset($lateEconomic['body']['score_preview'],$shared['body']['score_preview']);
ck($lateEconomic===$shared,'late career admission does not reset shared economy');
ck(req('GET',$pp.'/career',null,$t2)['body']['mandates']===[],'late join cannot acquire occupied presidency');
ck(req('GET',$pp.'/career',null,$t2)['body']['score_progress']['admission_mode']==='late_takeover','late career league distinguished');
for($n=3;$n<=8;$n++){
    $id='acct-'.bin2hex(random_bytes(10));$tok=bin2hex(random_bytes(32));
    $exec('INSERT INTO wp_accounts(account_id,username,password_hash) VALUES(?,?,?)',[$id,$id,'fixture']);
    $exec('INSERT INTO wp_sessions(token_sha,account_id,expires_at) VALUES(?,?,?)',[hash('sha256',$tok),$id,time()+3600]);
    $receipt=$cs->joinPublic($tok,'public-career-fill-'.$n,$secret,$pw);
    ck($receipt['world_id']===$pw&&($n===8?is_array($receipt['new_universe']):$receipt['new_universe']===null),'capacity and immediate reserve '.$n);
}
$dir=req('GET','/career-worlds/public',null,$etoken)['body'];
ck(count($dir['universes'])===2&&$dir['universes'][0]['vacant']===0&&!$dir['universes'][0]['joinable']
    &&$dir['universes'][1]['vacant']===8&&$dir['universes'][1]['next_tick_epoch']===null,'full public universe creates dormant reserve');
$id='acct-'.bin2hex(random_bytes(10));$tok=bin2hex(random_bytes(32));
$exec('INSERT INTO wp_accounts(account_id,username,password_hash) VALUES(?,?,?)',[$id,$id,'fixture']);
$exec('INSERT INTO wp_sessions(token_sha,account_id,expires_at) VALUES(?,?,?)',[hash('sha256',$tok),$id,time()+3600]);
try{$cs->joinPublic($tok,'public-career-full-09',$secret,$pw);throw new RuntimeException('Full world accepted');}catch(DomainException $expected){}
$next=$cs->joinPublic($tok,'public-career-auto-09',$secret);
ck($next['world_id']===$dir['universes'][1]['world_id']&&!$next['state_reset'],'next newcomer selects fresh reserve');
echo "PASS WP119 public shared career pool, reserve, late admission and autonomous clock\n";

// Transactional US career projection: the newly admitted documented VP
// inherits the original expiry, not a fresh four-year presidential term.
$usWorld=req('POST','/career-worlds',['label'=>'US succession','country'=>'US'],$etoken,'career-us-v3-creation');
ck($usWorld['status']===201,'US v3 career world');
$uw=$usWorld['body']['world_id'];$up='/career-worlds/'.$uw;
$usState=req('GET',$up.'/institutions',null,$etoken)['body']['state'];
$uc=\Aeternum\WorldPolitics\UsPresidentialElection::class;
$vp=$uc::vicePresident($usState['accountability']);
$proof=['world_id'=>$uw,'country'=>'US','evidence_id'=>'vp-succession-fixture','at'=>'2027-05-14T12:00:00Z','procedure_certified'=>true,'successor'=>$vp,'term_end'=>'2029-01-20','cause'=>'resignation'];
$receipt=$cs->applyForeignEvidence($uw,'accountability_us_succession',$proof,$secret);
ck($cs->applyForeignEvidence($uw,'accountability_us_succession',$proof,$secret)===$receipt,'US certified succession receipt replay');
$uv=req('GET',$up.'/career',null,$etoken)['body'];
ck(count($uv['occupied_offices'])===1&&$uv['occupied_offices'][0]['identity']['id']===$vp&&$uv['occupied_offices'][0]['expires']==='2029-01-20','US successor retains original term end');
$cs->tickForeignInstitution($uw,'2027-05-15');
ck(req('GET',$up.'/career',null,$etoken)['body']['occupied_offices'][0]['identity']['id']===$vp,'US successor survives committed day');
