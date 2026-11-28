<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/AlphaGateway.php';
use Aeternum\WorldPolitics\{AlphaGateway,AlphaAccounts,UniverseLobby};
if(getenv('WP_TEST_ISOLATED')!=='YES'||!str_contains(getenv('WP_DB_DSN')?:'','dbname=wp_test_multinode;'))exit(2);
function ck102(bool $v,string $msg):void{if(!$v)throw new RuntimeException('WP102 '.$msg);echo "PASS WP102: $msg\n";}
// Pure fiscal subledger: explicit executed flows, no hidden financing or calibration.
require_once __DIR__.'/../games/world-politics/src/NationalFiscalLedger.php';
$ledger=\Aeternum\WorldPolitics\NationalFiscalLedger::class;
$opening=['cash'=>1000,'gross_debt'=>20000];
$flows=['tax_received'=>900,'other_received'=>100,'primary_paid'=>1100,'interest_paid'=>100,'principal_repaid'=>500,'borrowing_received'=>700];
$closed=$ledger::close($opening,$flows);
ck102($closed['primary_cash_balance']===-100&&$closed['overall_cash_balance']===-200&&$closed['net_borrowing']===200,'national fiscal cash and financing identities');
ck102($closed['closing']===['cash'=>1000,'gross_debt'=>20200]&&!$closed['rankable'],'national fiscal stocks reconcile');
ck102($ledger::close($closed['closing'],$flows)['closing']===['cash'=>1000,'gross_debt'=>20400],'national fiscal repeated period rolls stocks forward');
$badCases=[
    [$opening,array_replace($flows,['primary_paid'=>5000])],
    [$opening,array_replace($flows,['principal_repaid'=>20001])],
    [$opening,array_replace($flows,['tax_received'=>null])],
    [$opening,array_replace($flows,['tax_received'=>-1])],
    [$opening,array_diff_key($flows,['interest_paid'=>true])],
    [$opening,array_replace($flows,['unexpected'=>1])],
    [['cash'=>100000000000000000,'gross_debt'=>20000],array_replace($flows,['tax_received'=>1000])]
];
foreach($badCases as [$badOpening,$badFlows]){
    $rejected=false;try{$ledger::close($badOpening,$badFlows);}catch(\DomainException $e){$rejected=true;}
    ck102($rejected,'national fiscal incomplete, invalid or unfunded flow rejected');
}

$due=['tax_received'=>900,'other_received'=>100,'primary_due'=>1100,'interest_due'=>100,'principal_due'=>500];
$plan=$ledger::plan($opening,$due,['committed_credit_available'=>700,'gross_debt_ceiling'=>21000]);
ck102($plan['funding_need_before_credit']===0&&$plan['credit_draw_scenario']===0&&$plan['cash_subledger']['closing']===['cash'=>300,'gross_debt'=>19500],'settlement does not draw unnecessary credit');
$poor=['cash'=>0,'gross_debt'=>20000];
$plan=$ledger::plan($poor,$due,['committed_credit_available'=>200,'gross_debt_ceiling'=>20100]);
ck102($plan['funding_need_before_credit']===700&&$plan['credit_draw_scenario']===100&&$plan['unpaid']===['interest'=>0,'principal'=>0,'primary'=>600],'credit cap and unpaid obligations visible');
ck102($plan['cash_subledger']['closing']===['cash'=>0,'gross_debt'=>19600]&&!$plan['persisted'],'no unpaid amount booked as paid');
$plan=$ledger::plan($poor,array_replace($due,['tax_received'=>0,'other_received'=>0]),['committed_credit_available'=>0,'gross_debt_ceiling'=>20000]);
ck102($plan['unpaid']===['interest'=>100,'principal'=>500,'primary'=>1100]&&$plan['cash_subledger']['closing']===$poor,'no funding means no extinguished debt or capitalized interest');
$rejected=false;try{$ledger::plan($opening,$due,['committed_credit_available'=>null,'gross_debt_ceiling'=>21000]);}catch(\DomainException $e){$rejected=true;}
ck102($rejected,'unknown credit availability rejected');


require_once __DIR__.'/../games/world-politics/src/NationalFiscalCalendar.php';
$calendar=\Aeternum\WorldPolitics\NationalFiscalCalendar::class;
foreach([2027=>365,2028=>366] as $yr=>$len){
    $dt=new DateTimeImmutable($yr.'-01-01');$sum=0;
    for($i=0;$i<$len;$i++)$sum+=$calendar::daily(100000000000000000,$dt->modify('+'.$i.' day')->format('Y-m-d'));
    ck102($sum===100000000000000000,'daily annual allocation exact including leap year');
}
$annual=['tax_received'=>0,'other_received'=>0,'primary_due'=>36500,'interest_due'=>3650];
$state=['day'=>'2027-05-13','stocks'=>['cash'=>0,'gross_debt'=>1000],'arrears'=>['interest'=>0,'principal'=>0,'primary'=>0],'credit_remaining'=>0];
$t1=$calendar::tick($state,'2027-05-14',$annual,['2027-05-14'=>200],1000);
ck102($t1['next']['arrears']===['interest'=>10,'principal'=>200,'primary'=>100]&&$t1['next']['stocks']['gross_debt']===1000,'calendar retains unpaid principal inside debt');
$t2=$calendar::tick($t1['next'],'2027-05-15',array_replace($annual,['tax_received'=>146000]),['2027-05-14'=>200],1000);
ck102($t2['next']['arrears']===['interest'=>0,'principal'=>0,'primary'=>20]&&$t2['next']['stocks']['gross_debt']===800,'arrears paid exactly once from next receipts');
ck102($t2===$calendar::tick($t1['next'],'2027-05-15',array_replace($annual,['tax_received'=>146000]),['2027-05-14'=>200],1000),'calendar deterministic replay');
$t3=$calendar::tick(array_replace($state,['credit_remaining'=>150]),'2027-05-14',$annual,[],2000);
ck102($t3['next']['credit_remaining']===40&&$t3['next']['stocks']['gross_debt']===1110,'facility consumed only by actual draw');
$t4=$calendar::tick($t3['next'],'2027-05-15',$annual,[],2000);
ck102($t4['next']['credit_remaining']===0&&$t4['next']['arrears']['primary']===70,'credit not recycled across ticks');
$reject=false;try{$calendar::tick($state,'2027-05-15',$annual,[],1000);}catch(\DomainException $e){$reject=true;}
ck102($reject,'calendar rejects skipped day');

$rent=json_decode(file_get_contents(__DIR__.'/../games/world-politics/data/wp130-historical-resource-rents-staging-v1.json'),true,32,JSON_THROW_ON_ERROR);
$rentCodes=array_keys($rent['countries']);sort($rentCodes);$expectedRent=UniverseLobby::DIPLOMATIC_COUNTRIES;sort($expectedRent);
ck102($rentCodes===$expectedRent&&$rent['year']===2021&&!$rent['runtime_admission'],'resource rent staging exact roster and historical classification');
ck102($rent['countries']['MT']===0&&$rent['countries']['SA']>25,'published zero distinguished from absent resource rent');
foreach($rent['countries'] as $value)ck102(is_numeric($value)&&$value>=0&&$value<=100,'resource rent plausible percentage');

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
$iv=call102('GET','/universes/'.$no.'/international',null,1);
ck102($iv['status']===200&&$iv['body']['world_id']===$world&&$iv['body']['country']==='FR'&&count($iv['body']['countries'])===43,'international view shares committed universe and 43 slots');
ck102(count(array_filter($iv['body']['countries'],fn($r)=>$r['national_institution_clock']))===7,'seven synchronized national clocks distinguished');
ck102(count(array_filter($iv['body']['countries'],fn($r)=>$r['domestic_policy']))===1,'foreign domestic model not misrepresented');
$ns=\Aeternum\WorldPolitics\NationalEconomicScenario::class;
ck102(count($ns::pack()['countries'])===42&&count($ns::pack()['series'])===52,'WP115 foreign source pack loaded');
$np=$ns::profile('DE','2027-05-14');
ck102($np['observations']['total_natural_resource_rents']['data_year']===2021&&$np['observations']['government_education_expenditure_share']['data_year']===2022,'game uses latest available historical gap references with dates');
ck102($np['observations']['household_npish_consumption_per_capita_real']['value']>0&&$np['next_ordinary_chamber_renewal_month']==='2029-02','German consumption and calendar reference');
$baseE=['day'=>'2027-05-14','projections'=>[],'policies'=>[]];
$gdp=['kind'=>'observed','value'=>1000000000];
$baseN=$ns::project($np,'state_de','2027-05-14',$gdp,$baseE);
ck102($baseN['scenario_index_bps']===10000&&!$baseN['rankable'],'opening base and score isolation');
$baseE['day']='2028-05-14';
$baseN=$ns::project($np,'state_de','2028-05-14',$gdp,$baseE);
$baseE['policies']=['a'=>['from'=>'state_fr','to'=>'state_de','status'=>'effective'],'b'=>['from'=>'state_de','to'=>'state_fr','status'=>'effective']];
$baseE['projections']=['a'=>['central_usd'=>100000000,'effective_on'=>'2027-05-14'],'b'=>['central_usd'=>50000000,'effective_on'=>'2027-05-14']];
$shockN=$ns::project($np,'state_de','2028-05-14',$gdp,$baseE);
ck102($shockN['trade_stress_bps']===250&&$shockN['scenario_index_bps']===$baseN['scenario_index_bps']-250,'trade projection affects index without double counting pair');
// Fiscal stress is separate from the persistent budget and ranking.
$fp=['observations'=>[
    'revenue_excluding_grants_central_government'=>['year'=>2025,'value'=>30,'unit'=>'percent_gdp'],
    'expense_central_government'=>['year'=>2025,'value'=>35,'unit'=>'percent_gdp'],
    'tax_revenue_central_government'=>['year'=>2025,'value'=>20,'unit'=>'percent_gdp']]];
$fgdp=['kind'=>'observed','year'=>2025,'value'=>1000000000];
$fiscal=$ns::fiscal($fp,$shockN,$fgdp);
ck102($fiscal['revenue_minus_expense_excluding_grants_bps_gdp']===-500&&$fiscal['gap_reference_year']===2025,'same-year central-government reference gap');
ck102($fiscal['trade_tax_shortfall_bps_reference_gdp']===50&&$fiscal['trade_tax_shortfall_usd_reference_year']===5000000&&!$fiscal['rankable'],'experimental fiscal transmission uses trade stress and tax base');
ck102($ns::fiscal($fp,$baseN,$fgdp)['trade_tax_shortfall_bps_reference_gdp']===0,'no effective trade shock has zero modeled tax shortfall');
$fp['observations']['expense_central_government']['year']=2024;
$fgdp['year']=2024;
$fiscal=$ns::fiscal($fp,$shockN,$fgdp);
ck102($fiscal['revenue_minus_expense_excluding_grants_bps_gdp']===null&&$fiscal['trade_tax_shortfall_usd_reference_year']===null&&$fiscal['trade_tax_shortfall_bps_reference_gdp']===50,'mixed-year fiscal amounts not synthesized');
$fp['observations']['tax_revenue_central_government']['value']=null;
ck102($ns::fiscal($fp,$shockN,$fgdp)['trade_tax_shortfall_bps_reference_gdp']===null,'missing tax observation is not zero');
$fp['observations']['tax_revenue_central_government']['value']=20;
$baseE['projections']['b']['central_usd']=null;
ck102($ns::fiscal($fp,$ns::project($np,'state_de','2028-05-14',$gdp,$baseE),$fgdp)['trade_tax_shortfall_bps_reference_gdp']===null,'missing trade observation propagates to fiscal stress');
ck102($ns::project($np,'state_de','2028-05-14',$gdp,$baseE)['scenario_index_bps']===null,'unknown effect is not treated as zero');
ck102(call102('GET','/universes/'.$no.'/international',null,2)['status']===404,'international member isolation');
$restricted=array_values(array_filter($iv['body']['actors'],fn($r)=>in_array($r['id'],['state_il','state_ir','state_ps'],true)));
ck102(count($restricted)===3&&count(array_filter($restricted,fn($r)=>$r['controller']==='ai_only'))===3,'restricted actors represented but not playable');
$refs102=call102('GET','/universes/'.$no.'/characters/CH',null,1);
ck102($refs102['status']===200&&$refs102['body']['controller']==='ai'&&!$refs102['body']['runtime_identity_bound']&&$refs102['body']['reference']['government_mode']==='collective_executive_not_single_prime_minister','Swiss documentary directory not a presidential grant');
$refs102=call102('GET','/universes/'.$no.'/characters/FR',null,1);
ck102($refs102['status']===200&&$refs102['body']['is_you']&&count($refs102['body']['reference']['people'])===22,'French directory alongside actual slot');
ck102($refs102['body']['opening_identity']['kind']==='real'&&$refs102['body']['runtime_identity_bound']&&count($refs102['body']['scenario_challengers'])===3,'French real scenario casting');
$refClass=\Aeternum\WorldPolitics\UniverseIdentityReference::class;
$frSlot=array_values(array_filter($directory[0]['slots'],fn($v)=>$v['country']==='FR'))[0];
ck102($directory[0]['roster_version']===$refClass::PROGRAMME_ROSTER&&$frSlot['domestic_programme']===$refClass::documentedProgramme($refClass::openingPerson($world,'FR')),'new shared world pins documented partial programme');
$refsDE=call102('GET','/universes/'.$no.'/characters/DE',null,1);
ck102($refsDE['status']===200&&$refsDE['body']['runtime_identity_bound']&&$refsDE['body']['opening_identity']['name']==='Friedrich Merz','German initial simulated government identity');
ck102(count($refsDE['body']['documentary_parties']['parties'])===4,'documented comparative parties exposed');
ck102(call102('GET','/universes/'.$no.'/characters/IL',null,1)['status']===404,'restricted slot absent');

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
// Exercise the comparative publication path against an isolated rollback
// fixture; the real slot and other tests must remain unchanged.
$db->beginTransaction();
try{
    $q=$db->prepare("SELECT state_json FROM wp_institutional_states WHERE world_id=? AND country_code='DE'");$q->execute([$world]);
    $sim=\Aeternum\WorldPolitics\InstitutionalSimulation::restore(json_decode($q->fetchColumn(),true,64,JSON_THROW_ON_ERROR));
    $candidate=$sim->snapshot()['electoral']['config']['parties']['spd']['leader'];
    $common=['world_id'=>$world,'country'=>'DE','motion_id'=>'test-de-motion','procedure_certified'=>true];
    $sim->accountability('parliament_clerk','accountability_file',$common+['evidence_id'=>'test-de-file','at'=>'2027-05-14T00:00:00Z','chamber'=>'bundestag','signatures'=>30,'candidate'=>$candidate,'kind'=>'no_confidence'],'test-de-file');
    $sim->advanceTo('2027-05-15');$sim->advanceTo('2027-05-16');
    $sim->accountability('parliament_clerk','accountability_vote',$common+['evidence_id'=>'test-de-vote','at'=>'2027-05-16T00:00:00Z','chamber'=>'bundestag','present'=>100,'yes'=>60,'no'=>40,'abstain'=>0],'test-de-vote');
    $sim->accountability('constitutional_clerk','accountability_resolve',$common+['evidence_id'=>'test-de-resolve','at'=>'2027-05-16T01:00:00Z','outcome'=>'appoint_successor','successor'=>$candidate],'test-de-resolve');
    $q=$db->prepare("UPDATE wp_institutional_states SET state_json=?,state_sha=? WHERE world_id=? AND country_code='DE'");
    $q->execute([json_encode($sim->snapshot(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$sim->hash(),$world]);
    \Aeternum\WorldPolitics\UniverseContinuity::publishComparative($db,$world,'DE','2027-05-15','2027-05-16');
    $q=$db->prepare("SELECT account_id,closed_on FROM wp_universe_slots WHERE world_id=? AND country_code='DE'");$q->execute([$world]);
    $slot=$q->fetch(PDO::FETCH_ASSOC);ck102($slot['account_id']===null&&$slot['closed_on']===null,'certified foreign successor releases old player without resetting country');
    $q=$db->prepare("SELECT left_on FROM wp_universe_control_periods WHERE world_id=? AND country_code='DE' AND generation=1");$q->execute([$world]);
    ck102($q->fetchColumn()==='2027-05-16','foreign control period closes at certified handover');
}finally{$db->rollBack();}

ck102(call102('POST',$root.'/domestic/proposals',['policy_id'=>'education-reform','effective_day'=>'2027-05-20'],2)['status']===403,'foreign player cannot command France');
$extraPlayer=array_search('CH',$extra,true)+9;
$dipExtra=call102('GET',$root.'/diplomacy',null,$extraPlayer);
ck102(call102('POST',$root.'/diplomacy/proposals',['from'=>'state_ch','to'=>'state_at','action'=>'dialogue'],$extraPlayer,['if-match'=>$dipExtra['headers']['ETag'],'idempotency-key'=>'wp111-swiss-dialogue'])['status']===202,'expanded Swiss slot commands own diplomacy');
$dip=call102('GET',$root.'/diplomacy',null,2);
$payload=['from'=>'state_de','to'=>'state_fr','action'=>'dialogue'];
$headers=['if-match'=>$dip['headers']['ETag'],'idempotency-key'=>'wp102-diplomacy-de'];
ck102(call102('POST',$root.'/diplomacy/proposals',$payload,2,$headers)['status']===202,'foreign player commands own diplomacy');
$ivFr=call102('GET','/universes/'.$no.'/international',null,1);
$ivDe=call102('GET','/universes/'.$no.'/international',null,2);
ck102($ivFr['status']===200&&$ivDe['status']===200&&$ivFr['body']['day']===$ivDe['body']['day']&&$ivFr['body']['world_id']===$ivDe['body']['world_id'],'two country members read same day');
ck102(count(array_filter($ivFr['body']['incoming'],fn($p)=>$p['from']==='state_de'&&$p['to']==='state_fr'))===1,'German proposal reaches French inbox');
ck102(count(array_filter($ivDe['body']['incoming'],fn($p)=>$p['from']==='state_de'))===0,'outgoing offer not an incoming one');
$deView=array_values(array_filter($ivDe['body']['countries'],fn($c)=>$c['is_you']))[0];
ck102($deView['national_reference']['reference_as_of']==='2026-11-27'&&$deView['consumption_scenario']['scenario_index_bps']===10000,'foreign member sees pinned macro scenario');
ck102($ivDe['body']['national_fiscal_ledger']['country']==='DE'&&$ivDe['body']['national_fiscal_ledger']['persisted']&&!$ivDe['body']['national_fiscal_ledger']['rankable'],'foreign member receives pinned experimental fiscal ledger');
ck102($deView['fiscal_scenario']['tax_reference_year']===2024&&$deView['fiscal_scenario']['trade_tax_shortfall_bps_reference_gdp']===0&&!$deView['fiscal_scenario']['rankable'],'international member receives separate fiscal scenario');
$dip=call102('GET',$root.'/diplomacy',null,2);
$payload['from']='state_fr';$payload['to']='state_de';
ck102(call102('POST',$root.'/diplomacy/proposals',$payload,2,['if-match'=>$dip['headers']['ETag'],'idempotency-key'=>'wp102-spoof-fr'])['status']===403,'foreign player cannot spoof another state');
ck102(call102('DELETE','/universes/'.$no.'/membership',null,1)['status']===200,'release France to AI');
ck102(call102('GET',$root.'/clock',null,1)['status']===404,'former member access revoked');
ck102(call102('GET','/universes/'.$no.'/international',null,1)['status']===404,'former member international view revoked');
ck102(join102(1,'FR')['status']===409,'WP109 stale join receipt cannot resurrect released control');
$rejoin=join102(8,'FR',$no);
ck102($rejoin['status']===201&&$rejoin['body']['world_id']===$world&&$rejoin['body']['generation']===2,'late claimant inherits same world');
ck102(call102('GET',$root.'/countries/FR/institutions',null,8)===$before,'institutional state unchanged at handover');
ck102(count(call102('GET','/universes/'.$no.'/rankings',null,8)['body']['rows'])===44,'universe standings preserve past player');
$openingPayload=['country'=>'FR','universe_no'=>$directory[1]['universe_no'],'candidate'=>['type'=>'historical','reference_id'=>'real-emmanuel-macron']];
ck102(call102('POST','/universes/join',$openingPayload,8,['idempotency-key'=>'wp114-archive-unavailable'])['status']===409,'WP114 historical archive cannot be selected in 2027');
$openingPayload['candidate']=['type'=>'fictional','name'=>'Présidence fictive de test','programme'=>[
    ['id'=>'education-reform','domain'=>'education','proposal'=>'Réforme fictive'],
    ['id'=>'hist2022-emmanuel-macron-1','domain'=>'retirement','proposal'=>'Objectif de test'],
]];
$openingHeaders=['idempotency-key'=>'wp102-other-universe'];
$opening=call102('POST','/universes/join',$openingPayload,8,$openingHeaders);
ck102($opening['status']===201&&$opening['body']['opening_candidate']['programme_count']===2,'account may choose a fictional opening identity in a separate universe');
ck102(call102('POST','/universes/join',$openingPayload,8,$openingHeaders)===$opening,'candidate join receipt stable');
$q=$db->prepare("SELECT domestic_programme FROM wp_universe_slots WHERE world_id=? AND country_code='FR'");$q->execute([$directory[1]['world_id']]);
ck102($q->fetchColumn()==='manual','fictional opening never inherits unrelated real programme');
$openingProgress=call102('GET','/universes/'.$directory[1]['universe_no'].'/programme',null,8)['body']['candidate_programme'];
ck102(count($openingProgress['measures'])===2&&$openingProgress['kind']==='fictional'&&$openingProgress['automatic_import']===false,'WP114 opening programme source choices visible');
ck102(count(array_filter($openingProgress['measures'],fn($m)=>$m['available_in_world']))===2,'WP114 reviewed programme IDs executable in new world');
$qr=$db->prepare("SELECT state_json,state_sha,revision FROM wp_world_rosters WHERE world_id=? AND country_code='FR'");$qr->execute([$directory[1]['world_id']]);$openingRoster=$qr->fetch(PDO::FETCH_ASSOC);
ck102((int)$openingRoster['revision']===1&&json_decode($openingRoster['state_json'],true,64,JSON_THROW_ON_ERROR)['people']['campaign-president']['kind']==='fictional','opening candidate identity durably recorded');
$openingConflict=$openingPayload;$openingConflict['candidate']=['type'=>'fictional','name'=>'Autre présidence fictive'];
ck102(call102('POST','/universes/join',$openingConflict,8,$openingHeaders)['status']===409,'candidate idempotency conflict');
ck102(call102('POST','/universes/join',$openingConflict,8,['idempotency-key'=>'wp114-replace-candidate'])['status']===409,'cannot rename an already claimed country');
$global=call102('GET','/universe-rankings/global',null,8)['body'];
ck102(count($global['rows'])===44&&!$global['composite_score_available']&&$global['rows'][0]['score']===null,'global standings one row per player; no fabricated scores');

require_once __DIR__.'/../games/world-politics/src/UniverseScheduler.php';
$scheduler=new \Aeternum\WorldPolitics\UniverseScheduler($connect);
$spare=$directory[1]['world_id'];$spareNo=$directory[1]['universe_no'];
ck102(call102('POST','/universes/'.$no.'/programme',['diplomacy'=>'cooperative','domestic'=>'manual'],8)['status']===200,'WP103 inherited diplomatic programme');
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','domestic'=>'education'],8)['status']===200,'WP107 opt-in domestic programme');
ck102(call102('POST','/universes/'.$no.'/programme',['diplomacy'=>'balanced','domestic'=>'education'],2)['status']===403,'WP108 foreign pilot cannot set French domestic programme');
ck102(call102('DELETE','/universes/'.$spareNo.'/membership',null,8)['status']===200,'WP103 activated second world empty');
ck102(call102('POST','/universes/join',$openingConflict,8,['idempotency-key'=>'wp114-replace-after-leave'])['status']===409,'cannot rename inherited AI leader even on opening day');
$qr->execute([$spare]);ck102($qr->fetch(PDO::FETCH_ASSOC)===$openingRoster,'candidate survives release to AI unchanged');
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
$db->prepare('INSERT INTO wp_game_dataset_consent(world_id,enabled) VALUES(?,1)')->execute([$world]);
putenv('WP_DATASET_BRIDGE_KEY=ci-dataset-bridge-key-not-for-production');
$due->execute([$world]);
ck102($scheduler->tick($world)['day']==='2027-05-15','WP103 server advances shared clock');
$dsq=$db->prepare('SELECT envelope_json FROM wp_game_dataset_outbox WHERE world_id=? AND committed_day=?');$dsq->execute([$world,'2027-05-15']);
$envelope=json_decode($dsq->fetchColumn(),true,32,JSON_THROW_ON_ERROR);$ds=\Aeternum\WorldPolitics\GameDataset::verify($envelope);
ck102($ds['version']===\Aeternum\WorldPolitics\GameDataset::INTERNATIONAL_ROW_VERSION&&count($ds['international']['country_rows'])===42,'signed international aggregates in real committed outbox');
$dst=array_column($ds['international']['country_rows'],null,'country');
ck102($dst['DE']['features']['purchasing_power_index']!==null&&$dst['SA']['features']['purchasing_power_index']===null&&$ds['international']['data_date']==='2027-05-15','dataset preserves household missingness and committed date');
$bad=$envelope;$bad['row']['international']['country_rows'][0]['features']['cash']++;
$rejected=false;try{\Aeternum\WorldPolitics\GameDataset::verify($bad);}catch(DomainException $e){$rejected=true;}
ck102($rejected,'international signature rejects modified feature');
$db->prepare('UPDATE wp_game_dataset_consent SET enabled=0 WHERE world_id=?')->execute([$world]);
$fq=$db->prepare('SELECT committed_day,config_json,state_json,state_sha FROM wp_national_fiscal_worlds WHERE world_id=?');$fq->execute([$world]);$fr=$fq->fetch(PDO::FETCH_ASSOC);
$fc=json_decode($fr['config_json'],true,32,JSON_THROW_ON_ERROR);$fst=json_decode($fr['state_json'],true,32,JSON_THROW_ON_ERROR);
ck102($fr['committed_day']==='2027-05-15'&&count($fst['countries'])===42&&!isset($fst['countries']['FR'])&&$fst['countries']['DE']['day']==='2027-05-15','42 normalized ledgers committed with common day');
ck102(count($fst['last_flows'])===42&&$fc['source']['data_year']===2025&&$fc['unit']==='normalized_reference_GDP_units','fiscal provenance and settlement retained');
ck102(count($fst['macro'])===42&&$fst['macro']['DE']['real_output_index']>1000000000&&$fc['countries']['DE']['opening_forecast']['opening_day']==='2027-05-14','forecast-backed daily macro state');
ck102(count($fst['households'])===42&&$fst['households']['DE']['available']&&!$fst['households']['DE']['rankable']&&$fst['macro']['DE']['trade']['data_date']==='2027-05-15','household and trade checkpoints published on same day');
$firstSha=$fr['state_sha'];ck102($scheduler->tick($world)['status']==='not_due','fiscal ledger not advanced before next deadline');$fq->execute([$world]);ck102($fq->fetch(PDO::FETCH_ASSOC)['state_sha']===$firstSha,'fiscal state cannot consume credit twice');
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


// WP114d: same REST programme can be configured using exact objective IDs.
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>['annual-defense-reduce','annual-defense-increase']],8)['status']===422,'WP114d conflicting objective IDs rejected');
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>['hist2022-unknown-1']],8)['status']===422,'WP114d unknown historical ID rejected');
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>['industry-active'],'agenda'=>$ext114],8)['status']===422,'WP114d exclusive programme configuration');
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>['industry-active','cabinet-civic']],8)['status']===200,'WP114d reviewed objective IDs accepted');
$r114d=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($r114d['agenda']['industry']==='active'&&$r114d['agenda']['cabinet']==='civic'&&count($r114d['executable_measure_catalog'])===25,'WP114d compiled agenda and discoverable catalogue');


// WP114e: programme choice, human precedence, persisted domestic proposal,
// and matching legislative draft in the coordinated real-HTTP world.
$ids114e=['hist2022-jean-luc-melenchon-1','hist2022-jean-luc-melenchon-2','industry-active'];
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>$ids114e],8)['status']===200,'WP114e mixed historical programme accepted');
for($n114=0;$n114<5;$n114++){$due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114e historical programme daily tick');}
$r114e=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($r114e['social_measure_ids']===array_slice($ids114e,0,2)&&count($r114e['social_programme']['measures'])===2,'WP114e both historic objectives persisted');
$q114e=$db->prepare("SELECT state_json FROM wp_institutional_states WHERE world_id=? AND country_code='FR'");$q114e->execute([$spare]);
$i114e=json_decode($q114e->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck102(count(array_filter($i114e['legislation']['bills'],fn($b)=>$b['policy_id']===$ids114e[0]))===1,'WP114e actual legislative record for historic pension proposal');
ck102(\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$spareNo)['integrity']==='passed','WP114e historical programme audit');


// WP114k: replay-preserving upgrade preparation against a real progressed
// coordinated fixture without active social laws, including its historic days.
require_once __DIR__.'/../games/world-politics/src/SocialUniverseUpgrade.php';
$qu=$db->prepare('SELECT assumptions_json,state_json,state_sha,assumptions_sha FROM wp_domestic_states WHERE world_id=?');$qu->execute([$spare]);$ru=$qu->fetch(PDO::FETCH_ASSOC);
$cu=json_decode($ru['assumptions_json'],true,64,JSON_THROW_ON_ERROR);$du=json_decode($ru['state_json'],true,64,JSON_THROW_ON_ERROR);
ck102(isset($du['social_economy_from'])&&isset($du['days'][array_key_last($du['days'])]['social_economy']),'WP114f-h daily social calculation persisted');
$up=\Aeternum\WorldPolitics\SocialUniverseUpgrade::run($db,$spareNo);
ck102($up['changed']===false&&$up['applied']===false,'WP114k idempotent upgrade dry run');
unset($cu['social_economy_from']);
$cu['catalog']=array_diff_key($cu['catalog'],\Aeternum\WorldPolitics\PrimaryManifestoPolicy::definitions()+\Aeternum\WorldPolitics\SocialProgramme::target2027()+\Aeternum\WorldPolitics\FrenchV1Policy::definitions());
$legacy=\Aeternum\WorldPolitics\PdoDomesticPolicyStore::build($spare,$cu);
$old=$legacy->snapshot();
foreach($old as $k=>$v)if($k!=='assumptions_sha'&&array_key_exists($k,$du))$old[$k]=$du[$k];
foreach($old['days'] as &$dayu){unset($dayu['social_economy']);}unset($dayu);
$oldHash=hash('sha256',json_encode($old,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
$q114e->execute([$spare]);$iu=json_decode($q114e->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
$ih=\Aeternum\WorldPolitics\InstitutionalSimulation::restore($iu)->hash();
$plan=\Aeternum\WorldPolitics\SocialUniverseUpgrade::prepare($cu,$old,$oldHash,$legacy->assumptionsHash(),$iu,$ih);
ck102($plan['changed']&&$plan['domestic']['days']===$old['days']&&$plan['effective_on']>$old['day'],'WP114k prior daily rows preserved exactly');


// WP114j: HTTP configuration recognizes explicit family repeal without
// changing current enacted laws at configuration time.
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>['social-repeal-pension','hist2022-jean-luc-melenchon-1']],8)['status']===422,'WP114j contradictory repeal and reform rejected');
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>['social-repeal-pension','industry-active']],8)['status']===200,'WP114j mixed repeal configuration accepted');
$rj=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($rj['social_measure_ids']===['social-repeal-pension'],'WP114j persisted repeal objective');


// WP114k: create a genuinely legacy-configured fixture before any game
// transition or control period, advance it, then exercise actual SQL apply.
// The fixture construction is isolated to the disposable CI database.
$bundleK=\Aeternum\WorldPolitics\GlobalReferenceBundle::published();
$db->beginTransaction();
$shaK=(new \Aeternum\WorldPolitics\PdoGlobalReferenceBundles($db))->install($bundleK);
$scK=new \Aeternum\WorldPolitics\AlphaScenario(\Aeternum\WorldPolitics\AlphaScenario::INVESTITURE,$bundleK,$shaK);
$wk=(new \Aeternum\WorldPolitics\AlphaWorlds($db,$scK))->provisionUniverse()['world_id'];
$q=$db->prepare('INSERT INTO wp_universes(world_id,roster_version) VALUES(?,?)');$q->execute([$wk,UniverseLobby::ROSTER_VERSION]);$nk=(int)$db->lastInsertId();
$q=$db->prepare("INSERT INTO wp_universe_slots(world_id,country_code,domestic_programme) VALUES(?,?,'manual')");
foreach(UniverseLobby::DIPLOMATIC_COUNTRIES as $cc)$q->execute([$wk,$cc]);
$legacyK=$scK->domestic();$dk=\Aeternum\WorldPolitics\PdoDomesticPolicyStore::build($wk,$legacyK);
$jsonK=fn(array $v)=>json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$q=$db->prepare('UPDATE wp_domestic_states SET assumptions_json=?,assumptions_sha=?,state_json=?,state_sha=? WHERE world_id=? AND revision=0');
$q->execute([$jsonK($legacyK),$dk->assumptionsHash(),$jsonK($dk->snapshot()),$dk->hash(),$wk]);
$q114e->execute([$wk]);$ik=json_decode($q114e->fetchColumn(),true,64,JSON_THROW_ON_ERROR);unset($ik['legislation']['social_programme']);
$ik=\Aeternum\WorldPolitics\InstitutionalSimulation::restore($ik);
$q=$db->prepare("UPDATE wp_institutional_states SET state_json=?,state_sha=? WHERE world_id=? AND country_code='FR' AND revision=0");$q->execute([$jsonK($ik->snapshot()),$ik->hash(),$wk]);
$q=$db->prepare('SELECT state_json,state_sha FROM wp_diplomatic_states WHERE world_id=?');$q->execute([$wk]);$dipK=$q->fetch(PDO::FETCH_ASSOC);
$q=$db->prepare('DELETE FROM wp_universe_income_checkpoints WHERE world_id=?');$q->execute([$wk]);
\Aeternum\WorldPolitics\UniverseScore::seed($db,$wk,$shaK,$legacyK,['state'=>$dk->snapshot(),'etag'=>'"'.$dk->hash().'"'],['state'=>json_decode($dipK['state_json'],true,64,JSON_THROW_ON_ERROR),'etag'=>'"'.$dipK['state_sha'].'"'],true,true);
$db->commit();
ck102(join102(8,'FR',$nk,'wp114k-legacy-fixture')['status']===201,'WP114k legacy fixture human control');
for($n=0;$n<2;$n++){$due->execute([$wk]);ck102($scheduler->tick($wk)['status']==='committed','WP114k legacy fixture progresses');}
$qu->execute([$wk]);$oldK=$qu->fetch(PDO::FETCH_ASSOC);$oldDaysK=json_decode($oldK['state_json'],true,64,JSON_THROW_ON_ERROR)['days'];
$dryK=\Aeternum\WorldPolitics\SocialUniverseUpgrade::run($db,$nk);
ck102($dryK['changed']&&!$dryK['applied']&&$dryK['old_score_cohort']!==$dryK['new_score_cohort'],'WP114k real dry-run with distinct ranking cohort');
$qu->execute([$wk]);ck102($qu->fetch(PDO::FETCH_ASSOC)===$oldK,'WP114k no dry-run writes');
$appliedK=\Aeternum\WorldPolitics\SocialUniverseUpgrade::run($db,$nk,true);
ck102($appliedK['applied']&&$appliedK['new_domestic_sha']===$dryK['new_domestic_sha'],'WP114k actual committed SQL migration');
$qu->execute([$wk]);$newK=$qu->fetch(PDO::FETCH_ASSOC);$stateK=json_decode($newK['state_json'],true,64,JSON_THROW_ON_ERROR);
ck102($stateK['days']===$oldDaysK&&$newK['state_sha']===$appliedK['new_domestic_sha'],'WP114k all prior days remain byte-equivalent');
$q=$db->prepare("SELECT before_sha,after_sha FROM wp_domestic_journal WHERE world_id=? AND operation='model_upgrade'");$q->execute([$wk]);$jK=$q->fetch(PDO::FETCH_ASSOC);
ck102($jK['before_sha']===$oldK['state_sha']&&$jK['after_sha']===$newK['state_sha'],'WP114k durable digest bridge');
ck102(!\Aeternum\WorldPolitics\SocialUniverseUpgrade::run($db,$nk,true)['changed'],'WP114k repeated apply inert');
$due->execute([$wk]);ck102($scheduler->tick($wk)['status']==='committed','WP114k new model advances after migration');
$qu->execute([$wk]);$afterK=json_decode($qu->fetch(PDO::FETCH_ASSOC)['state_json'],true,64,JSON_THROW_ON_ERROR);
ck102(array_slice($afterK['days'],0,count($oldDaysK))===$oldDaysK&&isset($afterK['days'][array_key_last($afterK['days'])]['social_economy']),'WP114k new model starts on exact next day');
ck102(\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$nk)['integrity']==='passed','WP114k post-migration score and control audit');


// WP114 quantitative primary commitments use the same HTTP and daily
// institutional/domestic pipeline; longer mixed masks persist in SQL.
$primIds=['pf2022-nicolas-dupont-aignan-2','pf2022-yannick-jadot-2','industry-active'];
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>$primIds],8)['status']===200,'WP114 extended primary mixed profile accepted');
for($n=0;$n<5;$n++){$due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114 primary programme daily pipeline');}
$rp=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($rp['social_measure_ids']===array_slice($primIds,0,2)&&strlen($rp['domestic'])>24,'WP114 extended profile durable');
$q114e->execute([$spare]);$ip=json_decode($q114e->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck102(count(array_filter($ip['legislation']['bills'],fn($b)=>$b['policy_id']===$primIds[0]))===1,'WP114 primary law draft is not an unrelated policy');
ck102(\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$spareNo)['integrity']==='passed','WP114 primary programme audit');


$largeIds=['pf2022-marine-le-pen-1','social-repeal-hospital-beds','industry-active'];
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>$largeIds],8)['status']===200,'WP114 32 digit profile accepted');
$largeState=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102(strlen($largeState['domestic'])>40&&$largeState['social_measure_ids']===array_slice($largeIds,0,2),'WP114 widened profile retains exact objectives');


$energyProfile=['pf2022-jean-luc-melenchon-4','pf2022-marine-le-pen-3','industry-active'];
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>$energyProfile],8)['status']===200,'WP114 economic transition objectives accepted');
for($n=0;$n<4;$n++){$due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114 structural economic programme tick');}
$eState=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102(count($eState['social_measure_ids'])===2,'WP114 economic transition programme persisted');


// WP114 NATO selection and actual parliamentary draft do not themselves
// constitute a notice. The independent organization clock waits for
// a committed effective law and exact publication receipt.
$natoId='pf2022-philippe-poutou-4';
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>[$natoId]],8)['status']===200,'WP114 treaty programme accepted');
for($n=0;$n<4;$n++){$due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114 treaty authorization pipeline');}
$nr=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($nr['nato_withdrawal']===null&&$nr['social_measure_ids']===[$natoId],'WP114 no notice before effective law');
$q114e->execute([$spare]);$ni=json_decode($q114e->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck102(count(array_filter($ni['legislation']['bills'],fn($b)=>$b['policy_id']===$natoId))===1,'WP114 separate treaty authorization bill');


$durId='pf2022-jean-lassalle-4';
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>[$durId]],8)['status']===200,'WP114 prospective duration referendum programme accepted');
// There may already be a pending constitutional procedure in this fixture;
// selecting a different objective cannot force an instant new amendment.
$durationState=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($durationState['agenda']['revision']==='presidential-seven-year-referendum'&&$durationState['agenda']['ratification']==='referendum','WP114 duration referendum canonical and durable');


$coId='pf2022-nathalie-arthaud-3';
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>[$coId]],8)['status']===200,'WP114 collective property authorization programme accepted');
for($n=0;$n<4;$n++){$due->execute([$spare]);ck102($scheduler->tick($spare)['status']==='committed','WP114 collective property law pipeline');}
$q114e->execute([$spare]);$ci=json_decode($q114e->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck102(count(array_filter($ci['legislation']['bills'],fn($b)=>$b['policy_id']===$coId))===1,'WP114 collective property has its own legal instrument');
$q114->execute([$spare]);$cd=json_decode($q114->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
ck102(!isset($cd['days'][array_key_last($cd['days'])]['public_equity']['collective']),'WP114 mere draft conveys no title');


foreach(['pf2022-nicolas-dupont-aignan-4'=>'citizen-initiative-500k','pf2022-nathalie-arthaud-4'=>'resident-worker-franchise'] as $id=>$template){
    ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>[$id]],8)['status']===200,'WP114 civic programme accepted '.$id);
    $cs=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
    ck102($cs['agenda']['revision']===$template&&$cs['agenda']['ratification']==='referendum','WP114 durable civic programme '.$id);
}


// WP114 target election: independently reviewed 2027 commitments use
// the same authenticated, pinned and conflict-checked programme pipeline.
$targetIds=['pf2027-jean-luc-melenchon-smic','pf2027-jean-luc-melenchon-pension'];
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>[$targetIds[0],'pf2022-jean-luc-melenchon-1']],8)['status']===422,'WP114 2027 and 2022 conflicting wage targets rejected');
ck102(call102('POST','/universes/'.$spareNo.'/programme',['diplomacy'=>'balanced','measure_ids'=>$targetIds],8)['status']===200,'WP114 reviewed 2027 measures accepted');
$targetState=call102('GET','/universes/'.$spareNo.'/programme',null,8)['body'];
ck102($targetState['social_measure_ids']===$targetIds,'WP114 exact 2027 identities persist without substitution');
ck102(\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$spareNo)['integrity']==='passed','WP114 2027 programme configuration preserves universe integrity');


// New pristine universe: actual authenticated selection sets the idle
// programme atomically, without automatically enacting an instrument.
$db->beginTransaction();
$cfg27=new \Aeternum\WorldPolitics\AlphaScenario(\Aeternum\WorldPolitics\AlphaScenario::INVESTITURE,$bundleK,$shaK);
$w27=(new \Aeternum\WorldPolitics\AlphaWorlds($db,$cfg27))->provisionUniverse()['world_id'];
$q27=$db->prepare('INSERT INTO wp_universes(world_id,roster_version) VALUES(?,?)');$q27->execute([$w27,UniverseLobby::ROSTER_VERSION]);$n27=(int)$db->lastInsertId();
$q27=$db->prepare("INSERT INTO wp_universe_slots(world_id,country_code,domestic_programme) VALUES(?,?,'manual')");
foreach(UniverseLobby::DIPLOMATIC_COUNTRIES as $cc)$q27->execute([$w27,$cc]);
$db->commit();
$body27=['country'=>'FR','universe_no'=>$n27,'candidate'=>['type'=>'campaign2027','reference_id'=>'campaign2027-jean-luc-melenchon-v2']];
$hdr27=['idempotency-key'=>'wp114-2027-opening-choice'];
$qr->execute([$w27]);$before27=$qr->fetch(PDO::FETCH_ASSOC);
$joined27=call102('POST','/universes/join',$body27,8,$hdr27);
ck102($joined27['status']===409,'WP114 incomplete programme cannot start a managed campaign');
$body27['candidate']['reference_id']='campaign2027-francois-asselineau';
ck102(call102('POST','/universes/join',$body27,8,$hdr27)['status']===409,'WP114 unpublished programme cannot start a managed campaign');
$qr->execute([$w27]);ck102($qr->fetch(PDO::FETCH_ASSOC)===$before27,'WP114 unavailable candidate cannot mutate roster');
$q27=$db->prepare("SELECT generation,account_id,domestic_programme FROM wp_universe_slots WHERE world_id=? AND country_code='FR'");$q27->execute([$w27]);$slot27=$q27->fetch(PDO::FETCH_ASSOC);
ck102((int)$slot27['generation']===0&&$slot27['account_id']===null&&$slot27['domestic_programme']==='manual','WP114 no slot mutation on rejected selection');
$body27['candidate']=['type'=>'fictional','name'=>'Président fictif 2027'];
ck102(call102('POST','/universes/join',$body27,8,$hdr27)['status']===201,'WP114 rejection does not consume idempotency key and fictional candidate remains available');
ck102(call102('POST','/universes/'.$n27.'/programme',['diplomacy'=>'balanced','measure_ids'=>$targetIds],8)['status']===200,'WP114 reviewed individual policies remain available to fictional president');
ck102(\Aeternum\WorldPolitics\UniverseAudit::inspect($db,$n27)['integrity']==='passed','WP114 admission integrity');
