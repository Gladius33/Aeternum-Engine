<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/CountryCatalog.php';
require __DIR__.'/../games/world-politics/src/GeopoliticalActorRegistry.php';
require __DIR__.'/../games/world-politics/src/DiplomaticSimulation.php';

use Aeternum\WorldPolitics\{
    CountryCatalog,GeopoliticalActorRegistry,DiplomaticSimulation,
    DiplomaticPrincipal,AutonomousDiplomacyPlanner
};

function wp6(bool $condition,string $message):void
{
    if (!$condition) throw new \RuntimeException($message);
    echo "PASS WP06: ".$message."\n";
}
function wp6reject(callable $fn,string $message):void
{
    try { $fn(); }
    catch (\DomainException|\InvalidArgumentException $error) {
        wp6(true,$message);return;
    }
    throw new \RuntimeException('Expected rejection: '.$message);
}

$unSource='https://www.un.org/en/about-us/member-states';
$member=static fn(string $code,string $name,bool $un):array=>[
    'iso2'=>$code,'name'=>$name,'un_member'=>$un,
    'as_of'=>'2026-11-24',
    'source'=>$un ? $GLOBALS['unSource'] : 'https://www.un.org/en/about-us/non-member-states',
];
$catalog=new CountryCatalog([
    $member('FR','France',true),
    $member('IL','Israël',true),
    $member('IR','Iran',true),
    $member('PS','Palestine',false),
]);
wp6($catalog->unMemberCount()===3,'Palestine observer not counted as full UN member');
wp6($catalog->country('PS')['un_observer_status']==='non_member_observer_state','Palestine carries distinct observer status');
wp6($catalog->country('PS')['control_policy']==='ai_only','Palestinian actor automated');
wp6($catalog->country('IL')['control_policy']==='ai_only','Israeli actor automated');
wp6($catalog->country('IR')['control_policy']==='ai_only','Iranian actor automated');
wp6($catalog->country('FR')['eligible_for_human_play']===true,'EU human eligibility preserved');
foreach (['IL','IR','PS'] as $restricted) {
    wp6reject(fn()=>new CountryCatalog([],[$restricted]),'forbidden playable override '.$restricted);
}
wp6reject(fn()=>$catalog->assertGlobalCoverage(),'global release refuses falsely claiming 193 nations');

$baseline=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
$actors=new GeopoliticalActorRegistry($baseline);
wp6(count($actors->all())===4,'4 sovereign/administrative public actors pinned');
wp6($actors->get('state_ps')['un_status']==='non_member_observer_state','Palestine represented as observer state');
wp6($actors->get('authority_pa')['kind']==='governing_authority'
    && $actors->get('authority_pa')['parent_id']==='state_ps','Palestinian Authority separate from State of Palestine');
wp6($actors->get('state_ir')['strategic_programmes'][0]['quantified_operational_capacity']===null
    && count($actors->get('state_ir')['strategic_programmes'])===2,
    'Iran ballistic and nuclear public programmes recorded without invented capacities');
wp6($actors->get('state_il')['controller']==='ai_only'
    && $actors->get('state_ir')['controller']==='ai_only','Israel and Iran both AI-only');
wp6(!$actors->canProposeInterstateTreaty('authority_pa')
    && $actors->canProposeInterstateTreaty('state_ps'),
    'separate authority from international treaty actor');

$bad=$baseline;
$bad['entities'][0]['controller']='human_or_ai';
wp6reject(fn()=>new GeopoliticalActorRegistry($bad),'administrative actor cannot be made human-playable');
$bad=$baseline;
$bad['entities'][2]['strategic_programmes'][0]['quantified_operational_capacity']=99999;
wp6reject(fn()=>new GeopoliticalActorRegistry($bad),'invented real military stockpile rejected');
$bad=$baseline;
$bad['entities'][0]['parent_id']='state_il';
wp6reject(fn()=>new GeopoliticalActorRegistry($bad),'Palestinian Authority cannot be relabeled as Israeli authority');

$baseline['entities'][]=[
    'id'=>'state_fr',
    'label'=>'France',
    'iso2'=>'FR',
    'kind'=>'state',
    'un_status'=>'un_member',
    'parent_id'=>null,
    'controller'=>'human_or_ai',
    'representation'=>'international_state_actor',
    'sources'=>[$unSource],
    'strategic_programmes'=>[],
];
$actors=new GeopoliticalActorRegistry($baseline);
$human=new class implements DiplomaticPrincipal {
    public function actorId():string {return 'verified-human-player';}
    public function allows(string $world,string $actorId,string $ability):bool
    {
        return $world==='world-test' && $actorId==='state_fr'
            && $ability==='diplomacy.propose';
    }
};
$bot=new class implements DiplomaticPrincipal {
    public function actorId():string {return 'trusted-server-ai';}
    public function allows(string $world,string $actorId,string $ability):bool
    {
        return $world==='world-test'
            && in_array($actorId,['state_il','state_ir','state_ps','authority_pa'],true)
            && in_array($ability,[
                'diplomacy.ai_controller','diplomacy.propose','diplomacy.respond'
            ],true);
    }
};
$simulation=new DiplomaticSimulation('world-test','2026-11-24',$actors);
wp6reject(fn()=>$simulation->propose($human,'state_ir','state_fr','dialogue','spoof-iran'),
    'human may not issue commands for Iran');
$forgedHuman=new class implements DiplomaticPrincipal {
    public function actorId():string {return 'client-imposter';}
    public function allows(string $world,string $actorId,string $ability):bool {
        return $world==='world-test' && $actorId==='state_il' && $ability==='diplomacy.propose';
    }
};
wp6reject(fn()=>$simulation->propose($forgedHuman,'state_il','state_fr','dialogue','spoof-israel'),
    'human claim without trusted AI-controller grant rejected');
wp6reject(fn()=>$simulation->propose($human,'state_fr','authority_pa','treaty_proposal','illegal-treaty'),
    'cannot treat Palestinian Authority as automatic sovereign treaty counterparty');

$aid=$simulation->propose($human,'state_fr','authority_pa','humanitarian_assistance','aid-pa');
wp6($aid['status']==='pending_response','humanitarian aid offer enters diplomatic queue');
$dialogue=$simulation->propose($human,'state_fr','authority_pa','dialogue','talk-pa');
wp6($dialogue['status']==='pending_response','direct dialogue with PA enabled');
$stance=$simulation->propose($human,'state_fr','state_il','express_support','stance-israel');
wp6($stance['status']==='declared_in_simulation','player can express support for Israel');
$otherStance=$simulation->propose($human,'state_fr','state_ir','express_opposition','stance-iran');
wp6($otherStance['status']==='declared_in_simulation','player can express opposition to Iran');
$treaty=$simulation->propose($human,'state_fr','state_ps','treaty_proposal','treaty-palestine');
wp6($treaty['status']==='pending_response','diplomacy with State of Palestine separate from PA');
wp6($simulation->propose($human,'state_fr','state_ps','treaty_proposal','treaty-palestine')===$treaty,
    'command retries are deterministic');
wp6reject(fn()=>$simulation->propose($human,'state_fr','state_il','dialogue','treaty-palestine'),
    'command-id payload conflict rejected');

$planner=new AutonomousDiplomacyPlanner();
$before=$simulation->hash();
$plans=$planner->plan($simulation,'authority_pa','humanitarian_open');
wp6(count($plans)===2 && $simulation->hash()===$before,
    'autonomous AI produces decisions without mutating source state');
$outcomes=$planner->execute($simulation,$bot,'authority_pa','humanitarian_open');
wp6(count($outcomes)===2
    && $simulation->snapshot()['proposals']['aid-pa']['status']==='accepted_in_principle',
    'Palestinian Authority AI can autonomously consider offered assistance');
wp6reject(fn()=>$planner->execute($simulation,$human,'state_ps','cautious'),
    'human actor cannot execute state of Palestine AI decisions');
$accepted=$planner->execute($simulation,$bot,'state_ps','open_dialogue');
wp6(count($accepted)===1
    && $accepted[0]['status']==='declined'
    && $accepted[0]['binding_effect']===false,
    'fictional AI policy may decline proposed international treaty');
wp6($simulation->snapshot()['proposals']['treaty-palestine']['status']==='declined',
    'no automatic alliance or treaty ratification');
$pending=$simulation->propose($human,'state_fr','state_ir','dialogue','talk-iran');
wp6(count($simulation->pendingFor('state_ir'))===1,'Iran AI receives player diplomatic overtures');
wp6($planner->execute($simulation,$bot,'state_ir','open_dialogue')[0]['status']==='accepted_in_principle',
    'Iran AI can independently respond through same command boundary');
wp6reject(fn()=>$simulation->respond($human,'state_ir','talk-iran',true,'fake-response'),
    'human cannot overwrite Iranian AI diplomatic response');
$simulation->propose($human,'state_fr','state_il','trade_cooperation','trade-israel');
$simulation->advanceTo('2026-12-15');
wp6($simulation->snapshot()['proposals']['trade-israel']['status']==='expired',
    'unanswered offers expire on game time not wall-clock time');
wp6reject(fn()=>$simulation->advanceTo('2026-11-24'),'game clock cannot reverse');

echo "WP06 diplomatic realism, autonomous Israel/Iran/Palestine actors passed.\n";
