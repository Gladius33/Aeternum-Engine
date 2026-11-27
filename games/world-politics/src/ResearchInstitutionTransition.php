<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Research-only aggregate observation, not a transition command or a
 * mechanism for acquiring power. All values describe a supplied hypothetical
 * state; no real targets, tactics, optimized actions or competitive rewards. */
final class ResearchInstitutionTransition
{
    public const VERSION='wp119-research-institution-transition-v1';
    private const FORMS=['presidential_republic','semi_presidential_republic','parliamentary_republic','constitutional_monarchy','absolute_monarchy','collective_executive','provisional_government','corporatist_government','theocracy','technocracy','single_party_government','military_government','direct_democracy','other','unknown'];
    private const TERRITORY=['unitary','federal','confederal','other','unknown'];
    public static function digest(array $e):string{
        return CareerInstitutionLedger::digest(['kind'=>self::VERSION,'world'=>$e['world'],'country'=>$e['country'],'day'=>$e['day'],'revision'=>$e['world_revision'],'payload'=>$e['payload']]);
    }
    public static function apply(array &$s,array $e):void{
        $p=$e['payload'];
        CareerInstitutionLedger::fields($p,['version','previous_sha','constitutional_order','effective_order','territorial_form','assembly_status','senate_status','legality','recognition','service_continuity_bps','classification']);
        CareerInstitutionLedger::check($s['mode']==='research'&&$s['active']===[]&&$s['careers']===[]&&$s['mandates']===[]&&$s['series']===[]&&
            $e['actor']==='research-clerk'&&$p['version']===self::VERSION&&$e['evidence_sha']===self::digest($e),'research isolation');
        $previous=$s['research_transition']??null;
        CareerInstitutionLedger::check($p['previous_sha']===CareerInstitutionLedger::digest($previous),'research observation predecessor');
        foreach(['constitutional_order','effective_order'] as $key)
            CareerInstitutionLedger::check(in_array($p[$key],self::FORMS,true),'unknown government form');
        CareerInstitutionLedger::check(in_array($p['territorial_form'],self::TERRITORY,true),'unknown territorial form');
        foreach(['assembly_status','senate_status'] as $key)
            CareerInstitutionLedger::check(in_array($p[$key],['active','legally_dissolved','disrupted','suspended_de_facto','abolished','restored','absent','unknown'],true),'unknown chamber observation');
        CareerInstitutionLedger::check(in_array($p['legality'],['recognized','disputed','unverified'],true)&&
            in_array($p['recognition'],['recognized','mixed','unrecognized','unknown'],true)&&
            ($p['service_continuity_bps']===null||(is_int($p['service_continuity_bps'])&&$p['service_continuity_bps']>=0&&$p['service_continuity_bps']<=10000))&&
            $p['classification']==='counterfactual_aggregate_observation','invalid research observation');
        // A prior observation is not authority. Preserve the original legal
        // reference, state identifier, country and all other domain state.
        $s['research_transition']=['version'=>self::VERSION,'world'=>$s['world'],'state_id'=>$s['state_id'],'country'=>$s['country'],
            'day'=>$e['day'],'ordinal'=>($previous['ordinal']??0)+1,'previous_sha'=>$p['previous_sha'],
            'constitutional_reference_sha'=>$s['constitution_sha'],
            'constitutional_order'=>$p['constitutional_order'],'effective_order'=>$p['effective_order'],'territorial_form'=>$p['territorial_form'],
            'assembly_status'=>$p['assembly_status'],'senate_status'=>$p['senate_status'],'legality'=>$p['legality'],'recognition'=>$p['recognition'],
            'service_continuity_bps'=>$p['service_continuity_bps'],'classification'=>$p['classification'],
            'runtime_authority'=>false,'rankable'=>false,'economic_effect'=>false];
    }
}
