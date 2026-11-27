<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/SocialProgramme.php';
require_once __DIR__.'/FrenchExpandedProgramme.php';

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
    /** Explicitly reviewed proposal capabilities. The PM may coordinate
     * every mapped objective; the relevant portfolio may propose only its
     * own domain. Housing, wages and retirement remain PM-only rather than
     * assigning them to a nonexistent labor/housing ministry. */
    public static function portfolio(string $id):?string{
        if($id==='education-reform')return 'education';
        if($id==='administration-report')return null;
        $definition=SocialProgramme::target2027()[$id]??((in_array($id,FrenchExpandedProgramme::policyIds(),true)||FrenchV1Policy::isPolicy($id))?SocialProgramme::definition($id):null);
        if($definition===null)throw new \DomainException('Policy not mapped to career powers');
        return match($definition['domain']){
            'education'=>'education','eldercare'=>'health','tax','industry'=>'economy',
            'wages','retirement','housing','employment','social','public_services','agriculture','culture','transport','environment'=>null,
            default=>throw new \DomainException('Policy portfolio not reviewed')
        };
    }
    public static function permitted(string $office,string $id):bool{
        $portfolio=self::portfolio($id);
        return $office==='prime-minister'||($portfolio!==null&&$office==='minister-'.$portfolio);
    }
    public static function objectives(array $career,bool $expanded=false):array{
        if($expanded&&isset($career['person_reference'])){
            require_once __DIR__.'/CampaignCandidate.php';
            if(isset($career['programme_edition'])){
                $v=CampaignCandidate::editionEvidence($career['programme_edition']);
                if(is_array($v)&&($v['base']??null)===$career['person_reference']&&isset($v['document_sha256'],$career['programme_sha'])&&hash_equals($v['document_sha256'],$career['programme_sha']))
                    return $v['measure_ids'];
                return [];
            }
        }
        if(($career['person_reference']??null)==='campaign2027-marine-tondelier'&&($career['programme_edition']??null)==='campaign2027-marine-tondelier-v1-public')
            return ['pf2027-ecologistes-smic','pf2027-ecologistes-renovation'];
        if(isset($career['person_reference']))return self::DOCUMENTED[$career['person_reference']]??[];
        if(($career['controller']??null)==='ai'||$career['account']===null)return [];
        return $career['fictional_profile']['measure_ids']??[];
    }
    /** Only the PM or explicitly competent minister may originate supported
     * requests. No president receives PM powers, no law is enacted. */
    public static function next(array $state,string $day,array $policies,array $catalog,array $humanActed=[]):?array{
        if($state['country']!=='FR'||$state['mode']!=='career')return null;
        foreach(['prime-minister','minister-economy','minister-education','minister-health'] as $office){
            $mid=$state['active'][$office]??null;if($mid===null)continue;
            $m=$state['mandates'][$mid];$career=$state['careers'][$m['career']];
            $ability=$office==='prime-minister'?'government.coordinate':'ministry.'.substr($office,9).'.propose';
            if($m['end']!==null||$m['interim']||$m['start']>$day||$m['expires']<=$day||!in_array($ability,$m['powers'],true)||isset($humanActed[$m['career']]))continue;
            foreach(self::objectives($career,isset($catalog['hist2022-fabien-roussel-1'])) as $id){
                if(!isset($catalog[$id])||isset($policies[$id]))continue;
                if(!in_array($id,['education-reform','administration-report'],true)&&!isset(SocialProgramme::target2027()[$id])&&!in_array($id,FrenchExpandedProgramme::policyIds(),true)&&!FrenchV1Policy::isPolicy($id))continue;
                if(!self::permitted($office,$id))continue;
                $effective=SocialProgramme::definition($id)!==null?SocialProgramme::effectiveDay($id,$day):
                    (new \DateTimeImmutable($day))->modify($id==='education-reform'?'+120 days':'+14 days')->format('Y-m-d');
                if(SocialProgramme::sameEffectiveFamily($id,$effective,$policies))continue;
                return ['mandate'=>$mid,'generation'=>$m['generation'],'career'=>$m['career'],'policy_id'=>$id,'effective_day'=>$effective];
            }
        }
        return null;
    }
}
