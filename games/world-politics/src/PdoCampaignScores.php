<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CampaignScore.php';

/** Materialized scores. Only bootstrap and the trusted clock transaction write.
 * Every public row refers to a committed day, never a partial subsystem tick. */
final class PdoCampaignScores
{
    public const PAGE_SIZE=50;
    public function __construct(private readonly \PDO $db){}
    private function tx():void
    {if(!$this->db->inTransaction())throw new \LogicException('Score publication requires owning transaction');}
    private static function rowReport(array $r):array
    {
        $raw=$r['report_json'];$report=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if(!hash_equals($r['report_sha'],hash('sha256',$raw))
            ||$report['version']!==CampaignScore::VERSION||$r['score_version']!==CampaignScore::VERSION
            ||$report['world_id']!==$r['world_id']||$report['cohort_sha']!==$r['cohort_sha']||$report['day']!==$r['committed_day']
            ||$report['score']!==(int)$r['score']||$report['elapsed_office_days']!==(int)$r['elapsed_days']
            ||$report['income_proxy_gain_bps']!==(int)$r['gain_bps']||$report['terminal']!==((int)$r['terminal']===1)
            ||$report['clock_revision']!==(int)$r['clock_revision']||$report['clock_revision']!==$report['elapsed_office_days']
            ||$report['league']!==CampaignScore::LEAGUE||$report['official_ranking_eligible']!==false||$report['rankable_in_alpha']!==true
            ||CampaignScore::points($report['elapsed_office_days'],$report['income_proxy_gain_bps'])['score']!==$report['score'])
            throw new \UnexpectedValueException('Score projection integrity mismatch');
        return $report;
    }
    private function record(string $world,bool $lock=false):?array
    {
        $q=$this->db->prepare('SELECT * FROM wp_campaign_scores WHERE world_id=?'.($lock?' FOR UPDATE':''));
        $q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);return $r===false?null:$r;
    }
    private static function fields(array $report,string $cohort,int $revision):array
    {
        if($revision!==$report['elapsed_office_days'])throw new \DomainException('Clock revision differs from tenure');
        $r=$report+['cohort_sha'=>$cohort,'clock_revision'=>$revision];$json=CampaignScore::json($r);
        return [$cohort,CampaignScore::VERSION,$r['day'],$revision,$r['score'],$r['elapsed_office_days'],$r['income_proxy_gain_bps'],
            $r['terminal']?1:0,$json,hash('sha256',$json)];
    }
    public function seed(string $world,string $bundle,array $config,array $dom,array $dip,array $op):void
    {
        $this->tx();$report=CampaignScore::report($dom,$dip,$op);
        if($report['world_id']!==$world||$report['day']!==CampaignScore::START||$report['terminal'])throw new \DomainException('Invalid score bootstrap');
        $cohort=CampaignScore::cohort($bundle,$config,$dip['state']);
        $q=$this->db->prepare('INSERT INTO wp_campaign_scores(world_id,cohort_sha,score_version,committed_day,clock_revision,score,elapsed_days,gain_bps,terminal,report_json,report_sha) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$world,...self::fields($report,$cohort,0)]);
    }
    /** Called inside the final publication transaction, with already replayed
     * domain snapshots and the old authoritative clock row locked. */
    public function publish(string $world,array $oldClock,string $target,array $dom,array $dip,array $op):void
    {
        $this->tx();$prior=$this->record($world,true);
        if($prior===null)throw new \DomainException('Pinned score row missing');
        $before=self::rowReport($prior);
        if($before['terminal']||$before['day']!==$oldClock['committed_day']||$before['clock_revision']!==(int)$oldClock['revision']
            ||$oldClock['pending_day']!==$target||(int)$oldClock['score_enabled']!==1)
            throw new \DomainException('Score checkpoint conflicts with world clock');
        $q=$this->db->prepare('SELECT w.scenario,w.reference_bundle_sha,d.assumptions_json FROM wp_alpha_worlds w JOIN wp_domestic_states d ON d.world_id=w.world_id WHERE w.world_id=?');
        $q->execute([$world]);$pins=$q->fetch(\PDO::FETCH_ASSOC);
        if($pins===false||$pins['scenario']!==CampaignScore::SCENARIO||!is_string($pins['reference_bundle_sha']))throw new \DomainException('Ranked world scope invalid');
        $config=json_decode($pins['assumptions_json'],true,64,JSON_THROW_ON_ERROR);
        $cohort=CampaignScore::cohort($pins['reference_bundle_sha'],$config,$dip['state']);
        if(!hash_equals($before['cohort_sha'],$cohort))throw new \DomainException('Ranked cohort changed');
        $report=CampaignScore::report($dom,$dip,$op);
        if($report['world_id']!==$world||$report['day']!==$target||$report['elapsed_office_days']!==$before['elapsed_office_days']+1)
            throw new \DomainException('Score does not follow committed checkpoint');
        $q=$this->db->prepare('UPDATE wp_campaign_scores SET cohort_sha=?,score_version=?,committed_day=?,clock_revision=?,score=?,elapsed_days=?,gain_bps=?,terminal=?,report_json=?,report_sha=? WHERE world_id=?');
        $q->execute([...self::fields($report,$cohort,(int)$oldClock['revision']+1),$world]);
        if($q->rowCount()!==1)throw new \DomainException('Score publication failed');
    }
    /** Private campaign read; gateway checks live membership first. */
    public function read(string $world):?array
    {
        $q=$this->db->prepare('SELECT s.*,c.committed_day AS clock_day,c.revision AS authoritative_revision,c.game_over,c.score_enabled,c.pending_day
            FROM wp_campaign_scores s JOIN wp_world_clock c ON c.world_id=s.world_id WHERE s.world_id=?');
        $q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);
        if($r===false)return null;
        $report=self::rowReport($r);
        if((int)$r['score_enabled']!==1||$r['clock_day']!==$report['day']||(int)$r['authoritative_revision']!==$report['clock_revision']
            ||((int)$r['game_over']===1)!==$report['terminal'])throw new \UnexpectedValueException('Score differs from committed clock');
        return $report+['world_advancing'=>$r['pending_day']!==null];
    }
    private static function page(int $page):int
    {if($page<1||$page>100000)throw new \InvalidArgumentException('Invalid leaderboard page');return ($page-1)*self::PAGE_SIZE;}
    /** List cohorts, not a mixed-data global ranking. */
    public function cohorts(int $page):array
    {
        $offset=self::page($page);$limit=self::PAGE_SIZE;
        $q=$this->db->query("SELECT s.cohort_sha,COUNT(DISTINCT w.owner_id) AS players,COUNT(*) AS campaigns
            FROM wp_campaign_scores s JOIN wp_alpha_worlds w ON w.world_id=s.world_id
            JOIN wp_accounts a ON a.account_id=w.owner_id AND a.enabled=1
            JOIN wp_world_clock c ON c.world_id=s.world_id AND c.score_enabled=1 AND c.committed_day=s.committed_day AND c.revision=s.clock_revision
            WHERE s.score_version='".CampaignScore::VERSION."' GROUP BY s.cohort_sha ORDER BY s.cohort_sha LIMIT $limit OFFSET $offset");
        $rows=[];foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $r)$rows[]=['cohort_sha'=>$r['cohort_sha'],'players'=>(int)$r['players'],'campaigns'=>(int)$r['campaigns']];
        return ['league'=>CampaignScore::LEAGUE,'score_version'=>CampaignScore::VERSION,'page'=>$page,'page_size'=>$limit,'cohorts'=>$rows,
            'official_ranking_eligible'=>false];
    }
    /** One best *current* campaign per enabled account, stable tie ordering,
     * equal competitive rank for equal score / tenure / economic gain.
     * No private username, world ID, label, token or account ID is published. */
    public function leaderboard(string $cohort,int $page,string $viewer):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$cohort))throw new \InvalidArgumentException('Invalid cohort');
        $offset=self::page($page);$limit=self::PAGE_SIZE;
        $q=$this->db->prepare("WITH candidates AS (
            SELECT s.world_id,w.owner_id,s.score,s.elapsed_days,s.gain_bps,
                ROW_NUMBER() OVER(PARTITION BY w.owner_id ORDER BY s.score DESC,s.elapsed_days DESC,s.gain_bps DESC,s.world_id ASC) AS selection_order
            FROM wp_campaign_scores s JOIN wp_alpha_worlds w ON w.world_id=s.world_id
            JOIN wp_accounts a ON a.account_id=w.owner_id AND a.enabled=1
            JOIN wp_world_clock c ON c.world_id=s.world_id AND c.score_enabled=1 AND c.committed_day=s.committed_day AND c.revision=s.clock_revision
            WHERE s.cohort_sha=? AND s.score_version=?
        ), ranked AS (
            SELECT world_id,owner_id,score,elapsed_days,gain_bps,
                RANK() OVER(ORDER BY score DESC,elapsed_days DESC,gain_bps DESC) AS player_rank,
                COUNT(*) OVER() AS total_players
            FROM candidates WHERE selection_order=1
        ), page_rows AS (
            SELECT * FROM ranked ORDER BY score DESC,elapsed_days DESC,gain_bps DESC,owner_id ASC LIMIT $limit OFFSET $offset
        )
        SELECT s.*,r.owner_id,r.player_rank,r.total_players FROM page_rows r JOIN wp_campaign_scores s ON s.world_id=r.world_id
        ORDER BY r.score DESC,r.elapsed_days DESC,r.gain_bps DESC,r.owner_id ASC");
        $q->execute([$cohort,CampaignScore::VERSION]);$rows=[];$total=null;
        foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $r){
            $report=self::rowReport($r);$total=(int)$r['total_players'];
            $rows[]=['rank'=>(int)$r['player_rank'],'player'=>'joueur-'.substr(hash('sha256','wp78-public-alias/'.$r['owner_id']),0,20),
                'is_you'=>hash_equals($r['owner_id'],$viewer),'score'=>$report['score'],'elapsed_office_days'=>$report['elapsed_office_days'],
                'income_proxy_gain_bps'=>$report['income_proxy_gain_bps'],'day'=>$report['day'],'terminal'=>$report['terminal'],
                'early_window_provisional'=>$report['early_window_provisional']];
        }
        return ['league'=>CampaignScore::LEAGUE,'score_version'=>CampaignScore::VERSION,'cohort_sha'=>$cohort,'page'=>$page,'page_size'=>$limit,
            'total_players'=>$total,'entries'=>$rows,'official_ranking_eligible'=>false,'one_campaign_per_player'=>true,
            'ordering'=>'score_desc_tenure_desc_gain_desc','classification'=>'experimental_alpha_leaderboard'];
    }
}
