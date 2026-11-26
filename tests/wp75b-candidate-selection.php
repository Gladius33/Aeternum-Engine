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
