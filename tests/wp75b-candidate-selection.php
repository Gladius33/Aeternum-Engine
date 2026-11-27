<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CampaignCandidate.php';
use Aeternum\WorldPolitics\{CampaignCandidate as C,CampaignLifecycle as L};
function ck75b(bool $v,string $s):void{if(!$v)throw new RuntimeException('WP75B FAIL '.$s);echo "PASS WP75B: $s\n";}
function reject75b(callable $f):void{try{$f();}catch(InvalidArgumentException|DomainException $e){return;}throw new RuntimeException('Invalid selection accepted');}
$opts=C::options();ck75b(count($opts['historical_choices'])===12&&$opts['selection_at_creation']&&!$opts['future_eligibility_certified'],'historical selection provenance');
$p=C::normalize(['type'=>'historical','reference_id'=>$opts['historical_choices'][0]['id']]);
ck75b($p['kind']==='real'&&$p['id']===L::PLAYER&&$p['as_of']==='2022-04-10'&&$p['sources']!==[]&&count($p['programme'])===2,'server-sourced identity, partial historical programme and stable actor');
$f=C::normalize(['type'=>'fictional','name'=>'Présidence test','programme'=>[['id'=>'education','domain'=>'education','proposal'=>'Réforme fictive']], 'profile'=>['party'=>'Parti fictif']]);
ck75b($f['kind']==='fictional'&&$f['id']===L::PLAYER&&$f['programme'][0]['sources']===[]&&$f['profile']['party']==='Parti fictif','custom candidate and programme');
reject75b(fn()=>C::normalize(['type'=>'historical','reference_id'=>'nonexistent']));
reject75b(fn()=>C::normalize(['type'=>'historical','reference_id'=>$opts['historical_choices'][0]['id'],'name'=>'Spoof']));
reject75b(fn()=>C::normalize(['type'=>'fictional','name'=>'Invalid','id'=>'another-actor']));
reject75b(fn()=>C::normalize(['type'=>'fictional','name'=>'Invalid','sources'=>['https://example.org']]));

require_once __DIR__.'/../games/world-politics/src/ProgrammeProgress.php';
$roster=new \Aeternum\WorldPolitics\PoliticalRoster('test-programme','FR',['prime_minister']);
$roster->addPerson($f);$roster->setHeadOfState(L::PLAYER);
$rr=['state'=>$roster->snapshot(),'etag'=>$roster->snapshotHash()];
$dd=['state'=>['world_id'=>'test-programme','day'=>'2027-05-14','policies'=>[],'days'=>[]],'etag'=>str_repeat('a',64)];
$report=\Aeternum\WorldPolitics\ProgrammeProgress::report($rr,$dd);
ck75b($report['counts']['no_matching_record']===1&&!$report['semantic_fulfilment_verified'],'WP92 free text not inferred as policy');
$dd['state']['policies']['education']=['proposed_on'=>'2027-05-14','effective_on'=>'2027-05-16','authorized_on'=>null,'instrument_sha'=>null];
ck75b(\Aeternum\WorldPolitics\ProgrammeProgress::report($rr,$dd)['counts']['proposed']===1,'WP92 proposal not enactment');
$dd['state']['policies']['education']['authorized_on']='2027-05-15';
$dd['state']['day']='2027-05-16';$dd['state']['days']=[['day'=>'2027-05-15','active_policies'=>[]],['day'=>'2027-05-16','active_policies'=>['education']]];
$report=\Aeternum\WorldPolitics\ProgrammeProgress::report($rr,$dd);
ck75b($report['counts']['active_latest_day']===1&&$report['measures'][0]['observed_activity']['active_days']===1&&!$report['score_rules_changed'],'WP92 independently observed policy activation');

$directory=\Aeternum\WorldPolitics\CampaignCandidate::politicalDirectory();
ck75b($directory['campaign_reference_count']===22&&$directory['supplementary_reference_count']===14&&count($directory['people'])===36,'French political directory expanded without changing ballot');
$additional=array_column(\Aeternum\WorldPolitics\FrenchPoliticalDirectory::supplementary(),null,'id');
foreach(['yvan-benedetti','jordan-bardella','francois-ruffin','philippe-poutou','francois-hollande','olivier-faure','sandrine-rousseau','marion-marechal','sarah-knafo','fabien-gay','manuel-bompard','mathilde-panot','eric-ciotti','laurent-wauquiez'] as $id){
    $r=$additional['real-'.$id];ck75b(!$r['playable']&&!$r['runtime_roster']&&!$r['certified_2027_candidate']&&$r['reviewed_measure_count']===0,'identity observation does not grant programme or authority');
    ck75b(!\Aeternum\WorldPolitics\CampaignCandidate::mayStart(['type'=>'campaign2027','reference_id'=>'campaign2027-'.$id]),'supplement cannot bypass programme approval');
}
ck75b($directory['playable_count']===0,'no unapproved real campaign enabled');

$provisional=\Aeternum\WorldPolitics\CampaignCandidate::provisionalChoices();
ck75b(count($provisional)===4&&array_sum(array_column($provisional,'reviewed_measure_count'))===11,'four explicit partial playable editions');
foreach($provisional as $r){
    $sel=['type'=>'campaign2027','reference_id'=>$r['id']];
    ck75b($r['playable']&&!$r['programme_complete']&&$r['provisional']&&\Aeternum\WorldPolitics\CampaignCandidate::mayStart($sel),'explicit provisional release only');
    ck75b(count(\Aeternum\WorldPolitics\CampaignCandidate::normalizeManaged($sel)['programme'])===$r['reviewed_measure_count'],'source mapped measures preserved');
}
ck75b(count(\Aeternum\WorldPolitics\ProvisionalFrenchProgramme::evidence()['records'])===9,'documentary evidence separate from executable editions');

$v1=C::v1Choices();ck75b(count($v1)===36&&count(array_unique(array_column($v1,'name')))===36,'all 36 public-source V1 identities');
ck75b(array_sum(array_column($v1,'reviewed_measure_count'))===13,'13 existing quantitative mappings, not fabricated new effects');
ck75b(C::politicalDirectory()['v1_playable_count']===36&&C::politicalDirectory()['full_programme_playable_count']===0,'provisional and complete availability separated');
foreach($v1 as $r){
    $sel=['type'=>'campaign2027','reference_id'=>$r['id']];
    ck75b(C::mayStart($sel)&&!$r['programme_complete']&&strlen(C::editionEvidence($r['id'])['document_sha256'])===64,'explicit edition permitted and digest-bound');
    ck75b(count(C::normalizeManaged($sel)['programme'])===count($r['measure_ids']),'only executable measures enter runtime profile');
}
