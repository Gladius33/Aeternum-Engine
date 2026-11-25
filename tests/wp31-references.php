<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\GlobalReferenceBundle;
require_once __DIR__.'/../games/world-politics/src/GlobalReferenceBundle.php';
function ck31(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('WP31 FAIL: '.$label);echo "PASS WP31: $label\n";}
$b=new GlobalReferenceBundle();
ck31($b->countries->unMemberCount()===193 && count($b->countries->all())===195 && count($b->actors->all())===196,'UN membership and separate authority');
foreach(['IL','IR','PS','VA'] as $c) ck31(!$b->actors->isHumanPlayable('state_'.strtolower($c)),'protected actor '.$c);
ck31($b->coverage()['population_total']['absent']===195,'missing observations never become zeros');
$rights=['license'=>'CI fixture only','license_url'=>'https://www.worldbank.org/ext/en/legal/terms-conditions/datasets','reviewed_at'=>'2026-11-24','redistribution_approved'=>true];
$page=json_encode([['page'=>1,'pages'=>1,'total'=>2],[
    ['indicator'=>['id'=>'SP.POP.TOTL'],'country'=>['id'=>'FR'],'date'=>'2024','value'=>123],
    ['indicator'=>['id'=>'SP.POP.TOTL'],'country'=>['id'=>'DE'],'date'=>'2024','value'=>null]
]],JSON_THROW_ON_ERROR);
$pack=GlobalReferenceBundle::import(['SP.POP.TOTL'=>['pages'=>[$page],'rights'=>$rights]],'2026-11-24');
ck31($pack->coverage()['population_total']===['observed'=>1,'missing'=>1,'absent'=>193],'archive coverage distinguishes missing and absent');
ck31((new GlobalReferenceBundle($pack->payload()))->sha()===$pack->sha(),'deterministic archive replay');
$bad=$pack->payload();$bad['records'][0]['value']=456;
try {new GlobalReferenceBundle($bad);throw new RuntimeException('tampered pack accepted');}catch(DomainException|InvalidArgumentException $e){ck31(true,'tampered series rejected');}
$rights['redistribution_approved']=false;
try {GlobalReferenceBundle::import(['SP.POP.TOTL'=>['pages'=>[$page],'rights'=>$rights]],'2026-11-24');throw new RuntimeException('unreviewed license accepted');}
catch(DomainException $e){ck31(true,'rights gate');}
