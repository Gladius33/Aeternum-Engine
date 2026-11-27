<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/AlphaGateway.php';
use Aeternum\WorldPolitics\{AlphaGateway,AlphaAccounts,UniverseLobby};
if(getenv('WP_TEST_ISOLATED')!=='YES'||!str_contains(getenv('WP_DB_DSN')?:'','dbname=wp_test_multinode;'))exit(2);
function ck102(bool $v,string $msg):void{if(!$v)throw new RuntimeException('WP102 '.$msg);echo "PASS WP102: $msg\n";}
$connect=fn()=>new PDO(getenv('WP_DB_DSN'),getenv('WP_DB_USER'),getenv('WP_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db=$connect();$accounts=new AlphaAccounts($db,getenv('WP_ALPHA_INVITE'));$gw=new AlphaGateway($connect,getenv('WP_ALPHA_INVITE'));
$tokens=[];foreach(range(1,8) as $n){$user='universe-'.$n;$password='universe-test-password-'.$n;
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
ck102(count($directory)===1&&$directory[0]['vacant']===6,'six automatic slots');
$before=call102('GET',$root.'/countries/FR/institutions',null,1);
ck102($before['status']===200&&call102('GET',$root.'/clock',null,2)['status']===404,'read membership isolation');
ck102(call102('POST',$root.'/clock/advances',['target_day'=>'2027-05-15'],1)['status']===403,'no human world clock grant');
foreach(['DE','ES','GB','IT','JP','US'] as $j=>$country){$last=join102($j+2,$country,$no,'wp102-fill-country-'.$country);ck102($last['status']===201,'claim '.$country);}
ck102($last['body']['new_universe']!==null&&$last['body']['new_universe']['world_id']!==$world,'last slot creates fresh universe immediately');
$directory=call102('GET','/universes',null,1)['body']['universes'];
ck102(count($directory)===2&&$directory[0]['vacant']===0&&$directory[1]['vacant']===7,'fresh spare contains only automatic slots');
ck102(call102('POST',$root.'/domestic/proposals',['policy_id'=>'education-reform','effective_day'=>'2027-05-20'],2)['status']===403,'foreign player cannot command France');
$dip=call102('GET',$root.'/diplomacy',null,2);
$payload=['from'=>'state_de','to'=>'state_fr','action'=>'dialogue'];
$headers=['if-match'=>$dip['headers']['ETag'],'idempotency-key'=>'wp102-diplomacy-de'];
ck102(call102('POST',$root.'/diplomacy/proposals',$payload,2,$headers)['status']===202,'foreign player commands own diplomacy');
$dip=call102('GET',$root.'/diplomacy',null,2);
$payload['from']='state_fr';$payload['to']='state_de';
ck102(call102('POST',$root.'/diplomacy/proposals',$payload,2,['if-match'=>$dip['headers']['ETag'],'idempotency-key'=>'wp102-spoof-fr'])['status']===403,'foreign player cannot spoof another state');
ck102(call102('DELETE','/universes/'.$no.'/membership',null,1)['status']===200,'release France to AI');
ck102(call102('GET',$root.'/clock',null,1)['status']===404,'former member access revoked');
$rejoin=join102(8,'FR',$no);
ck102($rejoin['status']===201&&$rejoin['body']['world_id']===$world&&$rejoin['body']['generation']===2,'late claimant inherits same world');
ck102(call102('GET',$root.'/countries/FR/institutions',null,8)===$before,'institutional state unchanged at handover');
ck102(count(call102('GET','/universes/'.$no.'/rankings',null,8)['body']['rows'])===8,'universe standings preserve past player');
ck102(join102(8,'FR',$directory[1]['universe_no'],'wp102-other-universe')['status']===201,'account may join separate universe');
$global=call102('GET','/universe-rankings/global',null,8)['body'];
ck102(count($global['rows'])===8&&!$global['composite_score_available']&&$global['rows'][0]['score']===null,'global standings one row per player; no fabricated scores');

require_once __DIR__.'/../games/world-politics/src/UniverseScheduler.php';
$scheduler=new \Aeternum\WorldPolitics\UniverseScheduler($connect);
$spare=$directory[1]['world_id'];$spareNo=$directory[1]['universe_no'];
ck102(call102('POST','/universes/'.$no.'/programme',['diplomacy'=>'cooperative'],8)['status']===200,'WP103 inherited diplomatic programme');
ck102(call102('DELETE','/universes/'.$spareNo.'/membership',null,8)['status']===200,'WP103 activated second world empty');
$due=$db->prepare('UPDATE wp_universes SET next_tick_epoch=UNIX_TIMESTAMP()-1 WHERE world_id=?');
$due->execute([$spare]);
ck102($scheduler->tick($spare)['day']==='2027-05-15','WP103 server advances world with no humans');
ck102($scheduler->tick($spare)['status']==='not_due','WP103 no extra tick before deadline');
$due->execute([$world]);
ck102($scheduler->tick($world)['day']==='2027-05-15','WP103 server advances shared clock');
$dip103=call102('GET',$root.'/diplomacy',null,8);
ck102($dip103['status']===200,'WP103 shared diplomacy after tick');
$check=$db->prepare('SELECT state_json FROM wp_diplomatic_states WHERE world_id=?');$check->execute([$world]);
$offer=array_values(json_decode($check->fetchColumn(),true,64,JSON_THROW_ON_ERROR)['proposals'])[0];
for($n=0;$n<5&&$offer['status']==='pending_response';$n++){
    $due->execute([$world]);ck102($scheduler->tick($world)['status']==='committed','WP103 automatic tick');
    $check->execute([$world]);$offer=array_values(json_decode($check->fetchColumn(),true,64,JSON_THROW_ON_ERROR)['proposals'])[0];
}
ck102($offer['status']!=='pending_response'&&$offer['responded_on']!==null,'WP103 idle French player gets automatic diplomatic response');
$global=call102('GET','/universe-rankings/global',null,8)['body'];
ck102($global['rows'][0]['control_days']>0&&count($global['rows'])===8,'WP103 standings reflect committed control time');

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
