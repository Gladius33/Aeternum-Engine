<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/HistoricalCandidateReference.php';
require_once __DIR__.'/PresidentialProgramme.php';

/** Explicitly separate historical claims from the executable catalogue. */
final class HistoricalProgrammeCoverage
{
    public static function report():array
    {
        $dir=__DIR__.'/../data/';
        $sources=json_decode(file_get_contents($dir.'wp114-social-source-register.json'),true,64,JSON_THROW_ON_ERROR);
        $p=json_decode(file_get_contents($dir.'wp114-primary-manifesto-inventory.json'),true,64,JSON_THROW_ON_ERROR);
        $old=HistoricalCandidateReference::pin()['pack'];
        if($p['schema']!=='wp114-primary-manifesto-inventory-v1'||$p['election_day']!==$old['poll_day'])throw new \DomainException('Invalid primary source inventory');
        $register=$sources['sources'][0];
        if($sources['schema']!=='wp114-social-source-register-v1'||$register['id']!=='cnccep-first-round'
            ||count($register['documents'])!==count($old['candidates']))throw new \DomainException('Invalid historical source register');
        $docs=[];$urls=[];
        foreach($register['documents'] as $doc){
            $candidate=$doc['candidate'];$url=$doc['url'];
            if(isset($docs[$candidate])||isset($urls[$url])||!is_int($doc['pages'])||$doc['pages']<1
                ||!str_starts_with($url,'https://www.cnccep.fr/pdfs/')||($doc['document_access_verified']??false)!==true
                ||($doc['complete_manifesto_extracted']??null)!==false)throw new \DomainException('Invalid or duplicate primary source document');
            $docs[$candidate]=$doc;$urls[$url]=true;
        }
        $expected=array_column($old['candidates'],'id');$listed=$register['candidates'];$documentIds=array_keys($docs);
        sort($expected);sort($listed);sort($documentIds);
        if($expected!==$listed||$expected!==$documentIds)throw new \DomainException('Historical candidate source mismatch');
        $report=[];$seen=[];$mapped=0;$archivedCount=0;
        foreach($old['candidates'] as $c){
            $id=$c['id'];$doc=$docs[$id]??throw new \DomainException('Missing candidate primary source');
            $report[$id]=['source'=>$doc['url'],'pages'=>$doc['pages'],'archived_executable_ids'=>array_column($c['programme'],'id'),'additional_unmapped'=>[],'additional_executable'=>[],'exhaustive'=>false];
            if($doc['archived_measure_ids']!==array_column($c['programme'],'id'))throw new \DomainException('Archived measure provenance mismatch');
            foreach($c['programme'] as $m){
                if(PresidentialProgramme::measureSupport($m['id'])['status']!=='historical_social_proxy')throw new \DomainException('Lost existing mapping');
                ++$archivedCount;
            }
        }
        foreach($p['measures'] as $m){
            $id=$m['candidate'];$doc=$docs[$id]??null;
            if($doc===null||!isset($report[$id])||$m['source']!==$doc['url']||!is_int($m['page'])||$m['page']<1||$m['page']>$doc['pages']
                ||!preg_match('/^pf2022-[a-z0-9-]+$/D',$m['id'])||isset($seen[$m['id']])||$m['execution_status']!=='unmapped_requires_review'
                ||!in_array(PresidentialProgramme::measureSupport($m['id'])['status'],['no_reviewed_mapping','primary_quantitative_proxy','primary_constitutional_route'],true))throw new \DomainException('Invalid or ambiguous primary-source mapping');
            $seen[$m['id']]=true;$support=PresidentialProgramme::measureSupport($m['id']);
            if(in_array($support['status'],['primary_quantitative_proxy','primary_constitutional_route'],true)){
                if($support['mapping']['target']!==$m['target'])throw new \DomainException('Primary target drift');
                $report[$id]['additional_executable'][]=array_replace($m,['execution_status'=>$support['status'],'mapping'=>$support['mapping']]);++$mapped;
            }else $report[$id]['additional_unmapped'][]=$m;
        }
        foreach($report as $c)if($c['additional_unmapped']===[]&&$c['additional_executable']===[])throw new \DomainException('Missing candidate review');
        if(array_keys($seen)!==$sources['additional_inventory']['measure_ids']||count($seen)!==$sources['additional_inventory']['count'])throw new \DomainException('Inventory mismatch');
        return ['classification'=>'historical_coverage_not_future_manifesto','candidates'=>$report,'executable_archived_count'=>$archivedCount,'additional_executable_count'=>$mapped,'additional_unmapped_count'=>count($seen)-$mapped,'selected_inventory_complete'=>$mapped===count($seen),'exhaustive'=>false,'wp114_closure_qualified'=>false];
    }
}
