<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrenchConstitutionalCrisis.php';
require_once __DIR__.'/AssemblyRollCalls.php';
require_once __DIR__.'/ParliamentaryLifecycle.php';
require_once __DIR__.'/FrenchCabinet.php';

/** A replay-only principal, built exclusively from clerk-certified scenario
 * evidence inside the legislative aggregate. No HTTP credential or role grant. */
final class LegislativeResponsibilityPrincipal implements FrenchPresidentialAppointmentPrincipal,FrenchParliamentaryPrincipal
{
    public function __construct(private readonly string $world,private readonly array $d=[]){}
    public function actorId():string{return 'legislative-responsibility-clerk';}
    public function allows(string $world,string $ability):bool{return $world===$this->world&&in_array($ability,
        ['fr.49_3.engage','fr.censure.file','fr.censure.vote','fr.constitution.clock','fr.president.appoint_pm','fr.49_1.engage','fr.49_1.vote','fr.49_2.file','fr.49_2.vote','fr.constitution.session'],true);}
    public function councilDeliberated(string $world,string $billId):bool{return $world===$this->world&&($this->d['council_deliberated']??false)===true;}
    public function verifiedBillCategory(string $world,string $billId):string{return 'ordinary';}
    public function verifiedSignatures(string $world,string $motionId):int{return $this->d['signatures']??0;}
    public function verifiedVotesFor(string $world,string $motionId):int{return $this->d['votes_for']??0;}
    public function verifiedDeputySignatories(string $world,string $motionId):array{return $this->d['signatories']??[];}
    public function verifiedConfidenceBallot(string $world,string $statementId):array{return $this->d['confidence_ballot']??[];}
    public function verifiedSessionKind(string $world,string $session):string{return $this->d['session_kind']??'ordinary';}
    public function verifiedInstant(string $world,string $operation):string{return $this->d['at']??throw new \DomainException('No certified instant');}
    public function verifiedResignationPresented(string $world,string $appointmentId):bool{return ($this->d['resignation_presented']??false)===true;}
    public function verifiedPrimeMinisterAppointee(string $world,string $appointmentId):string{return $this->d['prime_minister']??'';}
}
final class LegislativeResponsibility
{
    public const VERSION='wp41-responsibility-v1';
    public static function initial(string $world,string $day):array
    {
        return ['version'=>self::VERSION,'crisis'=>(new FrenchConstitutionalCrisis($world,'ordinary-pilot',FrenchConstitutionalCrisis::instant($day.'T00:00:00Z'),577))->snapshot(),
            'pending'=>null,'engagement_count'=>0,'last_engagement_day'=>null,'last_appointment'=>null];
    }
    public static function apply(array $r,array $b,array $e):array
    {
        $d=$e['data'];$k=$e['kind'];$id=$e['evidence_id'];$world=$e['world_id'];$day=$e['day'];
        $c=FrenchConstitutionalCrisis::restore($r['crisis']);
        $p=new LegislativeResponsibilityPrincipal($world,$d);
        $assembly=isset($r['assembly'])?FrenchParliament::restore($r['assembly']):null;
        $at=$d['at']??null;
        if(!is_string($at)||substr($at,0,10)!==$day||!str_ends_with($at,'Z'))throw new \DomainException('Certified UTC instant must match game day');
        FrenchConstitutionalCrisis::instant($at);
        if(isset($r['lifecycle'])&&$k==='responsibility_engage'&&!ParliamentaryLifecycle::quiet($r))throw new \DomainException('Pending proceeding');
        if(isset($r['lifecycle'])&&in_array($k,['responsibility_engage','responsibility_file'],true)&&$r['lifecycle']['session']===null)throw new \DomainException('No active session');
        if(isset($r['cabinet'])&&$k==='responsibility_appoint')throw new \DomainException('Use cabinet succession procedure');
        switch($k){
            case 'responsibility_engage':
                self::fields($d,['at','council_deliberated','version','text_sha','reading_delay_certified']);
                if($r['pending']!==null||$b['status']!=='navette'||$b['next_chamber']!=='assembly'
                    ||$d['reading_delay_certified']!==true||!is_string($d['version'])||!isset($b['texts'][$d['version']])
                    ||$b['texts'][$d['version']]!==$d['text_sha']||$d['version']!==$b['current'])
                    throw new \DomainException('No eligible Assembly reading');
                if(!$b['accelerated']&&$b['readings']['assembly']===0&&$day<(new \DateTimeImmutable($b['filed_on']))->modify('+42 days')->format('Y-m-d'))
                    throw new \DomainException('Ordinary reading delay not met');
                if($assembly!==null){
                    $s=$c->snapshot();
                    if($s['confidence']!==null)throw new \DomainException('Confidence vote pending');
                    foreach($s['spontaneous_motions'] as $m)if($m['status']==='filed')throw new \DomainException('Independent motion pending');
                }
                $c->engage($p,$b['id'],'ordinary',$at,$id);
                $r['pending']=['bill_id'=>$b['id'],'version'=>$d['version']];
                $r['engagement_count']++;$r['last_engagement_day']=$day;$b['status']='responsibility_pending';break;
            case 'responsibility_file':
                self::pending($r,$b);
                if($assembly!==null){
                    self::fields($d,['at','motion_id','signatories']);
                    $names=$assembly->signatures($d['motion_id'],$d['signatories']);
                    $p=new LegislativeResponsibilityPrincipal($world,['signatures'=>count($names)]);
                }else{
                    self::fields($d,['at','motion_id','signatures']);
                    if(!is_int($d['signatures']))throw new \InvalidArgumentException('Invalid signatures');
                }
                $c->fileMotion($p,$d['motion_id'],$at,$id);break;
            case 'responsibility_vote':
                self::pending($r,$b);
                if($assembly!==null){
                    self::fields($d,['at','motion_id']);
                    $roll=AssemblyRollCalls::resolve($assembly,$c,$d['motion_id'],'censure_49_3');
                    $p=new LegislativeResponsibilityPrincipal($world,['votes_for'=>$roll['tally']['for']]);
                }else{
                    self::fields($d,['at','motion_id','votes_for']);
                    if(!is_int($d['votes_for']))throw new \InvalidArgumentException('Invalid votes');
                }
                $c->vote($p,$d['motion_id'],$at,$id);break;
            case 'responsibility_appoint':
                self::fields($d,['at','appointment_id','prime_minister','resignation_presented']);
                $c->appointPrimeMinister($p,$d['appointment_id'],$at,$id);
                $r['last_appointment']=['day'=>$day,'prime_minister'=>$d['prime_minister'],'appointment_id'=>$d['appointment_id']];break;
            default:throw new \InvalidArgumentException('Unknown responsibility action');
        }
        if($assembly!==null)$r['assembly']=$assembly->snapshot();
        $r['crisis']=$c->snapshot();
        if(isset($r['cabinet']))$r['cabinet']=FrenchCabinet::sync($r['cabinet'],$r['crisis'],$day);
        return self::settle($r,$b,$day);
    }
    /** Independent confidence/censure procedures, not artificially attached
     * to a bill. This pilot serializes independent and linked proceedings. */
    public static function independent(array $r,array $e):array
    {
        if(!isset($r['assembly'])||$r['pending']!==null)throw new \DomainException('Independent Assembly proceeding unavailable');
        $d=$e['data'];$id=$e['evidence_id'];$world=$e['world_id'];$day=$e['day'];$at=$d['at']??null;
        if(!is_string($at)||substr($at,0,10)!==$day||!str_ends_with($at,'Z'))throw new \DomainException('Certified UTC instant required');
        FrenchConstitutionalCrisis::instant($at);
        $c=FrenchConstitutionalCrisis::restore($r['crisis']);$p=FrenchParliament::restore($r['assembly']);
        $principal=new LegislativeResponsibilityPrincipal($world,$d);
        if(isset($r['lifecycle'])&&in_array($e['kind'],['assembly_confidence_open','assembly_censure_file'],true)
            &&($r['lifecycle']['session']===null||!ParliamentaryLifecycle::quiet($r)))throw new \DomainException('No active session or proceeding already pending');
        switch($e['kind']){
            case 'assembly_cabinet_resign':
                if(!isset($r['cabinet']))throw new \DomainException('No cabinet pilot');
                self::fields($d,['at','prime_minister','resignation_id','resignation_presented']);
                if(!ParliamentaryLifecycle::quiet($r)||$d['resignation_presented']!==true||FrenchConstitutionalCrisis::instant($at)<$c->snapshot()['now'])
                    throw new \DomainException('Certified presentation and quiet proceedings required');
                $r['cabinet']=FrenchCabinet::resignation($r['cabinet'],$day,$d['prime_minister']);
                if($c->snapshot()['government']==='in_office')$c->presentResignation($principal,$d['resignation_id'],$at,$id);
                $result=['government'=>'caretaker','regime_changed'=>false];break;
            case 'assembly_cabinet_appoint':
                if(!isset($r['cabinet']))throw new \DomainException('No cabinet pilot');
                self::fields($d,['at','appointment_id','prime_minister','president','resignation_presented','presidential_act_certified','incompatibilities_cleared']);
                if(!ParliamentaryLifecycle::quiet($r)||$d['president']!==$r['cabinet']['president']||$d['presidential_act_certified']!==true||$d['incompatibilities_cleared']!==true)
                    throw new \DomainException('Certified presidential appointment required');
                $r['cabinet']=FrenchCabinet::appoint($r['cabinet'],$day,$d['prime_minister'],$p->snapshot());
                $result=$c->appointPrimeMinister($principal,$d['appointment_id'],$at,$id);
                break;
            case 'assembly_cabinet_portfolio':
                if(!isset($r['cabinet']))throw new \DomainException('No cabinet pilot');
                self::fields($d,['at','prime_minister','president','portfolio','person','pm_proposal_certified','presidential_act_certified','incompatibilities_cleared']);
                if(!ParliamentaryLifecycle::quiet($r)||FrenchConstitutionalCrisis::instant($at)<$c->snapshot()['now'])
                    throw new \DomainException('Portfolio change unavailable');
                $r['cabinet']=FrenchCabinet::reshuffle($r['cabinet'],$day,$d,$p->snapshot());
                $result=['portfolio'=>$d['portfolio'],'person'=>$d['person'],'regime_changed'=>false];break;
            case 'assembly_coalition':
                if(!isset($r['lifecycle']))throw new \DomainException('No lifecycle pilot');
                self::fields($d,['at','coalition_id','groups','active','agreement_certified']);
                if(!ParliamentaryLifecycle::quiet($r)||$d['agreement_certified']!==true||!is_bool($d['active'])||!is_array($d['groups']))
                    throw new \DomainException('Uncertified or overlapping coalition change');
                if(FrenchConstitutionalCrisis::instant($at)<$c->snapshot()['now'])throw new \DomainException('Backdated agreement');
                $result=$p->coalition($d['coalition_id'],$d['groups'],$d['active']);
                $r['lifecycle']['coalition_history'][]=['day'=>$day,'at'=>$at,'agreement'=>$result];break;
            case 'assembly_session':
                if(!isset($r['lifecycle']))throw new \DomainException('No lifecycle pilot');
                self::fields($d,['at','session_id','session_kind','end_day','opening_certified']);
                if(!ParliamentaryLifecycle::quiet($r)||$d['opening_certified']!==true)throw new \DomainException('Cannot change pending session');
                if($d['session_kind']==='ordinary'){
                    $session=ParliamentaryLifecycle::ordinarySession($day);
                    if($session===null||$day!==$session['start']||$d['session_id']!==$session['id']||$d['end_day']!==$session['end'])throw new \DomainException('Wrong ordinary session boundary');
                }elseif($d['session_kind']==='extraordinary'){
                    $end=\DateTimeImmutable::createFromFormat('!Y-m-d',$d['end_day']);
                    if(ParliamentaryLifecycle::ordinarySession($day)!==null||$end===false||$end->format('Y-m-d')!==$d['end_day']||$d['end_day']<$day
                        ||$d['end_day']>(new \DateTimeImmutable($day))->modify('+12 days')->format('Y-m-d')
                        ||$r['lifecycle']['session']!==null)throw new \DomainException('Invalid extraordinary session window');
                    $session=['id'=>$d['session_id'],'kind'=>'extraordinary','start'=>$day,'end'=>$d['end_day']];
                }else throw new \DomainException('Unknown session');
                $result=$c->openSession($principal,$d['session_id'],$at,$id);$r['lifecycle']['session']=$session;break;
            case 'assembly_confidence_open':
                self::fields($d,['at','statement_id','statement_kind','council_deliberated']);
                $result=$c->engageConfidence($principal,$d['statement_id'],$d['statement_kind'],$at,$id);break;
            case 'assembly_confidence_vote':
                self::fields($d,['at','statement_id']);
                $roll=AssemblyRollCalls::resolve($p,$c,$d['statement_id'],'confidence');
                $t=$roll['tally'];unset($t['absent']);
                $result=$c->voteConfidence(new LegislativeResponsibilityPrincipal($world,['confidence_ballot'=>$t]),$d['statement_id'],$at,$id);
                break;
            case 'assembly_censure_file':
                self::fields($d,['at','motion_id','signatories']);
                $names=$p->signatures($d['motion_id'],$d['signatories']);
                $result=$c->fileSpontaneousMotion(new LegislativeResponsibilityPrincipal($world,['signatories'=>$names]),$d['motion_id'],$at,$id);
                break;
            case 'assembly_censure_vote':
                self::fields($d,['at','motion_id']);
                $roll=AssemblyRollCalls::resolve($p,$c,$d['motion_id'],'censure_49_2');
                $result=$c->voteSpontaneousMotion(new LegislativeResponsibilityPrincipal($world,['votes_for'=>$roll['tally']['for']]),$d['motion_id'],$at,$id);
                break;
            case 'assembly_appoint':
                if(isset($r['cabinet']))throw new \DomainException('Use cabinet succession procedure');
                self::fields($d,['at','appointment_id','prime_minister','resignation_presented']);
                $result=$c->appointPrimeMinister($principal,$d['appointment_id'],$at,$id);
                $r['last_appointment']=['day'=>$day,'prime_minister'=>$d['prime_minister'],'appointment_id'=>$d['appointment_id']];break;
            default:throw new \InvalidArgumentException('Unknown Assembly procedure');
        }
        $r['assembly']=$p->snapshot();$r['crisis']=$c->snapshot();
        if(isset($r['cabinet']))$r['cabinet']=FrenchCabinet::sync($r['cabinet'],$r['crisis'],$day);
        return [$r,['procedure'=>$result,'responsibility'=>self::observation($r),'policy_authorized'=>false,
            ... (isset($roll)?['roll_call'=>$roll]:[])]];
    }

    /** Only create a clock event at the exact expiry boundary, making
     * day-by-day and jumped replay identical. Pending motions stay pending. */
    public static function advance(array $r,array $b,string $day):array
    {
        $c=FrenchConstitutionalCrisis::restore($r['crisis']);$s=$c->snapshot();
        $deadline=$s['current_bill']['censure_filing_deadline']??null;
        if($deadline!==null&&$s['now']<=$deadline&&FrenchConstitutionalCrisis::instant($day.'T00:00:00Z')>$deadline){
            $at=gmdate('Y-m-d\TH:i:s\Z',$deadline+1);
            $c->advance(new LegislativeResponsibilityPrincipal($s['world_id']),$at,'clock-'.substr(hash('sha256',$b['id'].'/'.$deadline),0,32));
            $r['crisis']=$c->snapshot();
            return self::settle($r,$b,substr($at,0,10));
        }
        return [$r,$b];
    }
    private static function settle(array $r,array $b,string $day):array
    {
        if($r['pending']===null||$r['crisis']['current_bill']!==null)return [$r,$b];
        self::pending($r,$b);
        $last=$r['crisis']['history'][array_key_last($r['crisis']['history'])];
        if($last['bill_id']!==$b['id'])throw new \DomainException('Bill outcome mismatch');
        $v=$r['pending']['version'];$r['pending']=null;
        if(!$last['assembly_adoption']){$b['status']='rejected';return [$r,$b];}
        $b['readings']['assembly']++;
        $b['last_votes']['assembly']=['day'=>$day,'version'=>$v,'vote'=>['passed'=>true,'route'=>'article_49_3']];
        $b['current']=$v;
        if(($b['last_votes']['senate']['vote']['passed']??false)&&$b['last_votes']['senate']['version']===$v){
            $b['status']='adopted';$b['final_version']=$v;$b['adopted_on']=$day;
        }else{$b['status']='navette';$b['next_chamber']='senate';}
        return [$r,$b];
    }
    public static function observation(array $r):array
    {
        return ['government'=>$r['crisis']['government'],'engagement_count'=>$r['engagement_count'],
            'last_engagement_day'=>$r['last_engagement_day'],'last_appointment'=>$r['last_appointment']];
    }
    private static function pending(array $r,array $b):void
    {if(($r['pending']['bill_id']??null)!==$b['id']||$b['status']!=='responsibility_pending')throw new \DomainException('No pending linked 49(3) bill');}
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Invalid responsibility evidence fields');}
}
