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
    private const CHAMBERS=['DE'=>'bundestag','ES'=>'congress','GB'=>'commons'];
    public static function bootstrap(array $s):array
    {
        if(($s['formation_protocol']??null)!==FormationProtocol::VERSION||$s['events']!==[]||isset($s['autonomous_formation']))
            throw new \DomainException('Autonomous formation must be pinned at bootstrap');
        $s['autonomous_formation']=self::VERSION;return $s;
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
    public static function plan(array $s):?array
    {
        $cc=$s['country_code'];$cycle=$s['electoral'];$poll=$cycle['config']['poll_day'];
        if(!isset(self::CHAMBERS[$cc])||$cycle['status']!=='results'||$cycle['government']!==null
            ||$s['day']!==self::day($poll,5))return null;
        $id='auto-formation-'.$poll;
        foreach($s['events'] as $ev)if($ev['id']===$id)return null;
        $c=$cycle['coalition'];$ch=self::CHAMBERS[$cc];$n=$s['accountability']['seats'][$ch];
        $attempt=['version'=>self::VERSION,'classification'=>'fictional_uncalibrated_seat_votes','poll_day'=>$poll,
            'candidate'=>$c['candidate']??null,'ballots'=>[],'status'=>'blocked','reason'=>null,'proof'=>null];
        if($s['accountability']['status']!=='in_office'||$s['accountability']['pending']!==null){
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
        FormationProtocol::certificate($proof,$cycle,$s['accountability']['seats']);
        $attempt['status']='certified';$attempt['proof']=$proof;return $attempt;
    }
    public static function validateHistory(array $s):void
    {
        if($s['autonomous_formation']!==self::VERSION||($s['formation_protocol']??null)!==FormationProtocol::VERSION)
            throw new \DomainException('Invalid autonomous formation pin');
        $cycles=[];foreach([...$s['foreign_recurring']['archive'],$s['electoral']] as $c)$cycles[$c['config']['poll_day']]=$c;
        $attempts=[];$certificates=[];
        foreach($s['events'] as $ev){
            if($ev['kind']==='electoral_certify'&&str_starts_with($ev['result']['national_proof']['evidence_id']??'','auto-formation-'))
                $certificates[$ev['result']['national_proof']['evidence_id']]=$ev['result']['national_proof'];
            if($ev['kind']!=='foreign_formation_attempt')continue;
            $r=$ev['result'];$poll=$r['poll_day'];$id='auto-formation-'.$poll;
            if(!isset($cycles[$poll],self::CHAMBERS[$s['country_code']])||isset($attempts[$id])
                ||$ev['id']!==$id||$ev['on']!==self::day($poll,5)||$ev['actor_role']!=='simulation_clock'
                ||$r['version']!==self::VERSION||$r['classification']!=='fictional_uncalibrated_seat_votes'
                ||!is_array($r['ballots'])||!array_is_list($r['ballots'])||count($r['ballots'])>2)
                throw new \DomainException('Invalid autonomous formation attempt');
            $cycle=$cycles[$poll];
            // A failed attempt may be followed by a separately certified procedure.
            // Persisted autonomous ballots are still bound to their original coalition.
            if($r['ballots']!==[]&&($cycle['coalition']['candidate']??null)!==$r['candidate'])
                throw new \DomainException('Autonomous attempt coalition changed');
            foreach($r['ballots'] as $i=>$b)if($b!==self::ballot($cycle,$i+1))
                throw new \DomainException('Autonomous formation ballot differs from pinned model');
            if($r['status']==='certified'){
                if($r['proof']===null||$r['reason']!==null||($certificates[$id]??null)!==$r['proof'])
                    throw new \DomainException('Autonomous certificate missing');
            }elseif($r['status']!=='blocked'||$r['proof']!==null||!in_array($r['reason'],['unresolved_constitutional_procedure','no_supported_majority_coalition','support_insufficient'],true))
                throw new \DomainException('Invalid blocked attempt');
            $attempts[$id]=$r;
        }
        foreach($certificates as $id=>$proof)if(($attempts[$id]['proof']??null)!==$proof)
            throw new \DomainException('Missing autonomous attempt for certificate');
    }
}
