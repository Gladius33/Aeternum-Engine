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
        $docs=array_column($sources['sources'][0]['documents'],null,'candidate');$report=[];$seen=[];
        foreach($old['candidates'] as $c){
            $id=$c['id'];$doc=$docs[$id]??throw new \DomainException('Missing candidate primary source');
            $report[$id]=['source'=>$doc['url'],'pages'=>$doc['pages'],'archived_executable_ids'=>array_column($c['programme'],'id'),'additional_unmapped'=>[],'exhaustive'=>false];
            foreach($c['programme'] as $m)if(PresidentialProgramme::measureSupport($m['id'])['status']!=='historical_social_proxy')throw new \DomainException('Lost existing mapping');
        }
        foreach($p['measures'] as $m){
            $id=$m['candidate'];$doc=$docs[$id]??null;
            if($doc===null||!isset($report[$id])||$m['source']!==$doc['url']||!is_int($m['page'])||$m['page']<1||$m['page']>$doc['pages']
                ||!preg_match('/^pf2022-[a-z0-9-]+$/D',$m['id'])||isset($seen[$m['id']])||$m['execution_status']!=='unmapped_requires_review'
                ||PresidentialProgramme::measureSupport($m['id'])['status']!=='no_reviewed_mapping')throw new \DomainException('Invalid or ambiguous primary-source mapping');
            $seen[$m['id']]=true;$report[$id]['additional_unmapped'][]=$m;
        }
        foreach($report as $c)if($c['additional_unmapped']===[])throw new \DomainException('Missing candidate review');
        if(array_keys($seen)!==$sources['additional_inventory']['measure_ids']||count($seen)!==$sources['additional_inventory']['count'])throw new \DomainException('Inventory mismatch');
        return ['classification'=>'historical_coverage_not_future_manifesto','candidates'=>$report,'executable_archived_count'=>24,'additional_unmapped_count'=>count($seen),'exhaustive'=>false,'wp114_closure_qualified'=>false];
    }
}
