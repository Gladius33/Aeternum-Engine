<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Documentary identity reference for the full diplomatic roster.
 * Observation dates do not certify opening incumbents or future elections.
 * The immutable inputs are verified against their exact Git blob hashes. */
final class UniverseIdentityReference
{
    public const VERSION='wp119-universe-identity-reference-v1';
    private const FR='ace236105d6db79412332e6dcd3f6896c518035c';
    private const FOREIGN='3fab407ba4ac1bf8097baf03b93082ceb3336ab5';
    private static function pinned(string $file,string $sha):array{
        $bytes=file_get_contents(__DIR__.'/../data/'.$file);
        if(!is_string($bytes)||!hash_equals($sha,sha1('blob '.strlen($bytes)."\0".$bytes)))throw new \DomainException('Unpinned identity reference');
        return json_decode($bytes,true,64,JSON_THROW_ON_ERROR);
    }
    /** No inherited monarchy identity is offered as a political candidate.
     * Preserve the separate, non-elective/collective executive observation. */
    public static function catalogue():array{
        $fr=self::pinned('wp119-real-fr-roster-v1.json',self::FR);
        $foreign=self::pinned('wp115-institutional-reference-v1.json',self::FOREIGN);
        $out=['FR'=>['country'=>'FR','source_blob'=>self::FR,'classification'=>'campaign_reference_not_certified_ballot',
            'people'=>array_map(fn($p)=>$p+['observed_roles'=>[],'programme_complete'=>false],$fr['people'])]];
        $monarchies=['AU','BE','CA','DK','ES','GB','JP','LU','NL','SA','SE'];
        foreach($foreign['countries'] as $country){
            $code=$country['iso2'];$obs=$country['officeholders'];$rows=[];
            $roles=['head_of_government','foreign_minister'];
            if(!in_array($code,$monarchies,true))$roles[]='head_of_state';
            foreach($roles as $role){
                $name=$obs[$role.'_name'];if(!is_string($name)||trim($name)==='')continue;
                if(isset($rows[$name])){$rows[$name]['observed_roles'][]=$role;continue;}
                // This reference key is not a career ID, an authority or an account.
                $id='observation-'.strtolower($code).'-'.substr(hash('sha256',$name),0,32);
                $rows[$name]=['id'=>$id,'name'=>$name,'source'=>$foreign['sources']['officeholders']['url'],
                    'observed_on'=>$foreign['officeholder_observation_date'],'observed_roles'=>[$role],
                    'publication_state'=>'office_observation_only','programme_complete'=>false];
            }
            if($rows===[]||isset($out[$code]))throw new \DomainException('Incomplete identity coverage');
            $out[$code]=['country'=>$code,'source_blob'=>self::FOREIGN,'classification'=>'dated_office_observation_not_simulated_mandate',
                'government_mode'=>$obs['government_mode'],'people'=>array_values($rows)];
        }
        ksort($out,SORT_STRING);
        return $out;
    }
    public const BOUND_ROSTER='wp119-identity-roster-v1';
    /** Deterministic scenario casting, not a forecast, endorsement, or
     * a claimed affiliation. Only the documented name is imported. */
    public static function openingPerson(string $world,string $country):?array{
        $ref=self::country($country);
        if($country==='FR'){
            $people=$ref['people'];usort($people,fn($a,$b)=>strcmp(hash('sha256',$world.'|opening|'.$a['id']),hash('sha256',$world.'|opening|'.$b['id'])));
            return self::record($people[0],$country,'campaign-president');
        }
        foreach($ref['people'] as $p)if(in_array('head_of_government',$p['observed_roles'],true))return self::record($p,$country,$p['id']);
        return null; // Switzerland's collective executive is not a single office.
    }
    private static function record(array $p,string $country,string $id):array{
        return ['id'=>$id,'kind'=>'real','name'=>$p['name'],'country'=>$country,
            'biography'=>'Documentary identity; alternate-history scenario casting, not an asserted real appointment or programme.',
            'as_of'=>$p['observed_on'],'sources'=>[$p['source']],'programme'=>[]];
    }
    /** Stable three-way casting of the legacy electoral aliases for one
     * simulated mandate. Numerical ballot fixtures remain unchanged. */
    public static function frenchChallengers(string $world,int $ordinal,string $incumbent):array{
        if($ordinal<1||$ordinal>100)throw new \DomainException('Invalid mandate ordinal');
        $people=array_values(array_filter(self::country('FR')['people'],fn($p)=>$p['name']!==$incumbent));
        usort($people,fn($a,$b)=>strcmp(hash('sha256',$world.'|'.$ordinal.'|'.$a['id']),hash('sha256',$world.'|'.$ordinal.'|'.$b['id'])));
        if(count($people)<3)throw new \DomainException('Insufficient documented challengers');
        $out=[];foreach(['challenger-a','challenger-b','challenger-c'] as $i=>$alias)$out[$alias]=self::record($people[$i],'FR','campaign-president');
        return $out;
    }
    public static function country(string $country):array{
        $rows=self::catalogue();if(!isset($rows[$country]))throw new \DomainException('No identity reference');
        return ['version'=>self::VERSION,...$rows[$country],'runtime_authority'=>false,'certifies_2027_incumbent'=>false];
    }
}
