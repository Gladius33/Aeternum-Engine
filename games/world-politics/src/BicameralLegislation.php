<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/LegislativeResponsibility.php';
require_once __DIR__.'/ExecutiveActs.php';
require_once __DIR__.'/FinanceBudget.php';
require_once __DIR__.'/NationalServiceLaw.php';
require_once __DIR__.'/SocialProgramme.php';

/** Ordinary government bill procedural pilot; not finance, organic, Art 49(3),
 * or constitutional legislation. Certified fictional procedural facts only.
 * One reviewed clause, with two immutable versions for amendment simulation.
 */
final class BicameralLegislation
{
    public const VERSION='wp40-fr-ordinary-v1';
    public const SOURCE='https://qpc360.conseil-constitutionnel.fr/constitution-4-octobre-1958';
    private array $s;
    public static function catalog(bool $minorUnits=false,bool $nationalService=false,bool $social=false):array
    {
        return ['education-reform'=>[
            'standard'=>$minorUnits?'The fictional education programme receives an additional daily allocation of 2000 EUR (200000 minor units) for thirty days.':'The fictional education programme receives an additional daily allocation of 200000 EUR for thirty days.',
            'amended'=>'The fictional education programme receives a revised allocation subject to a subsequent implementing measure.'
        ],...($nationalService?NationalServiceLaw::catalog():[]),...($social?SocialProgramme::legalCatalog():[])];
    }
    public function __construct(string $world,string $day)
    {
        self::id($world);self::day($day);
        $this->s=['version'=>self::VERSION,'source'=>self::SOURCE,'classification'=>'fictional_scenario',
            'world_id'=>$world,'country'=>'FR','start_day'=>$day,'day'=>$day,'bills'=>[],'history'=>[]];
    }
    public function enableSocialProgramme():void
    {
        if(!isset($this->s['minor_units'],$this->s['national_service'])||isset($this->s['social_programme'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])throw new \DomainException('Social programme must be pinned at bootstrap');
        $this->s['social_programme']=SocialProgramme::VERSION;
    }
    public function enablePreciseUnits():void
    {
        if(isset($this->s['minor_units'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])throw new \DomainException('Text units must be pinned at bootstrap');
        $this->s['minor_units']=true;
    }
    public function enableResponsibility():void
    {
        if(isset($this->s['responsibility'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])throw new \DomainException('Responsibility must be pinned at bootstrap');
        $this->s['responsibility']=LegislativeResponsibility::initial($this->s['world_id'],$this->s['day']);
    }
    public function enableAssembly():void
    {
        if(!isset($this->s['responsibility'])||isset($this->s['responsibility']['assembly'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])
            throw new \DomainException('Assembly must be pinned at bootstrap');
        $this->s['responsibility']['assembly']=AssemblyRollCalls::initial($this->s['world_id'],$this->s['day']);
    }
    public function enableLifecycle():void
    {
        if(!isset($this->s['responsibility']['assembly'])||isset($this->s['responsibility']['lifecycle'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])
            throw new \DomainException('Lifecycle must be pinned at bootstrap');
        $this->s['responsibility']['lifecycle']=ParliamentaryLifecycle::initial($this->s['day']);
        $session=$this->s['responsibility']['lifecycle']['session'];
        if($session!==null){
            $c=FrenchConstitutionalCrisis::restore($this->s['responsibility']['crisis']);
            $c->openSession(new LegislativeResponsibilityPrincipal($this->s['world_id']),$session['id'],$this->s['day'].'T00:00:00Z','initial-session');
            $this->s['responsibility']['crisis']=$c->snapshot();
        }
    }
    public function enableCabinet():void
    {
        if(!isset($this->s['responsibility']['lifecycle'])||isset($this->s['responsibility']['cabinet'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])
            throw new \DomainException('Cabinet must be pinned at bootstrap');
        $this->s['responsibility']['cabinet']=FrenchCabinet::initial($this->s['day']);
    }
    public function enableExecutive():void
    {
        if(!isset($this->s['responsibility']['cabinet'])||isset($this->s['executive'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])
            throw new \DomainException('Executive pilot must be pinned at bootstrap');
        $this->s['executive']=ExecutiveActs::initial();
    }
    public function enableFinance(int $year=2027,bool $series=false,bool $corrective=false):void
    {
        if(!isset($this->s['executive'])||isset($this->s['finance'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])
            throw new \DomainException('Finance pilot must be pinned at bootstrap');
        $this->s['finance']=FinanceBudget::initial($year,$series,$corrective);
    }
    public function enableCalendarReplay():void
    {
        if(isset($this->s['calendar_replay'])||!isset($this->s['responsibility']['lifecycle'])||$this->s['day']!==$this->s['start_day']||$this->s['history']!==[])throw new \DomainException('Calendar replay must be pinned at bootstrap');
        $this->s['calendar_replay']=true;
    }
    public function enableNationalService():void
    {
        if(isset($this->s['national_service'])||!isset($this->s['calendar_replay'])||$this->s['history']!==[]||$this->s['day']!==$this->s['start_day'])throw new \DomainException('National service requires canonical bootstrap');
        $this->s['national_service']=NationalServiceLaw::VERSION;
    }
    public function installElection(array $e):void
    {
        if(!isset($this->s['responsibility']['lifecycle']))throw new \DomainException('No parliamentary lifecycle');
        $this->s['responsibility']=ParliamentaryLifecycle::install($this->s['responsibility'],$e,$this->s['day']);
        $this->s['history'][]=['kind'=>'election_install','day'=>$this->s['day'],'election'=>$e];
    }
    public function snapshot():array{return $this->s;}
    public function advance(string $day):void
    {
        self::day($day);if($day<$this->s['day'])throw new \DomainException('Legislative clock regression');
        if(isset($this->s['responsibility']['lifecycle'])){
            $r=$this->s['responsibility'];
            foreach($r['lifecycle']['slots'] as $motion=>$entry){
                if($entry['status']!=='scheduled'||$entry['slot']['vote_at']>=FrenchConstitutionalCrisis::instant($day.'T00:00:00Z'))continue;
                $voteDay=substr($entry['vote_at'],0,10);
                if($r['pending']!==null){
                    $bid=$r['pending']['bill_id'];
                    [$r,$b]=LegislativeResponsibility::advance($r,$this->s['bills'][$bid],$voteDay);
                    $this->s['bills'][$bid]=$b;
                }
                $c=$r['crisis'];$m=$c['motions'][$motion]??$c['spontaneous_motions'][$motion]??null;
                if(($m['status']??null)==='filed'){
                    FrenchSittingCalendar::restore($entry['calendar'])->assertVoteAllowed($c,$motion,$entry['slot']['vote_at']);
                    $linked=isset($c['motions'][$motion]);$bid=$linked?$m['bill_id']:null;
                    $e=['world_id'=>$this->s['world_id'],'country'=>'FR','day'=>$voteDay,
                        'evidence_id'=>'auto-vote-'.substr(hash('sha256',$motion.'/'.$entry['vote_at']),0,32),
                        'bill_id'=>$bid,'kind'=>$linked?'responsibility_vote':'assembly_censure_vote','procedure_certified'=>true,
                        'data'=>['at'=>$entry['vote_at'],'motion_id'=>$motion]];
                    if($linked){[$r,$b]=LegislativeResponsibility::apply($r,$this->s['bills'][$bid],$e);$this->s['bills'][$bid]=$b;}
                    else [$r,$result]=LegislativeResponsibility::independent($r,$e);
                    $r['lifecycle']['slots'][$motion]['status']='resolved';
                }else $r['lifecycle']['slots'][$motion]['status']='moot';
            }
            $session=$r['lifecycle']['session'];
            if($session!==null&&$day>$session['end']&&ParliamentaryLifecycle::quiet($r))$r['lifecycle']['session']=null;
            // Open ordinary sessions automatically, once per boundary.
            $next=ParliamentaryLifecycle::ordinarySession($day);
            if($next!==null&&$r['lifecycle']['session']===null&&ParliamentaryLifecycle::quiet($r)){
                $c=FrenchConstitutionalCrisis::restore($r['crisis']);
                if($c->snapshot()['session']!==$next['id']){
                    $at=$next['start'].'T00:00:00Z';
                    if(FrenchConstitutionalCrisis::instant($at)<$c->snapshot()['now'])$at=$day.'T00:00:00Z';
                    $c->openSession(new LegislativeResponsibilityPrincipal($this->s['world_id']),$next['id'],$at,'session-'.$next['id']);
                    $r['crisis']=$c->snapshot();
                }
                $r['lifecycle']['session']=$next;
            }
            if(isset($this->s['calendar_replay'])&&$session!==$r['lifecycle']['session'])$this->s['history'][]=['kind'=>'calendar_boundary','day'=>$day];
            $this->s['responsibility']=$r;
        }
        if(isset($this->s['responsibility']['pending'])){
            $id=$this->s['responsibility']['pending']['bill_id'];
            [$r,$b]=LegislativeResponsibility::advance($this->s['responsibility'],$this->s['bills'][$id],$day);
            $this->s['responsibility']=$r;$this->s['bills'][$id]=$b;
        }
        if(isset($this->s['executive']))$this->s['executive']=ExecutiveActs::advance($this->s['executive'],$day);
        $this->s['day']=$day;
    }
    public function draft(string $id,string $policy):array
    {
        self::id($id);$catalog=self::catalog(isset($this->s['minor_units']),isset($this->s['national_service']),isset($this->s['social_programme']));
        if(isset($this->s['social_programme'])&&!isset($catalog[$policy])&&($text=SocialProgramme::legalText($policy))!==null)$catalog[$policy]=$text;
        if(!isset($catalog[$policy])||isset($this->s['bills'][$id])||count($this->s['bills'])>=100)
            throw new \DomainException('Unknown or duplicate bill');
        if(NationalServiceLaw::isPolicy($policy))NationalServiceLaw::validateDraft($this->s,$policy);
        $b=['id'=>$id,'policy_id'=>$policy,'status'=>'draft','filed_on'=>null,'accelerated'=>false,
            'texts'=>array_map(fn($t)=>hash('sha256',$t),$catalog[$policy]),'current'=>'standard',
            'next_chamber'=>'assembly','readings'=>['assembly'=>0,'senate'=>0],
            'last_votes'=>[],'cmp'=>null,'cmp_votes'=>[],'final_version'=>null,'adopted_on'=>null,
            'transmitted_on'=>null,'review'=>'none','review_on'=>null,'review_days'=>0,
            'promulgated_on'=>null,'published_on'=>null,'effect_on'=>null];
        $this->s['bills'][$id]=$b;$this->s['history'][]=['kind'=>'draft','day'=>$this->s['day'],'id'=>$id,'policy'=>$policy];return $b;
    }
    public function certify(array $e):array
    {
        self::fields($e,['world_id','country','day','evidence_id','bill_id','kind','procedure_certified','data']);
        self::id($e['evidence_id']);
        $executive=is_string($e['kind'])&&str_starts_with($e['kind'],'executive_');
        $finance=is_string($e['kind'])&&str_starts_with($e['kind'],'finance_');
        $independent=$finance||$executive||(is_string($e['kind'])&&str_starts_with($e['kind'],'assembly_'));
        if($independent){if($e['bill_id']!==null)throw new \DomainException('Independent proceedings have no bill');}
        else self::id($e['bill_id']);
        if($e['world_id']!==$this->s['world_id']||$e['country']!=='FR'||$e['day']!==$this->s['day']||$e['procedure_certified']!==true
            ||count($this->s['history'])>=2000)throw new \DomainException('Uncertified legislative evidence');
        foreach($this->s['history'] as $h)if(($h['evidence']['evidence_id']??null)===$e['evidence_id'])throw new \DomainException('Duplicate evidence');
        if($finance){
            if(!isset($this->s['finance']))throw new \DomainException('Finance pilot unavailable');
            [$s,$result]=FinanceBudget::apply($this->s['finance'],$this->s['responsibility']['cabinet'],$e);
            $this->s['finance']=$s;$this->s['history'][]=['kind'=>'evidence','day'=>$this->s['day'],'evidence'=>$e];
            return $result;
        }
        if($executive){
            if(!isset($this->s['executive']))throw new \DomainException('Executive pilot unavailable');
            [$s,$result]=ExecutiveActs::apply($this->s['executive'],$this->s['responsibility']['cabinet'],$e);
            $this->s['executive']=$s;$this->s['history'][]=['kind'=>'evidence','day'=>$this->s['day'],'evidence'=>$e];
            return $result;
        }
        $original=$e;
        if(isset($this->s['responsibility']['cabinet'])&&in_array($e['kind'],['file','responsibility_engage','assembly_confidence_open','cmp','last_word'],true)){
            if(!is_array($e['data']))throw new \DomainException('Missing incumbent authority');
            FrenchCabinet::requirePrimeMinister($this->s['responsibility']['cabinet'],$e['data']['prime_minister_id']??null);
            unset($e['data']['prime_minister_id']);
        }
        $scheduled=isset($this->s['responsibility']['lifecycle']);
        if($scheduled&&in_array($e['kind'],['responsibility_vote','assembly_censure_vote'],true))
            throw new \DomainException('Scheduled motion votes are clock resolved');
        $isFiling=$scheduled&&in_array($e['kind'],['responsibility_file','assembly_censure_file'],true);
        if($isFiling){
            if(!is_array($e['data'])||!is_string($e['data']['vote_at']??null)||!is_array($e['data']['sitting_days']??null))
                throw new \DomainException('Certified sitting agenda required');
            foreach($this->s['responsibility']['lifecycle']['slots'] as $slot)if($slot['status']==='scheduled')throw new \DomainException('Pilot permits one scheduled motion at a time');
            unset($e['data']['vote_at'],$e['data']['sitting_days']);
        }
        if($independent){
            if(!is_array($e['data'])||!isset($this->s['responsibility']))throw new \DomainException('Assembly not enabled');
            [$r,$result]=LegislativeResponsibility::independent($this->s['responsibility'],$e);
            if($isFiling)$r['lifecycle']['slots'][$e['data']['motion_id']]=ParliamentaryLifecycle::slot($r,$e['data']['motion_id'],$original['data']['vote_at'],$original['data']['sitting_days']);
            $this->s['responsibility']=$r;$this->s['history'][]=['kind'=>'evidence','day'=>$this->s['day'],'evidence'=>$original];
            return $result;
        }
        $b=$this->s['bills'][$e['bill_id']]??throw new \DomainException('Unknown bill');
        $d=$e['data'];if(!is_array($d))throw new \InvalidArgumentException('Invalid evidence data');
        if(is_string($e['kind'])&&str_starts_with($e['kind'],'responsibility_')){
            if(!isset($this->s['responsibility']))throw new \DomainException('Responsibility pilot not enabled');
            [$r,$b]=LegislativeResponsibility::apply($this->s['responsibility'],$b,$e);
            if($isFiling)$r['lifecycle']['slots'][$e['data']['motion_id']]=ParliamentaryLifecycle::slot($r,$e['data']['motion_id'],$original['data']['vote_at'],$original['data']['sitting_days']);
            $this->s['responsibility']=$r;$this->s['bills'][$b['id']]=$b;
            $this->s['history'][]=['kind'=>'evidence','day'=>$this->s['day'],'evidence'=>$original];
            return ['bill'=>$b,'responsibility'=>LegislativeResponsibility::observation($r),'policy_authorized'=>false];
        }
        if(isset($this->s['responsibility'])&&$this->s['responsibility']['crisis']['government']!=='in_office'&&in_array($e['kind'],['file','cmp','last_word'],true))
            throw new \DomainException('Active government required for this pilot');
        if(in_array($b['status'],['published','rejected'],true))throw new \DomainException('Terminal bill');
        switch($e['kind']){
            case 'file':
                self::fields($d,['government_project','accelerated','joint_opposition','filing_certified']);
                if($b['status']!=='draft'||$d['government_project']!==true||$d['filing_certified']!==true
                    ||!is_bool($d['accelerated'])||!is_bool($d['joint_opposition'])||($d['accelerated']&&$d['joint_opposition']))
                    throw new \DomainException('Invalid filing or acceleration');
                $b['filed_on']=$this->s['day'];$b['accelerated']=$d['accelerated'];$b['status']='navette';break;
            case 'reading':
                self::fields($d,['chamber','version','text_sha','yes','no','abstain','blank_invalid','amendment_certified','reading_delay_certified']);
                $ch=$d['chamber'];$v=$d['version'];
                if(!in_array($b['status'],['navette','post_cmp'],true)||$ch!==$b['next_chamber']||$d['reading_delay_certified']!==true)
                    throw new \DomainException('Wrong chamber or reading stage');
                self::text($b,$v,$d['text_sha']);
                if(!is_bool($d['amendment_certified'])||($v!==$b['current']&&!$d['amendment_certified']))
                    throw new \DomainException('Uncertified amendment');
                // Ordinary first reading time limits, except accelerated cases.
                if(!$b['accelerated']&&$b['readings'][$ch]===0){
                    $base=$ch==='assembly'?$b['filed_on']:($b['last_votes']['assembly']['day']??null);
                    if($base===null||$this->s['day']<self::shift($base,$ch==='assembly'?42:28))throw new \DomainException('First reading delay not met');
                }
                $vote=self::vote($d,$ch);$b['readings'][$ch]++;
                $b['last_votes'][$ch]=['day'=>$this->s['day'],'version'=>$v,'vote'=>$vote];
                $other=$ch==='assembly'?'senate':'assembly';
                if($vote['passed'])$b['current']=$v;
                if($vote['passed']&&($b['last_votes'][$other]['vote']['passed']??false)
                    &&$b['last_votes'][$other]['version']===$v){$b=self::adopt($b,$v,$this->s['day']);}
                else $b['next_chamber']=$other;
                if($b['status']==='post_cmp'&&$ch==='senate')$b['status']='final_assembly_available';
                break;
            case 'cmp':
                self::fields($d,['government_request','agreed','version','text_sha']);
                $min=$b['accelerated']?1:2;
                if($b['status']!=='navette'||$b['cmp']!==null||$d['government_request']!==true||!is_bool($d['agreed'])
                    ||min($b['readings'])<$min)throw new \DomainException('CMP not available');
                if($d['agreed'])self::text($b,$d['version'],$d['text_sha']);
                elseif($d['version']!==null||$d['text_sha']!==null)throw new \DomainException('No agreed CMP text');
                $b['cmp']=['agreed'=>$d['agreed'],'version'=>$d['version'],'day'=>$this->s['day']];
                $b['status']=$d['agreed']?'cmp_votes':'post_cmp';$b['next_chamber']='assembly';break;
            case 'cmp_vote':
                self::fields($d,['chamber','version','text_sha','yes','no','abstain','blank_invalid']);
                $ch=$d['chamber'];
                if($b['status']!=='cmp_votes'||!in_array($ch,['assembly','senate'],true)||isset($b['cmp_votes'][$ch])
                    ||$d['version']!==$b['cmp']['version'])throw new \DomainException('Invalid CMP ratification');
                self::text($b,$d['version'],$d['text_sha']);$v=self::vote($d,$ch);$b['cmp_votes'][$ch]=$v;
                if(!$v['passed']){$b['status']='post_cmp';$b['next_chamber']='assembly';}
                elseif(count($b['cmp_votes'])===2)$b=self::adopt($b,$d['version'],$this->s['day']);
                break;
            case 'last_word':
                self::fields($d,['government_request','version','text_sha','yes','no','abstain','blank_invalid']);
                if($b['status']!=='final_assembly_available'||$d['government_request']!==true)throw new \DomainException('Assembly last word unavailable');
                self::text($b,$d['version'],$d['text_sha']);
                if(!in_array($d['version'],[$b['last_votes']['assembly']['version']??null,$b['cmp']['version']??null],true))
                    throw new \DomainException('Unapproved final text');
                $v=self::vote($d,'assembly');$b=$v['passed']?self::adopt($b,$d['version'],$this->s['day']):array_replace($b,['status'=>'rejected']);
                break;
            case 'transmit':
                self::fields($d,['text_sha']);
                if($b['status']!=='adopted'||$b['transmitted_on']!==null||$d['text_sha']!==$b['texts'][$b['final_version']])
                    throw new \DomainException('No adopted text to transmit');
                $b['transmitted_on']=$this->s['day'];break;
            case 'refer_review':
                self::fields($d,['referral_certified']);
                if($b['status']!=='adopted'||$b['transmitted_on']===null||$b['review']!=='none'||$d['referral_certified']!==true
                    ||$this->s['day']>self::shift($b['transmitted_on'],15))throw new \DomainException('Invalid constitutional referral');
                $b['review']='pending';$b['review_on']=$this->s['day'];break;
            case 'review_result':
                self::fields($d,['constitutional','text_sha']);
                if($b['review']!=='pending'||!is_bool($d['constitutional'])||$d['text_sha']!==$b['texts'][$b['final_version']])
                    throw new \DomainException('No matching review');
                $b['review_days']=(int)(new \DateTimeImmutable($b['review_on']))->diff(new \DateTimeImmutable($this->s['day']))->days;
                $b['review']=$d['constitutional']?'cleared':'rejected';
                if(!$d['constitutional'])$b['status']='rejected';break;
            case 'promulgate':
                self::fields($d,['text_sha','presidential_act_certified']);
                if($b['status']!=='adopted'||$b['transmitted_on']===null||$b['review']==='pending'||$d['presidential_act_certified']!==true
                    ||$d['text_sha']!==$b['texts'][$b['final_version']]||$this->s['day']>self::shift($b['transmitted_on'],15+$b['review_days']))
                    throw new \DomainException('Promulgation not authorized');
                $b['promulgated_on']=$this->s['day'];$b['status']='promulgated';break;
            case 'publish':
                self::fields($d,['text_sha','effective_on','publication_certified']);self::day($d['effective_on']);
                if($b['status']!=='promulgated'||$d['publication_certified']!==true||$d['text_sha']!==$b['texts'][$b['final_version']]
                    ||$d['effective_on']<=$this->s['day'])throw new \DomainException('Publication or effective day invalid');
                if(NationalServiceLaw::isPolicy($b['policy_id']))NationalServiceLaw::validatePublication($this->s,$b,$d['effective_on']);
                $b['published_on']=$this->s['day'];$b['effect_on']=$d['effective_on'];$b['status']='published';break;
            default:throw new \InvalidArgumentException('Unknown legislative event');
        }
        $this->s['bills'][$b['id']]=$b;$this->s['history'][]=['kind'=>'evidence','day'=>$this->s['day'],'evidence'=>$original];
        return ['bill'=>$b,'policy_authorized'=>false];
    }
    private static function adopt(array $b,string $v,string $day):array
    {$b['status']='adopted';$b['final_version']=$v;$b['adopted_on']=$day;return $b;}
    private static function text(array $b,mixed $v,mixed $sha):void
    {if(!is_string($v)||!isset($b['texts'][$v])||$sha!==$b['texts'][$v])throw new \DomainException('Unreviewed bill text');}
    private static function vote(array $d,string $ch):array
    {
        $cap=$ch==='assembly'?577:348;
        foreach(['yes','no','abstain','blank_invalid'] as $k)if(!is_int($d[$k])||$d[$k]<0||$d[$k]>$cap)throw new \InvalidArgumentException('Invalid vote');
        $n=$d['yes']+$d['no'];if($n===0||$n+$d['abstain']+$d['blank_invalid']>$cap)throw new \DomainException('Invalid turnout');
        return ['yes'=>$d['yes'],'no'=>$d['no'],'abstain'=>$d['abstain'],'blank_invalid'=>$d['blank_invalid'],'passed'=>$d['yes']>$d['no']];
    }
    public function proof(string $policy,string $sha,string $effectiveDay):?array
    {
        foreach($this->s['bills'] as $b)if($b['policy_id']===$policy&&$b['status']==='published'&&$b['final_version']==='standard'
            &&$b['texts']['standard']===$sha&&$b['effect_on']===$effectiveDay)return $b;
        return null;
    }
    public static function restore(array $s):self
    {
        $x=new self($s['world_id'],$s['start_day']);
        if(!is_array($s['history'])||!array_is_list($s['history'])||count($s['history'])>2000)throw new \DomainException('Invalid legislation history');
        if(array_key_exists('minor_units',$s)){
            if($s['minor_units']!==true)throw new \DomainException('Invalid text unit model');
            $x->enablePreciseUnits();
        }
        if(array_key_exists('responsibility',$s))$x->enableResponsibility();
        if(isset($s['responsibility']['assembly']))$x->enableAssembly();
        if(isset($s['responsibility']['lifecycle']))$x->enableLifecycle();
        if(isset($s['responsibility']['cabinet']))$x->enableCabinet();
        if(isset($s['executive']))$x->enableExecutive();
        if(isset($s['finance']))$x->enableFinance($s['finance']['year'],$s['finance']['version']===FinanceBudget::SERIES,$s['finance']['corrective_enabled']??false);
        if(isset($s['calendar_replay']))$x->enableCalendarReplay();
        if(isset($s['national_service']))$x->enableNationalService();
        if(isset($s['social_programme']))$x->enableSocialProgramme();
        foreach($s['history'] as $h){
            $x->advance($h['day']);
            if($h['kind']==='draft')$x->draft($h['id'],$h['policy']);
            elseif($h['kind']==='evidence')$x->certify($h['evidence']);
            elseif($h['kind']==='election_install')$x->installElection($h['election']);
            elseif($h['kind']==='calendar_boundary'&&isset($s['calendar_replay'])){ /* advance generated the marker */ }
            else throw new \DomainException('Unknown history kind');
        }
        $x->advance($s['day']);if($x->snapshot()!==$s)throw new \DomainException('Legislative replay mismatch');return $x;
    }
    private static function shift(string $d,int $n):string{return (new \DateTimeImmutable($d))->modify('+'.$n.' days')->format('Y-m-d');}
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Unexpected legislative fields');}
    private static function id(mixed $v):void{if(!is_string($v)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$v))throw new \InvalidArgumentException('Invalid legislative id');}
    private static function day(mixed $v):void{if(!is_string($v)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v))===false||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Invalid legislative day');}
}
