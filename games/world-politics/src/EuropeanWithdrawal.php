<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Article 50 procedural pilot: fictional certified evidence, not actual legal advice.
 * National constitutional authority is deliberately not inferred from ownership. */
final class EuropeanWithdrawal
{
    public const VERSION='wp54r-article50-v1';
    public const SOURCE='https://eur-lex.europa.eu/eli/treaty/teu_2016/art_50/oj';
    public static function initial(string $day):array
    {
        self::date($day);
        return ['version'=>self::VERSION,'source'=>self::SOURCE,'start_day'=>$day,'day'=>$day,
            'status'=>'member','membership'=>true,'intent_on'=>null,'notified_on'=>null,'deadline'=>null,
            'agreement_effective_on'=>null,'exit_on'=>null,'exit_route'=>null,'history'=>[],
            'currency_changed'=>false,'tariff_regime_implemented'=>false];
    }
    public static function request(array $s,string $day):array
    {
        if($s['day']!==$day||count($s['history'])>=1000||!in_array($s['status'],['member','revoked'],true))throw new \DomainException('Withdrawal intent unavailable');
        $s['status']='intent_pending';$s['intent_on']=$day;$s['notified_on']=null;$s['deadline']=null;$s['agreement_effective_on']=null;
        $s['history'][]=['day'=>$day,'kind'=>'intent','data'=>[]];return $s;
    }
    public static function certify(array $s,string $day,string $kind,array $data):array
    {
        if($s['day']!==$day||count($s['history'])>=1000)throw new \DomainException('Invalid European evidence day');
        switch($kind){
            case 'notification':
                self::fields($data,['national_decision_sha','notification_sha']);
                self::sha($data['national_decision_sha']);self::sha($data['notification_sha']);
                if($s['status']!=='intent_pending')throw new \DomainException('No withdrawal intent');
                $s['status']='negotiating';$s['notified_on']=$day;
                // Calendar years, including leap-day anniversary.
                $year=(int)substr($day,0,4)+2;$month=(int)substr($day,5,2);$d=(int)substr($day,8,2);
                if(!checkdate($month,$d,$year))$d=28;
                $s['deadline']=sprintf('%04d-%02d-%02d',$year,$month,$d);break;
            case 'extension':
                self::fields($data,['new_deadline','unanimous_european_council','france_agrees','decision_sha']);
                self::date($data['new_deadline']);self::sha($data['decision_sha']);
                if(!in_array($s['status'],['negotiating','agreement_concluded'],true)||$day>=$s['deadline']||
                    $data['new_deadline']<=$s['deadline']||$data['unanimous_european_council']!==true||$data['france_agrees']!==true)
                    throw new \DomainException('No valid Article 50 extension');
                $s['deadline']=$data['new_deadline'];break;
            case 'agreement':
                self::fields($data,['effective_on','parliament_consent','council_qualified_majority','france_agrees','agreement_sha']);
                self::date($data['effective_on']);self::sha($data['agreement_sha']);
                if($s['status']!=='negotiating'||$day>=$s['deadline']||$data['effective_on']<=$day||$data['effective_on']>$s['deadline']
                    ||$data['parliament_consent']!==true||$data['council_qualified_majority']!==true||$data['france_agrees']!==true)
                    throw new \DomainException('Withdrawal agreement not concluded');
                $s['status']='agreement_concluded';$s['agreement_effective_on']=$data['effective_on'];break;
            case 'revocation':
                self::fields($data,['national_decision_sha','notice_sha','unequivocal_unconditional']);
                self::sha($data['national_decision_sha']);self::sha($data['notice_sha']);
                if(!in_array($s['status'],['negotiating','agreement_concluded'],true)||$day>=$s['deadline']
                    ||$data['unequivocal_unconditional']!==true)throw new \DomainException('Revocation not certified');
                $s['status']='revoked';$s['deadline']=null;$s['agreement_effective_on']=null;break;
            default:throw new \InvalidArgumentException('Unknown European evidence');
        }
        $s['history'][]=['day'=>$day,'kind'=>$kind,'data'=>$data];return $s;
    }
    public static function advance(array $s,string $day):array
    {
        self::date($day);if($day<$s['day'])throw new \DomainException('European clock regression');
        if($s['status']==='agreement_concluded'&&$day>=$s['agreement_effective_on']){
            $s['status']='withdrawn';$s['membership']=false;$s['exit_on']=$s['agreement_effective_on'];$s['exit_route']='agreement';
        }elseif(in_array($s['status'],['negotiating','agreement_concluded'],true)&&$day>=$s['deadline']){
            $s['status']='withdrawn';$s['membership']=false;$s['exit_on']=$s['deadline'];$s['exit_route']='deadline';
        }
        $s['day']=$day;return $s;
    }
    public static function restore(array $s):array
    {
        $r=self::initial($s['start_day']);
        if(!is_array($s['history'])||!array_is_list($s['history'])||count($s['history'])>1000)throw new \DomainException('Invalid EU history');
        foreach($s['history'] as $h){
            self::fields($h,['day','kind','data']);$r=self::advance($r,$h['day']);
            $r=$h['kind']==='intent'?self::request($r,$h['day']):self::certify($r,$h['day'],$h['kind'],$h['data']);
        }
        $r=self::advance($r,$s['day']);if($r!==$s)throw new \DomainException('EU replay mismatch');return $r;
    }
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Unexpected EU evidence');}
    private static function date(mixed $v):void{if(!is_string($v)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v))===false||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Invalid EU date');}
    private static function sha(mixed $v):void{if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new \InvalidArgumentException('Invalid EU evidence hash');}
}
