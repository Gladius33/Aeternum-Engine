<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/SocialProgramme.php';

/** Bounded, proposal-only career autonomy. No inference from a name or free text.
 * The frozen WP114 mappings describe simulated proposals, not real intentions. */
final class CareerProgrammeAutonomy
{
    public const VERSION='wp119-career-proposals-v1';
    private const DOCUMENTED=[
        'campaign2027-jean-luc-melenchon'=>['pf2027-jean-luc-melenchon-smic','pf2027-jean-luc-melenchon-pension','pf2027-lfi-public-housing','pf2027-lfi-renovation','pf2027-lfi-public-index','pf2027-lfi-eldercare-capacity'],
        'campaign2027-bruno-retailleau'=>['pf2027-retailleau-contributions','pf2027-retailleau-overtime'],
        'campaign2027-nicolas-dupont-aignan'=>['pf2027-dlf-production-tax','pf2027-dlf-industrial-grants'],
        'campaign2027-edouard-philippe'=>['pf2027-philippe-teacher-pay'],
    ];
    public static function normalizeFictional(array $p,string $country):array{
        foreach(array_keys($p) as $key)CareerInstitutionLedger::check(in_array($key,['name','biography','measure_ids'],true),'unknown fictional profile field');
        $name=$p['name']??null;$bio=$p['biography']??'';$ids=$p['measure_ids']??[];
        CareerInstitutionLedger::check(is_string($name)&&trim($name)!==''&&strlen($name)<=160&&!preg_match('/[\x00-\x1f\x7f]/',$name)&&
            is_string($bio)&&strlen($bio)<=2000&&!preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$bio)&&is_array($ids)&&array_is_list($ids)&&count($ids)<=15,'invalid fictional profile');
        CareerInstitutionLedger::check($country==='FR'||$ids===[],'no reviewed foreign programme mapping');
        $catalog=['education-reform'=>true,'administration-report'=>true]+SocialProgramme::target2027();
        $seen=[];foreach($ids as $id){CareerInstitutionLedger::check(is_string($id)&&isset($catalog[$id])&&!isset($seen[$id]),'unmapped or duplicate objective');$seen[$id]=true;}
        return ['name'=>trim($name),'biography'=>$bio,'measure_ids'=>$ids];
    }
    public static function objectives(array $career):array{
        if(isset($career['person_reference']))return self::DOCUMENTED[$career['person_reference']]??[];
        if(($career['controller']??null)==='ai'||$career['account']===null)return [];
        return $career['fictional_profile']['measure_ids']??[];
    }
    /** Only an active PM or education minister may originate the supported
     * requests. No president receives PM powers, no law is enacted. */
    public static function next(array $state,string $day,array $policies,array $catalog,array $humanActed=[]):?array{
        if($state['country']!=='FR'||$state['mode']!=='career')return null;
        foreach(['prime-minister','minister-education'] as $office){
            $mid=$state['active'][$office]??null;if($mid===null)continue;
            $m=$state['mandates'][$mid];$career=$state['careers'][$m['career']];
            $ability=$office==='prime-minister'?'government.coordinate':'ministry.education.propose';
            if($m['end']!==null||$m['interim']||$m['start']>$day||$m['expires']<=$day||!in_array($ability,$m['powers'],true)||isset($humanActed[$m['career']]))continue;
            foreach(self::objectives($career) as $id){
                if(!isset($catalog[$id])||isset($policies[$id]))continue;
                if($office==='minister-education'&&!in_array($id,['education-reform','pf2027-philippe-teacher-pay'],true))continue;
                if(!in_array($id,['education-reform','administration-report'],true)&&!isset(SocialProgramme::target2027()[$id]))continue;
                $effective=isset(SocialProgramme::target2027()[$id])?SocialProgramme::effectiveDay($id,$day):
                    (new \DateTimeImmutable($day))->modify($id==='education-reform'?'+120 days':'+14 days')->format('Y-m-d');
                if(SocialProgramme::sameEffectiveFamily($id,$effective,$policies))continue;
                return ['mandate'=>$mid,'generation'=>$m['generation'],'career'=>$m['career'],'policy_id'=>$id,'effective_day'=>$effective];
            }
        }
        return null;
    }
}
