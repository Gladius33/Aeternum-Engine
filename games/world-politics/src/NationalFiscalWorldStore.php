<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/NationalFiscalLedger.php';
require_once __DIR__.'/NationalFiscalCalendar.php';

/** Private-alpha normalized fiscal pilot. One atomic row per managed world;
 * never confuses general-government ratios with central-government cash or
 * national currencies. Old worlds without an edition are not backfilled. */
final class NationalFiscalWorldStore
{
    public const VERSION='wp130-normalized-fiscal-world-v1';
    private const BLOB='0b5c74c7e43ea7ffea66cf4b9ed0afb76c0be8e6';
    private const BASE=1000000000;
    private static function json(array $v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    private static function hash(array $v):string{return hash('sha256',self::json($v));}
    private static function query(\PDO $db,string $sql,array $p=[]):\PDOStatement{$q=$db->prepare($sql);$q->execute($p);return $q;}
    private static function transaction(\PDO $db):void{if(!$db->inTransaction())throw new \LogicException('Fiscal world transaction required');}
    public static function opening(string $day):array{
        if($day!=='2027-05-14')throw new \DomainException('Fiscal pilot requires new opening world');
        $raw=file_get_contents(__DIR__.'/../data/wp130-general-government-fiscal-pilot-v1.json');
        if(!is_string($raw)||!hash_equals(self::BLOB,sha1('blob '.strlen($raw)."\0".$raw)))throw new \DomainException('Fiscal reference changed');
        $pack=json_decode($raw,true,32,JSON_THROW_ON_ERROR);$codes=array_keys($pack['countries']);sort($codes);
        if($codes!==array_values(array_diff(UniverseLobby::DIPLOMATIC_COUNTRIES,['FR'])))throw new \DomainException('Fiscal country roster mismatch');
        $config=['version'=>self::VERSION,'unit'=>'normalized_reference_GDP_units','reference_gdp_units'=>self::BASE,
            'source'=>array_diff_key($pack,['countries'=>true]),'reference_blob'=>self::BLOB,
            'assumptions'=>['opening_cash_bps_gdp'=>100,'credit_facility_bps_gdp'=>1000,'debt_ceiling_headroom_bps_gdp'=>1000,
                'annual_interest_bps_opening_debt'=>200,'revenue_split'=>'all_unclassified_receipts_tax_component_unknown',
                'principal_schedule'=>'unknown_not_modeled','annual_envelopes'=>'constant_historical_ratio_scenario',
                'allocation'=>'uniform_calendar_days','payment_priority'=>'interest_then_principal_then_primary_scenario'],
            'countries'=>[]];
        $states=[];
        foreach($pack['countries'] as $cc=>$r){
            $debt=intdiv(self::BASE*$r['gross_debt_bps'],10000);$revenue=intdiv(self::BASE*$r['revenue_bps'],10000);
            $expense=intdiv(self::BASE*$r['expense_bps'],10000);$interest=min($expense,intdiv($debt*200,10000));
            $config['countries'][$cc]=['reference'=>$r,'annual'=>['tax_received'=>0,'other_received'=>$revenue,'primary_due'=>$expense-$interest,'interest_due'=>$interest],
                'principal_schedule'=>[],'ceiling'=>$debt+intdiv(self::BASE,10)];
            $states[$cc]=['day'=>$day,'stocks'=>['cash'=>intdiv(self::BASE,100),'gross_debt'=>$debt],
                'arrears'=>['interest'=>0,'principal'=>0,'primary'=>0],'credit_remaining'=>intdiv(self::BASE,10)];
        }
        return ['config'=>$config,'state'=>['day'=>$day,'countries'=>$states,'last_flows'=>[]]];
    }
    public static function provision(\PDO $db,string $world,string $day):void{
        self::transaction($db);$p=self::opening($day);
        self::query($db,'INSERT INTO wp_national_fiscal_worlds(world_id,edition,committed_day,config_json,config_sha,state_json,state_sha) VALUES(?,?,?,?,?,?,?)',
            [$world,self::VERSION,$day,self::json($p['config']),self::hash($p['config']),self::json($p['state']),self::hash($p['state'])]);
    }
    private static function load(\PDO $db,string $world,string $day):?array{
        self::transaction($db);
        $r=self::query($db,'SELECT * FROM wp_national_fiscal_worlds WHERE world_id=? FOR UPDATE',[$world])->fetch(\PDO::FETCH_ASSOC);
        if($r===false)return null;
        $c=json_decode($r['config_json'],true,32,JSON_THROW_ON_ERROR);$s=json_decode($r['state_json'],true,32,JSON_THROW_ON_ERROR);
        if($r['edition']!==self::VERSION||$c['version']!==self::VERSION||$r['committed_day']!==$day||$s['day']!==$day
            ||!hash_equals($r['config_sha'],self::hash($c))||!hash_equals($r['state_sha'],self::hash($s))
            ||array_keys($c['countries'])!==array_keys($s['countries']))throw new \DomainException('Fiscal world checkpoint mismatch');
        return ['config'=>$c,'state'=>$s,'config_sha'=>$r['config_sha'],'state_sha'=>$r['state_sha']];
    }
    /** Called within final coordinator transaction. Publication or rollback
     * includes fiscal stocks; a failed day cannot consume credit twice. */
    public static function advance(\PDO $db,string $world,string $previous,string $target):void{
        $r=self::load($db,$world,$previous);if($r===null)return;
        $s=$r['state'];$s['day']=$target;
        foreach($r['config']['countries'] as $cc=>$c){
            $t=NationalFiscalCalendar::tick($s['countries'][$cc],$target,$c['annual'],$c['principal_schedule'],$c['ceiling']);
            $s['countries'][$cc]=$t['next'];
            $s['last_flows'][$cc]=['newly_due'=>$t['newly_due'],'executed_flows'=>$t['settlement']['cash_subledger']['executed_flows'],
                'overall_cash_balance'=>$t['settlement']['cash_subledger']['overall_cash_balance'],'unpaid'=>$t['settlement']['unpaid']];
        }
        $q=self::query($db,'UPDATE wp_national_fiscal_worlds SET committed_day=?,state_json=?,state_sha=? WHERE world_id=? AND committed_day=? AND state_sha=?',
            [$target,self::json($s),self::hash($s),$world,$previous,$r['state_sha']]);
        if($q->rowCount()!==1)throw new \DomainException('Fiscal publication conflict');
    }
    public static function view(\PDO $db,string $world,string $day,string $country):?array{
        $r=self::load($db,$world,$day);if($r===null||!isset($r['state']['countries'][$country]))return null;
        return ['version'=>self::VERSION,'classification'=>'synthetic_normalized_general_government_fiscal_pilot',
            'country'=>$country,'day'=>$day,'unit'=>$r['config']['unit'],'reference_gdp_units'=>self::BASE,
            'state'=>$r['state']['countries'][$country],'last_flows'=>$r['state']['last_flows'][$country]??null,
            'reference'=>$r['config']['countries'][$country]['reference'],'source'=>$r['config']['source'],
            'assumptions'=>$r['config']['assumptions'],'config_sha256'=>$r['config_sha'],'state_sha256'=>$r['state_sha'],
            'rankable'=>false,'persisted'=>true,'real_currency'=>false,'full_national_budget'=>false];
    }
}
