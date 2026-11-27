<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrenchV1Policy.php';

final class FrenchV1QuantitativeEditions
{
    public static function corporatistChoice(array $base):array{
        $b=array_column($base,null,'base')['campaign2027-yvan-benedetti'];
        $id='frv1-benedetti-branch-wage';$d=FrenchV1Policy::definitions()[$id];
        $source='https://www.les-nationalistes.com/retraites-nos-solutions/';
        $rows=[['id'=>$id,'domain'=>'employment','proposal'=>'Organisation professionnelle en corporations ; variante de simulation précisée par le concepteur : minima de branche négociés puis validés par l’État.','sources'=>[$source]]];
        $evidence=['source_period'=>'2023-02','attribution'=>'historical_corporation_statement_and_explicit_scenario_specification','mapped_programme'=>$rows,'previous_edition'=>$b['id'],'targets'=>[$id=>$d],
            'source_support'=>'Corporations réunissant employeurs, cadres et employés et gérant leurs conditions de travail. Les minima, le calendrier, les critères de vote et le rôle de validation sont des paramètres de jeu, pas une citation ni un chiffrage personnel.'];
        $edition=$b['base'].'-v3-public';PresidentialProgramme::compileProfile([$id]);
        return array_replace($b,['id'=>$edition,'reference_id'=>$edition,'measure_ids'=>[$id],'reviewed_measure_count'=>1,'source'=>$source,
            'mapped_programme'=>$rows,'mapped_evidence'=>$evidence,'as_of'=>'2026-11-28',
            'document_sha256'=>hash('sha256',json_encode([$edition,$b['document_sha256'],$evidence],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),
            'autonomy_scope'=>'reviewed_mapped_subset','availability_code'=>'provisional_available','availability_label_fr'=>'Programme provisoire — régime salarial de branche']);
    }
    public static function choices(array $base):array{
        $p=json_decode(file_get_contents(__DIR__.'/../data/fr-public-policy-evidence-v3.json'),true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!=='fr-public-policy-evidence-v3'||$p['observed_on']!=='2026-11-28'||count($p['records'])!==10)throw new \DomainException('Invalid French policy evidence');
        $base=array_column($base,null,'base');$defs=FrenchV1Policy::definitions();$seen=[];$out=[];
        foreach($p['records'] as $r){
            $b=$base['campaign2027-'.$r['person']]??null;
            if($b===null||isset($seen[$r['person']])||$r['period']===''||$r['attribution']===''||$r['sources']===[]||$r['measures']===[])throw new \DomainException('Invalid French profile evidence');
            $seen[$r['person']]=true;
            foreach($r['sources'] as $url)if(!is_string($url)||!str_starts_with($url,'https://'))throw new \DomainException('Invalid source URL');
            $rows=[];$targets=[];
            foreach($r['measures'] as $m){
                $d=$defs[$m['id']]??null;if($d===null||isset($targets[$m['id']])||$m['summary']==='')throw new \DomainException('Unsupported public target');
                $targets[$m['id']]=$d;
                $rows[]=['id'=>$m['id'],'domain'=>$d['domain'],'proposal'=>$m['summary'],'sources'=>$r['sources']];
            }
            $ids=array_column($rows,'id');PresidentialProgramme::compileProfile($ids);
            $id=$b['base'].'-v3-public';
            $evidence=['source_period'=>$r['period'],'attribution'=>$r['attribution'],'mapped_programme'=>$rows,'previous_edition'=>$b['id'],'targets'=>$targets];
            $out[]=array_replace($b,['id'=>$id,'reference_id'=>$id,'measure_ids'=>$ids,'reviewed_measure_count'=>count($ids),'source'=>$r['sources'][0],
                'mapped_programme'=>$rows,'mapped_evidence'=>$evidence,'as_of'=>$p['observed_on'],
                'document_sha256'=>hash('sha256',json_encode([$id,$b['document_sha256'],$evidence],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),
                'autonomy_scope'=>'reviewed_mapped_subset','availability_code'=>'provisional_available',
                'availability_label_fr'=>'Programme provisoire — objectifs documentés modélisés']);
        }
        return $out;
    }
}
