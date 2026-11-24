<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/InstitutionalRulebook.php';
require __DIR__.'/../games/world-politics/src/CountryCatalog.php';
require __DIR__.'/../games/world-politics/src/InstitutionalSimulation.php';

use Aeternum\WorldPolitics\{CountryCatalog,InstitutionalRulebook,InstitutionalSimulation};

function wp5(bool $condition,string $message):void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS WP05: $message\n";
}
function wp5reject(callable $callback,string $message):void {
    try { $callback(); }
    catch (\DomainException|\InvalidArgumentException $e) {
        wp5(true,$message);return;
    }
    throw new RuntimeException('Expected rejection: '.$message);
}
wp5(count(CountryCatalog::EU_2026)===27 && count(array_unique(CountryCatalog::EU_2026))===27,'27 distinct EU member codes');
wp5(!in_array('CH',CountryCatalog::EU_2026,true) && in_array('GB',CountryCatalog::STRATEGIC_STARTER,true),'Swiss separate; UK strategic and not EU');
$source='https://www.un.org/about-us/member-states';
$records=[];
foreach(['FR'=>'France','DE'=>'Germany','US'=>'United States','CH'=>'Switzerland','CN'=>'China','BR'=>'Brazil','JP'=>'Japan','GB'=>'United Kingdom','ZA'=>'South Africa'] as $code=>$name) {
    $records[]=['iso2'=>$code,'name'=>$name,'un_member'=>true,'as_of'=>'2026-11-24','source'=>$source];
}
$catalog=new CountryCatalog($records);
wp5(count($catalog->all())===9,'subset source import does not fabricate absent countries');
wp5($catalog->country('FR')['institution_rules_ready'] && $catalog->country('DE')['institution_rules_ready'] && !$catalog->country('FR')['human_play_ready'],'FR and DE are eligible and rule-backed');
wp5($catalog->country('US')['institution_rules_ready'] && $catalog->country('CN')['eligible_for_human_play'] && !$catalog->country('US')['human_play_ready'],'major-power selection is configurable');
wp5(!$catalog->country('CH')['institution_rules_ready'] && $catalog->country('CH')['eligible_for_human_play'],'Switzerland is mandatory target but rules not yet implemented');
wp5(!$catalog->country('BR')['human_play_ready'],'do not claim a playable legal simulation before rules exist');
wp5($catalog->unMemberCount()===9,'real count reflects only imported records');
wp5reject(fn()=> $catalog->assertGlobalCoverage(),'global 193-state gate rejects incomplete source import');
wp5reject(fn()=> $catalog->country('PL'),'missing country cannot materialize from eligibility list');
wp5reject(fn()=> new CountryCatalog([$records[0],$records[0]]),'duplicate imported country rejected');
wp5reject(fn()=> new CountryCatalog($records,['CH']),'duplicated mandatory eligibility rejected');
wp5reject(fn()=> InstitutionalRulebook::forCountry('CH'),'country without legal rules fails closed');

$fr=new InstitutionalSimulation('scenario-fr','FR','2026-11-24');
wp5reject(fn()=> $fr->decide('president','submit_bill',['title'=>'Budget fictif'],'bill1'),'French president cannot directly introduce a government bill');
$bill=$fr->decide('prime_minister','submit_bill',['title'=>'Budget fictif'],'bill1');
wp5($bill['status']==='filed' && $fr->snapshot()['bills']['bill1']['status']==='filed','French PM submits bill without instant passage');
wp5reject(fn()=> $fr->decide('president','dissolve_lower_house',[],'diss1'),'French consultations are mandatory');
wp5reject(fn()=> $fr->decide('president','dissolve_lower_house',[],'diss1'),'client boolean is not evidence of consultation');
foreach(['prime_minister','assembly_speaker','senate_speaker'] as $i=>$office) {
    $fr->decide($office,'record_consultation',[],'consult'.($i+1));
}
$diss=$fr->decide('president','dissolve_lower_house',[],'diss1');
wp5($diss['election_earliest']==='2026-12-14' && $diss['election_deadline']==='2027-01-03','article 12 election window 20 to 40 days');
wp5($fr->snapshot()['legislature']==='dissolved','lower house dissolved but election not automatically invented');
wp5reject(fn()=> $fr->decide('president','hold_election',[],'election1'),'president cannot independently record election');
wp5reject(fn()=> $fr->decide('president','cancel_elections',[],'cancel1'),'unconstitutional cancellation is not a normal presidential power');
wp5reject(fn()=> $fr->decide('election_authority','hold_election',[],'election1'),'election cannot be recorded before window');
$day7=$fr->advanceTo('2026-12-01');
wp5(count($day7['processed'])===1 && $fr->snapshot()['bills']['bill1']['status']==='debate_ready','abstract parliamentary debate readiness is deferred');
$fr->advanceTo('2026-12-15');
$election=$fr->decide('election_authority','hold_election',[],'election1');
wp5($election['status']==='voting_recorded_results_pending','only authority records polling without fabricated results');
wp5($fr->snapshot()['legislature']==='sitting','election reopens institutional legislative session');
foreach(['prime_minister','assembly_speaker','senate_speaker'] as $i=>$office) {
    $fr->decide($office,'record_consultation',[],'reconsult'.($i+1));
}
wp5reject(fn()=> $fr->decide('president','dissolve_lower_house',[],'diss2'),'repeat dissolution restricted for one year after the election');
$fr->advanceTo('2027-01-04');
wp5($fr->snapshot()['constitutional_breaches']===0,'a held election prevents deadline breach');

$replay=new InstitutionalSimulation('scenario-fr','FR','2026-11-24');
$replay->decide('prime_minister','submit_bill',['title'=>'Budget fictif'],'bill1');
foreach(['prime_minister','assembly_speaker','senate_speaker'] as $i=>$office) {
    $replay->decide($office,'record_consultation',[],'consult'.($i+1));
}
$replay->decide('president','dissolve_lower_house',[],'diss1');
$replay->advanceTo('2026-12-01');
$replay->advanceTo('2026-12-15');
$replay->decide('election_authority','hold_election',[],'election1');
foreach(['prime_minister','assembly_speaker','senate_speaker'] as $i=>$office) {
    $replay->decide($office,'record_consultation',[],'reconsult'.($i+1));
}
$replay->advanceTo('2027-01-04');
wp5($fr->hash()===$replay->hash(),'same commands and game dates produce same deterministic snapshot hash');
wp5(InstitutionalSimulation::restore($fr->snapshot())->hash()===$fr->hash(),'trusted rule-pinned aggregate can be restored');
wp5reject(fn()=> $replay->advanceTo('2026-12-31'),'simulation clock cannot move backwards');
$invalid=$fr->snapshot();
$invalid['ruleset']='arbitrary-unpinned-version';
wp5reject(fn()=>InstitutionalSimulation::restore($invalid),'unapproved constitutional ruleset rejected');

$crisis=new InstitutionalSimulation('crisis-fr','FR','2026-11-24');
foreach(['prime_minister','assembly_speaker','senate_speaker'] as $i=>$office) {
    $crisis->decide($office,'record_consultation',[],'crconsult'.($i+1));
}
$crisis->decide('president','dissolve_lower_house',[],'crisis1');
$crisis->advanceTo('2027-01-04');
wp5($crisis->snapshot()['election']['status']==='constitutional_deadline_missed'
  && $crisis->snapshot()['constitutional_breaches']===1,'missed legal election deadline becomes documented crisis, not lawful cancellation');

$de=new InstitutionalSimulation('scenario-de','DE','2026-11-24');
wp5($de->snapshot()['ruleset']==='de-gg-wp05-0.2','Germany has separate constitutional ruleset');
wp5reject(fn()=> $de->decide('federal_president','dissolve_lower_house',[],'diss1'),'German president cannot use French dissolution power');
wp5reject(fn()=> $de->decide('federal_chancellor','submit_bill',['title'=>'Test'],'law1'),'German Chancellor does not bypass federal cabinet introduction process');
$de->decide('federal_government','submit_bill',['title'=>'Projet gouvernemental'],'law2');
wp5($de->snapshot()['bills']['law2']['status']==='filed','Germany federal government can initiate bill');
$us=new InstitutionalSimulation('scenario-us','US','2026-11-24');
wp5reject(fn()=> $us->decide('president','dissolve_lower_house',[],'diss1'),'US president has no congressional dissolution power');
wp5reject(fn()=> $us->decide('president','submit_bill',['title'=>'Presidential decree'],'law1'),'US president cannot file a congressional bill as legislator');
$us->decide('member_of_congress','submit_bill',['title'=>'Congressional legislation'],'law2');
wp5($us->snapshot()['bills']['law2']['initiated_by_role']==='member_of_congress','US Congress alone originates this simplified legislative action');

echo "WP05 country-specific institutional domain tests passed.\n";
