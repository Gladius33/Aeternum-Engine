<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** A bounded, documented party/representative cast. The ballots and neutral
 * coordinates are counterfactual fixtures, NOT party manifestos or forecasts.
 * Only a previously documented coalition pattern can be simulated jointly.
 * No assertion of future leadership or individual willingness. */
final class DocumentaryForeignBallot
{
    public const VERSION='wp119-documentary-ballot-v1';
    public const CAREER_VERSION='wp119-career-documentary-ballot-v1';
    private static function pinned(string $file,string $hash):array{
        $raw=file_get_contents(__DIR__.'/../data/'.$file);
        if(!is_string($raw)||!hash_equals($hash,sha1('blob '.strlen($raw)."\0".$raw)))throw new \DomainException('Documentary cast pin mismatch');
        return json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    }
    private static function legacyCareers(string $cc):array{
        return self::pinned('wp119-real-comparative-roster-v1.json','1e5c2b754066c556fc033d7cd15d8633b1ece7b6')['countries'][$cc]??[];
    }
    public static function reference(string $cc,bool $career=false):array{
        $data=self::pinned('wp119-documentary-ballot-v1.json','592381c813fdc21043359b716fca8c59e23ac263');
        if($data['version']!==self::VERSION||$data['observed_on']!=='2026-11-27'||!isset($data['countries'][$cc]))throw new \DomainException('Unreviewed documentary cast');
        $r=$data['countries'][$cc];$out=[];
        foreach($r['parties'] as [$id,$name,$label,$source]){
            if(isset($out[$id])||!preg_match('/^[a-z][a-z0-9-]{0,63}$/D',$id)||!str_starts_with($source,'https://'))throw new \DomainException('Invalid documentary party');
            $pid='observation-'.strtolower($cc).'-'.substr(hash('sha256',$name),0,32);
            if($career)foreach(self::legacyCareers($cc) as $person)if($person['name']===$name){$pid=$person['id'];break;}
            $out[$id]=['party_id'=>$id,'party_label'=>$label,'person_id'=>$pid,'name'=>$name,'source'=>$source,'observed_on'=>$data['observed_on'],'programme_complete'=>false];
        }
        return ['version'=>$career?self::CAREER_VERSION:self::VERSION,'classification'=>$data['classification'],'parties'=>$out,'coalitions'=>$r['coalitions'],'fixture_not_real_election'=>true];
    }
    public static function people(string $cc,bool $career=false):array{
        $out=[];foreach(self::reference($cc,$career)['parties'] as $p)$out[$p['person_id']]=[
            'id'=>$p['person_id'],'kind'=>'real','name'=>$p['name'],'country'=>$cc,
            'biography'=>'Documented political affiliation; counterfactual scenario representative, not an asserted future candidate.',
            'as_of'=>$p['observed_on'],'sources'=>[$p['source']],'programme'=>[]];
        return $out;
    }
    /** Add only genuinely new identities; preserve old reference IDs on replay. */
    public static function extraCareers(string $cc):array{
        $legacy=array_column(self::legacyCareers($cc),'id');$out=[];
        foreach(self::reference($cc,true)['parties'] as $p){
            $id=$p['person_id'];if(in_array($id,$legacy,true))continue;
            $out[$id]=['id'=>$id,'person_reference'=>'person-'.$id,'name'=>$p['name'],'source'=>$p['source'],'observed_on'=>$p['observed_on'],
                'publication_state'=>'documented_affiliation_only','observed_roles'=>[],'party_id'=>$p['party_id'],'programme_complete'=>false];
        }
        ksort($out,SORT_STRING);return $out;
    }
    public static function config(string $cc,array $base,bool $career=false):array{
        $r=self::reference($cc,$career);$ids=array_keys($r['parties']);
        if(count($ids)!==($cc==='US'?2:4)||isset($base['documentary_cast']))throw new \DomainException('Invalid fixture base');
        $map=$cc==='US'?['civic'=>$ids[0],'regional'=>$ids[0],'social'=>$ids[1],'liberal'=>$ids[1]]:
            array_combine(['civic','social','liberal','regional'],$ids);
        $parties=[];foreach($r['parties'] as $id=>$person)$parties[$id]=['leader'=>$person['person_id'],'programme'=>['economy'=>500,'services'=>500],'tolerance'=>0];
        foreach($base['districts'] as &$district){
            foreach(['first','second'] as $round){
                if($district[$round]===null)continue;
                $votes=[];foreach($district[$round]['votes'] as $id=>$count){$target=$map[$id];$votes[$target]=($votes[$target]??0)+$count;}
                $district[$round]['votes']=$votes;
            }
        }unset($district);
        $base['parties']=$parties;$base['documentary_cast']=$career?self::CAREER_VERSION:self::VERSION;
        return $base;
    }
    public static function validate(string $cc,array $config):void{
        $version=$config['documentary_cast']??null;
        if(!in_array($version,[self::VERSION,self::CAREER_VERSION],true))throw new \DomainException('Unknown documentary cast');
        $ref=self::reference($cc,$version===self::CAREER_VERSION);$expected=[];
        foreach($ref['parties'] as $id=>$person)$expected[$id]=['leader'=>$person['person_id'],'programme'=>['economy'=>500,'services'=>500],'tolerance'=>0];
        ksort($expected);$actual=$config['parties'];ksort($actual);
        if($actual!==$expected)throw new \DomainException('Unverified identity or programme coordinates');
    }
    public static function compatible(string $cc,array $parties):bool{
        if(count($parties)===1)return true;
        sort($parties,SORT_STRING);
        foreach(self::reference($cc)['coalitions'] as $r){$known=$r['parties'];sort($known,SORT_STRING);if($known===$parties)return true;}
        return false;
    }
}
