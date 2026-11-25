<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrenchConstitutionalCrisis.php';
require_once __DIR__.'/AssemblyRollCalls.php';

/** A replay-only principal, built exclusively from clerk-certified scenario
 * evidence inside the legislative aggregate. No HTTP credential or role grant. */
final class LegislativeResponsibilityPrincipal implements FrenchPresidentialAppointmentPrincipal,FrenchParliamentaryPrincipal
{
    public function __construct(private readonly string $world,private readonly array $d=[]){}
    public function actorId():string{return 'legislative-responsibility-clerk';}
    public function allows(string $world,string $ability):bool{return $world===$this->world&&in_array($ability,
        ['fr.49_3.engage','fr.censure.file','fr.censure.vote','fr.constitution.clock','fr.president.appoint_pm','fr.49_1.engage','fr.49_1.vote','fr.49_2.file','fr.49_2.vote'],true);}
    public function councilDeliberated(string $world,string $billId):bool{return $world===$this->world&&($this->d['council_deliberated']??false)===true;}
    public function verifiedBillCategory(string $world,string $billId):string{return 'ordinary';}
    public function verifiedSignatures(string $world,string $motionId):int{return $this->d['signatures']??0;}
    public function verifiedVotesFor(string $world,string $motionId):int{return $this->d['votes_for']??0;}
    public function verifiedDeputySignatories(string $world,string $motionId):array{return $this->d['signatories']??[];}
    public function verifiedConfidenceBallot(string $world,string $statementId):array{return $this->d['confidence_ballot']??[];}
    public function verifiedSessionKind(string $world,string $session):string{return 'ordinary';}
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
        $r['crisis']=$c->snapshot();return self::settle($r,$b,$day);
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
        switch($e['kind']){
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
                self::fields($d,['at','appointment_id','prime_minister','resignation_presented']);
                $result=$c->appointPrimeMinister($principal,$d['appointment_id'],$at,$id);
                $r['last_appointment']=['day'=>$day,'prime_minister'=>$d['prime_minister'],'appointment_id'=>$d['appointment_id']];break;
            default:throw new \InvalidArgumentException('Unknown Assembly procedure');
        }
        $r['assembly']=$p->snapshot();$r['crisis']=$c->snapshot();
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
