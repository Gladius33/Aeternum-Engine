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
    /** Historical elected terms are distinguished from later simulated ones.
     * Unknown/exceptional succession is not inferred from congressional votes. */
    public static function result(array $g,int $year,string $version=self::VERSION):array{
        if(!in_array($version,[self::VERSION,self::VERSION_V2],true))throw new \DomainException('Unknown US presidential rule pin');
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
        $nominees=[];
        foreach(['republican','democrat'] as $party){
            $eligible=array_keys(array_filter($candidates,fn($p)=>$p['party']===$party&&$terms[$p['id']]<2));
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
        if($version===self::VERSION_V2){
            $result['contingent_house']=$winner===null?self::contingentHouse($g['world_id'],$year,$nominees):null;
            if($result['contingent_house']!==null)$result['winner']=$result['contingent_house']['winner'];
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
        if($version!==self::VERSION_V2||$r['contingent_house']===null||$day===$r['day'])return $r;
        $rounds=array_values(array_filter($r['contingent_house']['rounds'],fn($x)=>$x['day']<=$day));
        $r['contingent_house']['rounds']=$rounds;
        $r['contingent_house']['winner']=$rounds===[]?null:$rounds[array_key_last($rounds)]['winner'];
        $r['winner']=$r['contingent_house']['winner'];return $r;
    }
    public static function bootstrap(array $s,bool $extended=false):array{
        if($s['country_code']!=='US'||$s['day']!=='2027-05-14'||$s['events']!==[]||isset($s['us_presidential'])
            ||!in_array($s['accountability']['initial_incumbent']??null,[self::initial(true),self::initial(false)],true))
            throw new \DomainException('US presidential cycle must be pinned at documented opening');
        $s['us_presidential']=$extended?self::VERSION_V2:self::VERSION;return $s;
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
        if(!in_array($s['us_presidential']??null,[self::VERSION,self::VERSION_V2],true)||$s['country_code']!=='US')throw new \DomainException('Invalid US presidential pin');
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
    }
}
