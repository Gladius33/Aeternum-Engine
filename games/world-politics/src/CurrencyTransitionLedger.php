<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/FrancExchangeMarket.php';

/** Closed fictional contract book. FRF reuses the historic French franc code
 * for the scenario's new national currency; never the Swiss CHF. No actual national ledger changes. */
final class CurrencyTransitionLedger
{
    public const VERSION='wp54r-currency-contracts-v1';
    public const SECTORS=['fr_treasury','fr_banks','fr_households','fr_firms','foreign_public','foreign_banks','foreign_private'];
    private static function fields(array $a,array $b):void
    {$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Invalid currency fields');}
    private static function date(mixed $v):void
    {if(!is_string($v)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v))===false||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Invalid currency date');}
    private static function sha(mixed $v):void
    {if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new \InvalidArgumentException('Invalid currency evidence');}
    private static function scale(int $n,int $r,int $d):int
    {return intdiv($n,$d)*$r+intdiv(($n%$d)*$r,$d);}
    public static function validateTerms(array $data,string $day):void
    {
        self::fields($data,['effective_on','units_per_eur','instrument_sha','contracts']);
        self::date($data['effective_on']);self::sha($data['instrument_sha']);
        if($data['effective_on']<=$day||!is_int($data['units_per_eur'])||$data['units_per_eur']<1||$data['units_per_eur']>10
            ||!is_array($data['contracts'])||!array_is_list($data['contracts'])||count($data['contracts'])<1||count($data['contracts'])>128)
            throw new \DomainException('Invalid prospective currency scenario');
        $ids=[];$sum=0;
        foreach($data['contracts'] as $v){
            if(!is_array($v))throw new \DomainException('Invalid contract');
            self::fields($v,['id','debtor','creditor','kind','principal_eur_minor','governing_law','treatment','evidence_sha']);
            if(!is_string($v['id'])||!preg_match('/^[a-z][a-z0-9-]{0,63}$/D',$v['id'])||isset($ids[$v['id']])
                ||!in_array($v['debtor'],self::SECTORS,true)||!in_array($v['creditor'],self::SECTORS,true)||$v['debtor']===$v['creditor']
                ||(!str_starts_with($v['debtor'],'fr_')&&!str_starts_with($v['creditor'],'fr_'))
                ||!in_array($v['kind'],['sovereign_bond','deposit','loan','trade_payable'],true)
                ||($v['kind']==='sovereign_bond'&&$v['debtor']!=='fr_treasury')
                ||!is_int($v['principal_eur_minor'])||$v['principal_eur_minor']<=0||$v['principal_eur_minor']>10000000000000
                ||!in_array($v['governing_law'],['fr','foreign','unknown'],true)
                ||!in_array($v['treatment'],['convert_certified','retain_eur','contested'],true))
                throw new \DomainException('Invalid fictional contract');
            if($v['treatment']==='convert_certified'&&$v['governing_law']!=='fr')throw new \DomainException('No authority for this conversion');
            if($v['treatment']==='convert_certified'||$v['treatment']==='retain_eur')self::sha($v['evidence_sha']);
            elseif($v['evidence_sha']!==null)throw new \DomainException('Contested evidence must remain unresolved');
            $ids[$v['id']]=true;$sum+=$v['principal_eur_minor'];
        }
        if($sum>100000000000000)throw new \DomainException('Scenario book too large');
    }
    public static function validateMark(array $data,string $day,?string $last):void
    {
        self::fields($data,['effective_on','value_index_bps','instrument_sha']);
        self::date($data['effective_on']);self::sha($data['instrument_sha']);
        if($data['effective_on']<=$day||($last!==null&&$data['effective_on']<=$last)
            ||!is_int($data['value_index_bps'])||$data['value_index_bps']<5000||$data['value_index_bps']>15000)
            throw new \DomainException('Invalid future FX mark');
    }
    public static function project(array $eu):array
    {
        if(($eu['currency_contract_version']??null)!==self::VERSION)throw new \DomainException('Unpinned currency book');
        $terms=$eu['currency_terms'];$start=$terms!==null&&!$eu['membership']?max($eu['exit_on'],$terms['effective_on']):null;
        $active=$start!==null&&$eu['day']>=$start;$ratio=$terms['units_per_eur']??1;$mark=10000;$markDay=null;
        if($active)foreach($eu['currency_marks'] as $m)if($m['effective_on']<=$eu['day']){
            // Never apply a pre-conversion valuation to the opening parity.
            if($m['effective_on']>$start){$mark=$m['value_index_bps'];$markDay=$m['effective_on'];}
        }
        $market=$active&&($eu['franc_fx_market_terms']??null)!==null?FrancExchangeMarket::quotes($eu,$eu['day'],$start):null;
        if($market!==null){$mark=$market['value_index_bps'];$markDay=$market['last_change_day'];}
        $sectors=[];
        foreach(self::SECTORS as $id)$sectors[$id]=['assets_eur_minor'=>0,'liabilities_eur_minor'=>0,'assets_frf_minor'=>0,'liabilities_frf_minor'=>0,
            'assets_at_eur_value_minor'=>0,'liabilities_at_eur_value_minor'=>0,'opening_net_eur_minor'=>0,'net_eur_valuation_delta_minor'=>0];
        $claims=[];$total=0;$converted=0;$retained=0;$contested=0;$sovereign=0;$sovereignEur=0;$sovereignFrf=0;$sovereignLocal=0;
        foreach($terms['contracts']??[] as $v){
            $p=$v['principal_eur_minor'];$total+=$p;
            $convertedNow=$active&&$v['treatment']==='convert_certified';
            $currency=$convertedNow?'FRF':'EUR';$nominal=$convertedNow?$p*$ratio:$p;
            $eur=$convertedNow?self::scale($p,$mark,10000):$p;
            $local=$active?($convertedNow?$nominal:self::scale($p,$ratio*10000,$mark)):null;
            $delta=$eur-$p;
            if($convertedNow)$converted+=$p;else $retained+=$p;
            if($v['treatment']==='contested')$contested+=$p;
            if($v['kind']==='sovereign_bond'){
                $sovereign+=$p;if($convertedNow)$sovereignFrf+=$nominal;else $sovereignEur+=$nominal;
                if($active)$sovereignLocal+=$local;
            }
            $cr=$v['creditor'];$de=$v['debtor'];$c=strtolower($currency);
            $sectors[$cr]['assets_'.$c.'_minor']+=$nominal;$sectors[$de]['liabilities_'.$c.'_minor']+=$nominal;
            $sectors[$cr]['assets_at_eur_value_minor']+=$eur;$sectors[$de]['liabilities_at_eur_value_minor']+=$eur;
            $sectors[$cr]['opening_net_eur_minor']+=$p;$sectors[$de]['opening_net_eur_minor']-=$p;
            $sectors[$cr]['net_eur_valuation_delta_minor']+=$delta;$sectors[$de]['net_eur_valuation_delta_minor']-=$delta;
            $claims[]=$v+['current_currency'=>$currency,'current_principal_minor'=>$nominal,'eur_valuation_minor'=>$eur,
                'frf_valuation_minor'=>$local,'eur_valuation_delta_minor'=>$delta,'conversion_applied'=>$convertedNow];
        }
        foreach(['eur','frf','at_eur_value'] as $unit){
            if(array_sum(array_column($sectors,'assets_'.$unit.'_minor'))!==array_sum(array_column($sectors,'liabilities_'.$unit.'_minor')))
                throw new \DomainException('Unbalanced currency book');
        }
        if($converted+$retained!==$total||array_sum(array_column($sectors,'net_eur_valuation_delta_minor'))!==0)
            throw new \DomainException('Currency book conservation failure');
        return ['version'=>self::VERSION,'classification'=>'fictional_closed_contract_book_not_forecast',
            'status'=>$terms===null?'not_configured':($active?'conversion_simulated':'pending_effective_date'),
            'effective_from'=>$start,'currency_conversion_simulated'=>$active,'national_currency_changed'=>false,
            'national_treasury_booked'=>false,'new_currency'=>'FRF','original_currency'=>'EUR','currency_projection_schema'=>'frf-v2',
            'units_per_eur_at_parity'=>$terms===null?null:$ratio,'new_currency_value_index_bps'=>$active?$mark:null,
            'fx_mark_effective_on'=>$markDay,'eur_per_frf_rate'=>$active?['numerator'=>$mark,'denominator'=>$ratio*10000]:null,
            'frf_per_eur_rate'=>$active?['numerator'=>$ratio*10000,'denominator'=>$mark]:null,
            'opening_contract_principal_eur_minor'=>$total,'converted_opening_principal_eur_minor'=>$converted,
            'retained_opening_principal_eur_minor'=>$retained,'contested_opening_principal_eur_minor'=>$contested,
            'claims'=>$claims,'sectors'=>$sectors,'consolidated_net_eur_valuation_delta_minor'=>0,
            'sovereign_subset'=>['opening_principal_eur_minor'=>$sovereign,'retained_eur_principal_minor'=>$sovereignEur,
                'converted_frf_principal_minor'=>$sovereignFrf,'frf_valuation_minor'=>$active?$sovereignLocal:null,
                'national_debt_coverage'=>null,'whole_debt_repriced'=>false],
            'unmodelled_contracts_and_maturities'=>true,'bank_capital_and_liquidity_modelled'=>false,
            'ecb_target_settlement_modelled'=>false,...($market===null?[]:['fx_market'=>$market]),'not_a_forecast'=>true];
    }
}
