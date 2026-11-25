<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Article 89 procedural pilot. Every legal event is fictional, certified by
 * a separate trusted simulation clerk. A player draft has no legal effect.
 * Only reviewed, non-executive reporting clauses are supported in this pilot.
 */
final class ConstitutionalRevision
{
    public const VERSION='wp39-fr-art89-v1';
    public const SOURCE='https://www.legifrance.gouv.fr/loda/article_lc/LEGIARTI000019240655/';
    private array $s;
    public static function catalog():array
    {
        return [
            'environment-report'=>['article'=>'fictional-89-a','text'=>'An annual public report describes the state of the environment and the implementation of environmental commitments.'],
            'institutional-report'=>['article'=>'fictional-89-b','text'=>'An annual public report describes the functioning of parliamentary institutions and access to their public records.']
        ];
    }
    public function __construct(string $world,string $day)
    {
        self::id($world);self::day($day);
        $this->s=['version'=>self::VERSION,'classification'=>'fictional_scenario','source'=>self::SOURCE,'world_id'=>$world,
            'country'=>'FR','start_day'=>$day,'day'=>$day,'context'=>['territorial_integrity'=>true,'presidential_vacancy'=>false,'definitive_incapacity'=>false],
            'constitution_version'=>0,'effective_clauses'=>[],'procedures'=>[],'history'=>[]];
    }
    public function snapshot():array{return $this->s;}
    public function advance(string $day):void
    {
        self::day($day);
        if($day<$this->s['day'])throw new \DomainException('Revision clock cannot regress');
        $this->s['day']=$day;
    }
    public function draft(string $id,string $template):array
    {
        self::id($id);
        $item=self::catalog()[$template]??throw new \DomainException('Unreviewed constitutional text');
        if(isset($this->s['procedures'][$id])||count($this->s['procedures'])>=100||count($this->s['history'])>=2000)
            throw new \DomainException('Duplicate or excessive revision draft');
        $p=['id'=>$id,'template'=>$template,'text'=>$item['text'],'article'=>$item['article'],
            'text_sha'=>hash('sha256',$item['text']),'base_version'=>$this->s['constitution_version'],
            'status'=>'draft','initiative'=>null,'sponsor'=>null,'votes'=>[],'route'=>null,'final_vote'=>null,
            'effective_on'=>null];
        $this->s['procedures'][$id]=$p;
        $this->s['history'][]=['day'=>$this->s['day'],'kind'=>'draft','id'=>$id,'template'=>$template];
        return $p;
    }
    public function certify(array $e):array
    {
        self::fields($e,['world_id','country','day','evidence_id','revision_id','kind','procedure_certified','data']);
        self::id($e['evidence_id']);self::day($e['day']);
        if($e['world_id']!==$this->s['world_id']||$e['country']!=='FR'||$e['day']!==$this->s['day']||$e['procedure_certified']!==true
            ||count($this->s['history'])>=2000)throw new \DomainException('Uncertified revision evidence');
        foreach($this->s['history'] as $h)if(($h['evidence']['evidence_id']??null)===$e['evidence_id'])throw new \DomainException('Replayed revision evidence');
        $d=$e['data'];$kind=$e['kind'];
        if(!is_array($d))throw new \InvalidArgumentException('Invalid revision data');
        if($kind==='context'){
            if($e['revision_id']!==null)throw new \InvalidArgumentException('Context is world-wide');
            self::fields($d,['territorial_integrity','presidential_vacancy','definitive_incapacity']);
            foreach($d as $v)if(!is_bool($v))throw new \InvalidArgumentException('Invalid context');
            $this->s['context']=['territorial_integrity'=>$d['territorial_integrity'],'presidential_vacancy'=>$d['presidential_vacancy'],
                'definitive_incapacity'=>$d['definitive_incapacity']];
            $result=['context'=>$this->s['context'],'revision_allowed'=>$this->allowed()];
        }else{
            if(!$this->allowed())throw new \DomainException('Constitutional revision suspended');
            self::id($e['revision_id']);$id=$e['revision_id'];
            $p=$this->s['procedures'][$id]??throw new \DomainException('Unknown revision');
            if($p['base_version']!==$this->s['constitution_version']||in_array($p['status'],['effective','rejected'],true))
                throw new \DomainException('Revision is terminal or based on superseded text');
            switch($kind){
                case 'file':
                    self::fields($d,['initiative','sponsor','prime_minister_proposal','republican_form_preserved']);
                    self::id($d['sponsor']);
                    if($p['status']!=='draft'||$d['republican_form_preserved']!==true
                        ||!in_array($d['initiative'],['government_project','parliamentary_proposal'],true)
                        ||!is_bool($d['prime_minister_proposal'])
                        ||($d['initiative']==='government_project'&&(!$d['prime_minister_proposal']||$d['sponsor']!=='president'))
                        ||($d['initiative']==='parliamentary_proposal'&&$d['prime_minister_proposal']))
                        throw new \DomainException('Invalid Article 89 initiative');
                    // For a parliamentary proposal the clerk certifies the
                    // sponsor's actual fictional parliamentary mandate.
                    $p['initiative']=$d['initiative'];$p['sponsor']=$d['sponsor'];$p['status']='filed';break;
                case 'chamber_vote':
                    self::fields($d,['chamber','text_sha','yes','no','abstain','blank_invalid']);
                    if(!in_array($p['status'],['filed','chambers_approved'],true)||!in_array($d['chamber'],['assembly','senate'],true)
                        ||$d['text_sha']!==$p['text_sha'])throw new \DomainException('Identical chamber text required');
                    $ch=$d['chamber'];
                    if(isset($p['votes'][$ch]))throw new \DomainException('Chamber vote already recorded');
                    $v=self::vote($d,$ch==='assembly'?577:348,false);
                    $p['votes'][$ch]=$v;
                    if(!$v['passed'])$p['status']='rejected';
                    elseif(count($p['votes'])===2)$p['status']='chambers_approved';
                    break;
                case 'route':
                    self::fields($d,['route','presidential_decision']);
                    if($p['status']!=='chambers_approved'||!in_array($d['route'],['referendum','congress'],true)
                        ||$d['presidential_decision']!==true||($d['route']==='congress'&&$p['initiative']!=='government_project'))
                        throw new \DomainException('Invalid ratification route');
                    $p['route']=$d['route'];$p['status']='ratification_pending';break;
                case 'ratify':
                    self::fields($d,['route','text_sha','yes','no','abstain','blank_invalid']);
                    if($p['status']!=='ratification_pending'||$d['route']!==$p['route']||$d['text_sha']!==$p['text_sha'])
                        throw new \DomainException('Wrong ratification text or route');
                    $v=self::vote($d,$p['route']==='congress'?925:100000000,$p['route']==='congress');
                    $p['final_vote']=$v;$p['status']=$v['passed']?'adopted':'rejected';break;
                case 'promulgate':
                    self::fields($d,['text_sha','published_on']);
                    if($p['status']!=='adopted'||$d['text_sha']!==$p['text_sha']||$d['published_on']!==$this->s['day'])
                        throw new \DomainException('No certified adopted text for publication');
                    $p['status']='effective';$p['effective_on']=$this->s['day'];
                    $this->s['constitution_version']++;
                    $this->s['effective_clauses'][$p['article']]=['text'=>$p['text'],'text_sha'=>$p['text_sha'],
                        'effective_on'=>$p['effective_on'],'revision_id'=>$id,'version'=>$this->s['constitution_version']];
                    break;
                default:throw new \InvalidArgumentException('Unknown revision event');
            }
            $this->s['procedures'][$id]=$p;
            $result=['procedure'=>$p,'constitution_version'=>$this->s['constitution_version']];
        }
        $this->s['history'][]=['day'=>$this->s['day'],'kind'=>'evidence','evidence'=>$e];return $result;
    }
    private function allowed():bool
    {
        $c=$this->s['context'];return $c['territorial_integrity']&&!$c['presidential_vacancy']&&!$c['definitive_incapacity'];
    }
    private static function vote(array $d,int $capacity,bool $congress):array
    {
        foreach(['yes','no','abstain','blank_invalid'] as $k)
            if(!is_int($d[$k])||$d[$k]<0||$d[$k]>$capacity)throw new \InvalidArgumentException('Invalid revision ballot');
        $valid=$d['yes']+$d['no'];
        if($valid===0||$valid+$d['abstain']+$d['blank_invalid']>$capacity)throw new \DomainException('Invalid revision turnout');
        $threshold=$congress?intdiv(3*$valid+4,5):intdiv($valid,2)+1;
        return ['yes'=>$d['yes'],'no'=>$d['no'],'abstain'=>$d['abstain'],'blank_invalid'=>$d['blank_invalid'],
            'expressed'=>$valid,'threshold'=>$threshold,'passed'=>$d['yes']>=$threshold,'text_sha'=>$d['text_sha']];
    }
    public static function restore(array $s):self
    {
        $x=new self($s['world_id'],$s['start_day']);
        if(!is_array($s['history'])||!array_is_list($s['history'])||count($s['history'])>2000)
            throw new \DomainException('Invalid revision history');
        foreach($s['history'] as $h){
            $x->advance($h['day']);
            if($h['kind']==='draft')$x->draft($h['id'],$h['template']);
            elseif($h['kind']==='evidence')$x->certify($h['evidence']);
            else throw new \DomainException('Unknown revision history event');
        }
        $x->advance($s['day']);
        if($x->snapshot()!==$s)throw new \DomainException('Constitutional revision replay mismatch');
        return $x;
    }
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Unexpected revision fields');}
    private static function id(mixed $id):void{if(!is_string($id)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id))throw new \InvalidArgumentException('Invalid revision identifier');}
    private static function day(mixed $d):void{if(!is_string($d)||($v=\DateTimeImmutable::createFromFormat('!Y-m-d',$d))===false||$v->format('Y-m-d')!==$d)throw new \InvalidArgumentException('Invalid revision day');}
}
