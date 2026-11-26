<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CurrencyTransitionLedger.php';
require_once __DIR__.'/FrancProductionRule.php';
require_once __DIR__.'/FrancPublicFunding.php';
require_once __DIR__.'/FrancCommercialBank.php';
require_once __DIR__.'/FrancExchangeReserves.php';
require_once __DIR__.'/FrancExchangeMarket.php';
require_once __DIR__.'/FrancFiscalLedger.php';

/** Article 50 procedural pilot: fictional certified evidence, not actual legal advice.
 * National constitutional authority is deliberately not inferred from ownership. */
final class EuropeanWithdrawal
{
    public const VERSION='wp54r-article50-v1';
    public const SPILLOVERS_VERSION='wp54r-eu-spillovers-v1';
    public const MONETARY_STRESS_VERSION='wp54r-euro-stress-v1';
    public const PARTNERS=['AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI','GR','HR','HU','IE','IT','LT','LU','LV','MT','NL','PL','PT','RO','SE','SI','SK'];
    public const SOURCE='https://eur-lex.europa.eu/eli/treaty/teu_2016/art_50/oj';
    public static function initial(string $day,bool $tradeEnabled=false,bool $spilloversEnabled=false,bool $monetaryStressEnabled=false,bool $currencyContractsEnabled=false,bool $francRuleEnabled=false,bool $francFundingEnabled=false,bool $francBankEnabled=false,bool $francBankCrisisEnabled=false,bool $francFxEnabled=false,bool $francFxMarketEnabled=false,bool $francFxFlowEnabled=false,bool $francFiscalEnabled=false):array
    {
        self::date($day);
        if($francFiscalEnabled&&!$currencyContractsEnabled)throw new \DomainException('Fiscal conversion requires currency terms');
        if($francFxFlowEnabled&&!$francFxMarketEnabled)throw new \DomainException('FX flow requires market');
        if($francFxMarketEnabled&&!$francFxEnabled)throw new \DomainException('FX market requires reserves');
        if($francFxEnabled&&!$francBankCrisisEnabled)throw new \DomainException('FX reserves require bank crisis allocation');
        if($francBankCrisisEnabled&&!$francBankEnabled)throw new \DomainException('Bank crisis requires commercial banking');
        if($francBankEnabled&&!$francFundingEnabled)throw new \DomainException('Franc bank requires public funding ledger');
        if($francFundingEnabled&&!$francRuleEnabled)throw new \DomainException('Franc funding requires production rule');
        if($francRuleEnabled&&!$currencyContractsEnabled)throw new \DomainException('Franc rule requires currency book');
        if($currencyContractsEnabled&&!$monetaryStressEnabled)throw new \DomainException('Currency book requires monetary scenario');
        if($monetaryStressEnabled&&!$spilloversEnabled)throw new \DomainException('Monetary stress requires spillover model');
        if($spilloversEnabled&&!$tradeEnabled)throw new \DomainException('Spillovers require trade model');
        return ['version'=>self::VERSION,'source'=>self::SOURCE,'start_day'=>$day,'day'=>$day,
            'status'=>'member','membership'=>true,'intent_on'=>null,'notified_on'=>null,'deadline'=>null,
            'agreement_effective_on'=>null,'exit_on'=>null,'exit_route'=>null,'history'=>[],
            'currency_changed'=>false,'tariff_regime_implemented'=>false,...($tradeEnabled?['trade_regime_version'=>'wp54r-eu-trade-v1','trade_regime'=>null]:[]),...($spilloversEnabled?['spillovers_version'=>self::SPILLOVERS_VERSION,'budget_terms'=>null,'partner_allocation'=>null]:[]),...($monetaryStressEnabled?['monetary_stress_version'=>self::MONETARY_STRESS_VERSION,'monetary_stress'=>null]:[]),...($currencyContractsEnabled?['currency_contract_version'=>CurrencyTransitionLedger::VERSION,'currency_terms'=>null,'currency_marks'=>[]]:[]),...($francRuleEnabled?['franc_rule_version'=>FrancProductionRule::VERSION,'franc_rule'=>null]:[]),...($francFundingEnabled?['franc_funding_version'=>FrancPublicFunding::VERSION,'franc_funding_orders'=>[]]:[]),...($francBankEnabled?['franc_bank_version'=>FrancCommercialBank::VERSION,'franc_bank_terms'=>null,'franc_bank_orders'=>[]]:[]),...($francBankCrisisEnabled?['franc_bank_crisis_version'=>FrancCommercialBank::CRISIS_VERSION,'franc_bank_crisis_terms'=>null]:[]),...($francFxEnabled?['franc_fx_version'=>FrancExchangeReserves::VERSION,'franc_fx_terms'=>null,'franc_fx_orders'=>[]]:[]),...($francFxMarketEnabled?['franc_fx_market_version'=>FrancExchangeMarket::VERSION,'franc_fx_market_terms'=>null]:[]),...($francFxFlowEnabled?['franc_fx_flow_version'=>FrancExchangeMarket::FLOW_VERSION,'franc_fx_flow_terms'=>null,'franc_fx_capital_orders'=>[]]:[]),...($francFiscalEnabled?['franc_fiscal_version'=>FrancFiscalLedger::VERSION,'franc_fiscal_terms'=>null]:[])];
    }
    public static function request(array $s,string $day):array
    {
        if($s['day']!==$day||count($s['history'])>=1000||!in_array($s['status'],['member','revoked'],true))throw new \DomainException('Withdrawal intent unavailable');
        $s['status']='intent_pending';$s['intent_on']=$day;$s['notified_on']=null;$s['deadline']=null;$s['agreement_effective_on']=null;
        if(isset($s['trade_regime_version']))$s['trade_regime']=null;
        if(isset($s['spillovers_version'])){$s['budget_terms']=null;$s['partner_allocation']=null;}
        if(isset($s['currency_contract_version'])){$s['currency_terms']=null;$s['currency_marks']=[];}
        if(isset($s['franc_rule_version']))$s['franc_rule']=null;
        if(isset($s['franc_funding_version']))$s['franc_funding_orders']=[];
        if(isset($s['franc_bank_version'])){$s['franc_bank_terms']=null;$s['franc_bank_orders']=[];}
        if(isset($s['franc_bank_crisis_version']))$s['franc_bank_crisis_terms']=null;
        if(isset($s['franc_fx_version'])){$s['franc_fx_terms']=null;$s['franc_fx_orders']=[];}
        if(isset($s['franc_fx_market_version']))$s['franc_fx_market_terms']=null;
        if(isset($s['franc_fx_flow_version'])){$s['franc_fx_flow_terms']=null;$s['franc_fx_capital_orders']=[];}
        if(isset($s['franc_fiscal_version']))$s['franc_fiscal_terms']=null;
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
            case 'trade_regime':
                self::fields($data,['regime','effective_on','import_duty_bps','import_friction_bps','export_friction_bps','instrument_sha']);
                self::date($data['effective_on']);self::sha($data['instrument_sha']);
                if(!isset($s['trade_regime_version'])||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true)||$s['trade_regime']!==null||$data['effective_on']<=$day||!in_array($data['regime'],['transition','free_trade','customs_scenario'],true))throw new \DomainException('No valid prospective trade scenario');
                foreach(['import_duty_bps','import_friction_bps','export_friction_bps'] as $k)if(!is_int($data[$k])||$data[$k]<0||$data[$k]>3000)throw new \DomainException('Invalid scenario rate');
                if($data['regime']!=='customs_scenario'&&$data['import_duty_bps']!==0)throw new \DomainException('No duties in this scenario');
                $s['trade_regime']=$data;break;
            case 'budget_terms':
                self::fields($data,['effective_on','gross_retained_bps','returns_retained_bps','settlement_daily_minor','settlement_days','instrument_sha']);
                self::date($data['effective_on']);self::sha($data['instrument_sha']);
                self::prospectiveSpillover($s,$day,$data['effective_on'],'budget_terms');
                foreach(['gross_retained_bps','returns_retained_bps'] as $k)
                    if(!is_int($data[$k])||$data[$k]<0||$data[$k]>10000)throw new \DomainException('Invalid budget retention');
                if(!is_int($data['settlement_daily_minor'])||$data['settlement_daily_minor']<0||$data['settlement_daily_minor']>100000000000
                    ||!is_int($data['settlement_days'])||$data['settlement_days']<0||$data['settlement_days']>3650
                    ||(($data['settlement_daily_minor']===0)!==($data['settlement_days']===0)))throw new \DomainException('Invalid illustrative settlement');
                $s['budget_terms']=$data;break;
            case 'partner_allocation':
                self::fields($data,['effective_on','export_loss_weights','import_loss_weights','instrument_sha']);
                self::date($data['effective_on']);self::sha($data['instrument_sha']);
                self::prospectiveSpillover($s,$day,$data['effective_on'],'partner_allocation');
                foreach(['export_loss_weights','import_loss_weights'] as $k){
                    if(!is_array($data[$k]))throw new \DomainException('Missing scenario weights');
                    self::fields($data[$k],self::PARTNERS);
                    foreach($data[$k] as $v)if(!is_int($v)||$v<0||$v>10000)throw new \DomainException('Invalid country weight');
                    if(array_sum($data[$k])!==10000)throw new \DomainException('Country weights must sum to 10000');
                }
                $s['partner_allocation']=$data;break;
            case 'monetary_stress':
                self::fields($data,['effective_on','refinancing_days','euro_fx_move_bps','country_refinancing','instrument_sha']);
                self::date($data['effective_on']);self::sha($data['instrument_sha']);
                if(!isset($s['monetary_stress_version'])||$s['monetary_stress']!==null||$data['effective_on']<=$day
                    ||!is_int($data['refinancing_days'])||$data['refinancing_days']<1||$data['refinancing_days']>365
                    ||!is_int($data['euro_fx_move_bps'])||$data['euro_fx_move_bps']< -2000||$data['euro_fx_move_bps']>2000
                    ||!is_array($data['country_refinancing'])||count($data['country_refinancing'])>27)
                    throw new \DomainException('Invalid prospective monetary sensitivity');
                foreach($data['country_refinancing'] as $iso=>$v){
                    if(!in_array($iso,[...self::PARTNERS,'FR'],true)||!is_array($v))throw new \DomainException('Unknown refinancing country');
                    self::fields($v,['tranche_minor','spread_change_bps']);
                    if(!is_int($v['tranche_minor'])||$v['tranche_minor']<0||$v['tranche_minor']>100000000000000
                        ||!is_int($v['spread_change_bps'])||$v['spread_change_bps']< -1000||$v['spread_change_bps']>2000)
                        throw new \DomainException('Invalid refinancing scenario');
                }
                // Independent financial sensitivity, not a legal consequence
                // of Article 50. Revoking withdrawal does not erase this case.
                $s['monetary_stress']=$data;break;
            case 'currency_terms':
                if(!isset($s['currency_contract_version'])||$s['currency_terms']!==null||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No authorized currency scenario');
                CurrencyTransitionLedger::validateTerms($data,$day);
                $s['currency_terms']=$data;break;
            case 'currency_mark':
                if(($s['franc_fx_market_terms']??null)!==null)throw new \DomainException('Market scenario cannot accept manual FX marks');
                if(!isset($s['currency_contract_version'])||$s['currency_terms']===null||$s['membership']
                    ||$day<max($s['exit_on'],$s['currency_terms']['effective_on'])||count($s['currency_marks'])>=64)
                    throw new \DomainException('Currency conversion not effective');
                $last=$s['currency_marks']===[]?null:$s['currency_marks'][array_key_last($s['currency_marks'])]['effective_on'];
                CurrencyTransitionLedger::validateMark($data,$day,$last);
                $s['currency_marks'][]=$data;break;
            case 'franc_rule':
                if(!isset($s['franc_rule_version'])||$s['franc_rule']!==null||$s['currency_terms']===null
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No prospective franc currency rule');
                FrancProductionRule::validateTerms($data,$day);
                $s['franc_rule']=$data;break;
            case 'franc_funding':
                if(!isset($s['franc_funding_version'])||$s['franc_rule']===null
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No authorized Franc funding model');
                FrancPublicFunding::validate($data,$day,$s);
                $s['franc_funding_orders'][$data['effective_on']]=$data;ksort($s['franc_funding_orders'],SORT_STRING);break;
            case 'franc_bank_terms':
                if(!isset($s['franc_bank_version'])||$s['franc_bank_terms']!==null||$s['franc_rule']===null
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No prospective Franc bank model');
                FrancCommercialBank::validateTerms($data,$day,$s);$s['franc_bank_terms']=$data;break;
            case 'franc_bank_crisis_terms':
                if(!isset($s['franc_bank_crisis_version'])||$s['franc_bank_crisis_terms']!==null||$s['franc_bank_terms']===null
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No prospective bank crisis model');
                FrancCommercialBank::validateCrisisTerms($data,$day,$s);$s['franc_bank_crisis_terms']=$data;break;
            case 'franc_bank_order':
                if(!isset($s['franc_bank_version'])||$s['franc_bank_terms']===null
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No authorized Franc bank');
                FrancCommercialBank::validateOrder($data,$day,$s);
                $s['franc_bank_orders'][$data['effective_on']]=$data;ksort($s['franc_bank_orders'],SORT_STRING);break;
            case 'franc_fx_terms':
                if(!isset($s['franc_fx_version'])||$s['franc_fx_terms']!==null||$s['franc_bank_crisis_terms']===null
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No prospective FX reserve model');
                FrancExchangeReserves::validateTerms($data,$day,$s);$s['franc_fx_terms']=$data;break;
            case 'franc_fx_market':
                if(!isset($s['franc_fx_market_version'])||$s['franc_fx_market_terms']!==null||$s['franc_fx_terms']===null||$s['currency_marks']!==[]
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No prospective independent FX market');
                FrancExchangeMarket::validate($data,$day,$s);$s['franc_fx_market_terms']=$data;break;
            case 'franc_fx_flow':
                if(!isset($s['franc_fx_flow_version'])||$s['franc_fx_flow_terms']!==null||$s['franc_fx_market_terms']===null
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No prospective trade FX flow mode');
                FrancExchangeMarket::validateFlow($data,$day,$s);$s['franc_fx_flow_terms']=$data;break;
            case 'franc_fx_capital':
                if(!isset($s['franc_fx_flow_version'])||$s['franc_fx_flow_terms']===null
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No private capital flow scenario');
                FrancExchangeMarket::validateCapital($data,$day,$s);
                $s['franc_fx_capital_orders'][$data['effective_on']]=$data;ksort($s['franc_fx_capital_orders'],SORT_STRING);break;
            case 'franc_fx_order':
                if(!isset($s['franc_fx_version'])||$s['franc_fx_terms']===null
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No authorized FX reserve model');
                FrancExchangeReserves::validateOrder($data,$day,$s);
                $s['franc_fx_orders'][$data['effective_on']]=$data;ksort($s['franc_fx_orders'],SORT_STRING);break;
            case 'franc_fiscal':
                if(!isset($s['franc_fiscal_version'])||$s['franc_fiscal_terms']!==null||$s['currency_terms']===null
                    ||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true))
                    throw new \DomainException('No prospective fiscal conversion');
                FrancFiscalLedger::validateTerms($data,$day,$s);$s['franc_fiscal_terms']=$data;break;
            case 'revocation':
                self::fields($data,['national_decision_sha','notice_sha','unequivocal_unconditional']);
                self::sha($data['national_decision_sha']);self::sha($data['notice_sha']);
                if(!in_array($s['status'],['negotiating','agreement_concluded'],true)||$day>=$s['deadline']
                    ||$data['unequivocal_unconditional']!==true)throw new \DomainException('Revocation not certified');
                $s['status']='revoked';$s['deadline']=null;$s['agreement_effective_on']=null;if(isset($s['trade_regime_version']))$s['trade_regime']=null;if(isset($s['spillovers_version'])){$s['budget_terms']=null;$s['partner_allocation']=null;}if(isset($s['currency_contract_version'])){$s['currency_terms']=null;$s['currency_marks']=[];}if(isset($s['franc_rule_version']))$s['franc_rule']=null;if(isset($s['franc_funding_version']))$s['franc_funding_orders']=[];if(isset($s['franc_bank_version'])){$s['franc_bank_terms']=null;$s['franc_bank_orders']=[];}if(isset($s['franc_bank_crisis_version']))$s['franc_bank_crisis_terms']=null;if(isset($s['franc_fx_version'])){$s['franc_fx_terms']=null;$s['franc_fx_orders']=[];}if(isset($s['franc_fx_market_version']))$s['franc_fx_market_terms']=null;if(isset($s['franc_fx_flow_version'])){$s['franc_fx_flow_terms']=null;$s['franc_fx_capital_orders']=[];}if(isset($s['franc_fiscal_version']))$s['franc_fiscal_terms']=null;break;
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
        if(isset($s['trade_regime_version']))$s['tariff_regime_implemented']=!$s['membership']&&$s['trade_regime']!==null&&$s['trade_regime']['effective_on']<=$day;
        $s['day']=$day;return $s;
    }
    public static function restore(array $s):array
    {
        $r=self::initial($s['start_day'],array_key_exists('trade_regime_version',$s),array_key_exists('spillovers_version',$s),array_key_exists('monetary_stress_version',$s),array_key_exists('currency_contract_version',$s),array_key_exists('franc_rule_version',$s),array_key_exists('franc_funding_version',$s),array_key_exists('franc_bank_version',$s),array_key_exists('franc_bank_crisis_version',$s),array_key_exists('franc_fx_version',$s),array_key_exists('franc_fx_market_version',$s),array_key_exists('franc_fx_flow_version',$s),array_key_exists('franc_fiscal_version',$s));
        if(!is_array($s['history'])||!array_is_list($s['history'])||count($s['history'])>1000)throw new \DomainException('Invalid EU history');
        foreach($s['history'] as $h){
            self::fields($h,['day','kind','data']);$r=self::advance($r,$h['day']);
            $r=$h['kind']==='intent'?self::request($r,$h['day']):self::certify($r,$h['day'],$h['kind'],$h['data']);
        }
        $r=self::advance($r,$s['day']);if($r!==$s)throw new \DomainException('EU replay mismatch');return $r;
    }
    private static function prospectiveSpillover(array $s,string $day,string $effective,string $key):void
    {
        if(!isset($s['spillovers_version'])||!in_array($s['status'],['negotiating','agreement_concluded','withdrawn'],true)
            ||$s[$key]!==null||$effective<=$day)throw new \DomainException('No valid prospective spillover scenario');
    }
    private static function fields(array $a,array $b):void{$k=array_keys($a);sort($k);sort($b);if($k!==$b)throw new \InvalidArgumentException('Unexpected EU evidence');}
    private static function date(mixed $v):void{if(!is_string($v)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v))===false||$d->format('Y-m-d')!==$v)throw new \InvalidArgumentException('Invalid EU date');}
    private static function sha(mixed $v):void{if(!is_string($v)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new \InvalidArgumentException('Invalid EU evidence hash');}
}
