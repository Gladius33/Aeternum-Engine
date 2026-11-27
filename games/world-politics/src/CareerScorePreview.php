<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CareerScoreContract.php';
require_once __DIR__.'/HouseholdAccount.php';

/** WP120 private experimental preview. Only call with a replayed career
 * ledger and a domestic snapshot restored by the trusted persistence layer.
 * The household model is not calibrated. No official ranking or write API. */
final class CareerScorePreview
{
    public const VERSION='wp120-private-score-preview-v1';
    public static function preview(array $ledger,?string $career,string $day,array $domestic):array
    {
        $progress=CareerScoreContract::ledgerProgress($ledger,$career,$day);
        $d=$domestic['state'];
        if($d['world_id']!==$ledger['world']||$d['day']!==$day)
            throw new \DomainException('Score preview source scope');
        $sha=trim($domestic['etag'],'"');
        if(!preg_match('/^[a-f0-9]{64}$/D',$sha))throw new \DomainException('Missing domestic source digest');
        $result=['version'=>self::VERSION,'day'=>$day,'model'=>HouseholdAccount::VERSION,
            'classification'=>'experimental_uncalibrated_household_proxy','source_sha'=>$sha,
            'rankable'=>false,'official_eligible'=>false,'series'=>[]];
        if(!isset($d['population_model'],$d['macro_baseline'],$d['days'])
            ||$d['population_model']['start_day']!==CareerScoreContract::START)return $result;
        $index=static function(string $at)use($d):int{
            $offset=CareerScoreContract::elapsed(CareerScoreContract::START,$at);
            if($offset===0)return 10000; // explicit scenario normalization, not an observed 2027 value
            $r=$d['days'][$offset-1]??null;
            if(!is_array($r)||$r['day']!==$at)throw new \DomainException('Missing score observation');
            $v=HouseholdAccount::day($d['macro_baseline'],$d['population_model'],$r,$offset);
            return $v['cases']['fixed_household_structure']['real_rdb_per_uc_proxy_index_bps'];
        };
        foreach($progress['series'] as $s)
            $result['series'][]=self::series($s,$progress['admission_mode'],$index);
        return $result;
    }

    /** Read-only arithmetic over trusted series and daily economic observations.
     * A closed series always uses its own cutoff; no future data is included.
     * The baseline is the human accession/claim day and subsequent observations
     * are strictly post-baseline, at most the last 365 days of the same series. */
    public static function series(array $s,string $mode,callable $index):array
    {
        $from=$s['human_tenure_from'];$through=$s['through_day'];
        $t=CareerScoreContract::elapsed($from,$through);
        if($t!==$s['tenure_days']||!in_array($mode,['opening','late_takeover'],true))
            throw new \DomainException('Inconsistent score series');
        $out=['series_id'=>$s['series_id'],'from'=>$from,'through'=>$through,
            'terminal'=>$s['status']==='closed','tenure_days'=>$t,
            'rankable'=>false,'official_eligible'=>false,'score'=>null];
        if(!$s['tenure_participation_verified'])return $out+['status'=>'participation_unverified'];
        $w=$s['verified_credit_days'];
        // Inherited AI series never earn pre-claim credit. No inferred
        // positive credit, even on a fully replayed historical mandate.
        if($w===null&&($mode==='late_takeover'||$s['accession_on']<$from))$w=0;
        if($w===null)return $out+['status'=>'career_credit_unverified'];
        if(!is_int($w)||$w<0||($mode==='late_takeover'&&$w!==0)
            ||$w>$s['maximum_unverified_credit_days'])
            throw new \DomainException('Invalid sealed credit');
        if($t===0)return $out+['status'=>'economic_window_pending','credit_days'=>$w,
            'career_credit_points'=>CareerScoreContract::timePoints($w)];
        $bounded=static function(string $at)use($index):int{
            $n=$index($at);
            if(!is_int($n)||$n<1||$n>1000000)throw new \DomainException('Invalid score observation');
            return $n;
        };
        $base=$bounded($from);$n=min(365,$t);$sum=0;
        $first=(new \DateTimeImmutable($through,new \DateTimeZone('UTC')))->modify('-'.($n-1).' days');
        for($i=0;$i<$n;$i++)$sum+=$bounded($first->modify('+'.$i.' days')->format('Y-m-d'));
        $mean=intdiv($sum,$n);$gain=intdiv($mean*10000,$base)-10000;
        if($gain>1000000)return $out+['status'=>'economic_gain_outside_contract'];
        $p=CareerScoreContract::points($t,$w,$gain);
        return array_replace($out,$p,['status'=>$t>=CareerScoreContract::MIN_TENURE?'experimental_preview':'provisional_preview',
            'baseline_index_bps'=>$base,'post_accession_mean_bps'=>$mean,'window_days'=>$n,
            'rankable'=>false,'official_eligible'=>false]);
    }
}
