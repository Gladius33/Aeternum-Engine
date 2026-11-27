<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/CampaignCandidate.php';
use Aeternum\WorldPolitics\{CampaignCandidate as C,CampaignLifecycle as L};
function ck75b(bool $v,string $s):void{if(!$v)throw new RuntimeException('WP75B FAIL '.$s);echo "PASS WP75B: $s\n";}
function reject75b(callable $f):void{try{$f();}catch(InvalidArgumentException|DomainException $e){return;}throw new RuntimeException('Invalid selection accepted');}
$opts=C::options();ck75b(count($opts['historical_choices'])===12&&$opts['selection_at_creation']&&!$opts['future_eligibility_certified'],'historical selection provenance');
$p=C::normalize(['type'=>'historical','reference_id'=>$opts['historical_choices'][0]['id']]);
ck75b($p['kind']==='real'&&$p['id']===L::PLAYER&&$p['as_of']==='2022-04-10'&&$p['sources']!==[],'server-sourced identity and stable actor');
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
