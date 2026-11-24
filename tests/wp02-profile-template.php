<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/PoliticalRoster.php';
require __DIR__.'/../games/world-politics/src/WorldPoliticsApi.php';

use Aeternum\WorldPolitics\{PoliticalProfileTemplate,PoliticalRoster,PoliticsAuthorizer,WorldPoliticsApi};

function wp2(bool $ok,string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS WP02: $label\n";
}
function wp2reject(callable $call,string $label):void {
    try { $call(); }
    catch (\InvalidArgumentException $e) { wp2(true,$label); return; }
    throw new RuntimeException('Expected rejection: '.$label);
}

$template=PoliticalProfileTemplate::definition();
wp2($template['schema_version']==='1.0.0','template has stable schema version');
wp2($template['avatar']['key']==='generic-v1' && $template['avatar']['uploads_enabled']===false,'only generic avatar in V1');
wp2(isset($template['modes']['quick'],$template['modes']['detailed']) && count($template['sections'])===5,'quick and detailed modes share one structure');

$quick=PoliticalProfileTemplate::normalizeFictional(['id'=>'short','name'=>'Alex Martin','mode'=>'quick'],'FR');
wp2($quick['programme']===[] && $quick['profile']===[] && $quick['biography']==='' && $quick['avatar']==='generic-v1','quick path uses safe defaults');
$full=PoliticalProfileTemplate::normalizeFictional([
    'id'=>'detailed','name'=>'Camille Moreau','mode'=>'detailed','biography'=>'Présentation fictive',
    'profile'=>[
       'birth_date'=>'1985-07-12','birth_place'=>'Paris','personal_statement'=>'Objectifs de mandat.',
       'education'=>['Université'],'languages'=>['Français'],
       'professional_history'=>['Entrepreneuriat'],'public_service'=>['Direction territoriale'],
       'expertise'=>['Finances'],'party'=>'Parti fictif','orientation'=>'Courant revendiqué',
       'political_history'=>['Élection fictive'],'policy_priorities'=>['Éducation']
    ],
    'programme'=>[['id'=>'schools','domain'=>'education','proposal'=>'Augmenter le budget scolaire']]
],'FR');
wp2($full['programme'][0]['sources']===[] && $full['profile']['party']==='Parti fictif','detailed personal and programme fields normalized');

$invalid=[
  ['id'=>'x','name'=>'X','kind'=>'real'],
  ['id'=>'x','name'=>'X','avatar'=>'custom-selfie'],
  ['id'=>'x','name'=>'X','country'=>'DE'],
  ['id'=>'x','name'=>'X','sources'=>['https://example.org']],
  ['id'=>'x','name'=>'X','mode'=>'bypass'],
  ['id'=>'x','name'=>'X','profile'=>['mental_fitness'=>99]],
  ['id'=>'x','name'=>'X','profile'=>['birth_date'=>'2026-02-30']],
  ['id'=>'x','name'=>'X','unknown_field'=>'payload'],
  ['id'=>'x','name'=>'X','programme'=>[['id'=>'fake','domain'=>'health','proposal'=>'A','sources'=>['https://example.org']]]],
];
foreach($invalid as $i=>$payload) wp2reject(fn()=>PoliticalProfileTemplate::normalizeFictional($payload,'FR'),"rejected dangerous input $i");

$roster=new PoliticalRoster('world-one','FR',['prime_minister','economy']);
$roster->addPerson($full); $roster->addPerson($quick);
$roster->setHeadOfState('short');
$roster->nominate('economy','detailed');
$roster->resolveNomination('economy',true);
$roster->nominate('prime_minister','detailed');
wp2(PoliticalRoster::fromSnapshot($roster->snapshot())->snapshotHash()===$roster->snapshotHash(),'snapshot restores detailed profile and appointments');

$corrupt=$roster->snapshot();$corrupt['people']['short']['id']='spoofed';
wp2reject(fn()=>PoliticalRoster::fromSnapshot($corrupt),'stored identity spoof rejected');
$corrupt=$roster->snapshot();$corrupt['cabinet']['undeclared_office']='detailed';
wp2reject(fn()=>PoliticalRoster::fromSnapshot($corrupt),'stored office spoof rejected');

$policy=new class implements PoliticsAuthorizer {
    public function actorId():string{return 'test-actor';}
    public function allows(string $world,string $country,string $ability):bool {
       return $world==='world-one' && $country==='FR';
    }
};
$api=new WorldPoliticsApi([$roster],$policy);
$base='/api/v1/worlds/world-one/countries/FR/politics';
$t=$api->handle('GET',$base.'/person-template');
wp2($t['status']===200 && $t['body']['schema_version']==='1.0.0','GET exposes UI-agnostic template');
$s=$api->handle('GET',$base);
$created=$api->handle('POST',$base.'/people',['id'=>'new-player','name'=>'Joueur rapide'],[
    'Idempotency-Key'=>'create-player-12345','If-Match'=>$s['headers']['ETag']
]);
wp2($created['status']===201 && $roster->snapshot()['people']['new-player']['profile']===[],'POST quick candidate without extended fields');
$latest=$api->handle('GET',$base);
$nameOnly=$api->handle('POST',$base.'/people',['name'=>'Création immédiate'],[
    'Idempotency-Key'=>'minimal-player-00001','If-Match'=>$latest['headers']['ETag']
]);
wp2($nameOnly['status']===201 && str_starts_with($nameOnly['body']['person_id'],'f-'),'in-memory REST accepts name-only candidate');
echo "WP02 headless character template tests passed.\n";
