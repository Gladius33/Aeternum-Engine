<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CampaignLifecycle.php';

/** Isolated fictional constitutional-order kernel, not an Article 89 procedure
 * and not a real-world transition strategy. No direct player activation.
 * Independent adjudicated outcomes are inputs from a trusted scenario service.
 * The full-world authority handoff is deliberately not installed by this class. */
final class AlternativeOrder
{
    public const VERSION='wp60-alternative-order-v1';
    public static function catalog():array
    {
        return [
            'elective_constitutional_monarchy'=>['head_of_state'=>'elected_monarch','term_years'=>7,'term_limit'=>null,'selection'=>'independent_constituent_election','government'=>'parliamentary','inherited_presidential_powers'=>false],
            'new_parliamentary_republic'=>['head_of_state'=>'elected_head_of_state','term_years'=>6,'term_limit'=>2,'selection'=>'independent_constituent_election','government'=>'parliamentary','inherited_presidential_powers'=>false]
        ];
    }
    private static function date(string $d):void
    { $x=\DateTimeImmutable::createFromFormat('!Y-m-d',$d);if(!$x||$x->format('Y-m-d')!==$d)throw new \DomainException('Invalid order date'); }
    private static function id(string $id):void
    {if(!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id))throw new \DomainException('Invalid order identity');}
    private static function sha(string $s):void
    {if(!preg_match('/^[a-f0-9]{64}$/D',$s))throw new \DomainException('Invalid order digest');}
    private static function fields(array $a,array $b):void
    {$keys=array_keys($a);sort($keys);sort($b);if($keys!==$b)throw new \DomainException('Unexpected order fields');}
    private static function anniversary(string $d,int $n):string
    {
        $x=new \DateTimeImmutable($d);$first=$x->setDate((int)$x->format('Y')+$n,(int)$x->format('n'),1);
        return $first->setDate((int)$first->format('Y'),(int)$first->format('n'),min((int)$x->format('j'),(int)$first->format('t')))->format('Y-m-d');
    }
    /** Trusted scenario certificate, not a cryptographic verifier. The caller
     * must independently authenticate the issuing service and committed campaign. */
    public static function initial(array $campaign,array $e):array
    {
        self::fields($e,['world_id','day','evidence_id','order','instrument_sha','campaign_sha','selected_holder','source']);
        self::date($e['day']);self::id($e['world_id']);self::id($e['evidence_id']);self::id($e['selected_holder']);self::sha($e['instrument_sha']);self::sha($e['campaign_sha']);
        $rule=self::catalog()[$e['order']]??throw new \DomainException('Unknown alternative order');
        if($e['source']!=='trusted_fictional_constituent_outcome'||$campaign['world_id']!==$e['world_id']||$campaign['day']!==$e['day']||$campaign['status']!=='active'
            ||($campaign['order_review']['status']??null)!=='pending_independent_review'||$campaign['constitutional_status']!=='disputed'
            ||!hash_equals(hash('sha256',json_encode($campaign,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)),$e['campaign_sha']))
            throw new \DomainException('Uncertified alternative order');
        $holder=$e['selected_holder'];$retained=$holder===CampaignLifecycle::PLAYER;
        return ['version'=>self::VERSION,'classification'=>'isolated_fictional_order','world_id'=>$e['world_id'],'day'=>$e['day'],'campaign_start'=>$campaign['start_day'],
            'order'=>$e['order'],'rules'=>$rule,'activation'=>$e,'term_start'=>$e['day'],'term_end'=>self::anniversary($e['day'],$rule['term_years']),
            'terms'=>1,'officeholder'=>$holder,'status'=>$retained?'active':'ended','ended_on'=>$retained?null:$e['day'],'reason'=>$retained?null:'not_selected_in_new_order',
            'history'=>[],'rank_eligible'=>false,'full_world_handoff'=>false];
    }
    /** A renewal certificate is valid only at the exact term boundary. Absence
     * of a certificate cannot extend an expired term. No vote numbers from a client. */
    public static function advance(array $s,string $day,?array $result=null):array
    {
        self::date($day);if($s['status']!=='active'||$day<$s['day']||$day>$s['term_end'])throw new \DomainException('Invalid alternative order clock');
        if($day!==$s['term_end']){
            if($result!==null)throw new \DomainException('Renewal outside term boundary');
            $s['day']=$day;return $s;
        }
        $winner=null;
        if($result!==null){
            self::fields($result,['world_id','day','evidence_id','order','term_start','selected_holder','instrument_sha','source']);
            self::id($result['evidence_id']);self::id($result['selected_holder']);self::sha($result['instrument_sha']);
            if($result['world_id']!==$s['world_id']||$result['day']!==$day||$result['order']!==$s['order']||$result['term_start']!==$s['term_start']||$result['source']!=='trusted_fictional_renewal_outcome'||$result['instrument_sha']!==$s['activation']['instrument_sha']||$result['evidence_id']===$s['activation']['evidence_id'])throw new \DomainException('Uncertified renewal');
            foreach($s['history'] as $h)if(($h['certificate']['evidence_id']??null)===$result['evidence_id'])throw new \DomainException('Duplicate renewal evidence');
            $winner=$result['selected_holder'];
        }
        $limit=$s['rules']['term_limit'];$eligible=$limit===null||$s['terms']<$limit;
        if(!$eligible&&$winner===CampaignLifecycle::PLAYER)throw new \DomainException('Ineligible incumbent in new order');
        $s['history'][]=['day'=>$day,'term_start'=>$s['term_start'],'certificate'=>$result,'eligible'=>$eligible,'winner'=>$winner];
        $s['day']=$day;
        if($winner===CampaignLifecycle::PLAYER&&$eligible){
            $s['terms']++;$s['term_start']=$day;$s['term_end']=self::anniversary($day,$s['rules']['term_years']);
        }else{
            $s['officeholder']=$winner;$s['status']='ended';$s['ended_on']=$day;$s['reason']=$winner===null?'term_expired_without_renewal':'renewal_lost';
        }
        return $s;
    }
    public static function restore(array $s,array $priorCampaign):array
    {
        $x=self::initial($priorCampaign,$s['activation']);
        foreach($s['history'] as $h)$x=self::advance($x,$h['day'],$h['certificate']);
        if($x['day']!==$s['day'])$x=self::advance($x,$s['day']);
        if($x!==$s)throw new \DomainException('Alternative order replay mismatch');return $x;
    }
}
