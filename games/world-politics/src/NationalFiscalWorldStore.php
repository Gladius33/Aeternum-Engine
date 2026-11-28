<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/NationalFiscalLedger.php';
require_once __DIR__.'/NationalFiscalCalendar.php';
require_once __DIR__.'/NationalInvestitureForecast.php';
require_once __DIR__.'/NationalHouseholdMacro.php';

/** Private-alpha normalized fiscal pilot. One atomic row per managed world;
 * never confuses general-government ratios with central-government cash or
 * national currencies. Old worlds without an edition are not backfilled. */
final class NationalFiscalWorldStore
{
    public const LEGACY='wp130-normalized-fiscal-world-v1';
    public const FORECAST='wp130-normalized-fiscal-world-v2';
    public const VERSION='wp130-normalized-fiscal-world-v3';
    private const BLOB='0b5c74c7e43ea7ffea66cf4b9ed0afb76c0be8e6';
    private const BASE=1000000000;
    private static function json(array $v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    private static function hash(array $v):string{return hash('sha256',self::json($v));}
    /** Divide before multiplying to avoid overflow on long-run normalized indexes. */
    private static function scaled(int $a,int $b):int{
        if($a<0||$b<0||$a>1000000000000||$b>1000000000000)throw new \DomainException('Fiscal index range');
        return intdiv($a,self::BASE)*$b+intdiv($b,self::BASE)*($a%self::BASE)+intdiv(($a%self::BASE)*($b%self::BASE),self::BASE);
    }
    private static function query(\PDO $db,string $sql,array $p=[]):\PDOStatement{$q=$db->prepare($sql);$q->execute($p);return $q;}
    private static function transaction(\PDO $db):void{if(!$db->inTransaction())throw new \LogicException('Fiscal world transaction required');}
    public static function opening(string $day,?GlobalReferenceBundle $bundle=null):array{
        if($day!=='2027-05-14')throw new \DomainException('Fiscal pilot requires new opening world');
        $raw=file_get_contents(__DIR__.'/../data/wp130-general-government-fiscal-pilot-v1.json');
        if(!is_string($raw)||!hash_equals(self::BLOB,sha1('blob '.strlen($raw)."\0".$raw)))throw new \DomainException('Fiscal reference changed');
        $pack=json_decode($raw,true,32,JSON_THROW_ON_ERROR);$codes=array_keys($pack['countries']);sort($codes);
        if($codes!==array_values(array_diff(UniverseLobby::DIPLOMATIC_COUNTRIES,['FR'])))throw new \DomainException('Fiscal country roster mismatch');
        $config=['version'=>self::VERSION,'unit'=>'normalized_reference_GDP_units','reference_gdp_units'=>self::BASE,
            'source'=>array_diff_key($pack,['countries'=>true]),'reference_blob'=>self::BLOB,
            'assumptions'=>['opening_cash_bps_gdp'=>100,'credit_facility_bps_gdp'=>1000,'debt_ceiling_headroom_bps_gdp'=>1000,
                'annual_interest_bps_opening_debt'=>200,'revenue_split'=>'all_unclassified_receipts_tax_component_unknown',
                'principal_schedule'=>'unknown_not_modeled','annual_envelopes'=>'projected_2027_ratio_scenario_dynamic_receipts_prices_and_interest',
                'allocation'=>'uniform_calendar_days','payment_priority'=>'interest_then_principal_then_primary_scenario'],
            'countries'=>[]];
        $states=[];$macro=[];$households=[];
        foreach($pack['countries'] as $cc=>$r){
            $opening=NationalInvestitureForecast::opening($cc);$forecast=$opening['fiscal_forecasts'];
            $debt=$opening['opening_gross_debt'];$revenue=intdiv(self::BASE*$forecast['revenue_bps'][1],10000);
            $expense=intdiv(self::BASE*$forecast['expense_bps'][1],10000);$interest=min($expense,intdiv($debt*200,10000));
            $config['countries'][$cc]=['reference'=>$r,'opening_forecast'=>$opening,'gdp_reference'=>$bundle?->references->latest('state_'.strtolower($cc),'gdp_current_usd'),'household_model'=>NationalHouseholdMacro::ASSUMPTIONS,'annual'=>['tax_received'=>0,'other_received'=>$revenue,'primary_due'=>$expense-$interest,'interest_due'=>$interest],
                'principal_schedule'=>[],'ceiling'=>$debt+intdiv(self::BASE,10)];
            $states[$cc]=['day'=>$day,'stocks'=>['cash'=>intdiv(self::BASE,100),'gross_debt'=>$debt],
                'arrears'=>['interest'=>0,'principal'=>0,'primary'=>0],'credit_remaining'=>intdiv(self::BASE,10)];
            $macro[$cc]=['real_output_index'=>self::BASE,'price_index'=>self::BASE,'indicative_real_wage_index'=>self::BASE,
                'unemployment_scaled'=>$opening['unemployment_opening_bps']===null?null:$opening['unemployment_opening_bps']*10000,'trade_gap_bps'=>0];
            $households[$cc]=NationalHouseholdMacro::household($macro[$cc],$opening,0,0);
        }
        return ['config'=>$config,'state'=>['day'=>$day,'countries'=>$states,'last_flows'=>[],'macro'=>$macro,'households'=>$households]];
    }
    public static function provision(\PDO $db,string $world,string $day,GlobalReferenceBundle $bundle):void{
        self::transaction($db);$p=self::opening($day,$bundle);
        self::query($db,'INSERT INTO wp_national_fiscal_worlds(world_id,edition,committed_day,config_json,config_sha,state_json,state_sha) VALUES(?,?,?,?,?,?,?)',
            [$world,self::VERSION,$day,self::json($p['config']),self::hash($p['config']),self::json($p['state']),self::hash($p['state'])]);
    }
    private static function load(\PDO $db,string $world,string $day):?array{
        self::transaction($db);
        $r=self::query($db,'SELECT * FROM wp_national_fiscal_worlds WHERE world_id=? FOR UPDATE',[$world])->fetch(\PDO::FETCH_ASSOC);
        if($r===false)return null;
        $c=json_decode($r['config_json'],true,32,JSON_THROW_ON_ERROR);$s=json_decode($r['state_json'],true,32,JSON_THROW_ON_ERROR);
        if(!in_array($r['edition'],[self::LEGACY,self::FORECAST,self::VERSION],true)||$c['version']!==$r['edition']||$r['committed_day']!==$day||$s['day']!==$day
            ||!hash_equals($r['config_sha'],self::hash($c))||!hash_equals($r['state_sha'],self::hash($s))
            ||array_keys($c['countries'])!==array_keys($s['countries']))throw new \DomainException('Fiscal world checkpoint mismatch');
        return ['config'=>$c,'state'=>$s,'config_sha'=>$r['config_sha'],'state_sha'=>$r['state_sha']];
    }
    /** Called within final coordinator transaction. Publication or rollback
     * includes fiscal stocks; a failed day cannot consume credit twice. */
    public static function advance(\PDO $db,string $world,string $previous,string $target,?array $economic=null):void{
        $r=self::load($db,$world,$previous);if($r===null)return;
        $s=$r['state'];$s['day']=$target;
        foreach($r['config']['countries'] as $cc=>$c){
            $annual=$c['annual'];
            if($r['config']['version']!==self::LEGACY){
                $m=$s['macro'][$cc];$nominal=self::scaled($m['real_output_index'],min($m['price_index'],10000000000));
                // Bound monetary exposure while preserving a finite credit facility.
                $factor=max(100000000,min(10000000000,$nominal));
                $annual['other_received']=self::scaled($annual['other_received'],$factor);
                $annual['primary_due']=self::scaled($annual['primary_due'],min($m['price_index'],10000000000));
                $annual['interest_due']=intdiv($s['countries'][$cc]['stocks']['gross_debt']*200,10000);
            }
            $t=NationalFiscalCalendar::tick($s['countries'][$cc],$target,$annual,$c['principal_schedule'],$c['ceiling']);
            $shortfall=$t['newly_due']['primary_due']-min($t['newly_due']['primary_due'],$t['settlement']['cash_subledger']['executed_flows']['primary_paid']);
            if($r['config']['version']===self::FORECAST)$s['macro'][$cc]=NationalInvestitureForecast::step($s['macro'][$cc],$c['opening_forecast'],$t['newly_due']['primary_due']-$t['settlement']['cash_subledger']['executed_flows']['primary_paid']);
            if($r['config']['version']===self::VERSION){
                if($economic===null)throw new \DomainException('Missing committed trade input');
                $trade=NationalHouseholdMacro::trade($economic,$world,$cc,$target,$c['gdp_reference']);
                $s['macro'][$cc]=NationalHouseholdMacro::step($s['macro'][$cc],$c['opening_forecast'],$shortfall,$trade);
                $s['households'][$cc]=NationalHouseholdMacro::household($s['macro'][$cc],$c['opening_forecast'],$t['newly_due']['primary_due'],$t['settlement']['cash_subledger']['executed_flows']['primary_paid']);
            }
            $s['countries'][$cc]=$t['next'];
            $s['last_flows'][$cc]=['newly_due'=>$t['newly_due'],'executed_flows'=>$t['settlement']['cash_subledger']['executed_flows'],
                'overall_cash_balance'=>$t['settlement']['cash_subledger']['overall_cash_balance'],'unpaid'=>$t['settlement']['unpaid']];
        }
        $q=self::query($db,'UPDATE wp_national_fiscal_worlds SET committed_day=?,state_json=?,state_sha=? WHERE world_id=? AND committed_day=? AND state_sha=?',
            [$target,self::json($s),self::hash($s),$world,$previous,$r['state_sha']]);
        if($q->rowCount()!==1)throw new \DomainException('Fiscal publication conflict');
    }
    public static function dataset(\PDO $db,string $world,string $day):?array{
        require_once __DIR__.'/InternationalGameDataset.php';$r=self::load($db,$world,$day);
        return $r===null?null:InternationalGameDataset::project($r,$day);
    }
    /** Read-only ratios against current annualized nominal output proxy.
     * CPI is not a measured GDP deflator; no change to the pinned world state. */
    public static function ratios(array $state,?array $macro):?array{
        if($macro===null)return null;
        $nominal=self::scaled($macro['real_output_index'],$macro['price_index']);
        if($nominal===0)return null;
        // Four decimal digits of the fractional ratio without multiplying
        // a large numerator or denominator by 10,000.
        $bps=static function(int $value)use($nominal):int{
            if($value<0)throw new \DomainException('Negative fiscal ratio input');
            $whole=intdiv($value,$nominal);$rem=$value%$nominal;
            if($whole>intdiv(PHP_INT_MAX-9999,10000))throw new \DomainException('Fiscal ratio overflow');
            $ratio=$whole*10000;$fraction=0;
            for($i=0;$i<4;$i++){$rem*=10;$fraction=$fraction*10+intdiv($rem,$nominal);$rem%=$nominal;}
            return $ratio+$fraction;
        };
        return ['version'=>'wp130-current-fiscal-ratios-v1','classification'=>'synthetic_annualized_nominal_output_proxy_not_official_gdp',
            'nominal_output_reference_units'=>$nominal,'gross_debt_bps'=>$bps($state['stocks']['gross_debt']),
            'cash_bps'=>$bps($state['stocks']['cash']),'remaining_credit_bps'=>$bps($state['credit_remaining']),
            'primary_arrears_bps'=>$bps($state['arrears']['primary']),'interest_arrears_bps'=>$bps($state['arrears']['interest']),
            'principal_arrears_included_in_debt_bps'=>$bps($state['arrears']['principal']),'rankable'=>false];
    }
    public static function view(\PDO $db,string $world,string $day,string $country):?array{
        $r=self::load($db,$world,$day);if($r===null||!isset($r['state']['countries'][$country]))return null;
        return ['version'=>$r['config']['version'],'classification'=>'synthetic_normalized_general_government_fiscal_pilot',
            'opening_forecast'=>$r['config']['countries'][$country]['opening_forecast']??null,'macro'=>$r['state']['macro'][$country]??null,'household'=>$r['state']['households'][$country]??null,
            'country'=>$country,'day'=>$day,'unit'=>$r['config']['unit'],'reference_gdp_units'=>self::BASE,
            'state'=>$r['state']['countries'][$country],'last_flows'=>$r['state']['last_flows'][$country]??null,
            'current_fiscal_ratios'=>self::ratios($r['state']['countries'][$country],$r['state']['macro'][$country]??null),
            'reference'=>$r['config']['countries'][$country]['reference'],'source'=>$r['config']['source'],
            'assumptions'=>$r['config']['assumptions'],'config_sha256'=>$r['config_sha'],'state_sha256'=>$r['state_sha'],
            'rankable'=>false,'persisted'=>true,'real_currency'=>false,'full_national_budget'=>false];
    }
}
