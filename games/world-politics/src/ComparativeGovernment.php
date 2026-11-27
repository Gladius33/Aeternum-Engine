<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/UsPresidentialElection.php';

/** Comparative accountability subset. Every count and procedural attestation
 * is certified by a simulation-side clerk, not submitted by a player.
 * Dates use UTC game days; hours are conservative scenario time boundaries.
 */
final class ComparativeGovernment
{
    public const VERSION='wp32-accountability-v1';
    public const COUNTRIES=['DE','ES','GB','IT','JP','US'];
    private array $state;
    public static function rules(string $country):array
    {
        return match($country) {
            'DE'=>['chambers'=>['bundestag'],'constructive'=>true,'wait_hours'=>48,'signatures_tenths'=>false,'majority'=>'members',
                'source'=>'https://www.gesetze-im-internet.de/gg/art_67.html','articles'=>['67'],'route'=>'constructive'],
            'ES'=>['chambers'=>['congress'],'constructive'=>true,'wait_hours'=>120,'signatures_tenths'=>true,'majority'=>'members',
                'source'=>'https://www.boe.es/buscar/act.php?id=BOE-A-1978-31229','articles'=>['113','114','115'],'route'=>'constructive'],
            'GB'=>['chambers'=>['commons'],'constructive'=>false,'wait_hours'=>0,'signatures_tenths'=>false,'majority'=>'cast',
                'source'=>'https://commonslibrary.parliament.uk/research-briefings/cbp-10915/','articles'=>['confidence convention'],'route'=>'resign_or_dissolve'],
            'IT'=>['chambers'=>['chamber','senate'],'constructive'=>false,'wait_hours'=>72,'signatures_tenths'=>true,'majority'=>'cast',
                'source'=>'https://www.senato.it/istituzione/la-costituzione/parte-ii/titolo-iii/sezione-i/articolo-94','articles'=>['94'],'route'=>'resign'],
            'JP'=>['chambers'=>['representatives'],'constructive'=>false,'wait_hours'=>0,'signatures_tenths'=>false,'majority'=>'present',
                'source'=>'https://www.sangiin.go.jp/eng/law/tcoj/','articles'=>['56','69'],'route'=>'resign_or_dissolve_10'],
            'US'=>['chambers'=>['house','senate'],'constructive'=>false,'wait_hours'=>0,'signatures_tenths'=>false,'majority'=>'cast',
                'source'=>'https://www.senate.gov/civics/constitution_item/constitution.htm','articles'=>['I.2','I.3','II.4'],'route'=>'impeachment'],
            default=>throw new \DomainException('No comparative accountability rulebook')
        };
    }
    public function __construct(string $world,string $country,string $day,string $incumbent,array $seats)
    {
        self::id($world);self::id($incumbent);self::day($day);
        $rules=self::rules($country);
        $keys=array_keys($seats);sort($keys);$ch=$rules['chambers'];sort($ch);
        if ($keys!==$ch) throw new \InvalidArgumentException('Chamber roster mismatch');
        foreach ($seats as $n) if (!is_int($n)||$n<1||$n>5000) throw new \InvalidArgumentException('Invalid certified seat count');
        ksort($seats);
        $this->state=['version'=>self::VERSION,'world_id'=>$world,'country'=>$country,'start_day'=>$day,'day'=>$day,
            'initial_incumbent'=>$incumbent,'incumbent'=>$incumbent,'seats'=>$seats,'status'=>'in_office','pending'=>null,
            'history'=>[],'breaches'=>0];
    }
    public function snapshot():array{return $this->state;}
    public function advance(string $day):void
    {
        self::day($day);
        if ($day<$this->state['day']) throw new \DomainException('Government clock cannot regress');
        if ($this->state['status']==='response_required' && $this->state['pending']['deadline']!==null
            && $day>$this->state['pending']['deadline']) {
            $this->state['status']='deadline_breached';$this->state['breaches']++;
        }
        $this->state['day']=$day;
    }
    /** Trusted evidence fields are fixed. Parliament clerk certifies filing,
     * ballot and trial procedure; constitutional clerk certifies succession
     * or resignation/dissolution. Evidence source is server-owned.
     */
    public function apply(string $action,string $role,array $e):array
    {
        if(in_array($action,['accountability_us_succession','accountability_us_vice_confirmation'],true))return $this->usSuccession($action,$role,$e);
        $common=['world_id','country','evidence_id','motion_id','at','procedure_certified'];
        $specific=match($action) {
            'accountability_file'=>['chamber','signatures','candidate','kind'],
            'accountability_vote'=>['chamber','present','yes','no','abstain'],
            'accountability_resolve'=>['outcome','successor'],
            default=>throw new \InvalidArgumentException('Unknown accountability action')
        };
        self::fields($e,[...$common,...$specific]);
        if ($role!==($action==='accountability_resolve'?'constitutional_clerk':'parliament_clerk')
            || $e['world_id']!==$this->state['world_id']||$e['country']!==$this->state['country']
            || $e['procedure_certified']!==true) throw new \DomainException('Uncertified institutional evidence');
        self::id($e['evidence_id']);self::id($e['motion_id']);
        if (!is_string($e['at'])||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$e['at']))
            throw new \InvalidArgumentException('Expected UTC evidence instant');
        $instant=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$e['at'],new \DateTimeZone('UTC'));
        if ($instant===false||$instant->format('Y-m-d\TH:i:s\Z')!==$e['at']||substr($e['at'],0,10)!==$this->state['day'])
            throw new \DomainException('Evidence must be certified for current game day');
        foreach ($this->state['history'] as $h) {
            if ($h['evidence']['evidence_id']===$e['evidence_id']||($h['evidence']['at']??$h['evidence']['day'].'T00:00:00Z')>$e['at'])
                throw new \DomainException('Replayed or backdated evidence');
            if ($action==='accountability_file'&&$h['action']===$action&&$h['evidence']['motion_id']===$e['motion_id'])
                throw new \DomainException('Duplicate motion identity');
        }
        $r=self::rules($this->state['country']);$p=$this->state['pending'];$status=$this->state['status'];
        if ($action==='accountability_file') {
            if ($status!=='in_office'||$p!==null) throw new \DomainException('An accountability procedure is already unresolved');
            $ch=$e['chamber'];$seats=$this->state['seats'][$ch]??null;
            if ($seats===null||($r['route']==='impeachment'&&$ch!=='house')) throw new \DomainException('Incompetent chamber');
            if ($e['kind']!==($r['route']==='impeachment'?'impeachment':'no_confidence')) throw new \DomainException('Wrong constitutional procedure');
            if (!is_int($e['signatures'])||$e['signatures']<0||$e['signatures']>$seats
                ||($r['signatures_tenths']&&$e['signatures']<intdiv($seats+9,10))) throw new \DomainException('Insufficient filing signatures');
            if ($r['constructive']) {self::id($e['candidate']);if($e['candidate']===$this->state['incumbent'])throw new \DomainException('Successor must differ');}
            elseif($e['candidate']!==null)throw new \DomainException('This procedure does not elect a successor');
            $this->state['pending']=['id'=>$e['motion_id'],'chamber'=>$ch,'filed_at'=>$e['at'],'candidate'=>$e['candidate'],
                'deadline'=>null,'stage'=>'filed'];
            $this->state['status']='motion_pending';
        } elseif ($action==='accountability_vote') {
            if ($p===null||$e['motion_id']!==$p['id']||!in_array($status,['motion_pending','senate_trial_pending'],true))
                throw new \DomainException('No matching pending vote');
            $senate=$status==='senate_trial_pending';
            if ($e['chamber']!==($senate?'senate':$p['chamber'])) throw new \DomainException('Wrong voting chamber');
            $seats=$this->state['seats'][$e['chamber']];
            foreach (['present','yes','no','abstain'] as $f) if(!is_int($e[$f])||$e[$f]<0||$e[$f]>$seats)throw new \InvalidArgumentException('Invalid ballot counts');
            if ($e['present']===0||$e['yes']+$e['no']+$e['abstain']!==$e['present'])
                throw new \DomainException('Invalid certified ballot arithmetic');
            $filed=new \DateTimeImmutable($p['filed_at']);
            if ($instant->getTimestamp()-$filed->getTimestamp()<$r['wait_hours']*3600)
                throw new \DomainException('Minimum procedural delay not elapsed');
            // Clerk certifies applicable quorum and eligibility rules.
            $threshold=$senate?intdiv(2*$e['present']+2,3):match($r['majority']) {
                'members'=>intdiv($seats,2)+1,'present'=>intdiv($e['present'],2)+1,
                'cast'=>intdiv($e['yes']+$e['no'],2)+1
            };
            $passed=$e['yes']>=$threshold;
            if (!$passed) {$this->state['pending']=null;$this->state['status']='in_office';}
            elseif ($r['route']==='impeachment'&&!$senate) {
                $this->state['status']='senate_trial_pending';$this->state['pending']['stage']='house_impeached';
            } else {
                $this->state['pending']['stage']=$senate?'convicted':'adopted';
                $this->state['status']=$senate?'removed_successor_pending':($r['constructive']?'appointment_required':'response_required');
                if($r['route']==='resign_or_dissolve_10')
                    $this->state['pending']['deadline']=$instant->modify('+10 days')->format('Y-m-d');
            }
            $result=['passed'=>$passed,'threshold'=>$threshold];
        } else {
            if ($p===null||$p['id']!==$e['motion_id'])throw new \DomainException('No matching constitutional resolution');
            if ($status==='appointment_required') {
                if($e['outcome']!=='appoint_successor'||$e['successor']!==$p['candidate'])throw new \DomainException('Constructive successor is binding');
                $this->state['incumbent']=$e['successor'];$this->state['status']='in_office';$this->state['pending']=null;
            } elseif (in_array($status,['response_required','deadline_breached'],true)) {
                $allowed=$r['route']==='resign'?['resign']:['resign','dissolve'];
                if(!in_array($e['outcome'],$allowed,true)||$e['successor']!==null
                    ||($status==='deadline_breached'&&$e['outcome']==='dissolve'))throw new \DomainException('Invalid constitutional response');
                $this->state['status']=$e['outcome']==='resign'?'caretaker':'election_pending';
            } elseif (in_array($status,['caretaker','election_pending','removed_successor_pending'],true)) {
                if($e['outcome']!=='certified_succession')throw new \DomainException('Certified succession required');
                self::id($e['successor']);
                $this->state['incumbent']=$e['successor'];$this->state['status']='in_office';$this->state['pending']=null;
            } else throw new \DomainException('No valid resolution at this stage');
        }
        $this->state['history'][]=['action'=>$action,'role'=>$role,'evidence'=>$e];
        return ['government_status'=>$this->state['status'],'incumbent'=>$this->state['incumbent'],'pending'=>$this->state['pending'],
            'regime_changed'=>false]+($result??[]);
    }
    private function usSuccession(string $action,string $role,array $e):array{
        $base=['world_id','country','evidence_id','at','procedure_certified','successor','term_end'];
        $confirm=$action==='accountability_us_vice_confirmation';
        self::fields($e,[...$base,...($confirm?['president','nomination_certified','house_yes','senate_yes']:['cause'])]);
        $g=$this->state;self::id($e['evidence_id']);self::id($e['successor']);
        $at=is_string($e['at'])?\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$e['at'],new \DateTimeZone('UTC')):false;
        if($role!=='constitutional_clerk'||$g['country']!=='US'||$e['country']!=='US'||$e['world_id']!==$g['world_id']
            ||$e['procedure_certified']!==true||$at===false||$at->format('Y-m-d\TH:i:s\Z')!==$e['at']||substr($e['at'],0,10)!==$g['day']
            ||$e['term_end']!==UsPresidentialElection::termEnd($g)||$g['day']>=$e['term_end'])
            throw new \DomainException('Invalid US constitutional succession evidence');
        foreach($g['history'] as $h)if($h['evidence']['evidence_id']===$e['evidence_id']||($h['evidence']['at']??$h['evidence']['day'].'T00:00:00Z')>$e['at'])
            throw new \DomainException('Replayed or backdated succession');
        $vp=UsPresidentialElection::vicePresident($g);
        if($confirm){
            $c=UsPresidentialElection::candidates($g['initial_incumbent']===UsPresidentialElection::initial(true));
            $terms=0;foreach($g['history'] as $h)if($h['action']==='us_presidential_inauguration'&&$h['evidence']['winner']===$e['successor'])$terms++;
            $limits=UsPresidentialElection::limitsAt($g,(new \DateTimeImmutable($g['day']))->modify('+1 day')->format('Y-m-d'),$c);
            if($vp!==null||$g['status']!=='in_office'||$g['pending']!==null||$e['president']!==$g['incumbent']||$e['nomination_certified']!==true
                ||$e['successor']===$g['incumbent']||!isset($c[$e['successor']])||$terms>=($limits[$e['successor']]??0))
                throw new \DomainException('No eligible vice presidential confirmation');
            foreach(['house','senate'] as $ch){$yes=$e[$ch.'_yes'];$n=$g['seats'][$ch];
                if(!is_int($yes)||$yes<=intdiv($n,2)||$yes>$n)throw new \DomainException('Both chambers must confirm vice president');}
            $result=['president'=>$g['incumbent'],'vice_president'=>$e['successor'],'term_end'=>$e['term_end'],'regime_changed'=>false];
        }else{
            if($vp===null||$e['successor']!==$vp||$vp===$g['incumbent']||!in_array($e['cause'],['resignation','death','removal'],true)
                ||($e['cause']==='removal'?$g['status']!=='removed_successor_pending':($g['status']!=='in_office'||$g['pending']!==null)))
                throw new \DomainException('Vice president must fill certified presidential vacancy');
            $this->state['incumbent']=$vp;$this->state['pending']=null;$this->state['status']='in_office';
            $result=['president'=>$vp,'vice_president'=>null,'term_end'=>$e['term_end'],'regime_changed'=>false];
        }
        $this->state['history'][]=['action'=>$action,'role'=>$role,'evidence'=>$e];return $result;
    }
    /** Verified formation evidence originates in the institutional store;
     * this route is not available to a player or ordinary confidence clerk.
     */
    /** A fresh ordinary ballot can replace a constitutionally recorded
     * caretaker. It must postdate the resignation/dissolution. This does
     * not bypass a motion, trial, deadline or unresolved appointment. */
    public static function formationAvailable(array $g,?string $poll=null):bool
    {
        if($g['country']==='US')return false;
        if($g['status']==='in_office')return $g['pending']===null;
        if($poll===null||!in_array($g['status'],['caretaker','election_pending'],true))return false;
        $last=$g['history'][array_key_last($g['history'])]??null;
        return $last!==null&&$last['action']==='accountability_resolve'
            &&$last['evidence']['outcome']===($g['status']==='caretaker'?'resign':'dissolve')
            &&substr($last['evidence']['at'],0,10)<$poll&&$poll<$g['day'];
    }
    public function installCertifiedFormation(array $e,array $formation,?string $poll=null):void
    {
        self::fields($e,['world_id','country','evidence_id','coalition_id','candidate','day','procedure_certified']);
        self::fields($formation,['candidate','parties','support_seats','programme','on','status','evidence_id']);
        if(!self::formationAvailable($this->state,$poll)
            ||$e['world_id']!==$this->state['world_id']||$e['country']!==$this->state['country']||$e['day']!==$this->state['day']
            ||$e['procedure_certified']!==true||$formation['candidate']!==$e['candidate']||$formation['evidence_id']!==$e['evidence_id']
            ||$formation['on']!==$e['day']||$formation['status']!=='certified_scenario_formation')
            throw new \DomainException('Formation cannot supersede unresolved constitutional procedure');
        self::id($e['evidence_id']);self::id($e['candidate']);self::id($e['coalition_id']);
        $lower=$this->state['seats'][self::rules($this->state['country'])['chambers'][0]];
        if(!is_int($formation['support_seats'])||$formation['support_seats']<=intdiv($lower,2)||$formation['support_seats']>$lower)
            throw new \DomainException('Formation lacks certified majority');
        foreach($this->state['history'] as $h){
            if($h['evidence']['evidence_id']===$e['evidence_id']||substr($h['evidence']['at']??$h['evidence']['day'],0,10)>$e['day'])
                throw new \DomainException('Replayed or backdated formation');
        }
        $recovery=$this->state['status']!=='in_office';
        if($recovery)self::day($poll);
        $this->state['incumbent']=$e['candidate'];$this->state['status']='in_office';$this->state['pending']=null;
        $this->state['history'][]=['action'=>'certified_electoral_formation','role'=>'constitutional_clerk','evidence'=>$e,'formation'=>$formation,...($recovery?['formation_poll'=>$poll]:[])];
    }
    public function installUsPresidential(array $proof):void
    {
        $year=$proof['year']??null;
        if(!is_int($year)||$this->state['country']!=='US'||$this->state['status']!=='in_office'||$this->state['pending']!==null
            ||$this->state['day']!==UsPresidentialElection::dates($year)['inauguration']||UsPresidentialElection::result($this->state,$year,$proof['version']??'')!==$proof
            ||$proof['winner']===null||(($proof['version']??null)===UsPresidentialElection::VERSION_V3&&$proof['vice_winner']===null))throw new \DomainException('Unresolved US presidential accession');
        foreach($this->state['history'] as $h){
            if($h['action']==='us_presidential_inauguration'&&$h['evidence']['year']===$year)
                throw new \DomainException('Duplicate US election');
            if($h['action']==='accountability_resolve')throw new \DomainException('Exceptional US succession requires separate term eligibility evidence');
        }
        $this->state['incumbent']=$proof['winner'];
        $this->state['history'][]=['action'=>'us_presidential_inauguration','role'=>'constitutional_clerk','evidence'=>$proof];
    }
    public static function restore(array $s):self
    {
        $x=new self($s['world_id'],$s['country'],$s['start_day'],$s['initial_incumbent'],$s['seats']);
        if (!is_array($s['history'])||!array_is_list($s['history'])||count($s['history'])>10000)throw new \DomainException('Invalid government journal');
        foreach($s['history'] as $h) {
            if($h['action']==='certified_electoral_formation'){
                $x->advance($h['evidence']['day']);$x->installCertifiedFormation($h['evidence'],$h['formation'],$h['formation_poll']??null);
            }elseif($h['action']==='us_presidential_inauguration'){
                $x->advance($h['evidence']['dates']['inauguration']);$x->installUsPresidential($h['evidence']);
            }else{
                $x->advance(substr($h['evidence']['at'],0,10));$x->apply($h['action'],$h['role'],$h['evidence']);
            }
        }
        $x->advance($s['day']);
        if ($x->snapshot()!==$s)throw new \DomainException('Government snapshot failed deterministic replay');
        return $x;
    }
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Unexpected evidence fields');}
    private static function id(mixed $id):void{if(!is_string($id)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id))throw new \InvalidArgumentException('Invalid evidence identifier');}
    private static function day(string $day):void{$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$day);if($d===false||$d->format('Y-m-d')!==$day)throw new \InvalidArgumentException('Invalid date');}
}
