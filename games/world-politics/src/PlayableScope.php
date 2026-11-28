<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/InstitutionalRulebook.php';
require_once __DIR__.'/UniverseIdentityReference.php';
require_once __DIR__.'/CareerProgrammeAutonomy.php';
require_once __DIR__.'/UniverseLobby.php';

final class PlayableScope{
    public static function report():array{
        $countries=[];
        foreach(UniverseIdentityReference::catalogue() as $code=>$r){
            $institutional=InstitutionalRulebook::hasRules($code);
            $shared=in_array($code,UniverseLobby::DIPLOMATIC_COUNTRIES,true);
            $countries[]=['code'=>$code,'shared_diplomatic_slot'=>$shared,'shared_national_institution_clock'=>$shared&&$institutional,
                'shared_domestic_policy'=>$code==='FR','documentary_reference'=>true,'isolated_institutions'=>$institutional,
                'public_economic_career'=>$code==='FR','shared_multicountry_career'=>false,
                'classification'=>$code==='FR'?'french_public_alpha':($institutional?'isolated_institution_fixture':'documentary_only')];
        }
        return ['version'=>'wp130-playable-scope-v1','countries'=>$countries,
            'fictional_objectives'=>CareerProgrammeAutonomy::fictionalOptions(CareerProgrammeAutonomy::fictionalObjectives()),
            'official_eligible'=>false,'international_career_pool'=>false,'international_executive_slot_pool'=>true,'international_executive_slot_count'=>count(UniverseLobby::DIPLOMATIC_COUNTRIES),'economic_calibration'=>'experimental_uncalibrated',
            'public_release_qualified'=>false];
    }
}
