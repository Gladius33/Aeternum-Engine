<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Fictional France-only implementation of the international notice rule.
 * The ordinary law is a scenario authorization gate, not a claim about the
 * domestic legal requirements for denunciation in real France. */
final class NatoWithdrawal
{
    public const VERSION='wp114-nato-article13-v1';
    public const POLICY='pf2022-philippe-poutou-4';
    public const SOURCE='https://www.nato.int/en/about-us/official-texts-and-resources/official-texts/1949/04/04/the-north-atlantic-treaty';
    public static function notice(string $day,string $policy,string $effective,string $instrument,string $domesticSha):array
    {
        self::day($day);self::day($effective);
        if($day<$effective||!preg_match('/^pf2022-philippe-poutou-4(?:-r(?:[2-9]|[1-9][0-9]{1,2}))?$/D',$policy))
            throw new \DomainException('Effective withdrawal authorization required');
        foreach([$instrument,$domesticSha] as $h)if(!preg_match('/^[a-f0-9]{64}$/D',$h))throw new \DomainException('Unverified notice source');
        // One calendar year. A leap-day notice reaches its anniversary
        // on March 1 in this day-granular simulation.
        $end=(new \DateTimeImmutable($day))->modify('+1 year')->format('Y-m-d');
        return ['version'=>self::VERSION,'classification'=>'fictional_treaty_notice','country'=>'FR','source'=>self::SOURCE,
            'day'=>$day,'status'=>'notice_deposited','notice_on'=>$day,'effective_on'=>$end,'depositary'=>'state_us',
            'policy_id'=>$policy,'authorization_effective_on'=>$effective,'instrument_sha'=>$instrument,'domestic_source_sha'=>$domesticSha,
            'article5_treaty_applies'=>true,'other_defense_treaties_unchanged'=>true,'withdrawal_requires_allied_approval'=>false];
    }
    public static function advance(array $s,string $day):array
    {
        self::day($day);
        $old=self::restore($s);
        if($day<$old['day'])throw new \DomainException('Treaty clock regression');
        $old['day']=$day;$done=$day>=$old['effective_on'];
        $old['status']=$done?'withdrawn':'notice_deposited';$old['article5_treaty_applies']=!$done;return $old;
    }
    public static function restore(array $s):array
    {
        $v=self::notice($s['notice_on'],$s['policy_id'],$s['authorization_effective_on'],$s['instrument_sha'],$s['domestic_source_sha']);
        self::day($s['day']);
        if($s['day']<$s['notice_on'])throw new \DomainException('Notice in future');
        $v['day']=$s['day'];$done=$s['day']>=$v['effective_on'];$v['status']=$done?'withdrawn':'notice_deposited';$v['article5_treaty_applies']=!$done;
        if($v!==$s)throw new \DomainException('Invalid Article 13 state');
        return $v;
    }
    private static function day(string $v):void
    {
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v);
        if($d===false||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Invalid treaty day');
    }
}
