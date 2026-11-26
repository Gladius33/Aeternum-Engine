<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Native-minor-unit EUR/FRF debt settlement pilot. No money creation,
 * no implicit redenomination and no automatic authority to draw reserves. */
final class FrancDebtSettlement
{
    public const VERSION='wp54r-frf-debt-settlement-v1';
    private const LIMIT=10000000000000000;
    private static function amount(mixed $v):int
    {
        if(!is_int($v)||$v<0||$v>self::LIMIT)throw new \DomainException('Invalid settlement amount');
        return $v;
    }
    private static function fields(array $a,array $expected):void
    {
        $keys=array_keys($a);sort($keys);sort($expected);
        if($keys!==$expected)throw new \DomainException('Invalid settlement fields');
    }
    private static function scale(int $n,int $r,int $d,bool $ceil=false):int
    {
        $remainder=($n%$d)*$r;
        return intdiv($n,$d)*$r+intdiv($remainder,$d)+($ceil&&$remainder%$d!==0?1:0);
    }
    /** Input claims contain the outstanding principal and ALL currently due
     * principal/interest, including previous arrears. Output claims preserve
     * unpaid dues; the caller adds only new maturities/coupons next day.
     * Interest precedes principal, then lexical claim ID within each tier.
     * A reserve purchase transfers treasury FRF to the official FX pool;
     * the official EUR goes directly to the creditor (not twice via cash).
     */
    public static function step(array $accounts,array $claims,int $ratio,int $mark,bool $reserveAuthorized=false,bool $moratorium=false):array
    {
        self::fields($accounts,['treasury_frf_minor','treasury_eur_minor','official_eur_reserves_minor','official_franc_pool_minor','minimum_official_eur_minor']);
        foreach($accounts as $v)self::amount($v);
        if($ratio<1||$ratio>10||$mark<5000||$mark>15000||count($claims)>128)throw new \DomainException('Invalid quote or claim count');
        $a=$accounts;
        $frfTotal=$a['treasury_frf_minor']+$a['official_franc_pool_minor'];
        $eurTotal=$a['treasury_eur_minor']+$a['official_eur_reserves_minor'];
        if(max($frfTotal,$eurTotal)>self::LIMIT||$a['official_eur_reserves_minor']<$a['minimum_official_eur_minor'])throw new \DomainException('Invalid opening pools');
        ksort($claims,SORT_STRING);
        foreach($claims as $id=>$c){
            if(!is_string($id)||!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,95}$/D',$id)||!is_array($c))throw new \DomainException('Invalid claim ID');
            self::fields($c,['currency','principal_outstanding_minor','principal_due_minor','interest_due_minor']);
            if(!in_array($c['currency'],['FRF','EUR'],true))throw new \DomainException('Unsupported settlement currency');
            foreach(['principal_outstanding_minor','principal_due_minor','interest_due_minor'] as $k)self::amount($c[$k]);
            if($c['principal_due_minor']>$c['principal_outstanding_minor'])throw new \DomainException('Due exceeds outstanding principal');
        }
        $openingClaims=$claims;$receipts=[];$received=['FRF'=>0,'EUR'=>0];$reserveEur=0;$reserveFrf=0;
        foreach($claims as $id=>$c)$receipts[$id]=['currency'=>$c['currency'],'interest_paid_minor'=>0,'principal_paid_minor'=>0,'reserve_eur_used_minor'=>0,'reserve_frf_cost_minor'=>0];
        foreach(['interest','principal'] as $kind){
            $due=$kind.'_due_minor';$paidKey=$kind.'_paid_minor';
            foreach($claims as $id=>&$c){
                $want=$c[$due];$paid=0;
                if(!$moratorium&&$want>0){
                    if($c['currency']==='FRF'){
                        $paid=min($want,$a['treasury_frf_minor']);$a['treasury_frf_minor']-=$paid;
                    }else{
                        $paid=min($want,$a['treasury_eur_minor']);$a['treasury_eur_minor']-=$paid;
                        if($reserveAuthorized&&$paid<$want){
                            $n=$ratio*10000;
                            $fx=min($want-$paid,$a['official_eur_reserves_minor']-$a['minimum_official_eur_minor'],self::scale($a['treasury_frf_minor'],$mark,$n));
                            $cost=self::scale($fx,$n,$mark,true);
                            $a['treasury_frf_minor']-=$cost;$a['official_franc_pool_minor']+=$cost;$a['official_eur_reserves_minor']-=$fx;
                            $paid+=$fx;$reserveEur+=$fx;$reserveFrf+=$cost;
                            $receipts[$id]['reserve_eur_used_minor']+=$fx;$receipts[$id]['reserve_frf_cost_minor']+=$cost;
                        }
                    }
                }
                $c[$due]-=$paid;
                if($kind==='principal')$c['principal_outstanding_minor']-=$paid;
                $receipts[$id][$paidKey]=$paid;$received[$c['currency']]+=$paid;
            }unset($c);
        }
        if(min($a)<0||$a['official_eur_reserves_minor']<$a['minimum_official_eur_minor']
            ||$a['treasury_frf_minor']+$a['official_franc_pool_minor']+$received['FRF']!==$frfTotal
            ||$a['treasury_eur_minor']+$a['official_eur_reserves_minor']+$received['EUR']!==$eurTotal
            ||$accounts['official_eur_reserves_minor']-$a['official_eur_reserves_minor']!==$reserveEur
            ||$a['official_franc_pool_minor']-$accounts['official_franc_pool_minor']!==$reserveFrf)
            throw new \DomainException('Settlement conservation failure');
        return ['version'=>self::VERSION,'opening_accounts'=>$accounts,'closing_accounts'=>$a,'opening_claims'=>$openingClaims,'closing_claims'=>$claims,
            'creditor_receipts'=>$receipts,'creditor_receipts_by_currency_minor'=>$received,'reserve_eur_used_minor'=>$reserveEur,'treasury_frf_paid_for_eur_minor'=>$reserveFrf,
            'frf_per_eur_rate'=>['numerator'=>$ratio*10000,'denominator'=>$mark], 'moratorium'=>$moratorium,'reserve_purchase_authorized'=>$reserveAuthorized,
            'base_money_created_frf_minor'=>0,'national_reference_ledger_changed'=>false];
    }
}
