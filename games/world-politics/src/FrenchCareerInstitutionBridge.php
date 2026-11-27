<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CareerInstitutionLedger.php';
require_once __DIR__.'/InstitutionalSimulation.php';

/** Reuses committed, restored institution state rather than trusting a client flag.
 * Only three reviewed bridge operations; other transitions require external attestation.
 */
final class FrenchCareerInstitutionBridge
{
    public static function supported(string $op):bool{return in_array($op,['dissolve_assembly','restore_assembly','constitution_revision'],true);}
    public static function evidence(array $institution,int $revision,array $event,string $storedSha):string{
        $sim=InstitutionalSimulation::restore($institution);$s=$sim->snapshot();
        CareerInstitutionLedger::check($revision>=0&&hash_equals($storedSha,$sim->hash())&&$s['world_id']===$event['world']&&$s['country_code']==='FR'&&$event['country']==='FR'&&$s['day']===$event['day'],'institution evidence scope or hash');
        $op=$event['operation'];$day=$event['day'];
        if($op==='dissolve_assembly'){
            CareerInstitutionLedger::check($s['legislature']==='dissolved'&&$s['last_dissolution']===$day,'no committed dissolution');
        }elseif($op==='restore_assembly'){
            CareerInstitutionLedger::check($s['legislature']==='sitting'&&$s['last_election']===$day&&$s['last_dissolution']!==null,'no committed subsequent election');
            // Legacy poll-only fixtures are not certified seat allocations.
            CareerInstitutionLedger::check(isset($s['legislation']['responsibility']['lifecycle']['election_digest'])&&isset($s['recurring_elections'])&&$s['recurring_elections']['last_dissolution_election']===$day,'no installed election result');
        }elseif($op==='constitution_revision'){
            CareerInstitutionLedger::check(isset($s['revision'])&&json_encode($s['revision'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)===$event['payload']['revision_state_json'],'revision differs from committed institution');
        }else throw new \DomainException('No reviewed French institution bridge');
        return CareerInstitutionLedger::digest(['version'=>'wp119-fr-institution-bridge-v1','world'=>$event['world'],'country'=>'FR','day'=>$day,'institution_revision'=>$revision,'institution_sha'=>$storedSha,'operation'=>$op,'payload'=>$event['payload']]);
    }
}
