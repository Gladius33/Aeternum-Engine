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
    /** Source discovery snapshot for the target election. No source is a
     * certified candidacy, completed extraction or executable programme. */
    public static function programme2027Reference():array
    {
        return ['election_year'=>2027,'scenario_start'=>'2027-05-14','observed_on'=>'2026-11-27',
            'classification'=>'provisional_source_discovery_not_final_ballot',
            'final_candidate_list_verified'=>false,'complete_programmes_verified'=>false,
            'historical_2022_is_2027_programme'=>false,'historical_2022_automatic_import'=>false,
            'sources'=>[
                ['id'=>'fr2027-les-ecologistes','publisher'=>'Les Écologistes','kind'=>'party_programme',
                    'url'=>'https://lesecologistes.fr/pages/6ImK65GKUnvibm33WGkjkj/projet-2027'],
                ['id'=>'fr2027-debout-la-france','publisher'=>'Debout la France','kind'=>'partial_project_announcement',
                    'url'=>'https://www.debout-la-france.fr/evenements/liste/?eventDisplay=past&tribe-bar-date=2055-10-01'],
                ['id'=>'fr2027-retailleau','publisher'=>'Bruno Retailleau','kind'=>'campaign_project',
                    'url'=>'https://www.avecretailleau.fr/projet/faq'],
                ['id'=>'fr2027-place-publique','publisher'=>'Place publique','kind'=>'partial_project_announcement',
                    'url'=>'https://place-publique.eu/'],
                ['id'=>'fr2027-data-gouv-swipe','publisher'=>'Swipe Your President','kind'=>'independent_discovery_index',
                    'url'=>'https://www.data.gouv.fr/datasets/programmes-presidentielle-2027-qui-a-publie-son-programme-quand-et-les-mesures-par-theme-swipe-your-president'],
                ['id'=>'fr2027-data-gouv-ratio','publisher'=>'Ratio 2027','kind'=>'independent_discovery_index',
                    'url'=>'https://www.data.gouv.fr/datasets/programmes-de-lelection-presidentielle-francaise-2027'],
            ],'extraction_complete'=>false,'executable_measure_count'=>0,
            'selection_enabled'=>false,'requires_primary_source_review'=>true];
    }
    public static function options():array
    {
        $ref=HistoricalCandidateReference::report(HistoricalCandidateReference::pin());
        return ['version'=>self::VERSION,'scenario'=>'fr-investiture-2027-v1','selection_at_creation'=>true,
            'fictional_template'=>PoliticalProfileTemplate::definition(),
            'historical_choices'=>array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name'],'as_of'=>$p['as_of'],'sources'=>$p['sources']],$ref['roster_compatible_profiles']),
            'historical_reference_sha'=>$ref['reference_sha'],'historical_programme_scope'=>$ref['programme_partial_scope'],'historical_measure_count'=>$ref['curated_measure_count'],'historical_selection_is_alternate_history'=>true,
            'target_election_year'=>2027,'historical_reference_election_year'=>2022,'historical_choices_are_2027_candidates'=>false,
            'programme_2027_reference'=>self::programme2027Reference(),
            'future_eligibility_certified'=>false,'programme_mechanics_enabled'=>false,
            'managed_universe_explicit_programme_catalog'=>PresidentialProgramme::measureCatalog(),
            'historical_social_measure_catalog'=>SocialProgramme::definitions(),'historical_measures_automatically_imported'=>false,'changes_score_rules'=>false];
    }
    /** Managed opening identity only; historical programme provenance is
     * extended without changing the immutable earlier reference pack. */
    public static function normalizeManaged(mixed $input):array
    {
        $person=self::normalize($input);
        if($input['type']==='historical'){
            require_once __DIR__.'/HistoricalProgrammeCoverage.php';
            $id=substr($input['reference_id'],5);$r=HistoricalProgrammeCoverage::report()['candidates'][$id]??throw new \DomainException('Missing candidate coverage');
            foreach([...$r['additional_executable'],...$r['additional_unmapped']] as $m){
                $person['programme'][]=['id'=>$m['id'],'domain'=>$m['domain'],'proposal'=>$m['summary'],'sources'=>[$m['source']]];
            }
        }
        $roster=new PoliticalRoster('candidate-validation','FR',['prime_minister']);
        $roster->addPerson($person);$roster->setHeadOfState(CampaignLifecycle::PLAYER);
        return $person;
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
