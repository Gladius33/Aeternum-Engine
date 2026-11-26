<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/PoliticalRoster.php';

/** Dated real identities only, with official public electoral results.
 * No psychological profiles, inferred intentions or future candidacies. */
final class HistoricalCandidateReference
{
    public const VERSION='wp75-fr-historical-candidates-v1';
    private static function digest(array $p):string
    {return hash('sha256',json_encode($p,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));}
    public static function pin():array
    {
        $p=json_decode(file_get_contents(__DIR__.'/../data/2026-11-26-fr-candidates-2022-v1.json'),true,32,JSON_THROW_ON_ERROR);
        self::validate($p);
        return ['pack'=>$p,'sha'=>self::digest($p)];
    }
    private static function validate(array $p):void
    {
        if($p['schema']!==self::VERSION||$p['country']!=='FR'||$p['poll_day']!=='2022-04-10'||count($p['candidates'])!==12
            ||$p['classification']!=='historical_election_not_future_candidacy_or_intention')throw new \DomainException('Invalid historical election reference');
        $f=$p['first_round'];
        foreach($f as $v)if(!is_int($v)||$v<0||$v>100000000)throw new \DomainException('Invalid turnout reference');
        if($f['registered']!==$f['abstentions']+$f['voters']||$f['voters']!==$f['blank']+$f['invalid']+$f['valid'])throw new \DomainException('Turnout reconciliation failed');
        $seen=[];$votes=0;
        $roster=new PoliticalRoster('historical-reference','FR',['head_of_government']);
        foreach($p['candidates'] as $c){
            if(isset($seen[$c['id']])||!is_int($c['votes'])||$c['votes']<1||$c['votes']>$f['valid']||!is_int($c['share_bps'])
                ||$c['candidacy_2027']!=='unknown'||$c['programme_extraction_status']!=='not_curated')throw new \DomainException('Invalid candidate record');
            if(abs($c['share_bps']-intdiv($c['votes']*10000+intdiv($f['valid'],2),$f['valid']))>1)throw new \DomainException('Historical candidate share mismatch');
            $seen[$c['id']]=true;$votes+=$c['votes'];$roster->addPerson(self::person($c,$p));
        }
        if($votes!==$f['valid'])throw new \DomainException('Historical vote reconciliation failed');
    }
    private static function person(array $c,array $p):array
    {
        return ['id'=>'real-'.$c['id'],'kind'=>'real','name'=>$c['name'],'country'=>'FR',
            'biography'=>'Candidature attestée au premier tour de l’élection présidentielle française de 2022.',
            'as_of'=>$p['poll_day'],'sources'=>[$p['sources']['results'],$p['sources']['manifestos']],
            'programme'=>[],'avatar'=>PoliticalProfileTemplate::AVATAR];
    }
    public static function report(array $pin):array
    {
        $p=$pin['pack'];self::validate($p);
        if(!hash_equals($pin['sha'],self::digest($p)))throw new \DomainException('Historical candidate digest mismatch');
        $people=[];foreach($p['candidates'] as $c)$people[]=self::person($c,$p);
        return ['version'=>self::VERSION,'reference_sha'=>$pin['sha'],'reference'=>$p,'roster_compatible_profiles'=>$people,
            'programme_content_curated'=>false,'selection_enabled'=>false,'future_eligibility_certified'=>false,
            'historical_votes_used_as_2027_support'=>false,'real_person_behavior_inferred'=>false];
    }
}
