<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/HistoricalProgrammeCoverage.php';

/** Source-bound policy aliases for the provisional simulator. A historical
 * objective remains historical; reused model IDs are instruments, not authors.
 * Never import constitutional, ownership or foreign-policy actions into the
 * economic-proposal route without their own legal authority adapter. */
final class FrenchExpandedProgramme
{
    private const HISTORICAL=[
        'marine-le-pen'=>['hist2022-marine-le-pen-1','hist2022-marine-le-pen-2','pf2022-marine-le-pen-2'],
        'fabien-roussel'=>['hist2022-fabien-roussel-1','hist2022-fabien-roussel-2','pf2022-fabien-roussel-1','pf2022-fabien-roussel-2','pf2022-fabien-roussel-3','pf2022-fabien-roussel-4'],
        'philippe-poutou'=>['hist2022-philippe-poutou-1','hist2022-philippe-poutou-2','pf2022-philippe-poutou-1','pf2022-philippe-poutou-2','pf2022-philippe-poutou-3'],
        'nathalie-arthaud'=>['hist2022-nathalie-arthaud-1','hist2022-nathalie-arthaud-2','pf2022-nathalie-arthaud-1','pf2022-nathalie-arthaud-2'],
        'eric-zemmour'=>['hist2022-eric-zemmour-1','hist2022-eric-zemmour-2','pf2022-eric-zemmour-1','pf2022-eric-zemmour-2','pf2022-eric-zemmour-3']
    ];
    private const COLLECTIVE=[
        'manuel-bompard'=>['pf2027-jean-luc-melenchon-smic','pf2027-jean-luc-melenchon-pension','pf2027-lfi-public-housing','pf2027-lfi-renovation','pf2027-lfi-public-index','pf2027-lfi-eldercare-capacity'],
        'mathilde-panot'=>['pf2027-jean-luc-melenchon-smic','pf2027-jean-luc-melenchon-pension','pf2027-lfi-public-housing','pf2027-lfi-renovation','pf2027-lfi-public-index','pf2027-lfi-eldercare-capacity']
    ];
    public static function policyIds():array{
        return array_values(array_unique(array_merge(['pf2022-jean-lassalle-1'],...array_values(self::HISTORICAL),...array_values(self::COLLECTIVE))));
    }
    public static function choices(array $base,array $reviewed):array{
        $base=array_column($base,null,'base');$reviewed=array_column($reviewed,null,'id');
        $hist=array_column(HistoricalCandidateReference::pin()['pack']['candidates'],null,'id');
        $coverage=HistoricalProgrammeCoverage::report()['candidates'];$out=[];
        $add=function(string $person,array $rows,string $period,string $kind,array $extra=[])use(&$out,$base){
            $b=$base['campaign2027-'.$person]??throw new \DomainException('Unknown public profile');
            $ids=array_column($rows,'id');
            if($ids===[]||count(array_unique($ids))!==count($ids))throw new \DomainException('Duplicate or empty mapped profile');
            foreach($rows as $r)if(SocialProgramme::definition($r['id'])===null||!in_array($r['id'],self::policyIds(),true))throw new \DomainException('Unsupported instrument');
            PresidentialProgramme::compileProfile($ids);
            $id=$b['base'].'-v2-public';
            $evidence=['source_period'=>$period,'attribution'=>$kind,'mapped_programme'=>$rows,'additional_sources'=>$extra,'previous_edition'=>$b['id']];
            $out[]=array_replace($b,[
                'id'=>$id,'reference_id'=>$id,'measure_ids'=>$ids,'reviewed_measure_count'=>count($ids),
                'mapped_programme'=>$rows,'mapped_evidence'=>$evidence,'source'=>$rows[0]['sources'][0],
                'document_sha256'=>hash('sha256',json_encode([$id,$b['document_sha256'],$evidence],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),
                'autonomy_scope'=>'reviewed_mapped_subset','availability_code'=>'provisional_available',
                'availability_label_fr'=>'Programme provisoire — objectifs documentés modélisés'
            ]);
        };
        foreach(self::HISTORICAL as $person=>$ids){
            $rows=[];
            foreach($hist[$person]['programme'] as $m)$rows[$m['id']]=$m;
            foreach($coverage[$person]['additional_executable'] as $m){
                if($m['execution_status']!=='primary_quantitative_proxy')continue;
                $rows[$m['id']]=['id'=>$m['id'],'domain'=>$m['domain'],'proposal'=>$m['summary'],'sources'=>[$m['source']]];
            }
            $selected=[];foreach($ids as $id)$selected[]=$rows[$id]??throw new \DomainException('Historical mapping missing');
            $add($person,$selected,'2022-04-10','historical_campaign_archive');
        }
        foreach(self::COLLECTIVE as $person=>$ids){
            $rows=[];
            foreach($ids as $id){
                $m=$reviewed[$id]??throw new \DomainException('Collective programme missing');$def=SocialProgramme::target2027()[$id];
                if($m['publisher']!=='La France insoumise'||$m['target']!==$def['target'])throw new \DomainException('Collective target mismatch');
                $rows[]=['id'=>$id,'domain'=>$def['domain'],'proposal'=>json_encode($def['target'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'sources'=>[$m['source']]];
            }
            $add($person,$rows,'2026-11-27','party_platform_not_personal_manifesto',['https://lafranceinsoumise.fr/lfi-comment-ca-marche/','https://lafranceinsoumise.fr/2026/09/09/fete-de-lhumanite-2026-lfi/','https://lafranceinsoumise.fr/']);
        }
        // The existing fuel VAT model is a restricted, reusable instrument.
        // Only the motor-fuel component is mapped, not gas or electricity.
        // The original model ID is retained for binary profile compatibility.
        $id='pf2022-jean-lassalle-1';
        if(SocialProgramme::definition($id)['target']!==['rate_bps'=>550])throw new \DomainException('Fuel VAT target drift');
        $add('jordan-bardella',[['id'=>$id,'domain'=>'tax','proposal'=>'TVA carburants à 5,5 % — composante carburants uniquement, modèle partagé; autorisation juridique distincte requise.','sources'=>['https://www.lemonde.fr/politique/live/2024/06/17/en-direct-elections-legislatives-2024-eric-ciotti-assure-que-parmi-les-62-candidats-investis-dans-le-cadre-de-son-accord-avec-le-rn-4-ou-5-sont-des-deputes-republicains-sortants_6240873_823448.html','https://www.institutmontaigne.org/legislatives-2024/rassemblement-national/baisser-la-tva-de-20-a-55-pour-les-carburants-lelectricite-le-gaz-et-le-fioul-domestique/']]],'2024-06-17','reported_legislative_platform_shared_instrument',['https://factuel.afp.com/doc.afp.com.34YN2DW']);
        return $out;
    }
}
