<?php
declare(strict_types=1);
if (getenv('WP_TEST_ISOLATED')!=='YES' || !str_contains(getenv('WP_DB_DSN')?:'','wp_test')) exit(2);
function ck30(bool $ok,string $label):void {if (!$ok) throw new RuntimeException('WP30 FAIL: '.$label);echo "PASS WP30: $label\n";}
function req30(string $method,string $path,?array $body=null,?string $token=null,array $extra=[]):array {
    $h=['Content-Type: application/json'];if ($token!==null) $h[]='Authorization: Bearer '.$token;
    foreach($extra as $k=>$v)$h[]=$k.': '.$v;
    $opts=['method'=>$method,'header'=>implode("\r\n",$h),'ignore_errors'=>true,'timeout'=>20];
    if ($body!==null)$opts['content']=json_encode($body,JSON_THROW_ON_ERROR);
    $raw=file_get_contents('http://127.0.0.1:18730/api/v1'.$path,false,stream_context_create(['http'=>$opts]));
    if ($raw===false) throw new RuntimeException('HTTP transport failure');
    $status=(int)explode(' ',$http_response_header[0])[1];$etag=null;
    foreach($http_response_header as $line)if(str_starts_with(strtolower($line),'etag:'))$etag=trim(substr($line,5));
    return ['status'=>$status,'body'=>json_decode($raw,true,32,JSON_THROW_ON_ERROR),'etag'=>$etag];
}
$invite=getenv('WP_ALPHA_INVITE');
ck30(req30('GET','/health')['status']===200,'live HTTP endpoint');
ck30(req30('GET','/worlds')['status']===401,'no anonymous worlds');
ck30(req30('POST','/accounts',['username'=>'alice','password'=>'strong-password-123','invite'=>'wrong'])['status']===403,'private enrollment gate');
$a=req30('POST','/accounts',['username'=>'alice','password'=>'strong-password-123','invite'=>$invite]);
$b=req30('POST','/accounts',['username'=>'bob','password'=>'strong-password-456','invite'=>$invite]);
ck30($a['status']===201 && $b['status']===201,'two real accounts');
ck30(req30('POST','/sessions',['username'=>'alice','password'=>'wrong-password-123'])['status']===401,'bad password rejected');
$ta=req30('POST','/sessions',['username'=>'alice','password'=>'strong-password-123'])['body']['access_token'];
$tb=req30('POST','/sessions',['username'=>'bob','password'=>'strong-password-456'])['body']['access_token'];
$pdo=new PDO(getenv('WP_DB_DSN'),getenv('WP_DB_USER'),getenv('WP_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
ck30($pdo->query("SELECT password_hash FROM wp_accounts WHERE username='alice'")->fetchColumn()!== 'strong-password-123'
    && str_starts_with($pdo->query("SELECT password_hash FROM wp_accounts WHERE username='alice'")->fetchColumn(),'$argon2id$'),
    'Argon2id persisted');
ck30(in_array(hash('sha256',$ta),$pdo->query('SELECT token_sha FROM wp_sessions')->fetchAll(PDO::FETCH_COLUMN),true),'only token digest persisted');
$h=['Idempotency-Key'=>'create-world-0001'];$input=['label'=>'Private simulation','scenario'=>'fr-sandbox-v1'];
$wa=req30('POST','/worlds',$input,$ta,$h);
ck30($wa['status']===201,'atomic world bootstrap');
$w=$wa['body']['world_id'];$root='/worlds/'.$w;
ck30(req30('POST','/worlds',$input,$ta,$h)['body']===$wa['body'],'durable create replay');
ck30(req30('POST','/worlds',['label'=>'different','scenario'=>'fr-sandbox-v1'],$ta,$h)['status']===409,'create key collision');
ck30(req30('GET',$root.'/clock',null,$tb)['status']===404,'cross-account read concealed');
$state=req30('GET',$root.'/countries/FR/politics',null,$ta);
ck30($state['status']===200 && $state['body']['people']===[],'durable country roster');
$created=req30('POST',$root.'/countries/FR/politics/people',['name'=>'Camille Test','mode'=>'quick'],$ta,
    ['If-Match'=>$state['etag'],'Idempotency-Key'=>'create-person-0001']);
ck30($created['status']===201,'authenticated fictional profile');
$head=req30('POST',$root.'/countries/FR/politics/head-of-state',['person_id'=>$created['body']['person_id']],$ta,
    ['If-Match'=>$created['etag'],'Idempotency-Key'=>'init-president-001']);
ck30($head['status']===200,'alternative presidency scenario');
ck30(req30('GET',$root.'/countries/FR/politics',null,$ta)['body']['head_of_state']===$created['body']['person_id'],'reconnected state');
ck30(req30('GET',$root.'/countries/FR/institutions',null,$ta)['status']===200,'national institution read');
ck30(req30('GET',$root.'/diplomacy',null,$ta)['status']===200,'diplomacy read');
ck30(req30('GET',$root.'/organizations',null,$ta)['status']===200,'organization read');
ck30(req30('GET',$root.'/economy',null,$ta)['status']===200,'international economy read');
$dom=req30('GET',$root.'/domestic',null,$ta);ck30($dom['status']===200,'domestic read');
$p=req30('POST',$root.'/domestic/proposals',['policy_id'=>'education-reform','effective_day'=>'2026-11-25'],$ta,
    ['If-Match'=>$dom['etag'],'Idempotency-Key'=>'proposal-000001']);
ck30($p['status']===202,'scenario policy proposed');
ck30(req30('POST',$root.'/domestic/enactments',['policy_id'=>'education-reform'],$ta)['status']===404,'no legal approval escalation');
ck30(req30('POST',$root.'/diplomacy/clock',['target_day'=>'2026-11-25'],$ta)['status']===404,'no direct subsystem clock');
ck30(req30('POST',$root.'/members',['account_id'=>$b['body']['account_id'],'access'=>'viewer'],$ta)['status']===200,'owner grants viewer');
ck30(req30('GET',$root.'/clock',null,$tb)['status']===200,'viewer can read shared world');
ck30(req30('POST',$root.'/clock/advances',['target_day'=>'2026-11-25'],$tb)['status']===403,'viewer cannot advance world');
$c=req30('GET',$root.'/clock',null,$ta);
$t=req30('POST',$root.'/clock/advances',['target_day'=>'2026-11-25'],$ta,['If-Match'=>$c['etag'],'Idempotency-Key'=>'day-advance-0001']);
ck30($t['status']===200 && $t['body']['coherent']===true,'server-authorized coordinated tick');
$after=req30('GET',$root.'/domestic',null,$ta);
ck30($after['body']['day']==='2026-11-25' && $after['body']['days'][0]['active_policies']===[],'unapproved reform remains inert');
ck30(req30('POST',$root.'/members',['account_id'=>$b['body']['account_id'],'access'=>'none'],$ta)['status']===200
    && req30('GET',$root.'/clock',null,$tb)['status']===404,'live membership revocation');
ck30(req30('DELETE','/session',null,$ta)['status']===200 && req30('GET','/worlds',null,$ta)['status']===401,'logout revokes token');
$ta2=req30('POST','/sessions',['username'=>'alice','password'=>'strong-password-123'])['body']['access_token'];
ck30(req30('GET',$root.'/clock',null,$ta2)['body']['committed_day']==='2026-11-25','login resumes saved world');
$scenarios=req30('GET','/scenarios',null,$ta2);
ck30($scenarios['status']===200 && count($scenarios['body']['scenarios'])===26,'WP31 scenario selection');
$g=req30('POST','/worlds',['label'=>'Global test','scenario'=>'fr-global-v1'],$ta2,['Idempotency-Key'=>'global-world-0001']);
ck30($g['status']===201,'WP31 global world provisioned');
$gr='/worlds/'.$g['body']['world_id'];
$refs=req30('GET',$gr.'/references',null,$ta2);
ck30($refs['status']===200 && $refs['body']['manifest']['un_members']===193 && count($refs['body']['countries'])===195,'WP31 worldwide reference API');
$actors=req30('GET',$gr.'/diplomacy/actors',null,$ta2);
ck30($actors['status']===200 && count($actors['body']['actors'])===196,'WP31 global diplomatic actors');
$legacy=req30('GET',$root.'/diplomacy/actors',null,$ta2);
ck30($legacy['status']===200 && count($legacy['body']['actors'])===5,'WP31 legacy world unchanged');
$gc=req30('GET',$gr.'/clock',null,$ta2);
ck30($gc['status']===200 && $gc['body']['committed_day']==='2026-11-25','WP31 pinned scenario date');
$next=req30('POST',$gr.'/clock/advances',['target_day'=>'2026-11-26'],$ta2,['If-Match'=>$gc['etag'],'Idempotency-Key'=>'global-tick-0001']);
ck30($next['status']===200 && $next['body']['coherent']===true,'WP31 global clock regression');
ck30(req30('GET',$gr.'/references',null,$tb)['status']===404,'WP31 no cross-world reference access');
$comp=req30('POST','/worlds',['label'=>'Comparative test','scenario'=>'fr-comparative-v1'],$ta2,['Idempotency-Key'=>'comparative-world-01']);
ck30($comp['status']===201,'WP32 comparative world created');
$cr='/worlds/'.$comp['body']['world_id'];
foreach(['DE','ES','GB','IT','JP','US'] as $cc) {
    $state=req30('GET',$cr.'/countries/'.$cc.'/institutions',null,$ta2);
    ck30($state['status']===200 && $state['body']['accountability']['status']==='in_office','WP32 state '.$cc);
}
$cc=req30('GET',$cr.'/clock',null,$ta2);
ck30(count($cc['body']['countries'])===7,'WP32 pinned seven national clocks');
$ct=req30('POST',$cr.'/clock/advances',['target_day'=>'2026-11-26'],$ta2,['If-Match'=>$cc['etag'],'Idempotency-Key'=>'comparative-tick-01']);
ck30($ct['status']===200 && $ct['body']['coherent']===true,'WP32 seven synchronized institutions');
$jp=req30('GET',$cr.'/countries/JP/institutions',null,$ta2);
ck30($jp['body']['accountability']['day']==='2026-11-26','WP32 nested accountability clock');
ck30(req30('POST',$cr.'/countries/DE/institutions/decisions',['action'=>'accountability_vote','payload'=>['evidence_id'=>'spoof']],$ta2)['status']===404,'WP32 HTTP cannot invent votes');
$el=req30('POST','/worlds',['label'=>'Electoral test','scenario'=>'fr-electoral-v1'],$ta2,['Idempotency-Key'=>'electoral-world-0001']);
ck30($el['status']===201,'WP33 electoral scenario creation');
$er='/worlds/'.$el['body']['world_id'];
$ep=$er.'/countries/FR/institutions';
$initial=req30('GET',$ep,null,$ta2);
ck30($initial['status']===200&&$initial['body']['electoral']['status']==='scheduled','WP33 initial poll');
$ec=req30('GET',$er.'/clock',null,$ta2);
for($i=1;$i<=8;$i++){
    $day=(new DateTimeImmutable('2026-11-25'))->modify('+'.$i.' days')->format('Y-m-d');
    $ec=req30('POST',$er.'/clock/advances',['target_day'=>$day],$ta2,['If-Match'=>$ec['etag'],'Idempotency-Key'=>'electoral-tick-'.sprintf('%03d',$i)]);
    ck30($ec['status']===200&&$ec['body']['coherent']===true,'WP33 daily tick '.$i);
}
$es=req30('GET',$ep,null,$ta2);
ck30($es['status']===200&&$es['body']['electoral']['result']['seats']['civic']===40,'WP33 final French seats');
$deal=['action'=>'electoral_coalition','payload'=>['parties'=>['civic','liberal'],'programme'=>['economy'=>575,'services'=>525],'candidate'=>'civic-leader']];
$neg=req30('POST',$ep.'/decisions',$deal,$ta2,['If-Match'=>$es['etag'],'Idempotency-Key'=>'electoral-deal-001']);
ck30($neg['status']===202&&$neg['body']['support_seats']===65&&!$neg['body']['appointed'],'WP33 owner negotiates fictional coalition');
ck30(req30('POST',$ep.'/decisions',['action'=>'electoral_certify','payload'=>['evidence_id'=>'fake']],$ta2)['status']===404,'WP33 no self-certified government');
ck30(req30('GET',$ep,null,$ta2)['body']['electoral']['government']===null,'WP33 majority remains separate from appointment');
ck30(req30('GET',$cr.'/countries/JP/institutions',null,$ta2)['body']['accountability']['day']==='2026-11-26','WP33 old comparative saves unchanged');
$pol=req30('POST','/worlds',['label'=>'Political AI test','scenario'=>'fr-political-v1'],$ta2,['Idempotency-Key'=>'political-world-0001']);
ck30($pol['status']===201,'WP34 political world');
$pr='/worlds/'.$pol['body']['world_id'];$pc=req30('GET',$pr.'/clock',null,$ta2);
$pc=req30('POST',$pr.'/clock/advances',['target_day'=>'2026-11-26'],$ta2,['If-Match'=>$pc['etag'],'Idempotency-Key'=>'political-tick-001']);
ck30($pc['status']===200&&$pc['body']['coherent']===true,'WP34 coherent political tick');
$gb=req30('GET',$pr.'/countries/GB/institutions',null,$ta2);
ck30($gb['status']===200&&$gb['body']['electoral']['coalition']['support_seats']===65,'WP34 foreign AI coalition');
ck30($gb['body']['accountability']['incumbent']==='initial-government'&&$gb['body']['electoral']['government']===null,'WP34 no unverified appointment');
$us=req30('GET',$pr.'/countries/US/institutions',null,$ta2);
ck30($us['body']['political_ai']['status']==='presidential_legislature','WP34 presidential separation');
$fr=req30('GET',$pr.'/countries/FR/institutions',null,$ta2);
ck30($fr['body']['political_ai']['status']==='awaiting_results','WP34 France human control');
ck30(req30('POST',$pr.'/countries/GB/institutions/decisions',['action'=>'electoral_certify','payload'=>['evidence_id'=>'fake']],$ta2)['status']===404,'WP34 foreign appointment denied');
ck30(!isset(req30('GET',$ep,null,$ta2)['body']['political_ai']),'WP34 previous electoral save unchanged');
$op=req30('POST','/worlds',['label'=>'Opinion test','scenario'=>'fr-opinion-v1'],$ta2,['Idempotency-Key'=>'opinion-world-0001']);
ck30($op['status']===201,'WP35 opinion world provisioned');
$or='/worlds/'.$op['body']['world_id'];$opPath=$or.'/countries/FR/opinion';
$ob=req30('GET',$opPath,null,$ta2);
ck30($ob['status']===200&&$ob['body']['history']===[]&&array_sum($ob['body']['intentions'])===10000,'WP35 baseline read');
$od=req30('GET',$or.'/domestic',null,$ta2);
$pp=req30('POST',$or.'/domestic/proposals',['policy_id'=>'education-reform','effective_day'=>'2026-11-26'],$ta2,
    ['If-Match'=>$od['etag'],'Idempotency-Key'=>'opinion-policy-001']);
ck30($pp['status']===202,'WP35 unapproved policy proposal');
$oc=req30('GET',$or.'/clock',null,$ta2);
ck30($oc['body']['opinion_enabled']===true,'WP35 opinion pinned');
$oh=['If-Match'=>$oc['etag'],'Idempotency-Key'=>'opinion-tick-0001'];
$ot=req30('POST',$or.'/clock/advances',['target_day'=>'2026-11-26'],$ta2,$oh);
ck30($ot['status']===200&&$ot['body']['coherent']===true&&in_array('opinion',$ot['body']['completed_subsystems'],true),'WP35 coherent fifth subsystem');
$oa=req30('GET',$opPath,null,$ta2);
ck30($oa['body']['day']==='2026-11-26'&&count($oa['body']['history'])===1&&$oa['body']['approval']===$ob['body']['approval'],'WP35 proposal not enactment');
ck30($oa['body']['history'][0]['source']['active_policies']===[]&&strlen($oa['body']['history'][0]['source']['domestic_sha'])===64,'WP35 source digest');
ck30(req30('POST',$or.'/clock/advances',['target_day'=>'2026-11-26'],$ta2,$oh)['body']===$ot['body'],'WP35 idempotent world replay');
ck30(count(req30('GET',$opPath,null,$ta2)['body']['history'])===1,'WP35 no duplicate opinion observation');
ck30(req30('POST',$opPath,['approval'=>1000],$ta2)['status']===404,'WP35 no client opinion mutation');
ck30(req30('GET',$opPath,null,$tb)['status']===404&&req30('GET',$pr.'/countries/FR/opinion',null,$ta2)['status']===404,'WP35 isolated and opt-in');
$dg=req30('POST','/worlds',['label'=>'Civic dialogue test','scenario'=>'fr-dialogue-v1'],$ta2,['Idempotency-Key'=>'dialogue-world-001']);
ck30($dg['status']===201,'WP36 dialogue world provisioned');
$dr='/worlds/'.$dg['body']['world_id'];$dp=$dr.'/countries/FR/dialogue';
$dv=req30('GET',$dp,null,$ta2);
ck30($dv['status']===200&&$dv['body']['dialogue']['groups']['workers-union']['phase']==='idle','WP36 baseline dialogue');
$dc=req30('GET',$dr.'/clock',null,$ta2);
$dt=req30('POST',$dr.'/clock/advances',['target_day'=>'2026-11-26'],$ta2,['If-Match'=>$dc['etag'],'Idempotency-Key'=>'dialogue-tick-0001']);
ck30($dt['status']===200&&$dt['body']['coherent']===true&&in_array('opinion',$dt['body']['completed_subsystems'],true),'WP36 durable opinion checkpoint');
$dv=req30('GET',$dp,null,$ta2);
$opv=req30('GET',$dr.'/countries/FR/opinion',null,$ta2);
ck30($dv['body']['dialogue']['day']==='2026-11-26'&&$opv['body']['dialogue']===$dv['body']['dialogue'],'WP36 consistent dialogue projection');
ck30($opv['body']['history'][0]['result']['dialogue']===$dv['body']['dialogue'],'WP36 dialogue daily journal');
ck30(req30('POST',$dp,['phase'=>'unresolved'],$ta2)['status']===404,'WP36 no client mutation');
ck30(req30('GET',$dp,null,$tb)['status']===404&&req30('GET',$or.'/countries/FR/dialogue',null,$ta2)['status']===404,'WP36 membership and legacy isolation');
$sur=req30('POST','/worlds',['label'=>'Survival test','scenario'=>'fr-survival-v1'],$ta2,['Idempotency-Key'=>'survival-world-001']);
ck30($sur['status']===201,'WP37 survival world provisioned');
$sr='/worlds/'.$sur['body']['world_id'];$sc=req30('GET',$sr.'/campaign',null,$ta2);
ck30($sc['body']['status']==='active'&&$sc['body']['office_retained'],'WP37 active mandate');
ck30(req30('GET',$sr.'/countries/FR/politics',null,$ta2)['body']['head_of_state']==='campaign-president','WP37 pinned player character');
ck30(req30('POST',$sr.'/campaign',['type'=>'revolution_succeeded'],$ta2)['status']===404,'WP37 no player outcome authoring');
$rev=req30('POST','/worlds',['label'=>'Revolution narrative case','scenario'=>'fr-revolution-case-v1'],$ta2,['Idempotency-Key'=>'revolution-world-001']);
ck30($rev['status']===201,'WP37 scripted crisis case');
$rr='/worlds/'.$rev['body']['world_id'];$rc=req30('GET',$rr.'/clock',null,$ta2);
for($n=1;$n<=4;$n++){
    $day=(new DateTimeImmutable('2026-11-25'))->modify('+'.$n.' days')->format('Y-m-d');
    $rh=['If-Match'=>$rc['etag'],'Idempotency-Key'=>'revolution-tick-'.sprintf('%03d',$n)];
    $rc=req30('POST',$rr.'/clock/advances',['target_day'=>$day],$ta2,$rh);
    ck30($rc['status']===200&&$rc['body']['coherent']===true&&$rc['body']['game_over']===($n===4),'WP37 distinct terminal tick '.$n);
}
$end=req30('GET',$rr.'/campaign',null,$ta2);
ck30($end['body']['status']==='game_over'&&$end['body']['regime_changed']&&$end['body']['reason']==='revolution_succeeded','WP37 archived revolutionary defeat');
ck30(req30('POST',$rr.'/clock/advances',['target_day'=>'2026-11-29'],$ta2,$rh)['body']===$rc['body'],'WP37 terminal receipt remains replayable');
$locked=req30('POST',$rr.'/clock/advances',['target_day'=>'2026-11-30'],$ta2,['If-Match'=>$rc['etag'],'Idempotency-Key'=>'revolution-tick-005']);
ck30($locked['status']===409&&$locked['body']['code']==='game_over','WP37 terminal clock locked');
$domEnd=req30('GET',$rr.'/domestic',null,$ta2);
ck30(req30('POST',$rr.'/domestic/proposals',['policy_id'=>'education-reform','effective_day'=>'2026-11-30'],$ta2,
    ['If-Match'=>$domEnd['etag'],'Idempotency-Key'=>'terminal-policy-001'])['status']===409,'WP37 terminal mutations blocked');
ck30(req30('GET',$rr.'/campaign',null,$tb)['status']===404&&req30('GET',$dr.'/campaign',null,$ta2)['status']===404,'WP37 isolated and opt-in');
ck30(req30('GET',$sr.'/campaign',null,$ta2)['body']['status']==='active','WP37 defeat does not affect another world');
$pres=req30('POST','/worlds',['label'=>'Presidential test','scenario'=>'fr-presidential-v1'],$tb,['Idempotency-Key'=>'presidential-world-001']);
ck30($pres['status']===201,'WP38 presidential world provisioned');
$ps='/worlds/'.$pres['body']['world_id'];$ppath=$ps.'/countries/FR/presidency';
$pv=req30('GET',$ppath,null,$tb);
ck30($pv['status']===200&&$pv['body']['term_end']==='2026-12-21'&&!isset($pv['body']['config']),'WP38 presidential calendar');
$pc=req30('GET',$ps.'/clock',null,$tb);
for($n=1;$n<=26;$n++){
    $day=(new DateTimeImmutable('2026-11-25'))->modify('+'.$n.' days')->format('Y-m-d');
    $pc=req30('POST',$ps.'/clock/advances',['target_day'=>$day],$tb,['If-Match'=>$pc['etag'],'Idempotency-Key'=>'presidential-tick-'.sprintf('%03d',$n)]);
    if($pc['status']!==200||!$pc['body']['coherent']||$pc['body']['game_over'])throw new RuntimeException('WP38 presidential clock failed '.$n.' '.json_encode($pc));
    if($n===1){
        $pv=req30('GET',$ppath,null,$tb);
        ck30($pv['body']['status']==='runoff_pending'&&$pv['body']['result']===null,'WP38 first round persisted');
    }
    if($n===15){
        $pv=req30('GET',$ppath,null,$tb);
        ck30($pv['body']['result']['winner']==='campaign-president'&&$pv['body']['consecutive_terms']===1,'WP38 results separate from handover');
    }
}
$pv=req30('GET',$ppath,null,$tb);
ck30($pv['body']['consecutive_terms']===2&&!$pv['body']['eligible_for_re_election']&&$pv['body']['term_end']==='2031-12-21','WP38 reelection and term cap persisted');
ck30(req30('GET',$ps.'/campaign',null,$tb)['body']['status']==='active','WP38 reelected player remains active');
ck30(req30('POST',$ppath,['winner'=>'campaign-president'],$tb)['status']===404,'WP38 no player ballot submission');
ck30(req30('GET',$ppath,null,$ta2)['status']===404&&req30('GET',$sr.'/countries/FR/presidency',null,$ta2)['status']===404,'WP38 isolation and legacy compatibility');
$rv=req30('POST','/worlds',['label'=>'Revision test','scenario'=>'fr-revision-v1'],$tb,['Idempotency-Key'=>'revision-world-001']);
ck30($rv['status']===201,'WP39 revision world provisioned');
$rvw=$rv['body']['world_id'];$rvr='/worlds/'.$rvw;$rvp=$rvr.'/countries/FR/constitution';$rvi=$rvr.'/countries/FR/institutions/decisions';
$rvb=req30('GET',$rvp,null,$tb);
ck30($rvb['status']===200&&$rvb['body']['revision']['constitution_version']===0,'WP39 initial constitution');
$draft=['action'=>'revision_draft','payload'=>['template'=>'environment-report']];
$dh=['If-Match'=>$rvb['etag'],'Idempotency-Key'=>'revision-draft-001'];
$drf=req30('POST',$rvi,$draft,$tb,$dh);
ck30($drf['status']===202&&!$drf['body']['legal_effect'],'WP39 player draft only');
ck30(req30('POST',$rvi,$draft,$tb,$dh)['body']===$drf['body'],'WP39 idempotent draft');
ck30(req30('POST',$rvi,['action'=>'revision_evidence','payload'=>['evidence_id'=>'fake']],$tb)['status']===404,'WP39 no client certification');
require_once __DIR__.'/../games/world-politics/src/LocalRevisionClerk.php';
$rid=$drf['body']['procedure']['id'];$hash=$drf['body']['procedure']['text_sha'];$day='2026-11-25';
$events=[
    ['file',['initiative'=>'government_project','sponsor'=>'president','prime_minister_proposal'=>true,'republican_form_preserved'=>true]],
    ['chamber_vote',['chamber'=>'assembly','text_sha'=>$hash,'yes'=>300,'no'=>250,'abstain'=>20,'blank_invalid'=>0]],
    ['chamber_vote',['chamber'=>'senate','text_sha'=>$hash,'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0]],
    ['route',['route'=>'congress','presidential_decision'=>true]],
    ['ratify',['route'=>'congress','text_sha'=>$hash,'yes'=>480,'no'=>320,'abstain'=>40,'blank_invalid'=>0]],
    ['promulgate',['text_sha'=>$hash,'published_on'=>$day]]
];
foreach($events as $n=>[$kind,$data]){
    $id='revision-proof-'.($n+1);
    $e=['world_id'=>$rvw,'country'=>'FR','day'=>$day,'evidence_id'=>$id,'revision_id'=>$rid,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
    $store=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalRevisionClerk($e));
    $state=$store->read($rvw,'FR');
    $result=$store->decide($rvw,'FR','revision_evidence',['evidence_id'=>$id],'revision-key-'.($n+1),$state['etag']);
    ck30($result['status']===202,'WP39 trusted clerk durable event '.$kind);
}
$rve=req30('GET',$rvp,null,$tb);
ck30($rve['body']['revision']['constitution_version']===1&&$rve['body']['revision']['effective_clauses']['fictional-89-a']['text_sha']===$hash,'WP39 effective constitutional text persisted');
$cc39=req30('GET',$rvr.'/clock',null,$tb);
$tt39=req30('POST',$rvr.'/clock/advances',['target_day'=>'2026-11-26'],$tb,['If-Match'=>$cc39['etag'],'Idempotency-Key'=>'revision-tick-001']);
ck30($tt39['status']===200&&$tt39['body']['coherent']===true,'WP39 synchronized constitutional clock');
ck30(req30('GET',$rvp,null,$tb)['body']['revision']['day']==='2026-11-26','WP39 revision date advanced');
ck30(req30('GET',$rvp,null,$ta2)['status']===404&&req30('GET',$ps.'/countries/FR/constitution',null,$tb)['status']===404,'WP39 membership and previous scenarios');
$lg=req30('POST','/worlds',['label'=>'Legislation test','scenario'=>'fr-legislative-v1'],$tb,['Idempotency-Key'=>'legislation-world-001']);
ck30($lg['status']===201,'WP40 legislation world');
$lw=$lg['body']['world_id'];$lr='/worlds/'.$lw;$lp=$lr.'/countries/FR/legislation';$li=$lr.'/countries/FR/institutions/decisions';
$lb=req30('GET',$lp,null,$tb);
$ld=req30('POST',$li,['action'=>'legislation_draft','payload'=>['policy_id'=>'education-reform']],$tb,['If-Match'=>$lb['etag'],'Idempotency-Key'=>'legislation-draft-001']);
ck30($ld['status']===202&&!$ld['body']['policy_authorized'],'WP40 draft not law');
$dd=req30('GET',$lr.'/domestic',null,$tb);
$dp40=req30('POST',$lr.'/domestic/proposals',['policy_id'=>'education-reform','effective_day'=>'2026-11-26'],$tb,['If-Match'=>$dd['etag'],'Idempotency-Key'=>'legislation-policy-001']);
ck30($dp40['status']===202,'WP40 domestic proposal');
require_once __DIR__.'/../games/world-politics/src/LocalLegislationClerk.php';
require_once __DIR__.'/../games/world-politics/src/LocalLegislativePublisher.php';
$bid=$ld['body']['bill']['id'];$sha=$ld['body']['bill']['texts']['standard'];
try{\Aeternum\WorldPolitics\LocalLegislativePublisher::apply($pdo,$lw,$bid);throw new RuntimeException('Premature enactment');}
catch(DomainException $e){ck30(true,'WP40 draft cannot enact');}
$events=[['file',['government_project'=>true,'accelerated'=>true,'joint_opposition'=>false,'filing_certified'=>true]]];
foreach(['assembly','senate'] as $ch)$events[]=['reading',['chamber'=>$ch,'version'=>'standard','text_sha'=>$sha,'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0,'amendment_certified'=>false,'reading_delay_certified'=>true]];
$events[]=['transmit',['text_sha'=>$sha]];
$events[]=['promulgate',['text_sha'=>$sha,'presidential_act_certified'=>true]];
$events[]=['publish',['text_sha'=>$sha,'effective_on'=>'2026-11-26','publication_certified'=>true]];
foreach($events as $n=>[$kind,$data]){
    $id='legislation-proof-'.($n+1);
    $e=['world_id'=>$lw,'country'=>'FR','day'=>'2026-11-25','evidence_id'=>$id,'bill_id'=>$bid,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
    $st=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalLegislationClerk($e));
    $read=$st->read($lw,'FR');
    ck30($st->decide($lw,'FR','legislation_evidence',['evidence_id'=>$id],'legislation-key-'.($n+1),$read['etag'])['status']===202,'WP40 durable '.$kind);
}
$auth=\Aeternum\WorldPolitics\LocalLegislativePublisher::apply($pdo,$lw,$bid);
ck30($auth['result']['policy']['authorized_on']==='2026-11-25','WP40 verified publication authorizes exact domestic proposal');
ck30(\Aeternum\WorldPolitics\LocalLegislativePublisher::apply($pdo,$lw,$bid)['result']===$auth['result'],'WP40 safe enactment retry');
$lc=req30('GET',$lr.'/clock',null,$tb);
$lt=req30('POST',$lr.'/clock/advances',['target_day'=>'2026-11-26'],$tb,['If-Match'=>$lc['etag'],'Idempotency-Key'=>'legislation-tick-001']);
ck30($lt['status']===200&&$lt['body']['coherent']===true,'WP40 synchronized legislation');
$ds=req30('GET',$lr.'/domestic',null,$tb)['body'];
ck30(in_array('education-reform',$ds['days'][0]['active_policies'],true)&&$ds['days'][0]['expense']===11200000,'WP40 enacted law has actual budget effect');
ck30(req30('POST',$li,['action'=>'legislation_evidence','payload'=>['evidence_id'=>'fake']],$tb)['status']===404,'WP40 player cannot certify vote');
ck30(req30('GET',$lp,null,$ta2)['status']===404&&req30('GET',$rvp.'/legislation',null,$tb)['status']===404,'WP40 membership isolation');
$resp=req30('POST','/worlds',['label'=>'Responsibility test','scenario'=>'fr-responsibility-v1'],$tb,['Idempotency-Key'=>'responsibility-world-001']);
ck30($resp['status']===201,'WP41 responsibility world provisioned');
$rw=$resp['body']['world_id'];$rp='/worlds/'.$rw;$rl=$rp.'/countries/FR/legislation';$ri=$rp.'/countries/FR/institutions/decisions';
$read=req30('GET',$rl,null,$tb);
$draft=req30('POST',$ri,['action'=>'legislation_draft','payload'=>['policy_id'=>'education-reform']],$tb,['If-Match'=>$read['etag'],'Idempotency-Key'=>'responsibility-draft-001']);
ck30($draft['status']===202,'WP41 ordinary bill draft');
$bill=$draft['body']['bill']['id'];$hash=$draft['body']['bill']['texts']['standard'];
$events=[['file',['government_project'=>true,'accelerated'=>true,'joint_opposition'=>false,'filing_certified'=>true]],
    ['responsibility_engage',['at'=>'2026-11-25T10:00:00Z','council_deliberated'=>true,'version'=>'standard','text_sha'=>$hash,'reading_delay_certified'=>true]],
    ['responsibility_file',['at'=>'2026-11-25T11:00:00Z','motion_id'=>'motion-one','signatures'=>58]]];
foreach($events as $n=>[$kind,$data]){
    $id='responsibility-proof-'.($n+1);
    $e=['world_id'=>$rw,'country'=>'FR','day'=>'2026-11-25','evidence_id'=>$id,'bill_id'=>$bill,'kind'=>$kind,'procedure_certified'=>true,'data'=>$data];
    $st=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalLegislationClerk($e));
    $rd=$st->read($rw,'FR');ck30($st->decide($rw,'FR','legislation_evidence',['evidence_id'=>$id],'responsibility-key-'.($n+1),$rd['etag'])['status']===202,'WP41 trusted '.$kind);
}
$cl=req30('GET',$rp.'/clock',null,$tb);
foreach(['2026-11-26','2026-11-27'] as $n=>$day){
    $cl=req30('POST',$rp.'/clock/advances',['target_day'=>$day],$tb,['If-Match'=>$cl['etag'],'Idempotency-Key'=>'responsibility-tick-00'.($n+1)]);
    ck30($cl['status']===200&&$cl['body']['coherent']&&!$cl['body']['game_over'],'WP41 coherent pending motion');
}
$e=['world_id'=>$rw,'country'=>'FR','day'=>'2026-11-27','evidence_id'=>'responsibility-vote','bill_id'=>$bill,'kind'=>'responsibility_vote','procedure_certified'=>true,
    'data'=>['at'=>'2026-11-27T11:00:00Z','motion_id'=>'motion-one','votes_for'=>289]];
$st=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalLegislationClerk($e));$rd=$st->read($rw,'FR');
$vr=$st->decide($rw,'FR','legislation_evidence',['evidence_id'=>'responsibility-vote'],'responsibility-vote-001',$rd['etag']);
ck30($vr['result']['bill']['status']==='rejected','WP41 censured bill persists');
$cl=req30('POST',$rp.'/clock/advances',['target_day'=>'2026-11-28'],$tb,['If-Match'=>$cl['etag'],'Idempotency-Key'=>'responsibility-tick-003']);
ck30($cl['status']===200&&!$cl['body']['game_over'],'WP41 presidential campaign survives censure');
$camp=req30('GET',$rp.'/campaign',null,$tb)['body'];
ck30($camp['cabinet_status']==='resignation_required'&&$camp['office_retained'],'WP41 cabinet status projected');
ck30(req30('POST',$ri,['action'=>'legislation_evidence','payload'=>['evidence_id'=>'fake']],$tb)['status']===404,'WP41 no public certification');
ck30(!isset(req30('GET',$lp,null,$tb)['body']['legislation']['responsibility']),'WP41 prior legislative world unchanged');
$as42=req30('POST','/worlds',['label'=>'Assembly test','scenario'=>'fr-assembly-v1'],$tb,['Idempotency-Key'=>'assembly-world-0001']);
ck30($as42['status']===201,'WP42 Assembly world');
$aw42=$as42['body']['world_id'];$ar42='/worlds/'.$aw42;$ap42=$ar42.'/countries/FR/assembly';
$ab42=req30('GET',$ap42,null,$tb);
ck30($ab42['status']===200&&count($ab42['body']['assembly']['deputies'])===577,'WP42 577 individual deputies');
$names42=array_slice(array_keys($ab42['body']['assembly']['deputies']),0,58);
$ev42=['world_id'=>$aw42,'country'=>'FR','day'=>'2026-11-25','evidence_id'=>'assembly-file','bill_id'=>null,'kind'=>'assembly_censure_file','procedure_certified'=>true,
    'data'=>['at'=>'2026-11-25T10:00:00Z','motion_id'=>'motion-one','signatories'=>$names42]];
$st42=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalLegislationClerk($ev42));
$rd42=$st42->read($aw42,'FR');
ck30($st42->decide($aw42,'FR','legislation_evidence',['evidence_id'=>'assembly-file'],'assembly-file-001',$rd42['etag'])['status']===202,'WP42 certified signature roll');
$clock42=req30('GET',$ar42.'/clock',null,$tb);
foreach(['2026-11-26','2026-11-27'] as $n42=>$day42){
    $clock42=req30('POST',$ar42.'/clock/advances',['target_day'=>$day42],$tb,['If-Match'=>$clock42['etag'],'Idempotency-Key'=>'assembly-tick-00'.($n42+1)]);
    ck30($clock42['status']===200&&$clock42['body']['coherent'],'WP42 coherent clock');
}
$ev42['day']='2026-11-27';$ev42['evidence_id']='assembly-vote';$ev42['kind']='assembly_censure_vote';
$ev42['data']=['at'=>'2026-11-27T10:00:00Z','motion_id'=>'motion-one'];
$st42=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalLegislationClerk($ev42));
$rd42=$st42->read($aw42,'FR');
$receipt42=$st42->decide($aw42,'FR','legislation_evidence',['evidence_id'=>'assembly-vote'],'assembly-vote-001',$rd42['etag']);
ck30($receipt42['status']===202&&array_sum($receipt42['result']['roll_call']['tally'])===577,'WP42 durable generated vote');
$ab42=req30('GET',$ap42,null,$tb);
ck30($ab42['body']['assembly']['matters']['motion-one']['result']===$receipt42['result']['roll_call'],'WP42 exact archived individual votes');
ck30(req30('GET',$ap42,null,$ta2)['status']===404&&req30('GET',$rp.'/countries/FR/assembly',null,$tb)['status']===404,'WP42 isolation and legacy');
$par43=req30('POST','/worlds',['label'=>'Parliament lifecycle','scenario'=>'fr-parliament-v1'],$tb,['Idempotency-Key'=>'parliament-world-001']);
ck30($par43['status']===201,'WP43 parliament world');
$w43=$par43['body']['world_id'];$r43='/worlds/'.$w43;$p43=$r43.'/countries/FR/assembly';
$a43=req30('GET',$p43,null,$tb);
ck30($a43['status']===200&&$a43['body']['lifecycle']['session']['id']==='ordinary-2026','WP43 session pinned');
$names43=array_slice(array_keys($a43['body']['assembly']['deputies']),0,58);
$ev43=['world_id'=>$w43,'country'=>'FR','day'=>'2026-11-25','evidence_id'=>'calendar-motion','bill_id'=>null,'kind'=>'assembly_censure_file','procedure_certified'=>true,
    'data'=>['at'=>'2026-11-25T10:00:00Z','motion_id'=>'motion-calendar','signatories'=>$names43,'vote_at'=>'2026-11-30T10:00:00Z',
        'sitting_days'=>['2026-11-27','2026-11-30','2026-12-01','2026-12-02','2026-12-03']]];
$st43=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalLegislationClerk($ev43));$rd43=$st43->read($w43,'FR');
ck30($st43->decide($w43,'FR','legislation_evidence',['evidence_id'=>'calendar-motion'],'calendar-motion-001',$rd43['etag'])['status']===202,'WP43 motion filed with certified agenda');
$c43=req30('GET',$r43.'/clock',null,$tb);
for($n43=1;$n43<=8;$n43++){
    $day43=(new DateTimeImmutable('2026-11-25'))->modify('+'.$n43.' days')->format('Y-m-d');
    $c43=req30('POST',$r43.'/clock/advances',['target_day'=>$day43],$tb,['If-Match'=>$c43['etag'],'Idempotency-Key'=>'parliament-tick-'.sprintf('%03d',$n43)]);
    if($c43['status']!==200||!$c43['body']['coherent'])throw new RuntimeException('WP43 clock failed '.$day43.' '.json_encode($c43));
}
$a43=req30('GET',$p43,null,$tb);
ck30($a43['body']['lifecycle']['slots']['motion-calendar']['status']==='resolved','WP43 automatic vote');
ck30($a43['body']['lifecycle']['installed_on']==='2026-12-03'&&count($a43['body']['assembly']['deputies'])===577,'WP43 election roster installed');
ck30(count($a43['body']['lifecycle']['archives'][0]['assembly']['matters']['motion-calendar']['roll_call'])===577,'WP43 historical roll preserved');
ck30(req30('GET',$p43,null,$ta2)['status']===404&&!isset(req30('GET',$ap42,null,$tb)['body']['lifecycle']),'WP43 membership and legacy');
$g44=req30('POST','/worlds',['label'=>'Cabinet lifecycle','scenario'=>'fr-cabinet-v1'],$tb,['Idempotency-Key'=>'cabinet-world-001']);
ck30($g44['status']===201,'WP44 cabinet world');
$w44=$g44['body']['world_id'];$r44='/worlds/'.$w44;$p44=$r44.'/countries/FR/cabinet';
$b44=req30('GET',$p44,null,$tb);
ck30($b44['status']===200&&$b44['body']['cabinet']['prime_minister']==='pm-initial'&&!$b44['body']['cohabitation'],'WP44 initial cabinet');
$events44=[
    ['assembly_cabinet_resign',['at'=>'2026-11-25T10:00:00Z','prime_minister'=>'pm-initial','resignation_id'=>'resignation-one','resignation_presented'=>true]],
    ['assembly_cabinet_appoint',['at'=>'2026-11-25T11:00:00Z','appointment_id'=>'appointment-one','prime_minister'=>'pm-social','president'=>'campaign-president','resignation_presented'=>true,'presidential_act_certified'=>true,'incompatibilities_cleared'=>true]],
    ['assembly_cabinet_portfolio',['at'=>'2026-11-25T12:00:00Z','prime_minister'=>'pm-social','president'=>'campaign-president','portfolio'=>'education','person'=>'social-education','pm_proposal_certified'=>true,'presidential_act_certified'=>true,'incompatibilities_cleared'=>true]]
];
foreach($events44 as $n44=>[$k44,$d44]){
    $id44='cabinet-proof-'.($n44+1);
    $e44=['world_id'=>$w44,'country'=>'FR','day'=>'2026-11-25','evidence_id'=>$id44,'bill_id'=>null,'kind'=>$k44,'procedure_certified'=>true,'data'=>$d44];
    $s44=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalLegislationClerk($e44));$rd44=$s44->read($w44,'FR');
    ck30($s44->decide($w44,'FR','legislation_evidence',['evidence_id'=>$id44],'cabinet-key-'.($n44+1),$rd44['etag'])['status']===202,'WP44 durable '.$k44);
}
$b44=req30('GET',$p44,null,$tb);
ck30($b44['body']['cohabitation']&&$b44['body']['cabinet']['ministers']['education']==='social-education','WP44 cohabitation and ministry persisted');
$c44=req30('GET',$r44.'/clock',null,$tb);
$c44=req30('POST',$r44.'/clock/advances',['target_day'=>'2026-11-26'],$tb,['If-Match'=>$c44['etag'],'Idempotency-Key'=>'cabinet-tick-001']);
ck30($c44['status']===200&&!$c44['body']['game_over'],'WP44 presidency remains active');
ck30(req30('GET',$p44,null,$ta2)['status']===404&&req30('GET',$r43.'/countries/FR/cabinet',null,$tb)['status']===404,'WP44 isolation and legacy');
$g45=req30('POST','/worlds',['label'=>'Executive acts','scenario'=>'fr-executive-v1'],$tb,['Idempotency-Key'=>'executive-world-001']);
ck30($g45['status']===201,'WP45 executive world');
$w45=$g45['body']['world_id'];$r45='/worlds/'.$w45;$p45=$r45.'/countries/FR/executive';
$e45=req30('GET',$p45,null,$tb);
ck30($e45['status']===200&&$e45['body']['executive']['acts']===[],'WP45 initial executive');
$d45=req30('GET',$r45.'/domestic',null,$tb);
$pr45=req30('POST',$r45.'/domestic/proposals',['policy_id'=>'administration-report','effective_day'=>'2026-11-26'],$tb,['If-Match'=>$d45['etag'],'Idempotency-Key'=>'executive-policy-001']);
ck30($pr45['status']===202,'WP45 domestic proposal');
$sign45=['template'=>'reporting-decree','kind'=>'pm_decree','habilitation'=>null,'signer'=>'pm-initial','prime_minister'=>'pm-initial','minister'=>'civic-economy','council_deliberated'=>false,'state_council_consulted'=>false,'procedure_reviewed'=>true];
$ev45=['world_id'=>$w45,'country'=>'FR','day'=>'2026-11-25','evidence_id'=>'decree-one','bill_id'=>null,'kind'=>'executive_sign','procedure_certified'=>true,'data'=>$sign45];
$st45=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalLegislationClerk($ev45));$rd45=$st45->read($w45,'FR');
$res45=$st45->decide($w45,'FR','legislation_evidence',['evidence_id'=>'decree-one'],'executive-sign-001',$rd45['etag']);
ck30($res45['status']===202,'WP45 signed decree persisted');
try{\Aeternum\WorldPolitics\LocalLegislativePublisher::apply($pdo,$w45,'decree-one',true);throw new RuntimeException('Premature decree implementation');}
catch(DomainException $e){ck30(true,'WP45 signature insufficient');}
$ev45['evidence_id']='decree-publication';$ev45['kind']='executive_publish';
$ev45['data']=['act_id'=>'decree-one','text_sha'=>$res45['result']['act']['text_sha'],'publication_certified'=>true,'effective_on'=>'2026-11-26'];
$st45=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalLegislationClerk($ev45));$rd45=$st45->read($w45,'FR');
ck30($st45->decide($w45,'FR','legislation_evidence',['evidence_id'=>'decree-publication'],'executive-publish-001',$rd45['etag'])['status']===202,'WP45 published decree persisted');
$ap45=\Aeternum\WorldPolitics\LocalLegislativePublisher::apply($pdo,$w45,'decree-one',true);
ck30($ap45['result']['policy']['authorized_on']==='2026-11-25','WP45 registry verified implementation');
$c45=req30('GET',$r45.'/clock',null,$tb);
$c45=req30('POST',$r45.'/clock/advances',['target_day'=>'2026-11-26'],$tb,['If-Match'=>$c45['etag'],'Idempotency-Key'=>'executive-tick-001']);
ck30($c45['status']===200&&$c45['body']['coherent'],'WP45 coherent clock');
$ds45=req30('GET',$r45.'/domestic',null,$tb)['body'];
ck30(in_array('administration-report',$ds45['days'][0]['active_policies'],true)&&$ds45['days'][0]['expense']===11050000,'WP45 actual administrative expense');
ck30(req30('GET',$p45,null,$ta2)['status']===404&&req30('GET',$r44.'/countries/FR/executive',null,$tb)['status']===404,'WP45 isolation and legacy');
$g46=req30('POST','/worlds',['label'=>'Finance budget','scenario'=>'fr-finance-v1'],$tb,['Idempotency-Key'=>'finance-world-001']);
ck30($g46['status']===201,'WP46 finance world');
$w46=$g46['body']['world_id'];$r46='/worlds/'.$w46;$p46=$r46.'/countries/FR/finance';
$f46=req30('GET',$p46,null,$tb);
ck30($f46['status']===200&&count($f46['body']['catalog'])===2,'WP46 initial finance catalog');
$d46=req30('GET',$r46.'/domestic',null,$tb);
ck30(req30('POST',$r46.'/domestic/proposals',['policy_id'=>'budget-2027-balance','effective_day'=>'2027-01-01'],$tb,['If-Match'=>$d46['etag'],'Idempotency-Key'=>'finance-policy-001'])['status']===202,'WP46 owner budget proposal');
$sha46=\Aeternum\WorldPolitics\FinanceBudget::digest('budget-2027-balance');
$v46=['bill_id'=>'finance-bill','text_sha'=>$sha46,'yes'=>180,'no'=>140,'abstain'=>20,'blank_invalid'=>0];
$events46=[
 ['finance-bill','finance_file',['policy_id'=>'budget-2027-balance','prime_minister'=>'pm-initial','finance_minister'=>'civic-economy','government_project_certified'=>true]],
 ['finance-a','finance_assembly',$v46],['finance-t','finance_transfer',['bill_id'=>'finance-bill','prime_minister'=>'pm-initial','transmission_certified'=>true]],
 ['finance-s','finance_senate',$v46],
 ['finance-final','finance_final',['bill_id'=>'finance-bill','text_sha'=>$sha46,'route'=>'identical','navette_certified'=>true,'yes'=>0,'no'=>0,'abstain'=>0,'blank_invalid'=>0]],
 ['finance-review','finance_review',['bill_id'=>'finance-bill','text_sha'=>$sha46,'constitutional'=>true,'decision_certified'=>true]],
 ['finance-publish','finance_publish',['bill_id'=>'finance-bill','text_sha'=>$sha46,'president'=>'campaign-president','promulgation_certified'=>true,'publication_certified'=>true]]
];
foreach($events46 as [$id46,$k46,$data46]){
 $e46=['world_id'=>$w46,'country'=>'FR','day'=>'2026-11-25','evidence_id'=>$id46,'bill_id'=>null,'kind'=>$k46,'procedure_certified'=>true,'data'=>$data46];
 $st46=new \Aeternum\WorldPolitics\PdoInstitutionalStore($pdo,new \Aeternum\WorldPolitics\LocalLegislationClerk($e46));$rd46=$st46->read($w46,'FR');
 ck30($st46->decide($w46,'FR','legislation_evidence',['evidence_id'=>$id46],'key-'.$id46,$rd46['etag'])['status']===202,'WP46 durable '.$k46);
}
$ap46=\Aeternum\WorldPolitics\LocalLegislativePublisher::apply($pdo,$w46,'finance-bill',false,true);
ck30($ap46['result']['policy']['authorized_on']==='2026-11-25','WP46 verified finance implementation');
$c46=req30('GET',$r46.'/clock',null,$tb);
$c46=req30('POST',$r46.'/clock/advances',['target_day'=>'2026-11-26'],$tb,['If-Match'=>$c46['etag'],'Idempotency-Key'=>'finance-tick-001']);
ck30($c46['status']===200&&$c46['body']['coherent'],'WP46 coherent finance clock');
$f46=req30('GET',$p46,null,$tb);
ck30($f46['body']['treasury']['bills']['finance-bill']['implementation_authorized']&&count($f46['body']['treasury']['days'])===1,'WP46 coherent treasury projection');
ck30(req30('GET',$p46,null,$ta2)['status']===404&&req30('GET',$r45.'/countries/FR/finance',null,$tb)['status']===404,'WP46 isolation and legacy');
// The isolated integration suite now exercises hundreds of requests in one
// minute. Begin WP47 with a fresh rate-limit window, not a weakened runtime
// limit. The WP_TEST_ISOLATED / wp_test guard at the top remains mandatory.
$pdo->exec('DELETE FROM wp_http_limits');
$g47=req30('POST','/worlds',['label'=>'Macroeconomic pilot','scenario'=>'fr-macro-v1'],$tb,['Idempotency-Key'=>'macro-world-001']);
ck30($g47['status']===201,'WP47 macro world');
$w47=$g47['body']['world_id'];$r47='/worlds/'.$w47;$p47=$r47.'/countries/FR/macro';
$m47=req30('GET',$p47,null,$tb);
ck30($m47['status']===200&&$m47['body']['latest']===null&&count($m47['body']['source_hashes'])===3,'WP47 initial scoped projection');
$c47=req30('GET',$r47.'/clock',null,$tb);
foreach(['2026-11-26','2026-11-27','2026-11-28'] as $n47=>$day47){
    $c47=req30('POST',$r47.'/clock/advances',['target_day'=>$day47],$tb,['If-Match'=>$c47['etag'],'Idempotency-Key'=>'macro-tick-00'.($n47+1)]);
    ck30($c47['status']===200&&$c47['body']['coherent'],'WP47 coherent day');
}
$m47=req30('GET',$p47,null,$tb);
if($m47['status']!==200)throw new RuntimeException('WP47 macro read HTTP '.$m47['status'].' '.json_encode($m47['body'],JSON_THROW_ON_ERROR));
$row47=$m47['body']['latest']??throw new RuntimeException('WP47 macro latest missing');
ck30($m47['status']===200&&count($m47['body']['days'])===3&&$row47['real_gdp']===$row47['consumption']+$row47['investment']+$row47['government_consumption']+$row47['exports']-$row47['imports'],'WP47 national accounting identity');
$again47=req30('GET',$p47,null,$tb);
ck30($again47['etag']===$m47['etag']&&$again47['body']===$m47['body'],'WP47 deterministic ETag and history');
$f47=req30('GET',$r47.'/countries/FR/finance',null,$tb);
ck30($f47['status']===200&&$f47['body']['treasury']['debt']===$row47['debt'],'WP47 finance source reconciliation');
ck30(req30('GET',$p47,null,$ta2)['status']===404&&req30('GET',$r46.'/countries/FR/macro',null,$tb)['status']===404,'WP47 membership and legacy isolation');
// WP48 is the eleventh world for Bob. Use a separate real test account;
// keep production world and request quotas unchanged.
$pdo->exec('DELETE FROM wp_http_limits');
$reg48=req30('POST','/accounts',['username'=>'fiscaltester','password'=>'strong-password-fiscal-48','invite'=>$invite]);
ck30($reg48['status']===201,'WP48 dedicated isolated test account');
$tc48=req30('POST','/sessions',['username'=>'fiscaltester','password'=>'strong-password-fiscal-48'])['body']['access_token'];
$g48=req30('POST','/worlds',['label'=>'France fiscal loop','scenario'=>'fr-fiscal-loop-v1'],$tc48,['Idempotency-Key'=>'fiscal-world-001']);
if($g48['status']!==201)throw new RuntimeException('WP48 world creation HTTP '.$g48['status'].' '.json_encode($g48['body'],JSON_THROW_ON_ERROR));
ck30(true,'WP48 observed baseline world');
$w48=$g48['body']['world_id'];$r48='/worlds/'.$w48;$p48=$r48.'/countries/FR/macro';
$m48=req30('GET',$p48,null,$tc48);
ck30($m48['status']===200&&$m48['body']['feedback_to_domestic_ledger']&&count($m48['body']['baseline']['source_pack']['observations'])===40,'WP48 persisted observed pack');
$q48=$pdo->prepare('SELECT assumptions_json FROM wp_domestic_states WHERE world_id=?');$q48->execute([$w48]);
$cfg48=json_decode($q48->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck30($cfg48['macro_baseline']['source_sha']===$m48['body']['baseline']['source_sha'],'WP48 MariaDB pinned provenance');
$c48=req30('GET',$r48.'/clock',null,$tc48);
foreach(['2026-11-26','2026-11-27','2026-11-28','2026-11-29','2026-11-30','2026-12-01','2026-12-02'] as $n48=>$day48){
    $c48=req30('POST',$r48.'/clock/advances',['target_day'=>$day48],$tc48,['If-Match'=>$c48['etag'],'Idempotency-Key'=>'fiscal-tick-00'.($n48+1)]);
    ck30($c48['status']===200&&$c48['body']['coherent'],'WP48 coherent fiscal day');
}
$m48=req30('GET',$p48,null,$tc48);$d48=req30('GET',$r48.'/domestic',null,$tc48);$f48=req30('GET',$r48.'/countries/FR/finance',null,$tc48);
ck30($m48['status']===200&&$d48['status']===200&&$f48['status']===200,'WP48 all read contracts');
ck30($m48['body']['latest']['cash']===$d48['body']['balance']&&$m48['body']['latest']['debt']===$f48['body']['treasury']['debt'],'WP48 cash debt consistency');
ck30($m48['body']['latest']['tax_feedback']!==0,'WP48 endogenous receipts enter persisted daily ledger');
ck30(req30('GET',$p48,null,$ta2)['status']===404&&!req30('GET',$p47,null,$tb)['body']['feedback_to_domestic_ledger'],'WP48 isolation and WP47 compatibility');
$g49=req30('POST','/worlds',['label'=>'Sovereign debt ledger','scenario'=>'fr-debt-v1'],$tc48,['Idempotency-Key'=>'debt-world-001']);
if($g49['status']!==201)throw new RuntimeException('WP49 creation HTTP '.$g49['status'].' '.json_encode($g49['body'],JSON_THROW_ON_ERROR));
ck30(true,'WP49 world creation');
$w49=$g49['body']['world_id'];$r49='/worlds/'.$w49;$p49=$r49.'/countries/FR/debt';
$d49=req30('GET',$p49,null,$tc48);
ck30($d49['status']===200&&array_sum($d49['body']['creditors'])===$d49['body']['debt']&&count($d49['body']['reference']['observations'])===41,'WP49 debt pack and opening claims');
$q49=$pdo->prepare('SELECT assumptions_json FROM wp_domestic_states WHERE world_id=?');$q49->execute([$w49]);
$cfg49=json_decode($q49->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck30($cfg49['debt_model']['source_sha']===$d49['body']['source_sha'],'WP49 durable debt provenance');
$c49=req30('GET',$r49.'/clock',null,$tc48);
foreach(['2026-11-26','2026-11-27','2026-11-28','2026-11-29','2026-11-30','2026-12-01','2026-12-02'] as $n49=>$day49){
    $c49=req30('POST',$r49.'/clock/advances',['target_day'=>$day49],$tc48,['If-Match'=>$c49['etag'],'Idempotency-Key'=>'debt-tick-00'.($n49+1)]);
    ck30($c49['status']===200&&$c49['body']['coherent'],'WP49 coherent day');
}
$d49=req30('GET',$p49,null,$tc48);$f49=req30('GET',$r49.'/countries/FR/finance',null,$tc48);$m49=req30('GET',$r49.'/countries/FR/macro',null,$tc48);
ck30($d49['status']===200&&count($d49['body']['months'])===2&&$d49['body']['days'][5]['scheduled_principal']>0,'WP49 monthly maturity and aggregation');
ck30($f49['status']===200&&$m49['status']===200&&$d49['body']['debt']===$f49['body']['treasury']['debt']&&$d49['body']['debt']===$m49['body']['latest']['debt'],'WP49 debt and macro consistency');
ck30(req30('GET',$p49,null,$ta2)['status']===404&&req30('GET',$r48.'/countries/FR/debt',null,$tc48)['status']===404,'WP49 isolation and legacy');
$g50=req30('POST','/worlds',['label'=>'Financial crisis pilot','scenario'=>'fr-financial-crisis-v1'],$tc48,['Idempotency-Key'=>'crisis-world-001']);
ck30($g50['status']===201,'WP50 world creation');
$w50=$g50['body']['world_id'];$r50='/worlds/'.$w50;$p50=$r50.'/domestic/crisis';
$d50=req30('GET',$r50.'/domestic',null,$tc48);
$h50=['If-Match'=>$d50['etag'],'Idempotency-Key'=>'crisis-act-0001'];
ck30(req30('POST',$p50,['action'=>'moratorium'],$ta2,$h50)['status']===404,'WP50 cross account denied');
$a50=req30('POST',$p50,['action'=>'moratorium'],$tc48,$h50);
ck30($a50['status']===200,'WP50 owner decision');
ck30(req30('POST',$p50,['action'=>'moratorium'],$tc48,$h50)===$a50,'WP50 receipt replay');
ck30(req30('POST',$p50,['action'=>'resume'],$tc48,['If-Match'=>$d50['etag'],'Idempotency-Key'=>'crisis-act-0002'])['status']===412,'WP50 stale ETag');
$c50=req30('GET',$r50.'/clock',null,$tc48);
$c50=req30('POST',$r50.'/clock/advances',['target_day'=>'2026-11-26'],$tc48,['If-Match'=>$c50['etag'],'Idempotency-Key'=>'crisis-tick-001']);
ck30($c50['status']===200&&$c50['body']['coherent'],'WP50 crisis coherent day');
$t50=req30('GET',$r50.'/countries/FR/debt',null,$tc48);
ck30($t50['status']===200&&$t50['body']['latest']['crisis']['moratorium']&&$t50['body']['latest']['interest_arrears']>0,'WP50 durable moratorium');
$m50=req30('GET',$r50.'/countries/FR/macro',null,$tc48);
ck30($m50['status']===200&&$m50['body']['latest']['debt']===$t50['body']['debt'],'WP50 macro reconciliation');
ck30(req30('POST',$r49.'/domestic/crisis',['action'=>'moratorium'],$tc48,$h50)['status']===404,'WP50 legacy route isolation');
$g51=req30('POST','/worlds',['label'=>'French population pilot','scenario'=>'fr-population-v1'],$tc48,['Idempotency-Key'=>'population-world-001']);
if($g51['status']!==201)throw new RuntimeException('WP51 creation HTTP '.$g51['status'].' '.json_encode($g51['body'],JSON_THROW_ON_ERROR));
ck30(true,'WP51 world creation');
$w51=$g51['body']['world_id'];$r51='/worlds/'.$w51;$p51=$r51.'/countries/FR/population';
$v51=req30('GET',$p51,null,$tc48);
ck30($v51['status']===200&&$v51['body']['indicators']['population']===69082000&&count($v51['body']['cohorts'])===28&&count($v51['body']['reference']['observations'])===51,'WP51 observed anchors and cohort totals');
$q51=$pdo->prepare('SELECT assumptions_json FROM wp_domestic_states WHERE world_id=?');$q51->execute([$w51]);
$cfg51=json_decode($q51->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck30($cfg51['population_model']['source_sha']===$v51['body']['source_sha'],'WP51 durable provenance');
$c51=req30('GET',$r51.'/clock',null,$tc48);
foreach(['2026-11-26','2026-11-27','2026-11-28'] as $n51=>$day51){
    $c51=req30('POST',$r51.'/clock/advances',['target_day'=>$day51],$tc48,['If-Match'=>$c51['etag'],'Idempotency-Key'=>'population-tick-00'.($n51+1)]);
    ck30($c51['status']===200&&$c51['body']['coherent'],'WP51 coherent day');
}
$v51=req30('GET',$p51,null,$tc48);$d51=req30('GET',$r51.'/domestic',null,$tc48);
ck30($v51['status']===200&&count($v51['body']['days'])===3&&$v51['body']['latest']===$d51['body']['days'][2]['population'],'WP51 authoritative population ledger');
ck30(req30('GET',$p51,null,$tc48)===$v51,'WP51 deterministic ETag');
ck30(req30('GET',$p51,null,$ta2)['status']===404&&req30('GET',$r50.'/countries/FR/population',null,$tc48)['status']===404,'WP51 isolation and legacy');
$h51=['If-Match'=>$d51['etag'],'Idempotency-Key'=>'population-crisis-001'];
ck30(req30('POST',$r51.'/domestic/crisis',['action'=>'moratorium'],$tc48,$h51)['status']===200,'WP51 retains crisis actions');
// Fresh isolated test rate window; runtime quotas unchanged.
$pdo->exec('DELETE FROM wp_http_limits');
$g52=req30('POST','/worlds',['label'=>'French services pilot','scenario'=>'fr-services-v1'],$tc48,['Idempotency-Key'=>'services-world-001']);
if($g52['status']!==201)throw new RuntimeException('WP52 creation HTTP '.$g52['status'].' '.json_encode($g52['body'],JSON_THROW_ON_ERROR));
ck30(true,'WP52 world creation');
$w52=$g52['body']['world_id'];$r52='/worlds/'.$w52;$p52=$r52.'/countries/FR/services';
$v52=req30('GET',$p52,null,$tc48);
ck30($v52['status']===200&&$v52['body']['initial_plan']['total_incremental_expense_minor']===0&&count($v52['body']['reference']['observations'])===33,'WP52 initial observed references without double count');
$q52=$pdo->prepare('SELECT assumptions_json FROM wp_domestic_states WHERE world_id=?');$q52->execute([$w52]);
$cfg52=json_decode($q52->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck30($cfg52['services_model']['source_sha']===$v52['body']['source_sha'],'WP52 durable provenance');
$c52=req30('GET',$r52.'/clock',null,$tc48);
foreach(['2026-11-26','2026-11-27','2026-11-28'] as $n52=>$day52){
    $c52=req30('POST',$r52.'/clock/advances',['target_day'=>$day52],$tc48,['If-Match'=>$c52['etag'],'Idempotency-Key'=>'services-tick-00'.($n52+1)]);
    ck30($c52['status']===200&&$c52['body']['coherent'],'WP52 coherent day');
}
$v52=req30('GET',$p52,null,$tc48);$d52=req30('GET',$r52.'/domestic',null,$tc48);
ck30($v52['status']===200&&count($v52['body']['days'])===3&&$v52['body']['latest']===$d52['body']['days'][2]['services'],'WP52 authoritative services ledger');
ck30(req30('GET',$p52,null,$tc48)===$v52,'WP52 deterministic ETag');
ck30(req30('GET',$p52,null,$ta2)['status']===404&&req30('GET',$r51.'/countries/FR/services',null,$tc48)['status']===404,'WP52 isolation and legacy');
ck30(req30('GET',$r52.'/countries/FR/population',null,$tc48)['status']===200&&req30('GET',$r52.'/countries/FR/debt',null,$tc48)['status']===200,'WP52 retains population and debt');
$pdo->exec('DELETE FROM wp_http_limits');
$g53=req30('POST','/worlds',['label'=>'Social partners pilot','scenario'=>'fr-social-partners-v1'],$tc48,['Idempotency-Key'=>'partners-world-001']);
if($g53['status']!==201)throw new RuntimeException('WP53 creation HTTP '.$g53['status'].' '.json_encode($g53['body'],JSON_THROW_ON_ERROR));
$r53='/worlds/'.$g53['body']['world_id'];$p53=$r53.'/countries/FR/social-partners';
$v53=req30('GET',$p53,null,$tc48);
ck30($v53['status']===200&&count($v53['body']['social_partners']['groups'])===5,'WP53 initial representative bodies');
$c53=req30('GET',$r53.'/clock',null,$tc48);
$c53=req30('POST',$r53.'/clock/advances',['target_day'=>'2026-11-26'],$tc48,['If-Match'=>$c53['etag'],'Idempotency-Key'=>'partners-tick-001']);
ck30($c53['status']===200&&$c53['body']['coherent'],'WP53 coherent day');
$v53=req30('GET',$p53,null,$tc48);$o53=req30('GET',$r53.'/countries/FR/opinion',null,$tc48);
ck30($v53['status']===200&&$v53['body']['social_partners']===$o53['body']['history'][0]['result']['social_partners'],'WP53 persisted replay');
ck30(req30('GET',$p53,null,$tc48)===$v53,'WP53 stable ETag');
ck30(req30('GET',$p53,null,$ta2)['status']===404&&req30('GET',$r52.'/countries/FR/social-partners',null,$tc48)['status']===404,'WP53 membership and legacy isolation');
ck30(req30('POST',$p53,['phase'=>'unresolved'],$tc48)['status']===404,'WP53 no direct mutation');
ck30(req30('GET',$r53.'/countries/FR/services',null,$tc48)['status']===200,'WP53 retains services');
$pdo->exec('DELETE FROM wp_http_limits');
$gc53=req30('POST','/worlds',['label'=>'Corporations pilot','scenario'=>'fr-corporations-v1'],$tc48,['Idempotency-Key'=>'corporation-world-001']);
if($gc53['status']!==201)throw new RuntimeException('WP53b creation HTTP '.$gc53['status'].' '.json_encode($gc53['body'],JSON_THROW_ON_ERROR));
$rc53='/worlds/'.$gc53['body']['world_id'];$pc53=$rc53.'/countries/FR/corporations';
$vc53=req30('GET',$pc53,null,$tc48);
ck30($vc53['status']===200&&$vc53['body']['state']['mode']==='pluralist','WP53b optional initial system');
$ac53=req30('POST',$rc53.'/domestic/corporation',['action'=>'establish-quarterly'],$tc48,['If-Match'=>$vc53['etag'],'Idempotency-Key'=>'corporation-act-001']);
ck30($ac53['status']===200,'WP53b owner decision');
$cc53=req30('GET',$rc53.'/clock',null,$tc48);
$cc53=req30('POST',$rc53.'/clock/advances',['target_day'=>'2026-11-26'],$tc48,['If-Match'=>$cc53['etag'],'Idempotency-Key'=>'corporation-tick-001']);
ck30($cc53['status']===200&&$cc53['body']['coherent'],'WP53b coherent clock');
$vc53=req30('GET',$pc53,null,$tc48);
ck30($vc53['status']===200&&$vc53['body']['state']['mode']==='parallel','WP53b durable corporate decision');
ck30(req30('GET',$pc53,null,$ta2)['status']===404&&req30('GET',$r53.'/countries/FR/corporations',null,$tc48)['status']===404,'WP53b isolation');
$pdo->prepare('UPDATE wp_sessions SET expires_at=0 WHERE token_sha=?')->execute([hash('sha256',$ta2)]);
ck30(req30('GET','/worlds',null,$ta2)['status']===401,'expired token denied');
echo "WP30 HTTP private alpha acceptance passed.\n";
