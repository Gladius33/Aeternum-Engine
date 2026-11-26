<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CivicDialogue.php';
require_once __DIR__.'/SocialPartners.php';
require_once __DIR__.'/CampaignLifecycle.php';
require_once __DIR__.'/PresidentialMandate.php';
require_once __DIR__.'/LegislativeResponsibility.php';

/** Fictional aggregate public mood, not survey data or a model for targeting real people.
 * Institutional continuity, approval and peaceful civic dissatisfaction are distinct.
 * No coefficient can cause a change of regime or authorize an institutional action.
 */
final class PublicOpinion
{
    public const VERSION='wp35-public-opinion-v1';
    private array $s;
    public function __construct(string $world,string $start,array $baseline)
    {
        if(!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$world))throw new \InvalidArgumentException('Invalid world');
        self::day($start);
        self::fields($baseline,['weights','pressure','approval','trust','intentions']);
        foreach(['weights','pressure','approval','intentions'] as $k)
            if(!is_array($baseline[$k])||$baseline[$k]===[])throw new \InvalidArgumentException('Empty baseline');
        $keys=array_keys($baseline['weights']);sort($keys);
        foreach(['pressure','approval'] as $k)self::fields($baseline[$k],$keys);
        if(count($keys)>32||count($baseline['intentions'])>30||!isset($baseline['intentions']['undecided']))throw new \InvalidArgumentException('Invalid baseline size');
        foreach($keys as $k)if(!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$k))throw new \InvalidArgumentException('Invalid cohort');
        foreach($baseline['weights'] as $n)self::range($n,0,10000);
        foreach($baseline['pressure'] as $n)self::range($n,0,1000);
        foreach($baseline['approval'] as $n)self::range($n,0,1000);
        foreach($baseline['intentions'] as $id=>$n){
            if(!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id))throw new \InvalidArgumentException('Invalid intention category');
            self::range($n,0,10000);
        }
        if(array_sum($baseline['weights'])!==10000||array_sum($baseline['intentions'])!==10000
            ||$baseline['intentions']['undecided']===10000)throw new \InvalidArgumentException('Weights must sum to 10000');
        self::range($baseline['trust'],0,1000);
        foreach(['weights','pressure','approval','intentions'] as $k)ksort($baseline[$k],SORT_STRING);
        $approval=self::weighted($baseline['approval'],$baseline['weights']);
        $this->s=['version'=>self::VERSION,'classification'=>'fictional_scenario','world_id'=>$world,'country'=>'FR','start_day'=>$start,
            'day'=>$start,'baseline'=>$baseline,'approval'=>$approval,'cohort_approval'=>$baseline['approval'],
            'trust'=>$baseline['trust'],'pressure'=>self::weighted($baseline['pressure'],$baseline['weights']),
            'intentions'=>$baseline['intentions'],'institutional_status'=>'not_assessed','constitutional_breaches'=>0,
            'civic_dissatisfaction'=>self::weighted($baseline['pressure'],$baseline['weights']),'history'=>[]];
    }
    public static function fixture():array
    {
        return ['weights'=>['workers'=>6500,'retired'=>3500],'pressure'=>['workers'=>400,'retired'=>300],
            'approval'=>['workers'=>520,'retired'=>540],'trust'=>600,
            'intentions'=>['civic'=>3300,'liberal'=>2200,'regional'=>500,'social'=>3000,'undecided'=>1000]];
    }
    public function enableDialogue():void
    {
        if(array_key_exists('dialogue',$this->s)||$this->s['day']!==$this->s['start_day']||$this->s['history']!==[])
            throw new \DomainException('Dialogue must be pinned at scenario bootstrap');
        foreach(['workers','retired'] as $id)if(!isset($this->s['baseline']['pressure'][$id]))
            throw new \DomainException('Dialogue needs its fictional cohorts');
        $this->s['dialogue']=CivicDialogue::initial($this->s['day']);
    }
    public function enableSocialPartners():void
    {
        if(!isset($this->s['dialogue'])||isset($this->s['social_partners'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])
            throw new \DomainException('Social partners must be pinned at bootstrap');
        $this->s['social_partners']=SocialPartners::initial($this->s['day']);
    }
    public function enableCampaign():void
    {
        if(isset($this->s['campaign'])||!isset($this->s['dialogue'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])
            throw new \DomainException('Campaign must be pinned at bootstrap');
        $this->s['campaign']=CampaignLifecycle::initial($this->s['world_id'],$this->s['day']);
    }
    public function enablePresidentialMandate(array $config):void
    {
        if(!isset($this->s['campaign'])||isset($this->s['presidency'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])
            throw new \DomainException('Presidential rules must be pinned at bootstrap');
        $this->s['presidency']=PresidentialMandate::initial($this->s['world_id'],$this->s['day'],$config);
    }
    /** Trusted inputs from the committed domestic and national stores, never player JSON. */
    public static function observe(array $dom,array $inst,bool $partners=false):array
    {
        $d=$dom['state'];$i=$inst['state'];
        if($d['world_id']!==$i['world_id']||$i['country_code']!=='FR'||$d['day']!==$i['day'])
            throw new \DomainException('Opinion source scope or date mismatch');
        $last=$d['days'][count($d['days'])-1]??null;
        if(!is_array($last)||$last['day']!==$d['day']||$last['pressure']!==$d['pressure'])throw new \DomainException('Domestic day is not committed');
        return ['world_id'=>$d['world_id'],'day'=>$d['day'],'domestic_sha'=>trim($dom['etag'],'"'),
            'institutional_sha'=>trim($inst['etag'],'"'),'pressure'=>$last['pressure'],'active_policies'=>$last['active_policies'],
            'production_index_bps'=>$last['economic']['production_index_bps']??null,
            'employment_index_bps'=>$last['economic']['employment_index_bps']??null,
            'constitutional_breaches'=>$i['constitutional_breaches'],'legislature'=>$i['legislature'],
            'election_status'=>$i['election']['status']??null,
            ...($partners?['partner_observation'=>SocialPartners::observe($last)]:[]),
            ...(isset($i['legislation']['responsibility'])?['responsibility'=>LegislativeResponsibility::observation($i['legislation']['responsibility'])]:[])];
    }
    public function advance(array $e,?array $campaignEvent=null):array
    {
        self::fields($e,['world_id','day','domestic_sha','institutional_sha','pressure','active_policies','production_index_bps',
            'employment_index_bps','constitutional_breaches','legislature','election_status',...(array_key_exists('responsibility',$e)?['responsibility']:[]),...(isset($this->s['social_partners'])?['partner_observation']:[])]);
        self::day($e['day']);
        if($e['world_id']!==$this->s['world_id']||$e['day']!==(new \DateTimeImmutable($this->s['day']))->modify('+1 day')->format('Y-m-d')
            ||count($this->s['history'])>=3650)throw new \DomainException('Invalid daily opinion transition');
        foreach(['domestic_sha','institutional_sha'] as $k)if(!is_string($e[$k])||!preg_match('/^[a-f0-9]{64}$/D',$e[$k]))throw new \InvalidArgumentException('Missing source hash');
        $b=$this->s['baseline'];self::fields($e['pressure'],array_keys($b['weights']));
        foreach($e['pressure'] as $n)self::range($n,0,1000);
        if(!is_array($e['active_policies'])||!array_is_list($e['active_policies'])||count($e['active_policies'])>128
            ||count(array_unique($e['active_policies']))!==count($e['active_policies']))throw new \InvalidArgumentException('Invalid active policies');
        foreach($e['active_policies'] as $p)if(!is_string($p)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$p))throw new \InvalidArgumentException('Invalid policy identity');
        foreach(['production_index_bps','employment_index_bps'] as $k)if($e[$k]!==null)self::range($e[$k],0,1300000);
        self::range($e['constitutional_breaches'],0,1000000);
        if($e['constitutional_breaches']<$this->s['constitutional_breaches']||!in_array($e['legislature'],['sitting','dissolved'],true)
            ||!in_array($e['election_status'],[null,'scheduled','voting_window_open','voting_recorded_results_pending','constitutional_deadline_missed'],true))
            throw new \DomainException('Invalid institutional observation');
        $campaign=null;
        if(isset($this->s['campaign']))$campaign=CampaignLifecycle::advance($this->s['campaign'],$e['day'],$campaignEvent);
        elseif($campaignEvent!==null)throw new \DomainException('No campaign enabled');
        $presidency=null;
        if(isset($this->s['presidency'])){
            if($campaignEvent!==null&&in_array($campaignEvent['type'],['presidential_election_lost','presidential_mandate_ended'],true))
                throw new \DomainException('Presidential calendar owns electoral outcomes');
            $presidency=PresidentialMandate::advance($this->s['presidency'],$e['day']);
            if($campaign['status']==='game_over'){
                // A prior independently certified terminal event takes precedence.
                if($presidency['status']!=='ended')$presidency=PresidentialMandate::interrupt($presidency,$e['day'],$campaign['reason']);
                else {$presidency['officeholder']=null;$presidency['successor']=null;$presidency['reason']=$campaign['reason'];}
            }elseif($presidency['status']==='ended'){
                $campaign=CampaignLifecycle::applyPresidentialOutcome($campaign,$presidency);
            }
        }
        $responsibilityPenalty=0;
        if(isset($e['responsibility'])){
            $g=$e['responsibility'];self::fields($g,['government','engagement_count','last_engagement_day','last_appointment']);
            if(!is_array($g)||!in_array($g['government'],['in_office','resignation_required'],true)||$campaign===null)
                throw new \DomainException('Invalid government observation');
            self::range($g['engagement_count'],0,2000);
            if($g['last_engagement_day']!==null){
                self::day($g['last_engagement_day']);
                if($g['last_engagement_day']>$e['day'])throw new \DomainException('Future engagement');
                $age=(int)(new \DateTimeImmutable($g['last_engagement_day']))->diff(new \DateTimeImmutable($e['day']))->days;
                // Fictional modest transient opinion response; not calibrated.
                $responsibilityPenalty=max(0,30-2*$age);
            }
            // The mandate remains active when the cabinet is censured.
            $campaign['cabinet_status']=$g['government'];
        }
        ksort($e['pressure'],SORT_STRING);sort($e['active_policies'],SORT_STRING);
        $economic=0;
        foreach(['production_index_bps','employment_index_bps'] as $k)
            if($e[$k]!==null)$economic+=max(-150,min(150,intdiv($e[$k]-10000,40)));
        $pressure=self::weighted($e['pressure'],$b['weights']);
        $basePressure=self::weighted($b['pressure'],$b['weights']);
        $breachPenalty=min(300,50*$e['constitutional_breaches']);
        $cohorts=[];
        foreach($e['pressure'] as $id=>$value){
            $target=self::clamp($b['approval'][$id]-intdiv($value-$b['pressure'][$id],2)+$economic-intdiv($breachPenalty,2)-$responsibilityPenalty);
            $cohorts[$id]=self::toward($this->s['cohort_approval'][$id],$target,5);
        }
        $approval=self::weighted($cohorts,$b['weights']);
        $trustTarget=self::clamp($b['trust']-intdiv($pressure-$basePressure,4)-$breachPenalty);
        $trust=self::toward($this->s['trust'],$trustTarget,8);
        $baseApproval=self::weighted($b['approval'],$b['weights']);
        // Only aggregate uncertainty changes; no claim of party switching based on demographics.
        $uncertain=max(0,min(7000,$b['intentions']['undecided']+3*($baseApproval-$approval)+2*($b['trust']-$trust)));
        $intentions=self::allocate($b['intentions'],self::toward($this->s['intentions']['undecided'],$uncertain,4));
        $civic=self::clamp($pressure+intdiv(1000-$trust,5));
        $status=$e['election_status']==='constitutional_deadline_missed'?'constitutional_deadline_breached':
            ($e['legislature']==='dissolved'?'electoral_transition':'ordinary_institutional_continuity');
        $this->s['day']=$e['day'];$this->s['approval']=$approval;$this->s['cohort_approval']=$cohorts;$this->s['trust']=$trust;
        $this->s['pressure']=$pressure;$this->s['intentions']=$intentions;$this->s['institutional_status']=$status;
        $this->s['constitutional_breaches']=$e['constitutional_breaches'];$this->s['civic_dissatisfaction']=$civic;
        $r=['day'=>$e['day'],'approval'=>$approval,'trust'=>$trust,'pressure'=>$pressure,'civic_dissatisfaction'=>$civic,
            'intentions'=>$intentions,'institutional_status'=>$status,'constitutional_breaches'=>$e['constitutional_breaches'],
            'economic_data_available'=>$e['production_index_bps']!==null&&$e['employment_index_bps']!==null,'regime_changed'=>false];
        if(isset($this->s['dialogue'])){
            $this->s['dialogue']=CivicDialogue::advance($this->s['dialogue'],$e['day'],$e['pressure'],$b['pressure']);
            $r['dialogue']=$this->s['dialogue'];
        }
        if(isset($this->s['social_partners'])){
            $this->s['social_partners']=SocialPartners::advance($this->s['social_partners'],$e['day'],$e['pressure'],$b['pressure'],$e['partner_observation']);
            $r['social_partners']=$this->s['social_partners'];
        }
        if($presidency!==null){
            $this->s['presidency']=$presidency;
            $r['presidency']=['status'=>$presidency['status'],'term_end'=>$presidency['term_end'],
                'consecutive_terms'=>$presidency['consecutive_terms'],'officeholder'=>$presidency['officeholder'],'reason'=>$presidency['reason']];
        }
        if(isset($e['responsibility']))$r['responsibility']=$e['responsibility'];
        if($campaign!==null){
            $this->s['campaign']=$campaign;
            $r['campaign']=CampaignLifecycle::summary($campaign);
            // Preserve distinction: opinion never causes regime change;
            // a separate certified fictional outcome can record one.
            $r['regime_changed']=$campaign['regime_changed'];
            $this->s['history'][]=['source'=>$e,'campaign_event'=>$campaignEvent,'result'=>$r];
        }else $this->s['history'][]=['source'=>$e,'result'=>$r];
        return $r;
    }
    private static function allocate(array $base,int $undecided):array
    {
        $den=10000-$base['undecided'];$available=10000-$undecided;
        $out=['undecided'=>$undecided];$rem=[];
        foreach($base as $id=>$weight)if($id!=='undecided'){
            $v=$weight*$available;$out[$id]=intdiv($v,$den);$rem[$id]=$v%$den;
        }
        $ids=array_keys($rem);usort($ids,fn($a,$b)=>$rem[$b]<=>$rem[$a]?:strcmp($a,$b));
        $left=10000-array_sum($out);
        for($j=0;$j<$left;$j++)$out[$ids[$j]]++;
        ksort($out,SORT_STRING);return $out;
    }
    public function snapshot():array{return $this->s;}
    public function hash():string{return hash('sha256',self::json($this->s));}
    public static function restore(array $s):self
    {
        $x=new self($s['world_id'],$s['start_day'],$s['baseline']);
        if(!is_array($s['history'])||!array_is_list($s['history'])||count($s['history'])>3650)throw new \DomainException('Invalid opinion journal');
        if(array_key_exists('dialogue',$s))$x->enableDialogue();
        if(array_key_exists('social_partners',$s))$x->enableSocialPartners();
        if(array_key_exists('campaign',$s))$x->enableCampaign();
        if(array_key_exists('presidency',$s))$x->enablePresidentialMandate($s['presidency']['config']);
        foreach($s['history'] as $r)$x->advance($r['source'],$r['campaign_event']??null);
        if($x->snapshot()!==$s)throw new \DomainException('Opinion replay mismatch');
        return $x;
    }
    public static function json(array $s):string{return json_encode($s,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    private static function weighted(array $v,array $w):int{$n=0;foreach($w as $id=>$weight)$n+=$v[$id]*$weight;return intdiv($n+5000,10000);}
    private static function toward(int $a,int $b,int $period):int{$d=$b-$a;return $d===0?$a:$a+($d>0?1:-1)*max(1,intdiv(abs($d),$period));}
    private static function clamp(int $n):int{return max(0,min(1000,$n));}
    private static function range(mixed $n,int $min,int $max):void{if(!is_int($n)||$n<$min||$n>$max)throw new \InvalidArgumentException('Out-of-range opinion input');}
    private static function fields(array $a,array $b):void{$keys=array_keys($a);sort($keys);sort($b);if($keys!==$b)throw new \InvalidArgumentException('Unexpected opinion fields');}
    private static function day(mixed $d):void{if(!is_string($d)||($v=\DateTimeImmutable::createFromFormat('!Y-m-d',$d))===false||$v->format('Y-m-d')!==$d)throw new \InvalidArgumentException('Invalid day');}
}
