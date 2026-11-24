<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/OrganizationSimulation.php';
use Aeternum\WorldPolitics\{
    GeopoliticalActorRegistry,OrganizationSimulation,DiplomaticPrincipal
};

function wp9(bool $ok,string $message):void
{
    if (!$ok) throw new RuntimeException($message);
    echo "PASS WP09: ".$message."\n";
}
function wp9deny(callable $f,string $message):void
{
    try { $f(); }
    catch (DomainException|InvalidArgumentException $e) { wp9(true,$message);return; }
    throw new RuntimeException('Expected rejection: '.$message);
}
$baseline=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
foreach(['FR'=>'France','DE'=>'Allemagne'] as $code=>$name) {
    $baseline['entities'][]=[
        'id'=>'state_'.strtolower($code),'label'=>$name,'iso2'=>$code,
        'kind'=>'state','un_status'=>'un_member','parent_id'=>null,
        'controller'=>'human_or_ai','representation'=>'international_state_actor',
        'sources'=>['https://www.un.org/en/about-us/member-states'],
        'strategic_programmes'=>[]
    ];
}
$actors=new GeopoliticalActorRegistry($baseline);
$orgs=[[
    'id'=>'fictional-forum','name'=>'Forum de coopération fictif',
    'status'=>'fictional_scenario','sources'=>[],
    'approval_rule'=>'unanimous',
    'ratification_days'=>5,'withdrawal_notice_days'=>30,
    'founders'=>['state_fr','state_ir']
],[
    'id'=>'scenario-alliance','name'=>'Alliance imaginaire',
    'status'=>'fictional_scenario','sources'=>[],
    'approval_rule'=>'simple_majority',
    'ratification_days'=>7,'withdrawal_notice_days'=>45,
    'founders'=>['state_fr','state_ir','state_il']
]];
$human=new class implements DiplomaticPrincipal {
    public function actorId():string { return 'human-player'; }
    public function allows(string $world,string $actor,string $ability):bool
    {
        if ($world!=='world-forum') return false;
        if ($actor==='state_de' && in_array($ability,['organizations.apply','organizations.withdraw'],true)) return true;
        return $actor==='state_fr' && $ability==='organizations.vote';
    }
};
$bot=new class implements DiplomaticPrincipal {
    public function actorId():string {return 'trusted-worker';}
    public function allows(string $world,string $actor,string $ability):bool
    {
        if ($world!=='world-forum')return false;
        if ($actor===$this->actorId() &&
            in_array($ability,['organizations.formalize','organizations.clock'],true))return true;
        return in_array($actor,['state_ir','state_il','authority_pa'],true)
            && in_array($ability,[
                'organizations.vote','organizations.ai_controller','organizations.apply'
            ],true);
    }
};
$sim=new OrganizationSimulation('world-forum','2026-11-24',$actors,$orgs);
$first=$sim->apply($human,'fictional-forum','state_de','application-one');
wp9($first['status']==='voting','German fictional application opens a member vote');
wp9($sim->apply($human,'fictional-forum','state_de','application-one')===$first,
    'same application command is idempotent');
wp9deny(fn()=>$sim->apply($human,'scenario-alliance','authority_pa','pa-apply'),
    'human cannot appoint the Palestinian Authority to a sovereign alliance');
wp9deny(fn()=>$sim->apply($bot,'scenario-alliance','authority_pa','pa-apply-bot'),
    'even verified AI cannot treat PA as a state under charter');
wp9deny(fn()=>$sim->vote($human,'state_ir','application-one',true,'spoof-vote'),
    'player cannot cast Iran AI membership vote');
$frBallot=$sim->vote($human,'state_fr','application-one',true,'fr-yes-one');
wp9($frBallot['status']==='voting','a single vote cannot instantly admit a new member');
$irBallot=$sim->vote($bot,'state_ir','application-one',true,'ir-yes-one');
wp9($irBallot['status']==='approved_pending_formalization',
    'unanimous consent opens formalization stage, not instant membership');
wp9(!in_array('state_de',$sim->snapshot()['organizations']['fictional-forum']['members'],true),
    'approved applicant not yet a formal treaty member');
wp9deny(fn()=>$sim->formalize($human,'application-one','illegitimate-ratification'),
    'human client cannot impersonate charter administration');
wp9deny(fn()=>$sim->formalize($bot,'application-one','premature-ratification'),
    'charter ratification delay is respected');
$sim->advanceTo($bot,'2026-11-29','clock-five-days');
$accession=$sim->formalize($bot,'application-one','ratification-final');
wp9($accession['status']==='member'
    && in_array('state_de',$sim->snapshot()['organizations']['fictional-forum']['members'],true),
    'trusted service formalizes only after both consent and delay');
$wp1=$sim->requestWithdrawal($human,'fictional-forum','state_de','leave-request');
wp9($wp1['status']==='pending' && $wp1['effective_on']==='2026-12-29',
    'withdrawal obeys organization-specific 30-day notice');
$sim->advanceTo($bot,'2026-12-28','clock-before-leave');
wp9(in_array('state_de',$sim->snapshot()['organizations']['fictional-forum']['members'],true),
    'membership remains active before effective withdrawal date');
$sim->advanceTo($bot,'2026-12-29','clock-leave-effective');
wp9(!in_array('state_de',$sim->snapshot()['organizations']['fictional-forum']['members'],true)
    && $sim->snapshot()['withdrawals']['leave-request']['status']==='effective',
    'membership withdrawal becomes effective on due game date');
wp9deny(fn()=>$sim->advanceTo($bot,'2026-11-29','clock-backwards'),
    'virtual time cannot be reversed');
$bad=$orgs;
$bad[0]['status']='curated_real_charter';
wp9deny(fn()=>new OrganizationSimulation('world-forum','2026-11-24',$actors,$bad),
    'real organization cannot have invented unsourced accession procedures');
$bad=$orgs;
$bad[0]['founders'][]='authority_pa';
wp9deny(fn()=>new OrganizationSimulation('world-forum','2026-11-24',$actors,$bad),
    'administrative authority cannot be silently upgraded to treaty state');

$parallel=new OrganizationSimulation('world-forum','2026-11-24',$actors,$orgs);
$parallel->apply($human,'fictional-forum','state_de','application-one');
$parallel->vote($human,'state_fr','application-one',true,'fr-yes-one');
$parallel->vote($bot,'state_ir','application-one',true,'ir-yes-one');
$parallel->advanceTo($bot,'2026-11-29','clock-five-days');
$parallel->formalize($bot,'application-one','ratification-final');
$parallel->requestWithdrawal($human,'fictional-forum','state_de','leave-request');
$parallel->advanceTo($bot,'2026-12-28','clock-before-leave');
$parallel->advanceTo($bot,'2026-12-29','clock-leave-effective');
wp9($parallel->hash()===$sim->hash(),
    'identical fictional treaties and virtual dates replay deterministically');

echo "WP09 charter-configured membership lifecycle passed.\n";
