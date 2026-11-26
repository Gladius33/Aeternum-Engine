<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** A conservative, source-labelled subset of ordinary government formation.
 * Evidence is supplied by a trusted simulation clerk, never a player.
 * A political recommendation alone has no constitutional effect. */
final class FormationProtocol
{
    public const VERSION='wp63-formation-protocol-v1';
    public static function references():array
    {
        return [
            'DE'=>['office'=>'chancellor','route'=>'nomination_election_appointment','articles'=>['63','69'],'source'=>'https://www.gesetze-im-internet.de/englisch_gg/','supported'=>'ordinary_absolute_majority'],
            'ES'=>['office'=>'prime_minister','route'=>'consultation_nomination_investiture_appointment','articles'=>['99','101'],'source'=>'https://www.boe.es/buscar/act.php?id=BOE-A-1978-31229','supported'=>'same_candidate_absolute_then_simple_48h'],
            'GB'=>['office'=>'prime_minister','route'=>'confidence_assessment_royal_appointment','articles'=>['confidence_convention'],'source'=>'https://www.legislation.gov.uk/ukpga/2026/17/notes/division/3/index.htm','supported'=>'majority_confidence_assessment'],
            'IT'=>['office'=>'prime_minister','route'=>'appointment_bicameral_confidence','articles'=>['92','94'],'source'=>'https://www.senato.it/istituzione/la-costituzione/parte-ii/titolo-iii/sezione-i/articolo-94','supported'=>'both_chambers_within_10_days'],
            'JP'=>['office'=>'prime_minister','route'=>'diet_designation_imperial_appointment','articles'=>['6','67','70','71'],'source'=>'https://www.shugiin.go.jp/internet/itdb_kenpou.nsf/html/kenpou/en/constitution.htm','supported'=>null,'blocker'=>'upper_house_not_modelled'],
            'US'=>['office'=>'president','route'=>'separate_presidential_election','articles'=>['II','12th Amendment','20th Amendment','22nd Amendment'],'source'=>'https://www.archives.gov/founding-docs/amendments-11-27','supported'=>null,'blocker'=>'not_parliamentary_formation'],
            'CH'=>['office'=>'federal_council','route'=>'joint_assembly_elects_seven_councillors','articles'=>['175','176'],'source'=>'https://www.admin.ch/en/federal-council-election-procedure','supported'=>null,'annual_presidency_is_separate'=>true],
            'FI'=>['office'=>'prime_minister','route'=>'parliament_election_presidential_appointment','articles'=>['61'],'source'=>'https://www.finlex.fi/fi/lainsaadanto/1999/731','supported'=>null],
            'SE'=>['office'=>'prime_minister','route'=>'speaker_proposal_negative_parliamentarism','articles'=>['Instrument of Government Ch. 6'],'source'=>'https://www.riksdagen.se/en/how-the-riksdag-works/democracy/forming-a-government/','supported'=>null],
            'PL'=>['office'=>'prime_minister','route'=>'presidential_appointment_sejm_confidence','articles'=>['154','155'],'source'=>'https://www.sejm.gov.pl/prawo/konst/angielski/konse.htm','supported'=>null],
            'PT'=>['office'=>'prime_minister','route'=>'presidential_appointment_after_consultations','articles'=>['187'],'source'=>'https://www.parlamento.pt/sites/EN/Parliament/Paginas/Competences.aspx','supported'=>null],
        ];
    }
    public static function bootstrap(array $s):array
    {
        if(!isset($s['foreign_recurring'])||isset($s['formation_protocol'])||$s['events']!==[])
            throw new \DomainException('Formation protocol must be pinned at bootstrap');
        $s['formation_protocol']=self::VERSION;
        return $s;
    }
    private static function keys(array $a,array $b):void
    {
        $k=array_keys($a);sort($k);sort($b);
        if($k!==$b)throw new \InvalidArgumentException('Unexpected formation evidence fields');
    }
    private static function instant(mixed $at):\DateTimeImmutable
    {
        if(!is_string($at)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$at,new \DateTimeZone('UTC')))===false
            ||$d->format('Y-m-d\TH:i:s\Z')!==$at)throw new \InvalidArgumentException('Invalid formation evidence instant');
        return $d;
    }
    /** Validates the independent procedural record, returning the legacy
     * certificate only after all supported national conditions are met.
     * Partial/minority and exceptional routes deliberately fail closed. */
    public static function certificate(array $e,array $cycle,array $seats):array
    {
        self::keys($e,['world_id','country','evidence_id','coalition_id','candidate','day','procedure_certified','procedure']);
        $p=$e['procedure'];$cc=$e['country'];$c=$cycle['coalition']??null;
        if(!is_array($p)||!is_array($c)||$e['world_id']!==$cycle['world_id']||$cc!==$cycle['country']
            ||$e['coalition_id']!==$c['id']||$e['candidate']!==$c['candidate']||$e['procedure_certified']!==true
            ||$c['status']!=='majority_agreement'||$cycle['status']!=='results')
            throw new \DomainException('Formation certificate not bound to a majority coalition');
        self::keys($p,['poll_day','steps']);
        if($p['poll_day']!==$cycle['config']['poll_day']||!is_array($p['steps'])||!array_is_list($p['steps'])||count($p['steps'])>5)
            throw new \DomainException('Formation proof not bound to current election');
        $steps=$p['steps'];$last=null;
        foreach($steps as $s){
            if(!is_array($s)||!isset($s['kind'],$s['at']))throw new \InvalidArgumentException('Missing procedure step');
            $t=self::instant($s['at']);
            if(substr($s['at'],0,10)<=$p['poll_day']||substr($s['at'],0,10)>$e['day']||($last!==null&&$t<=$last))
                throw new \DomainException('Formation steps out of order or outside election');
            $last=$t;
            if(str_ends_with($s['kind'],'_vote')){
                self::keys($s,['kind','at','chamber','yes','no','abstain']);
                $n=$seats[$s['chamber']]??null;
                if(!is_int($n)||$n<1)throw new \DomainException('Chamber roster unavailable');
                foreach(['yes','no','abstain'] as $k)if(!is_int($s[$k])||$s[$k]<0||$s[$k]>$n)
                    throw new \InvalidArgumentException('Invalid formation ballot count');
                $present=$s['yes']+$s['no']+$s['abstain'];
                if($present>$n||$present<=intdiv($n,2))throw new \DomainException('Invalid formation quorum');
            }else self::keys($s,['kind','at']);
        }
        if($last===null||$last->format('Y-m-d')!==$e['day'])throw new \DomainException('Certification must match last step day');
        $k=array_column($steps,'kind');
        $absolute=static fn(array $s,string $ch):bool=>$s['chamber']===$ch&&$s['yes']>intdiv($seats[$ch],2);
        $simple=static fn(array $s,string $ch):bool=>$s['chamber']===$ch&&$s['yes']>$s['no'];
        $ok=match($cc){
            'DE'=>$k===['presidential_nomination','chancellor_vote','presidential_appointment']&&$absolute($steps[1],'bundestag'),
            'ES'=>($k===['royal_nomination_after_consultations','investiture_vote','royal_appointment']&&$absolute($steps[1],'congress'))
                ||($k===['royal_nomination_after_consultations','investiture_vote','investiture_vote','royal_appointment']
                   &&$steps[1]['chamber']==='congress'&&!$absolute($steps[1],'congress')&&$simple($steps[2],'congress')
                   &&self::instant($steps[2]['at'])->getTimestamp()-self::instant($steps[1]['at'])->getTimestamp()===172800),
            'GB'=>$k===['commons_confidence_assessed','royal_appointment'],
            'IT'=>$k===['presidential_appointment','confidence_vote','confidence_vote']
                &&(($simple($steps[1],'chamber')&&$simple($steps[2],'senate'))||($simple($steps[1],'senate')&&$simple($steps[2],'chamber')))
                &&self::instant($steps[2]['at'])->getTimestamp()-self::instant($steps[0]['at'])->getTimestamp()<=864000,
            default=>false
        };
        if(!$ok)throw new \DomainException('National formation procedure not satisfied or not implemented');
        unset($e['procedure']);return $e;
    }
    /** Verify every persisted formation proof against the corresponding archived
     * or current electoral cycle and the replayed accountability journal. */
    public static function validateHistory(array $s):void
    {
        if($s['formation_protocol']!==self::VERSION||!isset($s['foreign_recurring'],$s['accountability'],$s['electoral']))
            throw new \DomainException('Invalid formation protocol pin');
        $cycles=[];
        foreach([...$s['foreign_recurring']['archive'],$s['electoral']] as $c){
            if($c['government']!==null){
                $id=$c['government']['evidence_id'];
                if(isset($cycles[$id]))throw new \DomainException('Duplicated formation across elections');
                $cycles[$id]=$c;
            }
        }
        $proofs=[];
        foreach($s['events'] as $event){
            if($event['kind']!=='electoral_certify')continue;
            $proof=$event['result']['national_proof']??null;
            if(!is_array($proof))throw new \DomainException('Missing national formation proof');
            $id=$proof['evidence_id']??'';
            if(isset($proofs[$id])||!isset($cycles[$id])||$event['on']!==$proof['day']||$event['actor_role']!=='constitutional_clerk')
                throw new \DomainException('Unmatched formation proof');
            $base=self::certificate($proof,$cycles[$id],$s['accountability']['seats']);
            $result=$event['result'];unset($result['national_proof']);
            if($result!==$cycles[$id]['government'])throw new \DomainException('Formation event differs from electoral result');
            $proofs[$id]=['evidence'=>$base,'formation'=>$result];
        }
        $seen=[];
        foreach($s['accountability']['history'] as $h){
            if($h['action']!=='certified_electoral_formation')continue;
            $id=$h['evidence']['evidence_id'];
            if(isset($seen[$id])||!isset($proofs[$id])||$proofs[$id]!==['evidence'=>$h['evidence'],'formation'=>$h['formation']])
                throw new \DomainException('Government journal lacks matching national proof');
            $seen[$id]=true;
        }
        if(count($cycles)!==count($proofs)||count($proofs)!==count($seen))throw new \DomainException('Incomplete formation chain');
    }
}
