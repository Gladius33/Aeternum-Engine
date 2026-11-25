<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Deterministic fictional electoral pilot, not certified real election data.
 * Integer counts, no floating-point seat allocation, reproducible tie draws.
 * Scenario data are immutable and visible, not secret ballots or forecasts.
 */
final class ElectoralCycle
{
    public const VERSION='wp33-election-v1';
    private array $s;
    public function __construct(string $world,string $country,string $day,array $config)
    {
        self::id($world);self::day($day);
        self::fields($config,['method','poll_day','runoff_day','threshold_bps','parties','districts','formation']);
        $method=$config['method'];
        if(!in_array($method,['plurality','two_round','dhondt'],true)||!is_bool($config['formation'])
            ||!is_int($config['threshold_bps'])||$config['threshold_bps']<0||$config['threshold_bps']>10000)
            throw new \InvalidArgumentException('Invalid electoral rule');
        self::day($config['poll_day']);
        if($config['poll_day']<=$day)throw new \InvalidArgumentException('Poll must be in future');
        if($method==='two_round'){
            self::day($config['runoff_day']);
            if($config['runoff_day']<=$config['poll_day'])throw new \InvalidArgumentException('Invalid runoff date');
        }elseif($config['runoff_day']!==null)throw new \InvalidArgumentException('Unexpected runoff');
        if(!is_array($config['parties'])||count($config['parties'])<2||count($config['parties'])>30
            ||!is_array($config['districts'])||count($config['districts'])<1||count($config['districts'])>1000)
            throw new \InvalidArgumentException('Invalid electoral roster');
        $leaders=[];
        foreach($config['parties'] as $id=>$p){
            self::id($id);self::fields($p,['leader','programme','tolerance']);self::id($p['leader']);
            if(isset($leaders[$p['leader']]))throw new \InvalidArgumentException('Duplicate party leader');
            $leaders[$p['leader']]=true;
            self::fields($p['programme'],['economy','services']);
            foreach($p['programme'] as $v)self::index($v);
            self::index($p['tolerance']);
        }
        ksort($config['parties']);ksort($config['districts']);
        $seatCount=0;
        foreach($config['districts'] as $id=>&$d){
            self::id($id);self::fields($d,['registered','seats','first','second']);
            if(!is_int($d['registered'])||$d['registered']<1||$d['registered']>100000000
                ||!is_int($d['seats'])||$d['seats']<1||$d['seats']>1000
                ||($method!=='dhondt'&&$d['seats']!==1))throw new \InvalidArgumentException('Invalid district');
            $seatCount+=$d['seats'];
            self::ballot($d['first'],$d['registered'],$config['parties']);
            ksort($d['first']['votes']);
            if($method==='two_round'){
                self::ballot($d['second'],$d['registered'],$config['parties']);ksort($d['second']['votes']);
                $eligible=self::qualify($d['first']['votes'],$d['registered']);
                if($eligible===[]&&array_sum($d['second']['votes'])!==0)throw new \InvalidArgumentException('Unneeded runoff votes');
                foreach($d['second']['votes'] as $p=>$v)
                    if($v>0&&!in_array($p,$eligible,true))throw new \InvalidArgumentException('Runoff includes eliminated candidate');
                if($eligible!==[]&&array_sum($d['second']['votes'])===0)throw new \InvalidArgumentException('Empty runoff');
            }elseif($d['second']!==null)throw new \InvalidArgumentException('Unexpected second ballot');
        }
        unset($d);
        if($seatCount>1000)throw new \InvalidArgumentException('Seat cap');
        $this->s=['version'=>self::VERSION,'classification'=>'fictional_scenario','world_id'=>$world,'country'=>$country,
            'start_day'=>$day,'day'=>$day,'config'=>$config,'status'=>'scheduled','seats_total'=>$seatCount,
            'first_result'=>null,'result'=>null,'coalition'=>null,'government'=>null,'history'=>[]];
    }
    private static function ballot(array $b,int $registered,array $parties):void
    {
        self::fields($b,['votes','blank_invalid']);
        if(!is_array($b['votes'])||$b['votes']===[]||!is_int($b['blank_invalid'])||$b['blank_invalid']<0||$b['blank_invalid']>$registered)
            throw new \InvalidArgumentException('Invalid ballot');
        foreach($b['votes'] as $p=>$v)if(!isset($parties[$p])||!is_int($v)||$v<0||$v>$registered)
            throw new \InvalidArgumentException('Invalid party vote');
        if(array_sum($b['votes'])+$b['blank_invalid']>$registered)throw new \DomainException('Turnout exceeds registered voters');
    }
    private static function order(array $votes):array
    {
        $ids=array_keys($votes);
        usort($ids,fn($a,$b)=>$votes[$b]<=>$votes[$a]?:strcmp($a,$b));
        return $ids;
    }
    /** Core French-inspired qualification thresholds; no claim to all legal exceptions. */
    private static function qualify(array $v,int $registered):array
    {
        $ids=self::order($v);$total=array_sum($v);
        if($total===0)throw new \DomainException('No valid first-round votes');
        $top=$ids[0];
        if($v[$top]*2>$total&&$v[$top]*4>=$registered)return [];
        $eligible=array_values(array_filter($ids,fn($id)=>$v[$id]*8>=$registered));
        if(count($eligible)<2)$eligible=array_slice($ids,0,2);
        if(count($eligible)<2)throw new \DomainException('Runoff requires two candidates');
        return $eligible;
    }
    public function advance(string $day):void
    {
        self::day($day);if($day<$this->s['day'])throw new \DomainException('Election clock cannot regress');
        $c=$this->s['config'];
        if($this->s['status']==='scheduled'&&$day>=$c['poll_day']){
            $first=$this->count(false);$this->s['first_result']=$first;
            if($first['pending_districts']!==[])$this->s['status']='runoff_pending';
            else{$this->s['status']='results';$this->s['result']=$first;}
        }
        if($this->s['status']==='runoff_pending'&&$day>=$c['runoff_day']){
            $this->s['result']=$this->count(true);$this->s['status']='results';
        }
        $this->s['day']=$day;
    }
    private function count(bool $second):array
    {
        $c=$this->s['config'];$seats=array_fill_keys(array_keys($c['parties']),0);
        $votes=$seats;$details=[];$pending=[];$registered=0;$ballots=0;$valid=0;
        foreach($c['districts'] as $id=>$d){
            $registered+=$d['registered'];$b=$d['first'];$first=$b['votes'];$total=array_sum($first);
            if($total===0)throw new \DomainException('District has no valid votes');
            $runoff=$c['method']==='two_round'?self::qualify($first,$d['registered']):[];
            if($second&&$runoff!==[])$b=$d['second'];
            $v=$b['votes'];$valid+=array_sum($v);$ballots+=array_sum($v)+$b['blank_invalid'];
            foreach($v as $p=>$n)$votes[$p]+=$n;
            $allocation=array_fill_keys(array_keys($c['parties']),0);
            if($c['method']==='two_round'&&$runoff!==[]&&!$second){$pending[]=$id;}
            elseif($c['method']==='dhondt'){
                $eligible=array_filter($v,fn($n)=>$n>0&&$n*10000>=array_sum($v)*$c['threshold_bps']);
                if($eligible===[])throw new \DomainException('No list passed the threshold');
                for($i=0;$i<$d['seats'];$i++){
                    $best=null;
                    foreach($eligible as $p=>$n){
                        if($best===null||$n*($allocation[$best]+1)>$eligible[$best]*($allocation[$p]+1)
                            ||($n*($allocation[$best]+1)===$eligible[$best]*($allocation[$p]+1)&&strcmp($p,$best)<0))$best=$p;
                    }
                    $allocation[$best]++;
                }
            }else{
                $rank=self::order($v);$winner=$rank[0];
                if($v[$winner]<=0)throw new \DomainException('No valid winner');
                $allocation[$winner]=1;
            }
            foreach($allocation as $p=>$n)$seats[$p]+=$n;
            $details[$id]=['seats'=>$allocation,'runoff_eligible'=>$runoff,'valid_votes'=>array_sum($v),
                'ballots'=>$b['blank_invalid']+array_sum($v),'registered'=>$d['registered']];
        }
        $vacant=$this->s['seats_total']-array_sum($seats);
        if($vacant!==count($pending))throw new \LogicException('Seat conservation failed');
        return ['seats'=>$seats,'votes'=>$votes,'registered'=>$registered,'ballots'=>$ballots,'valid_votes'=>$valid,
            'majority'=>intdiv($this->s['seats_total'],2)+1,'vacant_seats'=>$vacant,'pending_districts'=>$pending,'districts'=>$details,
            'vote_reporting'=>$second?'decisive_ballot_per_district':'first_ballot'];
    }
    /** Fictional party acceptance based on declared programme distance. */
    public function coalition(string $id,array $parties,array $programme,string $candidate):array
    {
        self::id($id);self::id($candidate);self::fields($programme,['economy','services']);foreach($programme as $v)self::index($v);
        if(!$this->s['config']['formation']||$this->s['status']!=='results'||$this->s['government']!==null)
            throw new \DomainException('Coalition formation unavailable');
        if(!array_is_list($parties)||$parties===[]||count($parties)!==count(array_unique($parties)))throw new \InvalidArgumentException('Invalid coalition roster');
        foreach($this->s['history'] as $h)if($h['id']===$id)throw new \DomainException('Duplicate coalition command');
        sort($parties,SORT_STRING);ksort($programme);
        $total=0;$accepted=[];$rejected=[];$leaderFound=false;
        foreach($parties as $p){
            self::id($p);$party=$this->s['config']['parties'][$p]??null;$seats=$this->s['result']['seats'][$p]??0;
            if($party===null||$seats===0)throw new \DomainException('Party has no mandate');
            if($party['leader']===$candidate)$leaderFound=true;
            $distance=max(abs($programme['economy']-$party['programme']['economy']),abs($programme['services']-$party['programme']['services']));
            if($distance<=$party['tolerance']){$accepted[]=$p;$total+=$seats;}else $rejected[]=$p;
        }
        if(!$leaderFound)throw new \DomainException('Candidate must represent a participating party');
        $status=$rejected!==[]?'negotiation_failed':($total>=$this->s['result']['majority']?'majority_agreement':'minority_agreement');
        $result=['id'=>$id,'candidate'=>$candidate,'parties'=>$parties,'programme'=>$programme,'accepted'=>$accepted,'rejected'=>$rejected,
            'support_seats'=>$total,'majority'=>$this->s['result']['majority'],'status'=>$status,'appointed'=>false];
        $this->s['coalition']=$result;
        $this->s['history'][]=['id'=>$id,'day'=>$this->s['day'],'kind'=>'coalition','parties'=>$parties,'programme'=>$programme,'candidate'=>$candidate];
        return $result;
    }
    /** Trusted institutional certificate, not an appointment granted by owner. */
    public function certify(string $id,array $e):array
    {
        self::id($id);self::fields($e,['world_id','country','evidence_id','coalition_id','candidate','day','procedure_certified']);
        self::id($e['evidence_id']);
        foreach($this->s['history'] as $h)if($h['id']===$id||($h['evidence']['evidence_id']??null)===$e['evidence_id'])
            throw new \DomainException('Duplicate formation evidence');
        $c=$this->s['coalition'];
        if($this->s['government']!==null||$c===null||$c['status']!=='majority_agreement'
            ||$e['world_id']!==$this->s['world_id']||$e['country']!==$this->s['country']||$e['coalition_id']!==$c['id']
            ||$e['candidate']!==$c['candidate']||$e['day']!==$this->s['day']||$e['procedure_certified']!==true)
            throw new \DomainException('Invalid certified formation');
        $this->s['government']=['candidate'=>$c['candidate'],'parties'=>$c['parties'],'support_seats'=>$c['support_seats'],
            'programme'=>$c['programme'],'on'=>$e['day'],'status'=>'certified_scenario_formation','evidence_id'=>$e['evidence_id']];
        $this->s['history'][]=['id'=>$id,'day'=>$this->s['day'],'kind'=>'certify','evidence'=>$e];
        return $this->s['government'];
    }
    public function snapshot():array{return $this->s;}
    public static function restore(array $s):self
    {
        $x=new self($s['world_id'],$s['country'],$s['start_day'],$s['config']);
        if(!is_array($s['history'])||!array_is_list($s['history'])||count($s['history'])>1000)throw new \DomainException('Invalid electoral history');
        foreach($s['history'] as $h){
            $x->advance($h['day']);
            if($h['kind']==='coalition')$x->coalition($h['id'],$h['parties'],$h['programme'],$h['candidate']);
            elseif($h['kind']==='certify')$x->certify($h['id'],$h['evidence']);
            else throw new \DomainException('Invalid electoral event');
        }
        $x->advance($s['day']);
        if($x->snapshot()!==$s)throw new \DomainException('Election snapshot replay failed');
        return $x;
    }
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Unexpected election fields');}
    private static function id(mixed $id):void{if(!is_string($id)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id))throw new \InvalidArgumentException('Invalid election id');}
    private static function day(mixed $v):void{if(!is_string($v)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v))===false||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Invalid poll date');}
    private static function index(mixed $v):void{if(!is_int($v)||$v<0||$v>1000)throw new \InvalidArgumentException('Invalid fictional policy index');}
}

/** Explicit fixture. Not a real constituency map or real party platform. */
final class ElectoralScenario
{
    public static function config(string $country,string $day):array
    {
        $date=new \DateTimeImmutable($day);
        $method=match($country){'FR'=>'two_round','GB','US'=>'plurality','DE','ES','IT','JP'=>'dhondt',default=>throw new \DomainException('No pilot election')};
        $parties=[
            'civic'=>['leader'=>'civic-leader','programme'=>['economy'=>500,'services'=>600],'tolerance'=>180],
            'liberal'=>['leader'=>'liberal-leader','programme'=>['economy'=>650,'services'=>450],'tolerance'=>180],
            'regional'=>['leader'=>'regional-leader','programme'=>['economy'=>500,'services'=>800],'tolerance'=>100],
            'social'=>['leader'=>'social-leader','programme'=>['economy'=>200,'services'=>900],'tolerance'=>150]
        ];
        $districts=[];
        if($method==='dhondt'){
            $districts['national']=['registered'=>120000,'seats'=>100,'first'=>['votes'=>['civic'=>40000,'social'=>28000,'liberal'=>20000,'regional'=>4000],'blank_invalid'=>2000],'second'=>null];
        }else for($i=0;$i<100;$i++){
            $type=$i<40?'civic':($i<75?'social':'liberal');
            $others=array_values(array_diff(['civic','social','liberal'],[$type]));
            $first=[$type=>390,$others[0]=>310,$others[1]=>200,'regional'=>50];
            $second=[$type=>470,$others[0]=>300,$others[1]=>150];
            $districts[sprintf('district-%03d',$i+1)]=['registered'=>1300,'seats'=>1,
                'first'=>['votes'=>$first,'blank_invalid'=>20],
                'second'=>$method==='two_round'?['votes'=>$second,'blank_invalid'=>30]:null];
        }
        return ['method'=>$method,'poll_day'=>$date->modify('+1 day')->format('Y-m-d'),
            'runoff_day'=>$method==='two_round'?$date->modify('+8 days')->format('Y-m-d'):null,
            'threshold_bps'=>$method==='dhondt'?500:0,'parties'=>$parties,'districts'=>$districts,'formation'=>$country!=='US'];
    }
}
