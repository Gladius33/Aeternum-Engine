<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrenchPoliticalDirectory.php';

/** Counterfactual profiles based on explicitly attributed public evidence.
 * Documentary text never becomes an executable policy. */
final class FrenchPublicSourceEditions
{
    public static function choices(array $reviewed):array{
        $raw=file_get_contents(__DIR__.'/../data/wp-fr-public-source-editions-v2.json');
        $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if($p['schema']!=='wp-fr-public-source-editions-v2'||$p['exhaustive']!==false||$p['observed_on']!=='2026-11-27'||count($p['records'])!==32)
            throw new \DomainException('Invalid public-source edition');
        $people=ProgrammeSourceInventory::report()['people'];
        foreach(FrenchPoliticalDirectory::supplementary() as $r)$people[substr($r['id'],5)]=['name'=>$r['name']];
        $reviewed=array_column($reviewed,null,'id');$out=[];
        $allowed=['party_project','campaign_statement','campaign_project','reported_campaign_statement','reported_campaign_project','reported_party_project','historical_statement','reported_historical_statement','historical_cosigned_bill','parliamentary_proposal','reported_statement','historical_collective_project','historical_campaign_project'];
        foreach($p['records'] as $r){
            $id=$r['person'];$person=$people[$id]??null;
            if(!is_array($person)||isset($out[$id])||!preg_match('/^[a-z0-9-]+$/D',$id)||!in_array($r['attribution'],$allowed,true)
                ||!str_starts_with($r['source'],'https://')||!is_string($r['summary'])||trim($r['summary'])===''||strlen($r['summary'])>1000
                ||!is_string($r['period'])||$r['period']===''||!is_string($r['domain'])||$r['programme_complete']!==false||$r['certified_2027_candidate']!==false
                ||!is_array($r['measure_ids'])||!array_is_list($r['measure_ids'])||count(array_unique($r['measure_ids']))!==count($r['measure_ids']))
                throw new \DomainException('Invalid political source profile');
            $mapped=[];
            foreach($r['measure_ids'] as $mid){
                // Only the two already reviewed collective 2027 Ecologist
                // objectives may be released under this new edition.
                if($id!=='marine-tondelier'||!in_array($mid,['pf2027-ecologistes-smic','pf2027-ecologistes-renovation'],true)
                    ||!isset($reviewed[$mid])||$reviewed[$mid]['publisher']!=='Les Écologistes'
                    ||(SocialProgramme::target2027()[$mid]['target']??null)!==$reviewed[$mid]['target'])throw new \DomainException('Unreviewed programme mapping');
                $mapped[]=$reviewed[$mid];
            }
            if($r['measure_ids']!==[])PresidentialProgramme::compileProfile($r['measure_ids']);
            $base='campaign2027-'.$id;$edition=$base.'-v1-public';
            $out[$id]=['id'=>$edition,'reference_id'=>$edition,'base'=>$base,'name'=>$person['name'],'source'=>$r['source'],'publisher'=>'Dated public evidence',
                'as_of'=>$p['observed_on'],'measure_ids'=>$r['measure_ids'],'reviewed_measure_count'=>count($mapped),
                'documentary_evidence'=>[$r],'document_sha256'=>hash('sha256',json_encode([$edition,$r,$mapped],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)),
                'classification'=>'provisional_public_source_subset','playable'=>true,'provisional'=>true,'programme_complete'=>false,'implementation_complete'=>false,
                'final_ballot_verified'=>false,'autonomy_scope'=>$mapped===[]?'manual_only':'reviewed_mapped_subset',
                'availability_code'=>$mapped===[]?'provisional_manual_available':'provisional_available',
                'availability_label_fr'=>$mapped===[]?'Personnage provisoire — pilotage manuel':'Programme provisoire V1 — mesures collectives modélisées'];
        }
        return array_values($out);
    }
}
