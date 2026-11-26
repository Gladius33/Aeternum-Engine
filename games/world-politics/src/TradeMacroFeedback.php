<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Bounded scenario transmission, not a currency conversion or a tariff model. */
final class TradeMacroFeedback
{
    public const VERSION='wp54r-trade-feedback-v1';
    public static function observe(array $read,string $world,string $day):array
    {
        $s=$read['state'];$hash=trim($read['etag'],'"');
        if($s['world_id']!==$world||$s['day']!==$day||!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new \DomainException('Trade observation scope mismatch');
        $pairs=[];$missing=[];
        foreach($s['projections'] as $id=>$p){
            $q=$s['policies'][$id]??throw new \DomainException('Missing trade policy');
            if($q['from']!=='state_fr'&&$q['to']!=='state_fr')continue;
            if($q['status']!=='effective'||$q['effective_on']!==$p['effective_on']||$p['effective_on']>$day)throw new \DomainException('Uncommitted trade effect');
            $rate=$q['effect_bps'];
            if(!is_int($rate)||$rate<0||$rate>10000||$rate!==$p['model_assumption_bps'])throw new \DomainException('Invalid trade effect');
            if($p['central_usd']===null){$missing[]=$id;continue;}
            // Multiple restrictions on one counterparty are overlapping, not
            // independent shocks. Bilateral total does not identify direction.
            $peer=$q['from']==='state_fr'?$q['to']:$q['from'];
            $pairs[$peer]=max($pairs[$peer]??0,$rate);
        }
        ksort($pairs,SORT_STRING);sort($missing,SORT_STRING);
        $e=['version'=>self::VERSION,'world_id'=>$world,'day'=>$day,'economic_sha'=>$hash,
            'exposure_bps'=>min(2000,intdiv(array_sum($pairs),10)),
            'covered_pairs'=>count($pairs),'missing_policies'=>count($missing)];
        self::validate($e,$world,$day);return $e;
    }
    public static function validate(array $e,string $world,string $day):void
    {
        if(array_keys($e)!==['version','world_id','day','economic_sha','exposure_bps','covered_pairs','missing_policies']
            ||$e['version']!==self::VERSION||$e['world_id']!==$world||$e['day']!==$day
            ||!is_string($e['economic_sha'])||!preg_match('/^[a-f0-9]{64}$/D',$e['economic_sha'])
            ||!is_int($e['exposure_bps'])||$e['exposure_bps']<0||$e['exposure_bps']>2000
            ||!is_int($e['covered_pairs'])||$e['covered_pairs']<0||$e['covered_pairs']>195
            ||!is_int($e['missing_policies'])||$e['missing_policies']<0
            ||($e['covered_pairs']===0&&$e['exposure_bps']!==0))throw new \DomainException('Invalid trade observation');
    }
}
