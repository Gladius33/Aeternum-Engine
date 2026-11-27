<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/DocumentaryForeignBallot.php';

/** Separate presidential calendar and deterministic counterfactual elector
 * fixture. Not a candidacy, polling forecast or real-world voting tool.
 * Source allocation: https://www.archives.gov/electoral-college/allocation
 * 2020 Census allocation frozen for the entire simulated scenario. */
final class UsPresidentialElection
{
    public const VERSION='wp119-us-presidential-v1';
    public const VERSION_V2='wp119-us-presidential-v2';
    public const VERSION_V3='wp119-us-presidential-v3';
    private const WEIGHTS=['AL'=>9,'AK'=>3,'AZ'=>11,'AR'=>6,'CA'=>54,'CO'=>10,'CT'=>7,'DE'=>3,'DC'=>3,'FL'=>30,'GA'=>16,'HI'=>4,'ID'=>4,'IL'=>19,'IN'=>11,'IA'=>6,'KS'=>6,'KY'=>8,'LA'=>8,'ME'=>4,'MD'=>10,'MA'=>11,'MI'=>15,'MN'=>10,'MS'=>6,'MO'=>10,'MT'=>4,'NE'=>5,'NV'=>6,'NH'=>4,'NJ'=>14,'NM'=>5,'NY'=>28,'NC'=>16,'ND'=>3,'OH'=>17,'OK'=>7,'OR'=>8,'PA'=>19,'RI'=>4,'SC'=>9,'SD'=>3,'TN'=>11,'TX'=>40,'UT'=>6,'VT'=>3,'VA'=>13,'WA'=>12,'WV'=>4,'WI'=>10,'WY'=>3];
    private static function observation(string $name):string{return 'observation-us-'.substr(hash('sha256',$name),0,32);}
    public static function initial(bool $career):string{return $career?'real-us-donald-j-trump':self::observation('Donald J. Trump');}
    /** Source-observed people are a scenario cast only. No asserted future
     * candidacy or eligibility certification outside the term-count fixture. */
    public static function candidates(bool $career):array{
        $out=[];
        foreach(DocumentaryForeignBallot::reference('US',$career)['parties'] as $p)
            $out[$p['person_id']]=['id'=>$p['person_id'],'name'=>$p['name'],'source'=>$p['source'],'party'=>$p['party_id']];
        foreach([['JD Vance','republican'],['Kamala D. Harris','democrat'],['Tim Walz','democrat'],['Marco A. Rubio','republican']] as [$name,$party]){
            $id=$career&&$name==='Marco A. Rubio'?'real-us-marco-a-rubio':self::observation($name);
            $source=$name==='Marco A. Rubio'?'https://stateintegrity.org.uk/power/world-leaders':'https://www.archives.gov/electoral-college/2024';
            $out[$id]=compact('id','name','source','party');
        }
        ksort($out,SORT_STRING);return $out;
    }
    public static function extraCareers():array{
        $out=[];$old=DocumentaryForeignBallot::extraCareers('US');
        foreach(self::candidates(true) as $id=>$p){
            if(isset($old[$id])||$id==='real-us-marco-a-rubio')continue;
            $out[$id]=['id'=>$id,'person_reference'=>'person-'.$id,'name'=>$p['name'],'source'=>$p['source'],'observed_on'=>'2024-11-05',
                'publication_state'=>'documented_ticket_only','observed_roles'=>[],'party_id'=>$p['party'],'programme_complete'=>false];
        }
        return $out;
    }
    public static function people():array{
        $out=[];foreach(self::candidates(false) as $id=>$p)$out[$id]=['id'=>$id,'kind'=>'real','name'=>$p['name'],'country'=>'US',
            'biography'=>'Documentary reference; simulated presidential cast, not a future candidacy.','as_of'=>in_array($p['name'],['JD Vance','Kamala D. Harris','Tim Walz'],true)?'2024-11-05':'2026-11-27','sources'=>[$p['source']],'programme'=>[]];
        return $out;
    }
    public static function dates(int $year):array{
        if($year<2028||$year>2044||$year%4!==0)throw new \DomainException('Unsupported US election cycle');
        $nov=new \DateTimeImmutable($year.'-11-01',new \DateTimeZone('UTC'));
        while($nov->format('N')!=='1')$nov=$nov->modify('+1 day');
        $dec=new \DateTimeImmutable($year.'-12-01',new \DateTimeZone('UTC'));
        while($dec->format('N')!=='3')$dec=$dec->modify('+1 day');
        return ['ballot'=>$nov->modify('+1 day')->format('Y-m-d'),'electors'=>$dec->modify('+13 days')->format('Y-m-d'),
            'count'=>($year+1).'-01-06','inauguration'=>($year+1).'-01-20'];
    }
    public static function termEnd(array $g):string{
        $end='2029-01-20';
        foreach($g['history'] as $h)if($h['action']==='us_presidential_inauguration')
            $end=(new \DateTimeImmutable($h['evidence']['day']))->modify('+4 years')->format('Y-m-d');
        return $end;
    }
    public static function vicePresident(array $g):?string{
        if($g['country']!=='US'||!in_array($g['initial_incumbent'],[self::initial(true),self::initial(false)],true))throw new \DomainException('Unsupported US opening');
        $vp=self::observation('JD Vance');
        foreach($g['history'] as $h){
            if($h['action']==='us_presidential_inauguration')$vp=$h['evidence']['vice_winner']??null;
            elseif($h['action']==='accountability_us_succession')$vp=null;
            elseif($h['action']==='accountability_us_vice_confirmation')$vp=$h['evidence']['successor'];
        }
        return $vp;
    }
    /** Day-level conservative eligibility at a fixed cutoff. The active
     * successor is projected to the original term end. Later evidence
     * cannot retrospectively rewrite an electoral certificate. */
    public static function limitsAt(array $g,string $cutoff,array $candidates):array{
        $limits=array_fill_keys(array_keys($candidates),2);$groups=[];
        foreach($g['history'] as $h)if($h['action']==='accountability_us_succession'){
            $e=$h['evidence'];$day=substr($e['at'],0,10);
            if($day<$cutoff)$groups[$e['term_end']][]=['day'=>$day,'id'=>$e['successor']];
        }
        foreach($groups as $end=>$entries){
            $days=[];
            foreach($entries as $i=>$e){
                $stop=$entries[$i+1]['day']??$end;
                $days[$e['id']]=($days[$e['id']]??0)+(int)(new \DateTimeImmutable($e['day']))->diff(new \DateTimeImmutable($stop))->format('%r%a');
            }
            $threshold=(int)(new \DateTimeImmutable($end))->modify('-2 years')->diff(new \DateTimeImmutable($end))->format('%a');
            foreach($days as $id=>$n)if($n>$threshold&&isset($limits[$id]))$limits[$id]=1;
        }
        return $limits;
    }
    private static function viceTicket(array $g,int $year,array $nominees,array $candidates,array $terms,array $limits):array{
        $out=[];$used=array_values($nominees);
        foreach($nominees as $party=>$id){
            $eligible=array_keys(array_filter($candidates,fn($p)=>!in_array($p['id'],$used,true)&&$terms[$p['id']]<$limits[$p['id']]));
            usort($eligible,fn($a,$b)=>(($candidates[$a]['party']===$party?0:1)<=>($candidates[$b]['party']===$party?0:1))
                ?:strcmp(hash('sha256',self::VERSION_V3.'|vice|'.$g['world_id'].'|'.$year.'|'.$a),hash('sha256',self::VERSION_V3.'|vice|'.$g['world_id'].'|'.$year.'|'.$b)));
            if($eligible===[])throw new \DomainException('No distinct eligible synthetic vice nominee');
            $out[$party]=$eligible[0];$used[]=$eligible[0];
        }
        return $out;
    }
    private static function contingentSenate(string $world,int $year,array $nominees):array{
        $ids=array_values($nominees);$rounds=[];$winner=null;
        for($r=0;$r<14;$r++){
            $day=(new \DateTimeImmutable(($year+1).'-01-06'))->modify('+'.$r.' days')->format('Y-m-d');
            $votes=array_fill_keys($ids,0);
            for($i=0;$i<100;$i++){
                $draw=hexdec(substr(hash('sha256',self::VERSION_V3.'|senate|'.$world.'|'.$year.'|'.$r.'|'.$i),0,8))%2;
                $votes[$ids[$draw]]++;
            }
            foreach($votes as $id=>$n)if($n>=51)$winner=$id;
            $rounds[]=['day'=>$day,'present'=>100,'votes'=>$votes,'winner'=>$winner];if($winner!==null)break;
        }
        return ['classification'=>'fictional_uncalibrated_senate_ballot','quorum'=>67,'required_votes'=>51,'rounds'=>$rounds,'winner'=>$winner];
    }
    /** Historical elected terms are distinguished from later simulated ones.
     * Unknown/exceptional succession is not inferred from congressional votes. */
    public static function result(array $g,int $year,string $version=self::VERSION):array{
        if(!in_array($version,[self::VERSION,self::VERSION_V2,self::VERSION_V3],true))throw new \DomainException('Unknown US presidential rule pin');
        $dates=self::dates($year);
        if($g['country']!=='US'||!in_array($g['initial_incumbent'],[self::initial(true),self::initial(false)],true))
            throw new \DomainException('Unsupported US presidential opening');
        $career=$g['initial_incumbent']===self::initial(true);
        $candidates=self::candidates($career);$terms=array_fill_keys(array_keys($candidates),0);$terms[$g['initial_incumbent']]=2;
        foreach($g['history'] as $h){
            if($h['action']==='us_presidential_inauguration'&&$h['evidence']['year']<$year){
                $winner=$h['evidence']['winner'];if(!isset($terms[$winner])||++$terms[$winner]>2)throw new \DomainException('US election term limit');
            }
        }
        $limits=$version===self::VERSION_V3?self::limitsAt($g,$dates['ballot'],$candidates):array_fill_keys(array_keys($candidates),2);
        $nominees=[];
        foreach(['republican','democrat'] as $party){
            $eligible=array_keys(array_filter($candidates,fn($p)=>$p['party']===$party&&$terms[$p['id']]<$limits[$p['id']]));
            usort($eligible,fn($x,$y)=>strcmp(hash('sha256',self::VERSION.'|'.$g['world_id'].'|'.$year.'|'.$x),hash('sha256',self::VERSION.'|'.$g['world_id'].'|'.$year.'|'.$y)));
            if($eligible===[])throw new \DomainException('No eligible synthetic party nominee');
            $nominees[$party]=$eligible[0];
        }
        $votes=array_fill_keys(array_values($nominees),0);$units=[];
        $draw=static fn(string $key):int=>hexdec(substr(hash('sha256',self::VERSION.'|'.$g['world_id'].'|'.$year.'|'.$key),0,8))%2;
        foreach(self::WEIGHTS as $state=>$n){
            if(in_array($state,['ME','NE'],true)){
                $districts=[];for($i=1;$i<=$n-2;$i++)$districts[$i]=$draw($state.'-'.$i);
                // Equal-size fictional districts. Resolve exact aggregate ties
                // by an independent deterministic statewide pilot draw.
                $sum=array_sum($districts);$atLarge=$sum*2===count($districts)?$draw($state.'-state'):(int)($sum*2>count($districts));
                $parts=['at-large'=>[2,$atLarge]];foreach($districts as $i=>$d)$parts['district-'.$i]=[1,$d];
            }else $parts=['state'=>[$n,$draw($state)]];
            foreach($parts as $part=>[$weight,$d]){$id=array_values($nominees)[$d];$votes[$id]+=$weight;$units[$state.'-'.$part]=['electors'=>$weight,'winner'=>$id];}
        }
        if(array_sum($votes)!==538)throw new \LogicException('Electoral votes do not conserve 538');
        $winner=null;foreach($votes as $id=>$v)if($v>=270)$winner=$id;
        $result=['version'=>$version,'classification'=>'fictional_uncalibrated_presidential_ballot','world_id'=>$g['world_id'],'evidence_id'=>'us-president-'.$year.'-inauguration','day'=>$dates['inauguration'],'year'=>$year,'dates'=>$dates,'nominees'=>$nominees,
            'prior_elected_terms'=>$terms,'units'=>$units,'electoral_votes'=>$votes,'winner'=>$winner,'allocation_frozen_from_2020_census'=>true];
        if(in_array($version,[self::VERSION_V2,self::VERSION_V3],true)){
            $result['contingent_house']=$winner===null?self::contingentHouse($g['world_id'],$year,$nominees):null;
            if($result['contingent_house']!==null)$result['winner']=$result['contingent_house']['winner'];
        }
        if($version===self::VERSION_V3){
            $vice=self::viceTicket($g,$year,$nominees,$candidates,$terms,$limits);$vv=[];
            foreach($vice as $party=>$id)$vv[$id]=$votes[$nominees[$party]];
            $vw=null;foreach($vv as $id=>$n)if($n>=270)$vw=$id;
            $senate=$vw===null?self::contingentSenate($g['world_id'],$year,$vice):null;
            $result+=['election_limits'=>$limits,'vice_nominees'=>$vice,'vice_electoral_votes'=>$vv,'contingent_senate'=>$senate,'vice_winner'=>$senate===null?$vw:$senate['winner']];
        }
        return $result;
    }
    /** Twelve Amendment fixture: every state delegation is represented;
     * one vote per state, not per representative. DC is not a state.
     * Separate synthetic delegation ballots are not popular vote results.
     * Each day is a modeled repeat ballot, not a legal scheduling claim. */
    private static function contingentHouse(string $world,int $year,array $nominees):array{
        $ids=array_values($nominees);$rounds=[];$winner=null;
        $states=array_values(array_diff(array_keys(self::WEIGHTS),['DC']));
        for($round=0;$round<14;$round++){
            $day=(new \DateTimeImmutable(($year+1).'-01-06'))->modify('+'.$round.' days')->format('Y-m-d');
            $votes=array_fill_keys($ids,0);$delegations=[];
            foreach($states as $state){
                $index=hexdec(substr(hash('sha256',self::VERSION_V2.'|house|'.$world.'|'.$year.'|'.$round.'|'.$state),0,8))%2;
                $delegations[$state]=$ids[$index];$votes[$ids[$index]]++;
            }
            foreach($votes as $id=>$n)if($n>=26)$winner=$id;
            $rounds[]=['day'=>$day,'represented_states'=>count($states),'delegations'=>$delegations,'votes'=>$votes,'winner'=>$winner];
            if($winner!==null)break;
        }
        return ['classification'=>'fictional_uncalibrated_state_delegations','required_states'=>26,'quorum_states'=>34,'rounds'=>$rounds,'winner'=>$winner];
    }
    /** Before accession, disclose only contingent ballots already held. */
    private static function stageResult(array $g,int $year,string $version,string $day):array{
        $r=self::result($g,$year,$version);
        if($version===self::VERSION||$day===$r['day'])return $r;
        foreach(['contingent_house'=>'winner','contingent_senate'=>'vice_winner'] as $key=>$winner){
            if(!isset($r[$key]))continue;
            $rounds=array_values(array_filter($r[$key]['rounds'],fn($x)=>$x['day']<=$day));
            $r[$key]['rounds']=$rounds;$r[$key]['winner']=$rounds===[]?null:$rounds[array_key_last($rounds)]['winner'];
            $r[$winner]=$r[$key]['winner'];
        }
        return $r;
    }
    public static function bootstrap(array $s,bool $extended=false,bool $succession=false):array{
        if($s['country_code']!=='US'||$s['day']!=='2027-05-14'||$s['events']!==[]||isset($s['us_presidential'])
            ||!in_array($s['accountability']['initial_incumbent']??null,[self::initial(true),self::initial(false)],true))
            throw new \DomainException('US presidential cycle must be pinned at documented opening');
        if($succession&&!$extended)throw new \DomainException('Succession requires extended US pin');
        $s['us_presidential']=$succession?self::VERSION_V3:($extended?self::VERSION_V2:self::VERSION);return $s;
    }
    public static function advance(array $s):array{
        $g=$s['accountability'];
        for($year=2028;$year<=2044;$year+=4)foreach(self::dates($year) as $stage=>$day){
            if($s['day']!==$day)continue;
            $r=self::stageResult($g,$year,$s['us_presidential'],$day);
            if($stage==='inauguration'){
                $gov=ComparativeGovernment::restore($g);$gov->installUsPresidential($r);$s['accountability']=$gov->snapshot();
            }
            $s['events'][]=['id'=>'us-president-'.$year.'-'.$stage,'on'=>$day,'kind'=>'us_presidential_'.$stage,'actor_role'=>'simulation_clock','result'=>$r];
        }
        return $s;
    }
    public static function validateHistory(array $s):void{
        if(!in_array($s['us_presidential']??null,[self::VERSION,self::VERSION_V2,self::VERSION_V3],true)||$s['country_code']!=='US')throw new \DomainException('Invalid US presidential pin');
        $g=$s['accountability'];$expected=[];$install=[];
        for($year=2028;$year<=2044;$year+=4)foreach(self::dates($year) as $stage=>$day){
            if($day>$s['day'])continue;
            $r=self::stageResult($g,$year,$s['us_presidential'],$day);$id='us-president-'.$year.'-'.$stage;
            $expected[$id]=['id'=>$id,'on'=>$day,'kind'=>'us_presidential_'.$stage,'actor_role'=>'simulation_clock','result'=>$r];
            if($stage==='inauguration')$install[$year]=$r;
        }
        foreach($s['events'] as $ev)if(str_starts_with($ev['kind'],'us_presidential_')){
            if(($expected[$ev['id']]??null)!==$ev)throw new \DomainException('US presidential event mismatch');
            unset($expected[$ev['id']]);
        }
        foreach($g['history'] as $h)if($h['action']==='us_presidential_inauguration'){
            $r=$h['evidence'];if(($install[$r['year']]??null)!==$r)throw new \DomainException('US presidential mandate proof mismatch');
            unset($install[$r['year']]);
        }
        if($expected!==[]||$install!==[])throw new \DomainException('Missing US presidential stage or mandate');
        $special=[];
        foreach($g['history'] as $h){
            if($s['us_presidential']===self::VERSION_V3&&$h['action']==='accountability_resolve')throw new \DomainException('Unqualified US succession in v3');
            if(!in_array($h['action'],['accountability_us_succession','accountability_us_vice_confirmation'],true))continue;
            if($s['us_presidential']!==self::VERSION_V3)throw new \DomainException('Unpinned vice succession');
            $e=$h['evidence'];$id=$e['evidence_id'];
            if(isset($special[$id]))throw new \DomainException('Duplicate succession evidence');
            $special[$id]=['id'=>$id,'on'=>substr($e['at'],0,10),'kind'=>$h['action'],'actor_role'=>'constitutional_clerk',
                'result'=>$h['action']==='accountability_us_succession'?['president'=>$e['successor'],'vice_president'=>null,'term_end'=>$e['term_end'],'regime_changed'=>false]:
                    ['president'=>$e['president'],'vice_president'=>$e['successor'],'term_end'=>$e['term_end'],'regime_changed'=>false]];
        }
        foreach($s['events'] as $event)if(in_array($event['kind'],['accountability_us_succession','accountability_us_vice_confirmation'],true)){
            if(($special[$event['id']]??null)!==$event)throw new \DomainException('US succession event mismatch');
            unset($special[$event['id']]);
        }
        if($special!==[])throw new \DomainException('Missing US succession event');
    }
}
