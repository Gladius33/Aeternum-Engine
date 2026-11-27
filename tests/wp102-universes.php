<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/AlphaGateway.php';
use Aeternum\WorldPolitics\{AlphaGateway,AlphaAccounts,UniverseLobby};
if(getenv('WP_TEST_ISOLATED')!=='YES'||!str_contains(getenv('WP_DB_DSN')?:'','dbname=wp_test_multinode;'))exit(2);
function ck102(bool $v,string $msg):void{if(!$v)throw new RuntimeException('WP102 '.$msg);echo "PASS WP102: $msg\n";}
$connect=fn()=>new PDO(getenv('WP_DB_DSN'),getenv('WP_DB_USER'),getenv('WP_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db=$connect();$accounts=new AlphaAccounts($db,getenv('WP_ALPHA_INVITE'));$gw=new AlphaGateway($connect,getenv('WP_ALPHA_INVITE'));
$tokens=[];foreach(range(1,count(UniverseLobby::DIPLOMATIC_COUNTRIES)+1) as $n){$user='universe-'.$n;$password='universe-test-password-'.$n;
    $accounts->register(['username'=>$user,'password'=>$password,'invite'=>getenv('WP_ALPHA_INVITE')]);$tokens[$n]=$accounts->login(['username'=>$user,'password'=>$password])['access_token'];}
function call102(string $method,string $path,?array $body,int $n,array $headers=[]):array{
    global $gw,$tokens;
    return $gw->handle($method,'/api/v1'.$path,$body===null?'':json_encode($body,JSON_THROW_ON_ERROR),['content-type'=>'application/json','authorization'=>'Bearer '.$tokens[$n],...$headers],'wp102-test-peer');
}
function join102(int $n,string $country,?int $no=null,string $key=''):array{
    return call102('POST','/universes/join',['country'=>$country,...($no===null?[]:['universe_no'=>$no])],$n,['idempotency-key'=>$key?:'wp102-join-test-'.$n]);
}
$first=join102(1,'FR');ck102($first['status']===201,'first player can join alone');
$no=$first['body']['universe_no'];$world=$first['body']['world_id'];$root='/worlds/'.$world;
ck102(join102(1,'FR')===$first,'join receipt replay');
ck102(join102(2,'FR',$no)['status']===409,'country exclusive');
ck102(join102(1,'DE',$no,'wp102-double-claim')['status']===409,'one slot per account and universe');
$directory=call102('GET','/universes',null,1)['body']['universes'];
ck102(count($directory)===1&&$directory[0]['vacant']===42,'42 automatic slots');
$before=call102('GET',$root.'/countries/FR/institutions',null,1);
ck102($before['status']===200&&call102('GET',$root.'/clock',null,2)['status']===404,'read membership isolation');
ck102(call102('POST',$root.'/clock/advances',['target_day'=>'2027-05-15'],1)['status']===403,'no human world clock grant');
foreach(['DE','ES','GB','IT','JP','US'] as $j=>$country){$last=join102($j+2,$country,$no,'wp102-fill-country-'.$country);ck102($last['status']===201,'claim '.$country);}
$extra=array_values(array_diff(UniverseLobby::DIPLOMATIC_COUNTRIES,UniverseLobby::COUNTRIES));
foreach($extra as $j=>$country){$last=join102($j+9,$country,$no,'wp111-fill-country-'.$country);ck102($last['status']===201,'expanded claim '.$country);}
ck102(join102(8,'IL',$no,'wp111-restricted-il')['status']===422,'restricted state not claimable');
ck102($last['body']['new_universe']!==null&&$last['body']['new_universe']['world_id']!==$world,'last slot creates fresh universe immediately');
$directory=call102('GET','/universes',null,1)['body']['universes'];
ck102(count($directory)===2&&$directory[0]['vacant']===0&&$directory[1]['vacant']===43,'fresh spare contains only automatic slots');
ck102(call102('POST',$root.'/domestic/proposals',['policy_id'=>'education-reform','effective_day'=>'2027-05-20'],2)['status']===403,'foreign player cannot command France');
$extraPlayer=array_search('CH',$extra,true)+9;
$dipExtra=call102('GET',$root.'/diplomacy',null,$extraPlayer);
ck102(call102('POST',$root.'/diplomacy/proposals',['from'=>'state_ch','to'=>'state_at','action'=>'dialogue'],$extraPlayer,['if-match'=>$dipExtra['headers']['ETag'],'idempotency-key'=>'wp111-swiss-dialogue'])['status']===202,'expanded Swiss slot commands own diplomacy');
$dip=call102('GET',$root.'/diplomacy',null,2);
$payload=['from'=>'state_de','to'=>'state_fr','action'=>'dialogue'];
$headers=['if-match'=>$dip['headers']['ETag'],'idempotency-key'=>'wp102-diplomacy-de'];
ck102(call102('POST',$root.'/diplomacy/proposals',$payload,2,$headers)['status']===202,'foreign player commands own diplomacy');
$dip=call102('GET',$root.'/diplomacy',null,2);
$payload['from']='state_fr';$payload['to']='state_de';
ck102(call102('POST',$root.'/diplomacy/proposals',$payload,2,['if-match'=>$dip['headers']['ETag'],'idempotency-key'=>'wp102-spoof-fr'])['status']===403,'foreign player cannot spoof another state');
ck102(call102('DELETE','/universes/'.$no.'/membership',null,1)['status']===200,'release France to AI');
ck102(call102('GET',$root.'/clock',null,1)['status']===404,'former member access revoked');
ck102(join102(1,'FR')['status']===409,'WP109 stale join receipt cannot resurrect released control');
$rejoin=join102(8,'FR',$no);
ck102($rejoin['status']===201&&$rejoin['body']['world_id']===$world&&$rejoin['body']['generation']===2,'late claimant inherits same world');
ck102(call102('GET',$root.'/countries/FR/institutions',null,8)===$before,'institutional state unchanged at handover');
ck102(count(call102('GET','/universes/'.$no.'/rankings',null,8)['body']['rows'])===44,'universe standings preserve past player');
ck102(join102(8,'FR',$directory[1]['universe_no'],'wp102-other-universe')['status']===201,'account may join separate universe');
$global=call102('GET','/universe-rankings/global',null,8)['body'];
ck102(count($global['rows'])===44&&!$global['composite_score_available']&&$global['rows'][0]['score']===null,'global standings one row per player; no fabricated scores');

require_once __DIR__.'/../games/world-politics/src/UniverseScheduler.php';
$scheduler=new \Aeternum\WorldPolitics\UniverseScheduler($connect);
$spare=$directory[1]['world_id'];$spareNo=$directory[1]['universe_no'];
ck102(call102('POST','/universes/'.$no.'/programme',['diplomacy'=>'cooperative','domestic'=>'manual'],8)['status']===200,'WP103 inherited diplomatic programme');
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','domestic'=>'education'],8)['status']===200,'WP107 opt-in domestic programme');
ck102(call102('POST','/universes/'.$no.'/programme',['diplomacy'=>'balanced','domestic'=>'education'],2)['status']===403,'WP108 foreign pilot cannot set French domestic programme');
ck102(call102('DELETE','/universes/'.$spareNo.'/membership',null,8)['status']===200,'WP103 activated second world empty');
$due=$db->prepare('UPDATE wp_universes SET next_tick_epoch=UNIX_TIMESTAMP()-1 WHERE world_id=?');
$due->execute([$spare]);
ck102($scheduler->tick($spare)['day']==='2027-05-15','WP103 server advances world with no humans');
ck102($scheduler->tick($spare)['status']==='not_due','WP103 no extra tick before deadline');
$q107=$db->prepare('SELECT policy_id,decision_day FROM wp_universe_programme_actions WHERE world_id=?');
$q107->execute([$spare]);$p107=$q107->fetchAll(PDO::FETCH_ASSOC);
ck102(count($p107)===1&&$p107[0]['policy_id']==='education-reform'&&$p107[0]['decision_day']==='2027-05-14','WP107 inherited programme proposes once with no human');
$q107=$db->prepare('SELECT state_json FROM wp_domestic_states WHERE world_id=?');$q107->execute([$spare]);
$p107=json_decode($q107->fetchColumn(),true,64,JSON_THROW_ON_ERROR)['policies']['education-reform'];
ck102($p107['authorized_on']===null&&$p107['instrument_sha']===null,'WP107 programme cannot bypass enactment');
$due->execute([$world]);
ck102($scheduler->tick($world)['day']==='2027-05-15','WP103 server advances shared clock');
$dip103=call102('GET',$root.'/diplomacy',null,8);
ck102($dip103['status']===200,'WP103 shared diplomacy after tick');
$check=$db->prepare('SELECT state_json FROM wp_diplomatic_states WHERE world_id=?');$check->execute([$world]);
$offer=array_values(array_filter(json_decode($check->fetchColumn(),true,64,JSON_THROW_ON_ERROR)['proposals'],fn($x)=>$x['from']==='state_de'))[0];
for($n=0;$n<5&&$offer['status']==='pending_response';$n++){
    $due->execute([$world]);ck102($scheduler->tick($world)['status']==='committed','WP103 automatic tick');
    $check->execute([$world]);$offer=array_values(array_filter(json_decode($check->fetchColumn(),true,64,JSON_THROW_ON_ERROR)['proposals'],fn($x)=>$x['from']==='state_de'))[0];
}
ck102($offer['status']!=='pending_response'&&$offer['responded_on']!==null,'WP103 idle French player gets automatic diplomatic response');
$global=call102('GET','/universe-rankings/global',null,8)['body'];
ck102($global['rows'][0]['control_days']>0&&count($global['rows'])===44,'WP103 standings reflect committed control time');

$cohorts=$global['pilot_score_cohorts'];ck102(count($cohorts)===1&&$cohorts[0]['country']==='FR','WP104 comparable France cohort');
$sha104=$cohorts[0]['cohort_sha'];
$rank104=call102('GET','/universe-rankings/global/score/'.$sha104,null,8);
ck102($rank104['status']===200&&count($rank104['body']['rows'])===2,'WP104 global composite deduplicates same player across worlds');
$rank104=call102('GET','/universes/'.$no.'/rankings/score/'.$sha104,null,8);
ck102($rank104['status']===200&&count($rank104['body']['rows'])===2,'WP104 universe composite includes former player');
foreach($rank104['body']['rows'] as $row104){
    if($row104['is_you'])ck102($row104['control_days']>0&&$row104['opening_income_index_bps']===10000,'WP104 inherited control period evolves');
    else ck102($row104['control_days']===0&&$row104['score']===0,'WP104 former player score frozen');
}
ck102(call102('DELETE','/universes/'.$no.'/membership',null,8)['status']===200,'WP104 leave freezes current score');
ck102(join102(1,'FR',$no,'wp104-late-claim')['status']===201,'WP104 late claim');
$q104=$db->prepare("SELECT s.opening_index_bps,s.latest_index_bps,s.score,s.elapsed_control_days,c.index_bps FROM wp_universe_period_scores s JOIN wp_universe_income_checkpoints c USING(world_id,country_code) WHERE s.world_id=? AND s.country_code='FR' AND s.generation=3");
$q104->execute([$world]);$r104=$q104->fetch(PDO::FETCH_ASSOC);
ck102($r104!==false&&(int)$r104['opening_index_bps']===(int)$r104['index_bps']&&(int)$r104['latest_index_bps']===(int)$r104['index_bps']&&(int)$r104['score']===0&&(int)$r104['elapsed_control_days']===0,'WP104 late entrant starts at current income and zero score');

/* WP105: a certified synthetic resignation freezes France only. */
$clock105=call102('GET',$root.'/clock',null,1)['body']['committed_day'];
$end105=(new DateTimeImmutable($clock105))->modify('+1 day')->format('Y-m-d');
(new \Aeternum\WorldPolitics\CampaignEventQueue($db))->publish(\Aeternum\WorldPolitics\CampaignLifecycle::event($world,$end105,'wp105-synthetic-resignation','presidential_resignation'));
$due->execute([$world]);ck102($scheduler->tick($world)['day']===$end105,'WP105 terminal national tick commits');
ck102(call102('GET',$root.'/clock',null,1)['status']===404,'WP105 former officeholder loses command access');
ck102(join102(1,'FR',$no,'wp105-closed-slot')['status']===409,'WP105 unresolved successor cannot be impersonated');
$next105=(new DateTimeImmutable($end105))->modify('+1 day')->format('Y-m-d');
$due->execute([$world]);ck102($scheduler->tick($world)['day']===$next105,'WP105 universe continues after France loses office');
$c105=call102('GET',$root.'/clock',null,2);
ck102($c105['status']===200&&$c105['body']['committed_day']===$next105&&!$c105['body']['game_over'],'WP105 other country continues');
$q105=$db->prepare("SELECT state_json,state_sha FROM wp_opinion_states WHERE world_id=?");$q105->execute([$world]);$row105=$q105->fetch(PDO::FETCH_ASSOC);
$o105=\Aeternum\WorldPolitics\PublicOpinion::restore(json_decode($row105['state_json'],true,64,JSON_THROW_ON_ERROR))->snapshot();
ck102($o105['day']===$next105&&$o105['campaign']['ended_on']===$end105&&$o105['campaign']['day']===$end105,'WP105 ended mandate frozen, opinion continues and replays');
$q104->execute([$world]);$r105=$q104->fetch(PDO::FETCH_ASSOC);
ck102((int)$r105['elapsed_control_days']===1,'WP105 former player score remains frozen');


require_once __DIR__.'/../games/world-politics/src/UniverseAudit.php';
$aud=\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$no);
ck102($aud['integrity']==='passed'&&$aud['human_slots']===42&&$aud['recomputed_scores']===3,'WP110 independent committed universe audit');
$aud=\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$spareNo);
ck102($aud['integrity']==='passed'&&$aud['human_slots']===0,'WP110 empty active universe audit');


// WP112: real coordinated vacancy, interim, election and re-opening.
$reopen112=(new DateTimeImmutable($end105))->modify('+37 days')->format('Y-m-d');
$day112=$next105;
while($day112<$reopen112){
    $due->execute([$world]);$tick112=$scheduler->tick($world);
    ck102($tick112['status']==='committed','WP112 daily vacancy tick');
    $day112=$tick112['day'];
}
$q105->execute([$world]);$row112=$q105->fetch(PDO::FETCH_ASSOC);
$o112=\Aeternum\WorldPolitics\PublicOpinion::restore(json_decode($row112['state_json'],true,64,JSON_THROW_ON_ERROR))->snapshot();
ck102($o112['campaign']['status']==='active'&&$o112['managed_vacancy']===null&&count($o112['managed_mandates'])===1,'WP112 successor published and former mandate archived');
ck102(join102(1,'FR',$no,'wp112-new-mandate-claim')['status']===201,'WP112 successor claim after interim');
$aud=\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$no);
ck102($aud['integrity']==='passed'&&$aud['human_slots']===43&&$aud['recomputed_scores']===4,'WP112 audit of reopened country');
$check->execute([$world]);$p113=json_decode($check->fetchColumn(),true,64,JSON_THROW_ON_ERROR)['proposals'];
ck102(count(array_filter($p113,fn($x)=>str_starts_with($x['id'],'daily-initiative-')))>0,'WP113 idle governments initiate diplomacy');


// WP114: human precedence, idle programme, lawful publication and application.
ck102(join102(8,'FR',$spareNo,'wp114-rejoin-spare')['status']===201,'WP114 human resumes earlier AI state');
$root114='/worlds/'.$spare;
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','domestic'=>'administration-law'],8)['status']===200,'WP114 executable profile selected');
$dr114=call102('GET',$root114.'/domestic',null,8);
ck102($dr114['status']===200,'WP114 domestic read');
ck102(call102('POST',$root114.'/domestic/proposals',['policy_id'=>'budget-2029-defense-maintain','effective_day'=>'2029-01-01'],8,['if-match'=>$dr114['headers']['ETag'],'idempotency-key'=>'wp114-human-proposal'])['status']===202,'WP114 human command');
$report114=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($report114['human_action_this_day']===true,'WP114 human priority visible');
$due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114 priority tick');
$report114=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102(count($report114['actions'])===1,'WP114 human command suppresses new fallback proposal');
$due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114 idle tick');
$report114=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102(count($report114['actions'])===2&&$report114['actions'][1]['policy_id']==='administration-report'&&$report114['actions'][1]['state']==='proposed','WP114 idle programme proposes independently');
$effective114=$report114['actions'][1]['effective_day'];
for($n114=0;$n114<20&&$report114['day']<$effective114;$n114++){
    $due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114 lawful progression tick');
    $report114=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
}
ck102($report114['day']===$effective114&&$report114['actions'][1]['state']==='active','WP114 administrative policy takes effect after verified publication');
ck102($report114['actions'][1]['authorized_on']!==null,'WP114 no effect without legal authorization');
$aud=\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$spareNo);
ck102($aud['integrity']==='passed','WP114 executable programme passes independent audit');


// WP114b: custom agenda survives departure, then an explicit human override
// is not undone by a once-completed automatic aid objective.
$ag114=['education'=>false,'administration'=>false,'defense'=>'none','health'=>'none','ukraine_aid'=>'stop'];
$bad114=$ag114;$bad114['extra']=true;
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','agenda'=>$bad114],8)['status']===422,'WP114b unknown objective rejected');
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','agenda'=>$ag114,'domestic'=>'manual'],8)['status']===422,'WP114b conflicting profiles rejected');
ck102(call102('POST','/universes/'.$no.'/programme',['diplomacy'=>'balanced','agenda'=>$ag114],2)['status']===403,'WP114b foreign controller cannot set French agenda');
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','agenda'=>$ag114],8)['status']===200,'WP114b custom programme accepted');
$re114=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($re114['agenda']===$ag114&&$re114['direct_actions']===[],'WP114b custom agenda committed');
ck102(call102('DELETE','/universes/'.$spareNo.'/membership',null,8)['status']===200,'WP114b handover to AI');
$due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114b unoccupied world executes direct objective');
ck102(join102(8,'FR',$spareNo,'wp114b-rejoin')['status']===201,'WP114b human takes over same agenda');
$re114=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($re114['agenda']===$ag114&&count($re114['direct_actions'])===1&&$re114['direct_actions'][0]['outcome']==='applied','WP114b durable direct receipt');
$dr114=call102('GET',$root114.'/domestic',null,8);
ck102(call102('POST',$root114.'/domestic/ukraine-aid',['action'=>'ukraine-aid-resume'],8,['if-match'=>$dr114['headers']['ETag'],'idempotency-key'=>'wp114b-human-override'])['status']===200,'WP114b human changes aid decision');
for($n114=0;$n114<2;$n114++){$due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114b override survives idle days');}
$q114=$db->prepare('SELECT state_json FROM wp_domestic_states WHERE world_id=?');$q114->execute([$spare]);
$d114=json_decode($q114->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck102(count($d114['ukraine_aid_decisions'])===2&&$d114['ukraine_aid_decisions'][1]['action']==='ukraine-aid-resume','WP114b no automatic reversal of human choice');
$aud=\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$spareNo);
ck102($aud['integrity']==='passed','WP114b audited custom direct programme');


// WP114c: live extended agenda through real coordinated domain clocks.
$ext114=['education'=>false,'administration'=>false,'defense'=>'none','health'=>'none','ukraine_aid'=>'none',
    'cabinet'=>'civic','revision'=>'environment-report','ratification'=>'congress','industry'=>'active','commitments'=>'request_deferral'];
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','agenda'=>$ext114],8)['status']===200,'WP114c extended agenda accepted');
$due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114c coordinated extended objectives');
$e114=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($e114['agenda']===$ext114&&count(array_filter($e114['direct_actions'],fn($a)=>$a['action_id']==='start-industrial-pilot'&&$a['outcome']==='applied'))===1,'WP114c industrial choice applied with durable receipt');
$families114=array_column($e114['institutional_and_commitment_objectives'],'family');
ck102(in_array('revision_requests',$families114,true)&&in_array('commitment',$families114,true)&&!in_array('cabinet_requests',$families114,true),'WP114c actual requests, no forced cabinet vacancy');
$q114->execute([$spare]);$d114=json_decode($q114->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck102($d114['days'][array_key_last($d114['days'])]['industrial']['mode']==='active','WP114c industrial ledger reflects programme');
$aud=\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$spareNo);
ck102($aud['integrity']==='passed','WP114c extended programme audited');
