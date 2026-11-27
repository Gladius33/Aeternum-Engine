<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ProgrammeSourceInventory.php';

/** Catalogue supplement, not a ballot, appointment or programme release.
 * Old world rosters remain pinned and the election capacity unchanged. */
final class FrenchPoliticalDirectory
{
    public const VERSION='wp-fr-political-person-directory-v1';
    public static function supplementary():array{
        $raw=file_get_contents(__DIR__.'/../data/wp-fr-political-person-supplement-v1.json');
        $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!=='wp-fr-political-person-supplement-v1'||$p['country']!=='FR'||$p['exhaustive']!==false||$p['checked_on']!=='2026-11-27'||!array_is_list($p['people']))
            throw new \DomainException('Invalid political reference edition');
        $kinds=['political_movement_observation','european_parliament_reference','national_assembly_reference','historical_presidential_reference','senate_reference'];
        $known=ProgrammeSourceInventory::report()['people'];$seen=[];$out=[];
        foreach($p['people'] as $r){
            $id=$r['id']??'';
            if(!is_string($id)||!preg_match('/^real-[a-z0-9-]+$/D',$id)||isset($seen[$id])||isset($known[substr($id,5)])
                ||!is_string($r['name']??null)||trim($r['name'])===''||!in_array($r['source_kind']??null,$kinds,true)
                ||!is_string($r['source_url']??null)||!str_starts_with($r['source_url'],'https://')
                ||!is_string($r['source_period']??null)||$r['source_period']===''||$r['programme_document_sha256']!==null
                ||$r['programme_complete']!==false||$r['implementation_complete']!==false||$r['certified_2027_candidate']!==false)
                throw new \DomainException('Invalid supplementary political identity');
            $seen[$id]=true;
            $out[]=$r+['reference_id'=>'political-fr-'.substr($id,5),'country'=>'FR','checked_on'=>$p['checked_on'],
                'playable'=>false,'runtime_roster'=>false,'availability_code'=>'programme_incomplete','availability_label_fr'=>'Programme encore non disponible','reviewed_measure_count'=>0];
        }
        return $out;
    }
    public static function runtimeSupplementV1():array{
        $out=[];
        foreach(self::supplementary() as $p){
            $id=$p['id'];
            $out[$id]=['id'=>$id,'person_reference'=>'campaign2027-'.substr($id,5),'name'=>$p['name'],'source'=>$p['source_url'],
                'observed_on'=>$p['checked_on'],'publication_state'=>'documentary_non_ballot_observation'];
        }
        return $out;
    }
    public static function report(array $campaign):array{
        $out=[];
        foreach($campaign as $r){
            $id=$r['reference_id'];if(isset($out[$id]))throw new \DomainException('Duplicate campaign reference');
            $out[$id]=$r+['reference_kind'=>'campaign_source_reference','runtime_roster'=>true,'certified_2027_candidate'=>false];
        }
        foreach(self::supplementary() as $r){
            if(isset($out[$r['reference_id']]))throw new \DomainException('Duplicate political identity');
            $out[$r['reference_id']]=$r+['reference_kind'=>'political_identity_reference'];
        }
        $people=array_values($out);
        return ['version'=>self::VERSION,'country'=>'FR','exhaustive'=>false,'final_ballot_verified'=>false,
            'campaign_reference_count'=>count($campaign),'supplementary_reference_count'=>count($people)-count($campaign),
            'playable_count'=>count(array_filter($people,fn($r)=>$r['playable']===true)),'people'=>$people];
    }
}
