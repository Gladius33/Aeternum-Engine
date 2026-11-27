<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Explicit counterfactual V1 release. Distinct immutable edition IDs; no
 * full manifesto or future-ballot certification. */
final class ProvisionalFrenchProgramme
{
    public static function evidence():array{
        $p=json_decode(file_get_contents(__DIR__.'/../data/wp-fr-public-programme-evidence-v1.json'),true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!=='wp-fr-public-programme-evidence-v1'||$p['exhaustive']!==false)throw new \DomainException('Invalid public evidence');
        foreach($p['records'] as $r)if(!preg_match('/^[a-z0-9-]+$/D',$r['person'])||!str_starts_with($r['source'],'https://')||$r['implementation_status']!=='documentary_unmapped'||$r['programme_complete']!==false||$r['certified_2027_candidate']!==false)throw new \DomainException('Invalid evidence row');
        return $p;
    }
    public static function choices(array $sources,array $base):array{
        $p=json_decode(file_get_contents(__DIR__.'/../data/wp-fr-provisional-release-v1.json'),true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!=='wp-fr-provisional-release-v1'||$p['observed_on']!=='2026-11-27')throw new \DomainException('Invalid provisional edition');
        $sources=array_column($sources,null,'id');$base=array_column($base,null,'id');$out=[];
        foreach($p['editions'] as $r){
            $b=$base[$r['base']]??null;
            if($b===null||$r['name']!==$b['name']||!str_starts_with($r['id'],$r['base'].'-v')||isset($base[$r['id']],$out[$r['id']])||isset($base[$r['id']])||isset($out[$r['id']])||$r['programme_complete']!==false
                ||$r['classification']!=='provisional_public_source_subset'||$r['measure_ids']===[]||count(array_unique($r['measure_ids']))!==count($r['measure_ids']))throw new \DomainException('Invalid provisional release');
            $rows=[];foreach($r['measure_ids'] as $mid){
                $m=$sources[$mid]??null;$def=SocialProgramme::target2027()[$mid]??null;
                if($m===null||$def===null||$m['publisher']!==$b['publisher']||$m['target']!==$def['target'])throw new \DomainException('Unverified provisional measure');
                $rows[]=$m;
            }
            PresidentialProgramme::compileProfile($r['measure_ids']);
            $sha=hash('sha256',json_encode([$r,$rows],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            $out[$r['id']]=$r+['source'=>$b['source'],'publisher'=>$b['publisher'],'as_of'=>$p['observed_on'],'reference_id'=>$r['id'],
                'playable'=>true,'provisional'=>true,'implementation_complete'=>false,'final_ballot_verified'=>false,'document_sha256'=>$sha,
                'reviewed_measure_count'=>count($rows),'availability_code'=>'provisional_available','availability_label_fr'=>'Programme provisoire V1 — disponible'];
        }
        return array_values($out);
    }
}
