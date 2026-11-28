<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/NationalBudgetPreview.php';

/** Optional fictional executive scenario command, NOT national law.
 * Caller must already hold the world fence and its current country slot. */
final class NationalBudgetCommands
{
    public const VERSION='wp130-budget-scenario-command-v1';
    public static function validate(array $body,string $key):void{
        $keys=array_keys($body);sort($keys);
        if($keys!==['opening_sha','primary_spending_change_bps','receipts_change_bps']
            ||!is_string($body['opening_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$body['opening_sha'])
            ||!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key))
            throw new \InvalidArgumentException('Invalid scenario command');
        NationalBudgetPreview::validate(array_diff_key($body,['opening_sha'=>true]));
    }
    private static function q(\PDO $db,string $sql,array $p):\PDOStatement{
        if(!$db->inTransaction())throw new \LogicException('Budget scenario transaction required');
        $q=$db->prepare($sql);$q->execute($p);return $q;
    }
    private static function publicRow(array $r):array{
        return ['version'=>self::VERSION,'country'=>$r['country_code'],'generation'=>(int)$r['generation'],
            'submitted_day'=>$r['submitted_day'],'effective_day'=>$r['effective_day'],'expires_day_exclusive'=>$r['expires_day'],
            'opening_sha'=>$r['opening_sha'],'receipts_change_bps'=>(int)$r['receipts_bps'],
            'primary_spending_change_bps'=>(int)$r['primary_bps'],'legal_effect'=>false,'rankable'=>false,
            'classification'=>'fictional_executive_scenario_not_enacted_law'];
    }
    public static function submit(\PDO $db,string $world,string $country,int $generation,string $day,string $sha,array $body,string $key):array{
        self::validate($body,$key);
        $old=self::q($db,'SELECT * FROM wp_national_budget_scenarios WHERE world_id=? AND country_code=? AND generation=? AND request_key=? FOR UPDATE',[$world,$country,$generation,$key])->fetch(\PDO::FETCH_ASSOC);
        if($old!==false){
            if($old['opening_sha']!==$body['opening_sha']||(int)$old['receipts_bps']!==$body['receipts_change_bps']||(int)$old['primary_bps']!==$body['primary_spending_change_bps'])
                throw new \DomainException('Budget scenario idempotency conflict');
            return self::publicRow($old);
        }
        if(!hash_equals($sha,$body['opening_sha']))throw new \DomainException('Stale budget scenario checkpoint');
        $date=new \DateTimeImmutable($day,new \DateTimeZone('UTC'));$effective=$date->modify('+1 day');$expires=$effective->modify('+365 days');
        $start=$effective->format('Y-m-d');$end=$expires->format('Y-m-d');
        if(self::q($db,'SELECT 1 FROM wp_national_budget_scenarios WHERE world_id=? AND country_code=? AND effective_day<? AND expires_day>? LIMIT 1 FOR UPDATE',[$world,$country,$end,$start])->fetchColumn()!==false)
            throw new \DomainException('Budget scenario already scheduled');
        $row=['country_code'=>$country,'generation'=>$generation,'submitted_day'=>$day,'effective_day'=>$start,'expires_day'=>$end,
            'receipts_bps'=>$body['receipts_change_bps'],'primary_bps'=>$body['primary_spending_change_bps'],'opening_sha'=>$sha];
        self::q($db,'INSERT INTO wp_national_budget_scenarios(world_id,country_code,generation,request_key,submitted_day,effective_day,expires_day,receipts_bps,primary_bps,opening_sha) VALUES(?,?,?,?,?,?,?,?,?,?)',
            [$world,$country,$generation,$key,$day,$start,$end,$row['receipts_bps'],$row['primary_bps'],$sha]);
        return self::publicRow($row);
    }
    public static function active(\PDO $db,string $world,string $day):array{
        $out=[];
        $rows=self::q($db,'SELECT * FROM wp_national_budget_scenarios WHERE world_id=? AND effective_day<=? AND expires_day>? ORDER BY country_code FOR UPDATE',[$world,$day,$day])->fetchAll(\PDO::FETCH_ASSOC);
        foreach($rows as $r){
            $country=$r['country_code'];$p=self::publicRow($r);
            if(isset($out[$country])||$country==='FR'||!in_array($country,UniverseLobby::DIPLOMATIC_COUNTRIES,true))
                throw new \DomainException('Invalid budget scenario scope');
            NationalBudgetPreview::validate(['receipts_change_bps'=>$p['receipts_change_bps'],'primary_spending_change_bps'=>$p['primary_spending_change_bps']]);
            $out[$country]=$p;
        }
        return $out;
    }
    public static function pending(\PDO $db,string $world,string $country,string $day):?array{
        $r=self::q($db,'SELECT * FROM wp_national_budget_scenarios WHERE world_id=? AND country_code=? AND effective_day>? ORDER BY effective_day LIMIT 1',[$world,$country,$day])->fetch(\PDO::FETCH_ASSOC);
        return $r===false?null:self::publicRow($r);
    }
    public static function apply(array $annual,array $policy):array{
        NationalBudgetPreview::validate(['receipts_change_bps'=>$policy['receipts_change_bps'],'primary_spending_change_bps'=>$policy['primary_spending_change_bps']]);
        $annual['other_received']=intdiv($annual['other_received']*(10000+$policy['receipts_change_bps']),10000);
        $annual['primary_due']=intdiv($annual['primary_due']*(10000+$policy['primary_spending_change_bps']),10000);
        return $annual;
    }
}
