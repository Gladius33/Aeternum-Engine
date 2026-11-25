<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrenchCabinet.php';

/** WP45: narrowly reviewed fictional executive acts. No emergency powers,
 * unrestricted lawmaking, or real-world legal effect. */
final class ExecutiveActs
{
    public const VERSION='wp45-executive-v1';
    public const POLICY='administration-report';
    public static function catalog():array
    {
        return [
            'reporting-decree'=>['domain'=>'regulatory','portfolio'=>'economy','policy_id'=>self::POLICY,
                'text'=>'Fictional administrative reporting pilot: use existing appropriations for reporting services, 500 EUR daily for 10 days.'],
            'reporting-ordinance'=>['domain'=>'legislative','portfolio'=>'economy','policy_id'=>null,
                'text'=>'Fictional delegated measure establishing a public administrative reporting obligation.']
        ];
    }
    public static function initial():array
    {return ['version'=>self::VERSION,'classification'=>'fictional_scenario','habilitations'=>[],'acts'=>[]];}
    public static function advance(array $s,string $day):array
    {
        foreach($s['acts'] as &$a){
            if($a['kind']==='ordinance'&&$a['status']==='published'&&$a['ratification_filed_on']===null
                &&$day>=$s['habilitations'][$a['habilitation']]['filing_deadline']){
                $a['status']='lapsed';$a['lapsed_on']=$s['habilitations'][$a['habilitation']]['filing_deadline'];
            }
        }unset($a);return $s;
    }
    public static function apply(array $s,array $cabinet,array $e):array
    {
        $d=$e['data'];$day=$e['day'];$id=$e['evidence_id'];
        if(!is_array($d))throw new \InvalidArgumentException('Invalid executive data');
        $s=self::advance($s,$day);
        switch($e['kind']){
            case 'executive_habilitation':
                self::fields($d,['scope','law_sha','authorization_end','filing_deadline','law_published_certified']);
                self::sha($d['law_sha']);self::day($d['authorization_end']);self::day($d['filing_deadline']);
                if($d['scope']!=='reporting-ordinance'||$d['law_published_certified']!==true||$d['authorization_end']<=$day
                    ||$d['filing_deadline']<$d['authorization_end']||isset($s['habilitations'][$id])||count($s['habilitations'])>=100)
                    throw new \DomainException('Invalid limited habilitation');
                $s['habilitations'][$id]=['id'=>$id,'scope'=>$d['scope'],'law_sha'=>$d['law_sha'],'published_on'=>$day,
                    'authorization_end'=>$d['authorization_end'],'filing_deadline'=>$d['filing_deadline']];
                return [$s,['habilitation'=>$s['habilitations'][$id],'policy_authorized'=>false]];
            case 'executive_sign':
                self::fields($d,['template','kind','habilitation','signer','prime_minister','minister','council_deliberated','state_council_consulted','procedure_reviewed']);
                $t=self::catalog()[$d['template']]??throw new \DomainException('Unreviewed executive template');
                FrenchCabinet::requirePrimeMinister($cabinet,$d['prime_minister']);
                if($d['minister']===null||$d['minister']!==$cabinet['ministers'][$t['portfolio']]||$d['procedure_reviewed']!==true
                    ||!is_bool($d['council_deliberated'])||!is_bool($d['state_council_consulted'])||isset($s['acts'][$id])||count($s['acts'])>=100)
                    throw new \DomainException('Missing competent countersignature or review');
                if($d['kind']==='pm_decree'){
                    if($t['domain']!=='regulatory'||$d['signer']!==$cabinet['prime_minister']||$d['council_deliberated']||$d['habilitation']!==null)
                        throw new \DomainException('Invalid prime minister decree');
                }elseif($d['kind']==='council_decree'){
                    if($t['domain']!=='regulatory'||$d['signer']!==$cabinet['president']||!$d['council_deliberated']||$d['habilitation']!==null)
                        throw new \DomainException('Invalid Council decree');
                }elseif($d['kind']==='ordinance'){
                    $h=$s['habilitations'][$d['habilitation']]??null;
                    if($t['domain']!=='legislative'||$h===null||$h['scope']!==$d['template']||$day>=$h['authorization_end']
                        ||$day<$h['published_on']||$d['signer']!==$cabinet['president']||!$d['council_deliberated']||!$d['state_council_consulted'])
                        throw new \DomainException('Ordinance outside habilitation or missing constitutional form');
                }else throw new \DomainException('Unknown executive act');
                $s['acts'][$id]=['id'=>$id,'template'=>$d['template'],'kind'=>$d['kind'],'habilitation'=>$d['habilitation'],
                    'text_sha'=>hash('sha256',$t['text']),'policy_id'=>$t['policy_id'],'status'=>'signed','signed_on'=>$day,
                    'signer'=>$d['signer'],'prime_minister'=>$d['prime_minister'],'minister'=>$d['minister'],'cabinet_generation'=>$cabinet['generation'],
                    'published_on'=>null,'effective_on'=>null,'ratification_filed_on'=>null,'ratified_on'=>null,'lapsed_on'=>null,
                    'ratification_law_sha'=>null,'ratification_bill_sha'=>null];
                return [$s,['act'=>$s['acts'][$id],'policy_authorized'=>false]];
            case 'executive_publish':
            case 'executive_ratification_file':
            case 'executive_ratification':
                $a=$s['acts'][$d['act_id']??'']??throw new \DomainException('Unknown executive act');
                if($e['kind']==='executive_publish'){
                    self::fields($d,['act_id','text_sha','publication_certified','effective_on']);self::day($d['effective_on']);
                    if($a['status']!=='signed'||$d['publication_certified']!==true||$d['text_sha']!==$a['text_sha']
                        ||($a['kind']==='ordinance'?$d['effective_on']!==$day:$d['effective_on']<=$day))
                        throw new \DomainException('Invalid publication');
                    if($a['kind']==='ordinance'&&$day>=$s['habilitations'][$a['habilitation']]['authorization_end'])
                        throw new \DomainException('Ordinance publication outside delegation');
                    $a['status']='published';$a['published_on']=$day;$a['effective_on']=$d['effective_on'];
                }elseif($e['kind']==='executive_ratification_file'){
                    self::fields($d,['act_id','bill_sha','filing_certified']);self::sha($d['bill_sha']);
                    if($a['kind']!=='ordinance'||$a['status']!=='published'||$a['ratification_filed_on']!==null
                        ||$d['filing_certified']!==true||$day>=$s['habilitations'][$a['habilitation']]['filing_deadline'])
                        throw new \DomainException('Ratification bill not timely');
                    $a['ratification_filed_on']=$day;$a['ratification_bill_sha']=$d['bill_sha'];
                }else{
                    self::fields($d,['act_id','law_sha','bill_sha','express_ratification_certified']);self::sha($d['law_sha']);
                    if($a['kind']!=='ordinance'||$a['status']!=='published'||$a['ratification_filed_on']===null
                        ||$d['bill_sha']!==$a['ratification_bill_sha']||$d['express_ratification_certified']!==true)
                        throw new \DomainException('Express ratification not verified');
                    $a['status']='ratified';$a['ratified_on']=$day;$a['ratification_law_sha']=$d['law_sha'];
                }
                $s['acts'][$a['id']]=$a;return [$s,['act'=>$a,'policy_authorized'=>false]];
            default:throw new \InvalidArgumentException('Unknown executive evidence');
        }
    }
    public static function proof(array $s,string $policy,string $sha,string $day):?array
    {
        foreach($s['acts'] as $a)if($a['policy_id']===$policy&&$a['status']==='published'&&$a['kind']!=='ordinance'
            &&$a['text_sha']===$sha&&$a['effective_on']===$day)return $a;
        return null;
    }
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Unexpected executive fields');}
    private static function day(mixed $v):void{if(!is_string($v)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v))===false||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Invalid executive day');}
    private static function sha(mixed $v):void{if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new \InvalidArgumentException('Invalid legal digest');}
}
