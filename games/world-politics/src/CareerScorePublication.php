<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CareerInstitutionLedger.php';
require_once __DIR__.'/CareerScorePreview.php';

/** WP120: opt-in public experimental league, signed append-only score evidence. */
final class CareerScorePublication
{
    public const VERSION='wp120-public-experimental-v1';
    private const LIMIT=5000;
    private static function q(\PDO $db,string $sql,array $p=[]):\PDOStatement{
        $q=$db->prepare($sql);$q->execute($p);return $q;
    }
    private static function decode(string $s):array{return json_decode($s,true,64,JSON_THROW_ON_ERROR);}
    private static function key():CareerInstitutionEvidence{
        $s=getenv('WP_CAREER_EVIDENCE_KEY');
        if(!is_string($s)||strlen($s)<32)throw new \DomainException('Score evidence key missing');
        return new CareerInstitutionEvidence($s);
    }
    private static function config(\PDO $db,string $world):array{
        $r=self::q($db,'SELECT w.reference_bundle_sha,d.assumptions_json FROM wp_alpha_worlds w JOIN wp_domestic_states d USING(world_id) WHERE w.world_id=?',[$world])->fetch(\PDO::FETCH_ASSOC);
        if($r===false||!is_string($r['reference_bundle_sha']))throw new \DomainException('Missing score configuration');
        return ['bundle'=>$r['reference_bundle_sha'],'config'=>hash('sha256',$r['assumptions_json'])];
    }
    public static function seed(\PDO $db,string $world):void{
        if(!$db->inTransaction())throw new \LogicException('Score seed outside transaction');
        $cfg=self::config($db,$world);
        $pin=['world'=>$world,'version'=>self::VERSION,'start'=>CareerScoreContract::START,'country'=>'FR','config'=>$cfg,
            'pins'=>['scenario'=>hash('sha256','public-economic-career-v1'),
                'rules'=>hash('sha256',self::VERSION.'|'.CareerScoreContract::VERSION.'|'.CareerScorePreview::VERSION),
                'economy'=>$cfg['config'],'data'=>$cfg['bundle'],'difficulty'=>hash('sha256','standard-public-capacity-8'),
                'participation'=>hash('sha256',CareerParticipation::VERSION),
                'autonomy'=>hash('sha256','wp119-cabinet-programme-autonomy-v1'),
                'population_scope'=>hash('sha256',HouseholdAccount::VERSION.'|fixed_household_structure'),
                'mandate_authority'=>hash('sha256',CareerInstitutionLedger::VERSION.'|FR')]];
        self::q($db,'INSERT INTO wp_career_score_pins(world_id,manifest_json,signature_sha) VALUES(?,?,?)',
            [$world,CareerInstitutionLedger::json($pin),self::key()->sign($pin)]);
    }
    private static function pin(\PDO $db,string $world):?array{
        try{$r=self::q($db,'SELECT manifest_json,signature_sha FROM wp_career_score_pins WHERE world_id=?',[$world])->fetch(\PDO::FETCH_ASSOC);}
        catch(\PDOException $e){if((int)($e->errorInfo[1]??0)===1146)return null;throw $e;}
        if($r===false)return null;
        $p=self::decode($r['manifest_json']);self::key()->verify($p,$r['signature_sha']);
        if($p['world']!==$world||$p['version']!==self::VERSION||$p['country']!=='FR'||$p['start']!==CareerScoreContract::START||$p['config']!==self::config($db,$world))
            throw new \DomainException('Score pin mismatch');
        return $p;
    }
    private static function cohort(array $p,string $mode):string{
        return CareerScoreContract::cohort($p['pins'],'FR',$p['start'],CareerScoreContract::HORIZON,$mode);
    }
    private static function prior(\PDO $db,string $world,string $series):?array{
        $r=self::q($db,'SELECT e.world_id,e.series_id,e.sequence_no,e.evidence_json,e.signature_sha,
            l.account_id,l.cohort_sha,l.qualified,l.sequence_no AS journal_head_sequence
            FROM wp_career_score_events e LEFT JOIN wp_career_score_latest l USING(world_id,series_id)
            WHERE e.world_id=? AND e.series_id=? ORDER BY e.sequence_no DESC LIMIT 1 FOR UPDATE',[$world,$series])->fetch(\PDO::FETCH_ASSOC);
        return $r===false?null:self::verified($r);
    }
    private static function verified(array $r):array{
        $e=self::decode($r['evidence_json']);self::key()->verify($e,$r['signature_sha']);
        if(array_key_exists('journal_head_sequence',$r)&&($r['journal_head_sequence']===null||(int)$r['journal_head_sequence']!==(int)$r['sequence_no']))
            throw new \DomainException('Score projection is not the journal head');
        foreach(['world'=>'world_id','series'=>'series_id','account'=>'account_id','cohort'=>'cohort_sha'] as $k=>$v)
            if($e[$k]!==$r[$v])throw new \DomainException('Score index mismatch');
        if($e['version']!==self::VERSION||$e['sequence']!==(int)$r['sequence_no']||(int)$e['qualified']!==(int)$r['qualified'])
            throw new \DomainException('Score projection mismatch');
        $p=$e['preview'];
        if($p['score']!==null){
            $calc=CareerScoreContract::points($p['tenure_days'],$p['credit_days'],$p['purchasing_power_points']);
            foreach($calc as $k=>$v)if($p[$k]!==$v)throw new \DomainException('Score arithmetic mismatch');
            if(CareerScoreContract::elapsed($p['from'],$p['through'])!==$p['tenure_days']
                ||$p['window_days']!==min(365,$p['tenure_days'])
                ||$p['baseline_index_bps']<1||$p['post_accession_mean_bps']<1
                ||intdiv($p['post_accession_mean_bps']*10000,$p['baseline_index_bps'])-10000!==$p['purchasing_power_points'])
                throw new \DomainException('Score economic window mismatch');
        }
        if($e['qualified']!==($p['score']!==null&&$p['status']==='experimental_preview'&&$p['tenure_days']>=CareerScoreContract::MIN_TENURE))
            throw new \DomainException('Score qualification mismatch');
        return $e;
    }
    private static function append(\PDO $db,array $e):void{
        self::q($db,'INSERT INTO wp_career_score_events(world_id,series_id,sequence_no,evidence_json,signature_sha) VALUES(?,?,?,?,?)',
            [$e['world'],$e['series'],$e['sequence'],CareerInstitutionLedger::json($e),self::key()->sign($e)]);
        self::q($db,'INSERT INTO wp_career_score_latest(world_id,series_id,sequence_no,account_id,cohort_sha,qualified) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE sequence_no=VALUES(sequence_no),qualified=VALUES(qualified)',
            [$e['world'],$e['series'],$e['sequence'],$e['account'],$e['cohort'],(int)$e['qualified']]);
    }
    /** Fenced daily publication, after signed participation and before clock commit. */
    public static function publish(\PDO $db,array $s,string $target,array $dom):void{
        if(!$db->inTransaction())throw new \LogicException('Score publication outside transaction');
        $world=$s['world'];$pin=self::pin($db,$world);if($pin===null)return;
        if($s['country']!=='FR'||$s['mode']!=='career'||($s['participation']['through']??null)!==$target)
            throw new \DomainException('Score publication scope');
        foreach($s['careers'] as $id=>$c){
            if($c['account']===null)continue;
            $view=CareerScorePreview::preview($s,$id,$target,$dom);
            $mode=($c['claimed_on']??$c['admitted_on'])===CareerScoreContract::START?'opening':'late_takeover';
            $cohort=self::cohort($pin,$mode);
            foreach($view['series'] as $p){
                $series=$p['series_id'];$prior=self::prior($db,$world,$series);
                if($prior!==null){
                    if($prior['account']!==$c['account']||$prior['cohort']!==$cohort)throw new \DomainException('Score identity changed');
                    if($prior['preview']['terminal'])continue;
                    if($prior['preview']['through']>$p['through']||$prior['sequence']>=$s['sequence'])
                        throw new \DomainException('Score chronology mismatch');
                    if(isset($prior['preview']['baseline_index_bps'],$p['baseline_index_bps'])&&$prior['preview']['baseline_index_bps']!==$p['baseline_index_bps'])
                        throw new \DomainException('Accession baseline changed');
                }
                $e=['version'=>self::VERSION,'world'=>$world,'series'=>$series,'account'=>$c['account'],
                    'cohort'=>$cohort,'mode'=>$mode,'sequence'=>$s['sequence'],'published_day'=>$target,
                    'ledger_sha'=>$s['last_event_sha'],'source_sha'=>$view['source_sha'],
                    'previous_sha'=>$prior===null?str_repeat('0',64):CareerInstitutionLedger::digest($prior),
                    'qualified'=>$p['score']!==null&&$p['status']==='experimental_preview','preview'=>$p];
                self::append($db,$e);
            }
        }
    }
    /** Freeze the committed cutoff within the mandate closure transaction. */
    public static function close(\PDO $db,array $before,array $after):void{
        if(!$db->inTransaction())throw new \LogicException('Score closure outside transaction');
        foreach($after['series'] as $id=>$s){
            if($s['end']===null||($before['series'][$id]['end']??null)!==null)continue;
            if(self::pin($db,$after['world'])===null)return;
            $prior=self::prior($db,$after['world'],$id);
            if($prior===null)continue;
            if($prior['preview']['terminal']||$prior['preview']['through']!==$s['end']||$prior['sequence']>=$after['sequence'])
                throw new \DomainException('Score closure cutoff mismatch');
            $e=$prior;$e['sequence']=$after['sequence'];$e['ledger_sha']=$after['last_event_sha'];
            $e['previous_sha']=CareerInstitutionLedger::digest($prior);$e['preview']['terminal']=true;
            self::append($db,$e);
        }
    }
    /** Independent journal audit; verifies signatures, predecessor links,
     * actual career event anchors and exact latest projection. Read-only. */
    public static function audit(\PDO $db,string $world):array{
        if(self::pin($db,$world)===null)throw new \DomainException('World is not score-pinned');
        $last=[];$count=0;
        $q=self::q($db,'SELECT e.*,c.event_sha AS career_event_sha FROM wp_career_score_events e
            LEFT JOIN wp_career_events c ON c.world_id=e.world_id AND c.sequence_no=e.sequence_no
            WHERE e.world_id=? ORDER BY e.series_id,e.sequence_no',[$world]);
        foreach($q as $r){
            $e=self::decode($r['evidence_json']);
            $e=self::verified($r+['account_id'=>$e['account'],'cohort_sha'=>$e['cohort'],'qualified'=>(int)$e['qualified']]);
            $id=$e['series'];$prev=$last[$id]??null;
            if($r['career_event_sha']!==$e['ledger_sha']||!preg_match('/^[a-f0-9]{64}$/D',$e['source_sha'])
                ||$e['previous_sha']!==($prev===null?str_repeat('0',64):CareerInstitutionLedger::digest($prev))
                ||($prev!==null&&($prev['preview']['terminal']||$prev['account']!==$e['account']||$prev['cohort']!==$e['cohort']
                    ||$prev['preview']['through']>$e['preview']['through']||$prev['published_day']>$e['published_day']
                    ||(isset($prev['preview']['baseline_index_bps'],$e['preview']['baseline_index_bps'])
                        &&$prev['preview']['baseline_index_bps']!==$e['preview']['baseline_index_bps']))))
                throw new \DomainException('Score journal chain mismatch');
            $last[$id]=$e;$count++;
        }
        $n=0;
        foreach(self::q($db,'SELECT l.*,e.evidence_json,e.signature_sha FROM wp_career_score_latest l JOIN wp_career_score_events e USING(world_id,series_id,sequence_no) WHERE l.world_id=?',[$world]) as $r){
            $e=self::verified($r);
            if(($last[$e['series']]??null)!==$e)throw new \DomainException('Score head mismatch');
            $n++;
        }
        if($n!==count($last))throw new \DomainException('Missing score projection');
        return ['world_id'=>$world,'version'=>self::VERSION,'events'=>$count,'series'=>$n,'verified'=>true];
    }
    /** Offline operator audit in one consistent, read-only snapshot. No
     * database repair, secret disclosure or client-supplied score input. */
    public static function inspect(\PDO $db,string $world):array{
        if($db->inTransaction()||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$world))
            throw new \DomainException('Invalid score audit scope');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $db->beginTransaction();
        try{
            $clock=self::q($db,'SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?',[$world])->fetch(\PDO::FETCH_ASSOC);
            if($clock===false||$clock['pending_day']!==null)throw new \DomainException('Missing or unsettled world clock');
            $report=self::audit($db,$world);
            $db->commit();
            return $report+['committed_day'=>$clock['committed_day'],'official_eligible'=>false];
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    /** Best single series per account; shared ties; stable order. */
    public static function rank(array $rows):array{
        $cmp=static fn(array $a,array $b):int=>$b['score']<=>$a['score']?:$b['gain']<=>$a['gain']?:$b['tenure']<=>$a['tenure'];
        usort($rows,fn($a,$b)=>$cmp($a,$b)?:strcmp($a['account'],$b['account'])?:$a['universe']<=>$b['universe']?:strcmp($a['series'],$b['series']));
        $out=[];$seen=[];$prev=null;$rank=0;
        foreach($rows as $r){
            if(isset($seen[$r['account']]))continue;$seen[$r['account']]=true;
            if($prev===null||$cmp($prev,$r)!==0)$rank=count($out)+1;
            $out[]=['rank'=>$rank,...$r];$prev=$r;
        }
        return $out;
    }
    public static function cohorts(\PDO $db,?int $no):array{
        $sql='SELECT p.world_id,p.manifest_json,p.signature_sha,u.universe_no FROM wp_career_score_pins p JOIN wp_career_public_worlds u USING(world_id)';
        $rows=self::q($db,$sql.($no===null?'':' WHERE u.universe_no=?').' ORDER BY u.universe_no LIMIT '.(self::LIMIT+1),$no===null?[]:[$no])->fetchAll(\PDO::FETCH_ASSOC);
        if(count($rows)>self::LIMIT)throw new \DomainException('Cohort discovery capacity');
        $out=[];
        foreach($rows as $r){
            $p=self::decode($r['manifest_json']);self::key()->verify($p,$r['signature_sha']);
            if($p['world']!==$r['world_id']||$p['version']!==self::VERSION)throw new \DomainException('Invalid cohort pin');
            foreach(['opening','late_takeover'] as $mode){$sha=self::cohort($p,$mode);$out[$sha]=['cohort_sha'=>$sha,'country'=>'FR','admission_mode'=>$mode,'official_eligible'=>false];}
        }
        ksort($out,SORT_STRING);return ['version'=>self::VERSION,'universe_no'=>$no,'cohorts'=>array_values($out),'official_eligible'=>false];
    }
    public static function rankings(\PDO $db,string $account,?int $no,string $cohort,int $page=1,?string $snapshot=null):array{
        if(!preg_match('/^[a-f0-9]{64}$/D',$cohort)||$page<1||$page>100||($page>1&&$snapshot===null))
            throw new \DomainException('Invalid ranking page or missing snapshot');
        // Start from the signed journal, not from mutable qualification or cohort
        // indexes. A missing projection or hidden qualified series must fail
        // closed rather than silently disappear from the league.
        $sql='SELECT e.world_id,e.series_id,e.sequence_no,e.evidence_json,e.signature_sha,
            l.account_id,l.cohort_sha,l.qualified,l.sequence_no AS journal_head_sequence,
            u.universe_no,c.committed_day,p.manifest_json,p.signature_sha AS pin_signature,
            a.enabled AS account_enabled,m.account_id AS member_account
            FROM wp_career_score_events e LEFT JOIN wp_career_score_latest l USING(world_id,series_id)
            JOIN wp_career_score_pins p ON p.world_id=e.world_id JOIN wp_career_public_worlds u ON u.world_id=e.world_id
            JOIN wp_world_clock c ON c.world_id=e.world_id LEFT JOIN wp_accounts a ON a.account_id=l.account_id
            LEFT JOIN wp_alpha_members m ON m.world_id=e.world_id AND m.account_id=l.account_id
            WHERE NOT EXISTS (SELECT 1 FROM wp_career_score_events h WHERE h.world_id=e.world_id AND h.series_id=e.series_id AND h.sequence_no>e.sequence_no)';
        $raw=self::q($db,$sql.($no===null?'':' AND u.universe_no=?').' ORDER BY e.world_id,e.series_id LIMIT '.(self::LIMIT+1),$no===null?[]:[$no])->fetchAll(\PDO::FETCH_ASSOC);
        if(count($raw)>self::LIMIT)throw new \DomainException('Ranking capacity exceeded');
        $rows=[];foreach($raw as $r){
            $e=self::verified($r);$p=$e['preview'];$pin=self::decode($r['manifest_json']);self::key()->verify($pin,$r['pin_signature']);
            if($pin['world']!==$e['world']||$pin['version']!==self::VERSION||self::cohort($pin,$e['mode'])!==$e['cohort']
                ||$e['published_day']>$r['committed_day']||(!$p['terminal']&&$e['published_day']!==$r['committed_day']))
                throw new \DomainException('Uncommitted ranking evidence');
            if($e['cohort']!==$cohort||!$e['qualified']||(int)$r['account_enabled']!==1||$r['member_account']!==$e['account'])continue;
            $rows[]=['account'=>$e['account'],'universe'=>(int)$r['universe_no'],'series'=>$e['series'],'score'=>$p['score'],
                'gain'=>$p['purchasing_power_points'],'tenure'=>$p['tenure_days'],'credit'=>$p['credit_days'],
                'through'=>$p['through'],'terminal'=>$p['terminal'],'evidence_sha'=>CareerInstitutionLedger::digest($e)];
        }
        $ranked=self::rank($rows);$digest=self::key()->sign(['scope'=>$no,'cohort'=>$cohort,'rows'=>$ranked]);
        if($snapshot!==null&&!hash_equals($digest,$snapshot))throw new \DomainException('Ranking changed; restart pagination');
        $out=[];foreach(array_slice($ranked,($page-1)*50,50) as $r){
            $out[]=['rank'=>$r['rank'],'player'=>'joueur-'.substr(hash('sha256','wp120/'.$r['account']),0,20),
                'is_you'=>$r['account']===$account,'universe_no'=>$r['universe'],'score'=>$r['score'],
                'tenure_days'=>$r['tenure'],'credit_days'=>$r['credit'],'purchasing_power_gain_bps'=>$r['gain'],
                'through_day'=>$r['through'],'terminal'=>$r['terminal']];
        }
        return ['version'=>self::VERSION,'scope'=>$no===null?'global':'universe','universe_no'=>$no,
            'cohort_sha'=>$cohort,'snapshot'=>$digest,'page'=>$page,'total'=>count($ranked),'rows'=>$out,
            'official_eligible'=>false,'classification'=>'experimental_uncalibrated_household_proxy'];
    }
}
