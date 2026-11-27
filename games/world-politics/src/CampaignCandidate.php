<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/HistoricalCandidateReference.php';
require_once __DIR__.'/CampaignLifecycle.php';
require_once __DIR__.'/PresidentialProgramme.php';

/** Choose the player-controlled identity at creation, not an electoral claim.
 * The immutable simulation character ID and all score rules are unchanged.
 * Programme text is a roleplay record; only actual authorized policy commands
 * can affect the economy, institutions or simulated electorate. */
final class CampaignCandidate
{
    public const VERSION='wp75-campaign-candidate-v1';
    public static function options():array
    {
        $ref=HistoricalCandidateReference::report(HistoricalCandidateReference::pin());
        return ['version'=>self::VERSION,'scenario'=>'fr-investiture-2027-v1','selection_at_creation'=>true,
            'fictional_template'=>PoliticalProfileTemplate::definition(),
            'historical_choices'=>array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name'],'as_of'=>$p['as_of'],'sources'=>$p['sources']],$ref['roster_compatible_profiles']),
            'historical_reference_sha'=>$ref['reference_sha'],'historical_programme_scope'=>$ref['programme_partial_scope'],'historical_measure_count'=>$ref['curated_measure_count'],'historical_selection_is_alternate_history'=>true,
            'future_eligibility_certified'=>false,'programme_mechanics_enabled'=>false,
            'managed_universe_explicit_programme_catalog'=>PresidentialProgramme::measureCatalog(),
            'historical_measures_automatically_imported'=>false,'changes_score_rules'=>false];
    }
    public static function normalize(mixed $input):array
    {
        if(!is_array($input)||array_is_list($input)||!is_string($input['type']??null))throw new \InvalidArgumentException('Invalid candidate selection');
        if($input['type']==='historical'){
            $keys=array_keys($input);sort($keys);
            if($keys!==['reference_id','type']||!is_string($input['reference_id']))throw new \InvalidArgumentException('Invalid historical candidate fields');
            $ref=HistoricalCandidateReference::report(HistoricalCandidateReference::pin());
            $person=null;
            foreach($ref['roster_compatible_profiles'] as $p)if($p['id']===$input['reference_id']){$person=$p;break;}
            if($person===null)throw new \DomainException('Unknown historical identity');
            $person['id']=CampaignLifecycle::PLAYER;
            $person['biography'].=' Identité utilisée dans une histoire alternative fictive ; ne constitue pas une candidature attestée en 2027.';
        }elseif($input['type']==='fictional'){
            foreach(array_keys($input) as $k)if(!in_array($k,['type','name','mode','biography','programme','profile'],true))throw new \InvalidArgumentException('Unknown fictional candidate field');
            unset($input['type']);$input['id']=CampaignLifecycle::PLAYER;
            $person=PoliticalProfileTemplate::normalizeFictional($input,'FR');
        }else throw new \InvalidArgumentException('Unknown candidate type');
        $roster=new PoliticalRoster('candidate-validation','FR',['prime_minister']);
        $roster->addPerson($person);$roster->setHeadOfState(CampaignLifecycle::PLAYER);
        return $person;
    }
}
