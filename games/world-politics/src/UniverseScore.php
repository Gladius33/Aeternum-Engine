<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CampaignScore.php';

/** France-only alpha composite. Never substitute foreign GDP for household
 * purchasing power. Claims pin an opening index; no retroactive scoring. */
final class UniverseScore
{
    public const VERSION='wp104-universe-score-v1';
    private static function query(\PDO $db,string $sql,array $args=[]):\PDOStatement
    {$q=$db->prepare($sql);$q->execute($args);return $q;}
    private static function measured(array $dom,array $dip):array
    {
        $h=HouseholdPurchasingPowerProxy::report($dom,$dip);
        $days=(int)(new \DateTimeImmutable(CampaignScore::START))->diff(new \DateTimeImmutable($h['day']))->format('%r%a');
        if($days<0||$h['window_days']!==min(365,$days))throw new \DomainException('Incomplete universe income history');
        $m=$h['rolling_means_bps'];$index=$m===null?10000:intdiv($m['constant_nominal_residual']+$m['price_indexed_residual'],2);
        if($index<1||$index>1000000)throw new \DomainException('Invalid income index');
        return ['day'=>$h['day'],'index'=>$index,'source'=>CampaignScore::digest($h['source_hashes'])];
    }
    private static function cohort(string $bundle,array $config,array $dip):string
    {return CampaignScore::digest(['rules'=>self::VERSION,'country'=>'FR','source_cohort'=>CampaignScore::cohort($bundle,$config,$dip)]);}
    public static function seed(\PDO $db,string $world,string $bundle,array $config,array $dom,array $dip):void
    {
        if(!$db->inTransaction())throw new \LogicException('Income checkpoint requires world transaction');
        $m=self::measured($dom,$dip);
        if($m['day']!==CampaignScore::START||$dom['state']['world_id']!==$world)throw new \DomainException('Invalid opening checkpoint');
        self::query($db,'INSERT INTO wp_universe_income_checkpoints(world_id,country_code,day,index_bps,cohort_sha,source_sha) VALUES(?,\'FR\',?,?,?,?)',
            [$world,$m['day'],$m['index'],self::cohort($bundle,$config,$dip['state']),$m['source']]);
    }
    public static function claim(\PDO $db,string $world,string $country,int $generation,string $day):void
    {
        if($country!=='FR')return;
        if(!$db->inTransaction())throw new \LogicException('Score claim requires handover transaction');
        $r=self::query($db,'SELECT * FROM wp_universe_income_checkpoints WHERE world_id=? AND country_code=? FOR UPDATE',[$world,$country])->fetch(\PDO::FETCH_ASSOC);
        if($r===false)return; // No retroactive baseline for pre-WP104 worlds.
        if($r['day']!==$day)throw new \DomainException('Stale takeover income baseline');
        self::query($db,'INSERT INTO wp_universe_period_scores(world_id,country_code,generation,cohort_sha,opening_index_bps,latest_index_bps,opening_source_sha,latest_source_sha,through_day) VALUES(?,?,?,?,?,?,?,?,?)',
            [$world,$country,$generation,$r['cohort_sha'],$r['index_bps'],$r['index_bps'],$r['source_sha'],$r['source_sha'],$day]);
    }
    public static function points(int $days,int $opening,int $current):array
    {
        if($opening<1||$opening>1000000||$current<1||$current>1000000)throw new \DomainException('Invalid score index');
        $gain=intdiv($current*10000,$opening)-10000;
        return ['gain_bps'=>$gain,...CampaignScore::points($days,$gain)];
    }
    public static function publish(\PDO $db,string $world,string $day,array $dom,array $dip):void
    {
        try{$exists=self::query($db,'SELECT 1 FROM wp_universes WHERE world_id=?',[$world])->fetchColumn()!==false;}
        catch(\PDOException $e){if($e->getCode()==='42S02'&&(int)($e->errorInfo[1]??0)===1146)return;throw $e;}
        if(!$exists)return;
        if(!$db->inTransaction())throw new \LogicException('Score publication requires clock transaction');
        $m=self::measured($dom,$dip);
        if($m['day']!==$day||$dom['state']['world_id']!==$world)throw new \DomainException('Income checkpoint mismatch');
        $r=self::query($db,"SELECT * FROM wp_universe_income_checkpoints WHERE world_id=? AND country_code='FR' FOR UPDATE",[$world])->fetch(\PDO::FETCH_ASSOC);
        if($r===false){
            // Old managed worlds may begin recording only now. Existing
            // periods remain unscored rather than inventing a past baseline.
            $cfg=self::query($db,'SELECT d.assumptions_json,w.reference_bundle_sha FROM wp_domestic_states d JOIN wp_alpha_worlds w ON w.world_id=d.world_id WHERE d.world_id=?',[$world])->fetch(\PDO::FETCH_ASSOC);
            if($cfg===false)throw new \DomainException('No pinned universe config');
            $cohort=self::cohort($cfg['reference_bundle_sha'],json_decode($cfg['assumptions_json'],true,64,JSON_THROW_ON_ERROR),$dip['state']);
            self::query($db,"INSERT INTO wp_universe_income_checkpoints(world_id,country_code,day,index_bps,cohort_sha,source_sha) VALUES(?,'FR',?,?,?,?)",[$world,$day,$m['index'],$cohort,$m['source']]);
            return;
        }
        if($day!==(new \DateTimeImmutable($r['day']))->modify('+1 day')->format('Y-m-d'))throw new \DomainException('Noncontiguous universe score publication');
        self::query($db,"UPDATE wp_universe_income_checkpoints SET day=?,index_bps=?,source_sha=? WHERE world_id=? AND country_code='FR'",[$day,$m['index'],$m['source'],$world]);
        $p=self::query($db,"SELECT s.*,p.joined_on FROM wp_universe_period_scores s JOIN wp_universe_control_periods p USING(world_id,country_code,generation)
            JOIN wp_universe_slots a USING(world_id,country_code,generation)
            WHERE s.world_id=? AND s.country_code='FR' AND p.left_on IS NULL AND a.account_id=p.account_id FOR UPDATE",[$world])->fetch(\PDO::FETCH_ASSOC);
        if($p===false)return;
        if($p['cohort_sha']!==$r['cohort_sha']||$p['through_day']!==$r['day'])throw new \DomainException('Score cohort or day mismatch');
        $elapsed=(int)(new \DateTimeImmutable($p['joined_on']))->diff(new \DateTimeImmutable($day))->format('%r%a');
        $points=self::points($elapsed,(int)$p['opening_index_bps'],$m['index']);
        self::query($db,'UPDATE wp_universe_period_scores SET latest_index_bps=?,latest_source_sha=?,through_day=?,elapsed_control_days=?,gain_bps=?,score=? WHERE world_id=? AND country_code=? AND generation=?',
            [$m['index'],$m['source'],$day,$elapsed,$points['gain_bps'],$points['score'],$world,'FR',$p['generation']]);
    }
    public static function cohorts(\PDO $db,?int $no):array
    {
        $where=$no===null?'':' AND u.universe_no=?';
        $rows=self::query($db,'SELECT s.cohort_sha,COUNT(DISTINCT p.account_id) AS players
            FROM wp_universe_period_scores s JOIN wp_universe_control_periods p USING(world_id,country_code,generation)
            JOIN wp_universes u ON u.world_id=s.world_id JOIN wp_accounts a ON a.account_id=p.account_id
            WHERE a.enabled=1'.$where.' GROUP BY s.cohort_sha ORDER BY s.cohort_sha LIMIT 50',$no===null?[]:[$no])->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r)=>['cohort_sha'=>$r['cohort_sha'],'players'=>(int)$r['players'],'country'=>'FR'],$rows);
    }
    public static function rankings(\PDO $db,AlphaSession $session,?int $no,string $cohort,int $page=1):array
    {
        if(!$session->active())throw new AlphaHttpError(401,'authentication_required');
        if(!preg_match('/^[a-f0-9]{64}$/D',$cohort)||$page<1||$page>100000)throw new AlphaHttpError(422,'invalid_score_query');
        $where=$no===null?'':' AND u.universe_no=?';$offset=($page-1)*50;
        $rows=self::query($db,'WITH candidates AS (
            SELECT s.*,p.account_id,u.universe_no,p.joined_on,p.left_on FROM wp_universe_period_scores s
            JOIN wp_universe_control_periods p USING(world_id,country_code,generation)
            JOIN wp_universes u ON u.world_id=s.world_id JOIN wp_accounts a ON a.account_id=p.account_id
            WHERE a.enabled=1 AND s.cohort_sha=?'.$where.'),
            chosen AS (SELECT candidates.*,ROW_NUMBER() OVER(PARTITION BY account_id ORDER BY score DESC,elapsed_control_days DESC,gain_bps DESC,universe_no,generation) AS selection_no FROM candidates),
            ranked AS (SELECT chosen.*,ROW_NUMBER() OVER(ORDER BY score DESC,elapsed_control_days DESC,gain_bps DESC,account_id) AS rank_no FROM chosen WHERE selection_no=1)
            SELECT * FROM ranked ORDER BY rank_no LIMIT 50 OFFSET '.$offset,$no===null?[$cohort]:[$cohort,$no])->fetchAll(\PDO::FETCH_ASSOC);
        $out=[];foreach($rows as $r)$out[]=['rank'=>(int)$r['rank_no'],'player'=>'joueur-'.substr(hash('sha256','wp102-universe/'.$r['account_id']),0,20),
            'is_you'=>$r['account_id']===$session->id,'country'=>$r['country_code'],'universe_no'=>(int)$r['universe_no'],
            'joined_on'=>$r['joined_on'],'through_day'=>$r['through_day'],'left_on'=>$r['left_on'],'control_days'=>(int)$r['elapsed_control_days'],
            'opening_income_index_bps'=>(int)$r['opening_index_bps'],'latest_income_index_bps'=>(int)$r['latest_index_bps'],'gain_bps'=>(int)$r['gain_bps'],'score'=>(int)$r['score']];
        return ['scope'=>$no===null?'global':'universe','universe_no'=>$no,'page'=>$page,'cohort_sha'=>$cohort,'rows'=>$out,
            'version'=>self::VERSION,'country_scope'=>['FR'],'official_ranking_eligible'=>false,
            'income_model'=>'experimental_household_proxy','score_basis'=>'best_single_control_period_with_relative_income_gain',
            'all_country_scores_available'=>false];
    }
}
