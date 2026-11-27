<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** National wage-floor indexation and fictional sector alternative.
 * Uses committed macro price/wage proxies, not observed low-income CPI or
 * the actual hourly wage survey. Nominal floor cannot decline under the
 * retained national rule; purchasing power can. */
final class WageFloorRegime
{
    public const VERSION='fr-wage-floor-regime-v1';
    public const POLICY='frv1-benedetti-branch-wage';
    public const OPENING_MINOR=142600;
    public static function national(string $day,?array $prior):array{
        $old=$prior['social_economy']['wage_floor_regime']['national_reference']??null;
        $price=max(1,(int)($prior['macro']['price_index_scaled']??100000000));
        $wage=max(1,(int)($prior['macro']['wage_index_scaled']??100000000));
        if($old===null)$old=['monthly_net_minor'=>self::OPENING_MINOR,'price_anchor'=>100000000,'annual_price_anchor'=>100000000,'annual_wage_anchor'=>100000000];
        $floor=$old['monthly_net_minor'];$p=$old['price_anchor'];$ap=$old['annual_price_anchor'];$aw=$old['annual_wage_anchor'];
        $annual=substr($day,5)==='01-01';$inflation=$price*100>=$p*102;
        if($annual||$inflation){
            $cpi=max(10000,intdiv($price*10000,max(1,$p)));
            $gain=$annual?max(0,intdiv(intdiv($wage*10000,max(1,$aw))*10000,max(1,intdiv($price*10000,max(1,$ap))))-10000):0;
            $floor=intdiv($floor*($cpi+intdiv($gain,2))+9999,10000);
            $p=max($p,$price);
            if($annual){$ap=$price;$aw=$wage;}
        }
        return ['monthly_net_minor'=>$floor,'price_anchor'=>$p,'annual_price_anchor'=>$ap,'annual_wage_anchor'=>$aw];
    }
    public static function report(array $national,int $effective,?array $corporations):array{
        $branches=[];$mode='national_indexed';
        if(($corporations['minimum_wage_regime']??null)==='state_validated_branches'){
            $mode='state_validated_branches';
            foreach($corporations['branches'] as $id=>$b)$branches[$id]=['minimum_net_minor'=>$b['minimum_net_minor'],'report'=>$b['report']];
        }
        return ['version'=>self::VERSION,'mode'=>$mode,'national_reference'=>$national,
            'national_legal_floor_minor'=>$mode==='national_indexed'?$effective:null,
            'branch_minima'=>$branches,'weighted_branch_floor_minor'=>$branches===[]?null:CorporationWageFeedback::weightedFloor($corporations),
            'national_reference_is_counterfactual'=>$mode!=='national_indexed','actual_statutory_indexation_certified'=>false,
            'individual_payroll_calibrated'=>false];
    }
}
