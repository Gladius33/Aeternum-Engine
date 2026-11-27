<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FormationProtocol.php';

/** Synthetic seat-level response to a coalition, never an actual vote forecast.
 * Only the ordinary DE/ES/GB majority routes have all required inputs.
 * No upper-house votes, presidential elections or appointment powers are invented. */
final class AutonomousForeignFormation
{
    public const VERSION='wp65-autonomous-formation-v1';
    public const VERSION_V2='wp65-autonomous-formation-v2';
    public const VERSION_V3='wp119-autonomous-formation-jp-v1';
    public const VERSION_V4='wp119-autonomous-formation-jp-v2';
    public const VERSION_V5='wp119-autonomous-recovery-v1';
    public const VERSION_V6='wp119-autonomous-jp-recovery-v1';
    public static function extended(array $s):bool{return in_array($s['autonomous_formation']??null,[self::VERSION_V4,self::VERSION_V6],true);}
    public static function recovery(array $s):bool{return in_array($s['autonomous_formation']??null,[self::VERSION_V5,self::VERSION_V6],true);}
    private static function due(string $version,string $poll):string{return self::day($poll,in_array($version,[self::VERSION_V4,self::VERSION_V6],true)?13:5);}
    private const CHAMBERS=['DE'=>'bundestag','ES'=>'congress','GB'=>'commons'];
    private static function chambers(string $version):array
    {return match($version){self::VERSION_V3,self::VERSION_V4,self::VERSION_V6=>self::CHAMBERS+['IT'=>'chamber','JP'=>'representatives'],self::VERSION_V2,self::VERSION_V5=>self::CHAMBERS+['IT'=>'chamber'],default=>self::CHAMBERS};}
    public static function bootstrap(array $s,bool $japan=false,bool $extended=false,bool $recovery=false):array
    {
        if(($s['formation_protocol']??null)!==FormationProtocol::VERSION||$s['events']!==[]||isset($s['autonomous_formation']))
            throw new \DomainException('Autonomous formation must be pinned at bootstrap');
        if(($extended&&!$japan)||($japan&&$s['country_code']!=='JP'))throw new \DomainException('Japanese formation pin only');
        if($japan&&$recovery&&!$extended)throw new \DomainException('Japanese recovery requires extended designation');
        $s['autonomous_formation']=$japan?($recovery?self::VERSION_V6:($extended?self::VERSION_V4:self::VERSION_V3)):($recovery?self::VERSION_V5:self::VERSION_V2);return $s;
    }
    private static function day(string $poll,int $delta):string
    {return (new \DateTimeImmutable($poll,new \DateTimeZone('UTC')))->modify('+'.$delta.' days')->format('Y-m-d');}
    private static function step(string $poll,string $kind,int $delta):array
    {return ['kind'=>$kind,'at'=>self::day($poll,$delta).'T12:00:00Z'];}
    /** Use pinned fictional seats and programmes, not client-provided totals.
     * Fixed probabilities are deliberately uncalibrated design assumptions. */
    public static function ballot(array $cycle,int $round):array
    {
        if(!in_array($round,[1,2],true)||$cycle['status']!=='results'||!is_array($cycle['coalition']))
            throw new \DomainException('Formation ballot requires final results and coalition');
        $c=$cycle['coalition'];$result=$cycle['result']['seats'];ksort($result,SORT_STRING);
        $out=['yes'=>0,'no'=>0,'abstain'=>0];
        foreach($result as $party=>$seats){
            $support=in_array($party,$c['accepted'],true);
            for($j=0;$j<$seats;$j++){
                $hash=hash('sha256',json_encode([self::VERSION,$cycle['world_id'],$cycle['country'],$cycle['config']['poll_day'],$c['candidate'],$c['programme'],$party,$j,$round],JSON_THROW_ON_ERROR));
                $draw=hexdec(substr($hash,0,8))%10000;
                // 97% support within the negotiated coalition, 5% outside.
                $yes=$support?9700:500;$abstain=$support?200:1500;
                $out[$draw<$yes?'yes':($draw<$yes+$abstain?'abstain':'no')]++;
            }
        }
        if(array_sum($out)!==$cycle['seats_total'])throw new \LogicException('Formation ballot seat mismatch');
        return $out;
    }
    /** Independent synthetic upper-chamber electorate, not a copy of lower-house results.
     * Fifty seats are a pilot fixture, not the actual Italian Senate membership. */
    public static function italianSenate(array $cycle,int $n):array
    {
        if($cycle['country']!=='IT'||$n!==50||$cycle['status']!=='results'||!is_array($cycle['coalition']))throw new \DomainException('Italian Senate fixture unavailable');
        $parties=array_keys($cycle['config']['parties']);sort($parties,SORT_STRING);
        $roster=array_fill_keys($parties,0);$votes=['yes'=>0,'no'=>0,'abstain'=>0];
        for($j=0;$j<$n;$j++){
            $seed=[self::VERSION_V2,'italian-senate',$cycle['world_id'],$cycle['config']['poll_day'],$j];
            $party=$parties[hexdec(substr(hash('sha256',json_encode($seed,JSON_THROW_ON_ERROR)),0,8))%count($parties)];$roster[$party]++;
            $draw=hexdec(substr(hash('sha256',json_encode([...$seed,'confidence',$cycle['coalition']['candidate'],$cycle['coalition']['programme']],JSON_THROW_ON_ERROR)),0,8))%10000;
            $support=in_array($party,$cycle['coalition']['accepted'],true);$yes=$support?9700:500;$abstain=$support?200:1500;
            $votes[$draw<$yes?'yes':($draw<$yes+$abstain?'abstain':'no')]++;
        }
        return ['seats_by_party'=>$roster,'votes'=>$votes];
    }
    /** Separately drawn upper house, fixed synthetic 50-seat pilot, not
     * a real election result. Only the concordant designation route is enabled. */
    public static function japaneseCouncillors(array $cycle):array
    {
        if($cycle['country']!=='JP'||$cycle['status']!=='results'||!is_array($cycle['coalition']))
            throw new \DomainException('Japanese designation fixture unavailable');
        $parties=array_keys($cycle['config']['parties']);sort($parties,SORT_STRING);
        $roster=array_fill_keys($parties,0);$votes=['yes'=>0,'no'=>0,'abstain'=>0];
        for($j=0;$j<50;$j++){
            $seed=[self::VERSION_V3,'councillors',$cycle['world_id'],$cycle['config']['poll_day'],$j];
            $party=$parties[hexdec(substr(hash('sha256',json_encode($seed,JSON_THROW_ON_ERROR)),0,8))%count($parties)];$roster[$party]++;
            $draw=hexdec(substr(hash('sha256',json_encode([...$seed,'designation',$cycle['coalition']['candidate']],JSON_THROW_ON_ERROR)),0,8))%10000;
            $support=in_array($party,$cycle['coalition']['accepted'],true);$yes=$support?9700:500;$abstain=$support?200:1500;
            $votes[$draw<$yes?'yes':($draw<$yes+$abstain?'abstain':'no')]++;
        }
        return ['seats_by_party'=>$roster,'votes'=>$votes];
    }
    /** Supplementary chamber is restricted to the new formation pin.
     * It is not a general upper-house confidence-vote authorization. */
    public static function formationSeats(array $s):array
    {
        $seats=$s['accountability']['seats'];
        if(in_array($s['autonomous_formation']??null,[self::VERSION_V3,self::VERSION_V4,self::VERSION_V6],true)){
            if($s['country_code']!=='JP'||isset($seats['councillors']))throw new \DomainException('Invalid Japanese formation pin');
            $seats['councillors']=50;
        }
        return $seats;
    }
    public static function plan(array $s):?array
    {
        $cc=$s['country_code'];$cycle=$s['electoral'];$poll=$cycle['config']['poll_day'];
        $version=$s['autonomous_formation']??self::VERSION;$chambers=self::chambers($version);
        if(!isset($chambers[$cc])||$cycle['status']!=='results'||$cycle['government']!==null
            ||$s['day']!==self::due($version,$poll))return null;
        $id='auto-formation-'.$poll;
        foreach($s['events'] as $ev)if($ev['id']===$id)return null;
        $c=$cycle['coalition'];$ch=$chambers[$cc];$n=$s['accountability']['seats'][$ch];
        $attempt=['version'=>$version,'classification'=>'fictional_uncalibrated_seat_votes','poll_day'=>$poll,
            'candidate'=>$c['candidate']??null,'ballots'=>[],'status'=>'blocked','reason'=>null,'proof'=>null];
        if((in_array($version,[self::VERSION_V2,self::VERSION_V5],true)&&$cc==='IT')||(in_array($version,[self::VERSION_V3,self::VERSION_V4,self::VERSION_V6],true)&&$cc==='JP'))$attempt['upper_chamber']=null;
        if(!ComparativeGovernment::formationAvailable($s['accountability'],self::recovery($s)?$poll:null)){
            $attempt['reason']='unresolved_constitutional_procedure';return $attempt;
        }
        if(($c['status']??null)!=='majority_agreement'||$cycle['seats_total']!==$n){
            $attempt['reason']='no_supported_majority_coalition';return $attempt;
        }
        $first=self::ballot($cycle,1);$attempt['ballots'][]=$first;
        $absolute=$first['yes']>intdiv($n,2);
        $vote=static fn(string $kind,int $offset,array $b):array=>self::step($poll,$kind,$offset)+['chamber'=>$ch]+$b;
        $steps=[];
        if($cc==='DE'&&$absolute){
            $steps=[self::step($poll,'presidential_nomination',1),$vote('chancellor_vote',3,$first),self::step($poll,'presidential_appointment',5)];
        }elseif($cc==='GB'&&$absolute){
            // Confidence assessment, not a compulsory House of Commons investiture ballot.
            $steps=[self::step($poll,'commons_confidence_assessed',2),self::step($poll,'royal_appointment',5)];
        }elseif($cc==='IT'){
            $upper=self::italianSenate($cycle,$s['accountability']['seats']['senate']);$attempt['upper_chamber']=$upper;
            $b=$upper['votes'];
            if($first['yes']>$first['no']&&$b['yes']>$b['no'])$steps=[self::step($poll,'presidential_appointment',1),$vote('confidence_vote',3,$first),self::step($poll,'confidence_vote',5)+['chamber'=>'senate']+$b];
        }elseif($cc==='JP'&&self::extended($s)){
            $upper=self::japaneseCouncillors($cycle);$attempt['upper_chamber']=$upper;$b=$upper['votes'];
            if($absolute){
                $steps=[$vote('designation_vote',2,$first),self::step($poll,'designation_vote',3)+['chamber'=>'councillors']+$b];
                if($b['yes']<=25){
                    // In this binary designation fixture, a majority for the
                    // independently selected alternative is a different designation.
                    // Otherwise neither candidate is designated; no recess is modeled.
                    if($b['no']>25&&FormationProtocol::japaneseAlternative($cycle)!==null)
                        $steps[]=self::step($poll,'joint_committee_no_agreement',4);
                    elseif($b['no']<=25)$steps[]=self::step($poll,'councillors_deadline_elapsed',12);
                    else $steps=[];
                }
                if($steps!==[])$steps[]=self::step($poll,'imperial_appointment',13);
            }
        }elseif($cc==='JP'&&$version===self::VERSION_V3){
            $upper=self::japaneseCouncillors($cycle);$attempt['upper_chamber']=$upper;$b=$upper['votes'];
            if($first['yes']>intdiv(array_sum($first),2)&&$b['yes']>intdiv(array_sum($b),2))
                $steps=[$vote('designation_vote',2,$first),self::step($poll,'designation_vote',3)+['chamber'=>'councillors']+$b,self::step($poll,'imperial_appointment',5)];
        }elseif($cc==='ES'){
            $steps=[self::step($poll,'royal_nomination_after_consultations',1),$vote('investiture_vote',2,$first)];
            if(!$absolute){
                $second=self::ballot($cycle,2);$attempt['ballots'][]=$second;
                if($second['yes']<=$second['no'])$steps=[];
                else $steps[]=$vote('investiture_vote',4,$second);
            }
            if($steps!==[])$steps[]=self::step($poll,'royal_appointment',5);
        }
        if($steps===[]){$attempt['reason']='support_insufficient';return $attempt;}
        $proof=['world_id'=>$s['world_id'],'country'=>$cc,'evidence_id'=>$id,'coalition_id'=>$c['id'],'candidate'=>$c['candidate'],
            'day'=>$s['day'],'procedure_certified'=>true,'procedure'=>['poll_day'=>$poll,'steps'=>$steps]];
        FormationProtocol::certificate($proof,$cycle,self::formationSeats($s),self::extended($s));
        $attempt['status']='certified';$attempt['proof']=$proof;return $attempt;
    }
    public static function validateHistory(array $s):void
    {
        $version=$s['autonomous_formation'];$chambers=self::chambers($version);
        if(!in_array($version,[self::VERSION,self::VERSION_V2,self::VERSION_V3,self::VERSION_V4,self::VERSION_V5,self::VERSION_V6],true)||(in_array($version,[self::VERSION_V3,self::VERSION_V4,self::VERSION_V6],true)&&$s['country_code']!=='JP')||($s['formation_protocol']??null)!==FormationProtocol::VERSION)
            throw new \DomainException('Invalid autonomous formation pin');
        $cycles=[];foreach([...$s['foreign_recurring']['archive'],$s['electoral']] as $c)$cycles[$c['config']['poll_day']]=$c;
        $attempts=[];$certificates=[];
        foreach($s['events'] as $ev){
            if($ev['kind']==='electoral_certify'&&str_starts_with($ev['result']['national_proof']['evidence_id']??'','auto-formation-'))
                $certificates[$ev['result']['national_proof']['evidence_id']]=$ev['result']['national_proof'];
            if($ev['kind']!=='foreign_formation_attempt')continue;
            $r=$ev['result'];$poll=$r['poll_day'];$id='auto-formation-'.$poll;
            if(!isset($cycles[$poll],$chambers[$s['country_code']])||isset($attempts[$id])
                ||$ev['id']!==$id||$ev['on']!==self::due($version,$poll)||$ev['actor_role']!=='simulation_clock'
                ||$r['version']!==$version||$r['classification']!=='fictional_uncalibrated_seat_votes'
                ||!is_array($r['ballots'])||!array_is_list($r['ballots'])||count($r['ballots'])>2)
                throw new \DomainException('Invalid autonomous formation attempt');
            $cycle=$cycles[$poll];
            // A failed attempt may be followed by a separately certified procedure.
            // Persisted autonomous ballots are still bound to their original coalition.
            if($r['ballots']!==[]&&($cycle['coalition']['candidate']??null)!==$r['candidate'])
                throw new \DomainException('Autonomous attempt coalition changed');
            foreach($r['ballots'] as $i=>$b)if($b!==self::ballot($cycle,$i+1))
                throw new \DomainException('Autonomous formation ballot differs from pinned model');
            if(in_array($version,[self::VERSION_V2,self::VERSION_V5],true)&&$s['country_code']==='IT'){
                if(!array_key_exists('upper_chamber',$r)||$r['upper_chamber']!==($r['ballots']===[]?null:self::italianSenate($cycle,$s['accountability']['seats']['senate'])))throw new \DomainException('Italian Senate evidence mismatch');
                if($r['status']==='certified'&&($r['proof']['procedure']['steps'][2]['yes']??null)!==$r['upper_chamber']['votes']['yes'])throw new \DomainException('Italian Senate certificate mismatch');
            }
            if(in_array($version,[self::VERSION_V3,self::VERSION_V4,self::VERSION_V6],true)&&$s['country_code']==='JP'){
                if(!array_key_exists('upper_chamber',$r)||$r['upper_chamber']!==($r['ballots']===[]?null:self::japaneseCouncillors($cycle)))
                    throw new \DomainException('Japanese upper-house designation evidence mismatch');
            }
            if($r['status']==='certified'){
                if($r['proof']===null||$r['reason']!==null||($certificates[$id]??null)!==$r['proof'])
                    throw new \DomainException('Autonomous certificate missing');
                // Bind every certified lower/upper-chamber tally to the
                // independently replayed seat model, not just a majority.
                $proofVotes=[];$upperVotes=[];
                foreach($r['proof']['procedure']['steps'] as $step){
                    if(!str_ends_with($step['kind'],'_vote'))continue;
                    $votes=array_intersect_key($step,array_fill_keys(['yes','no','abstain'],true));
                    if($step['chamber']===$chambers[$s['country_code']])$proofVotes[]=$votes;
                    elseif(($s['country_code']==='IT'&&$step['chamber']==='senate')||($s['country_code']==='JP'&&in_array($version,[self::VERSION_V3,self::VERSION_V4,self::VERSION_V6],true)&&$step['chamber']==='councillors'))$upperVotes[]=$votes;
                    else throw new \DomainException('Unexpected autonomous ballot chamber');
                }
                if($s['country_code']!=='GB'&&$proofVotes!==$r['ballots'])
                    throw new \DomainException('Autonomous certificate lower tally mismatch');
                if($s['country_code']==='GB'&&($proofVotes!==[]||count($r['ballots'])!==1||$r['ballots'][0]['yes']<=intdiv($cycle['seats_total'],2)))
                    throw new \DomainException('Unverified Commons confidence assessment');
                if(in_array($s['country_code'],['IT','JP'],true)&&$upperVotes!==[$r['upper_chamber']['votes']])
                    throw new \DomainException('Autonomous certificate upper tally mismatch');
            }elseif($r['status']!=='blocked'||$r['proof']!==null||!in_array($r['reason'],['unresolved_constitutional_procedure','no_supported_majority_coalition','support_insufficient'],true))
                throw new \DomainException('Invalid blocked attempt');
            $attempts[$id]=$r;
        }
        if(in_array($version,[self::VERSION_V2,self::VERSION_V3,self::VERSION_V4,self::VERSION_V5,self::VERSION_V6],true))foreach($cycles as $poll=>$cycle){
            if(!isset($chambers[$s['country_code']])||$s['day']<self::due($version,$poll))continue;
            $priorCertificate=$cycle['government']!==null&&$cycle['government']['on']<self::due($version,$poll);
            if(!$priorCertificate&&!isset($attempts['auto-formation-'.$poll]))throw new \DomainException('Missing due autonomous formation attempt');
        }
        foreach($certificates as $id=>$proof)if(($attempts[$id]['proof']??null)!==$proof)
            throw new \DomainException('Missing autonomous attempt for certificate');
    }
}
