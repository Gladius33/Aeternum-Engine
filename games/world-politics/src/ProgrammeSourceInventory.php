<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Direct-site discovery, not a complete extraction or ballot register. */
final class ProgrammeSourceInventory
{
    public static function report():array
    {
        $r=json_decode(file_get_contents(__DIR__.'/../data/wp114-direct-source-inventory.json'),true,32,JSON_THROW_ON_ERROR);
        if($r['schema']!=='wp114-direct-source-inventory-v1'||$r['election_year']!==2027||$r['exhaustive']!==false||$r['final_ballot_verified']!==false)
            throw new \DomainException('Invalid direct-source inventory');
        $states=['programme_and_archives_distinguished','published_project','party_project','published_in_progress','published_partial','campaign_material','announced_not_published'];
        foreach($r['people'] as $id=>$row){
            if(!preg_match('/^[a-z][a-z0-9-]+$/D',$id)||!in_array($row['publication_state'],$states,true)||$row['observed_on']!==$r['observed_on']||!str_starts_with($row['url'],'https://'))
                throw new \DomainException('Invalid direct-source row');
        }
        foreach($r['identified_unmapped'] as $m)if(!isset($r['people'][$m['person']])||!str_starts_with($m['source'],'https://')||$m['reason']==='')
            throw new \DomainException('Invalid unimplemented proposal');
        if(!is_array($r['approved_editions']??null))throw new \DomainException('Missing programme release manifest');
        foreach($r['approved_editions'] as $id=>$release){
            if(!preg_match('/^campaign2027-[a-z0-9-]+$/D',(string)$id)||($release['programme_complete']??null)!==true
                ||($release['implementation_complete']??null)!==true||!is_array($release['measure_ids']??null)
                ||!array_is_list($release['measure_ids'])||$release['measure_ids']===[]||count(array_unique($release['measure_ids']))!==count($release['measure_ids'])
                ||!preg_match('/^[a-f0-9]{64}$/D',$release['document_sha256']??'')||!str_starts_with($release['document_url']??'','https://')
                ||!preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$release['reviewed_on']??''))
                throw new \DomainException('Invalid complete programme release');
        }
        $r['sha256']=hash('sha256',json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        return $r;
    }
}
