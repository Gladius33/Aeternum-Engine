<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** A closed aggregate feature schema. No raw journals, user input, actor
 * IDs, credentials, biographies, action authors, or real-life records. */
final class GameDataset
{
    public const VERSION='wp-game-aggregate-dataset-v1';
    private const FEATURES=['revenue_minor','expense_minor','closing_balance_minor','real_output_index_bps','unemployment_bps','price_index_scaled','wage_index_scaled','population'];
    private static function key():string{
        $k=getenv('WP_DATASET_BRIDGE_KEY');
        if(!is_string($k)||strlen($k)<32||$k===getenv('WP_RESEARCH_EVIDENCE_KEY')||$k===getenv('WP_CAREER_EVIDENCE_KEY'))throw new \DomainException('Independent dataset bridge key required');
        return $k;
    }
    private static function json(array $x):string{return json_encode($x,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    private static function q(\PDO $db,string $sql,array $p=[]):\PDOStatement{$q=$db->prepare($sql);$q->execute($p);return $q;}
    public static function project(string $world,string $day,int $revision,array $dom):array{
        $d=$dom['state'];$r=end($d['days']);$m=$r['macro']??[];$p=$r['population']['indicators']??[];
        if($d['world_id']!==$world||$d['day']!==$day||($r['day']??null)!==$day||($r['currency']??null)!=='EUR'||$revision<1)
            throw new \DomainException('Dataset source mismatch');
        $values=['revenue_minor'=>$r['revenue']??null,'expense_minor'=>$r['expense']??null,'closing_balance_minor'=>$r['closing_balance']??null,
            'real_output_index_bps'=>$m['real_output_index_bps']??null,'unemployment_bps'=>$m['unemployment_bps']??null,
            'price_index_scaled'=>$m['price_index_scaled']??null,'wage_index_scaled'=>$m['wage_index_scaled']??null,'population'=>$p['population']??null];
        $row=['version'=>self::VERSION,'world'=>hash_hmac('sha256','world|'.$world,self::key()),'country'=>'FR','day'=>$day,'revision'=>$revision,
            'classification'=>'synthetic_game_simulation','features'=>$values];
        $e=['row'=>$row,'signature'=>hash_hmac('sha256',self::json($row),self::key())];self::verify($e);return $e;
    }
    public static function verify(array $e):array{
        if(array_keys($e)!==['row','signature']||!is_array($e['row'])||!is_string($e['signature']))throw new \DomainException('Invalid dataset envelope');
        $r=$e['row'];
        if(array_keys($r)!==['version','world','country','day','revision','classification','features']||$r['version']!==self::VERSION
            ||!preg_match('/^[a-f0-9]{64}$/D',$r['world'])||$r['country']!=='FR'||$r['classification']!=='synthetic_game_simulation'
            ||!is_int($r['revision'])||$r['revision']<1||!is_array($r['features'])||array_keys($r['features'])!==self::FEATURES
            ||!hash_equals(hash_hmac('sha256',self::json($r),self::key()),$e['signature']))
            throw new \DomainException('Dataset signature or scope');
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$r['day']);
        if(!$d||$d->format('Y-m-d')!==$r['day'])throw new \DomainException('Dataset day');
        foreach($r['features'] as $k=>$v)if($v!==null&&(!is_int($v)||abs($v)>100000000000000000))throw new \DomainException('Dataset feature bounds');
        return $r;
    }
    /** Capture committed simulation facts independently of account tiers.
     * Automatic coverage is enabled by the operator after the published data
     * policy is in force; explicit world exclusions always take precedence. */
    public static function capture(\PDO $db,string $world,string $day,int $revision,array $dom):void{
        if(!$db->inTransaction())throw new \LogicException('Dataset capture outside transaction');
        // Current schema covers EUR French macroeconomic observations only.
        $d=$dom['state'];$last=end($d['days']);
        if(!isset($last['macro'])||($last['currency']??null)!=='EUR')return;
        $automatic=getenv('WP_DATASET_AUTO_CAPTURE')==='YES';
        try{$enabled=self::q($db,'SELECT enabled FROM wp_game_dataset_consent WHERE world_id=? FOR UPDATE',[$world])->fetchColumn();}
        catch(\PDOException $e){if((int)($e->errorInfo[1]??0)===1146&&!$automatic)return;throw $e;}
        if($enabled===false&&$automatic){
            self::q($db,'INSERT INTO wp_game_dataset_consent(world_id,enabled) VALUES(?,1)',[$world]);$enabled=1;
        }
        if((int)$enabled!==1)return;
        // A shared transactional lock orders outbox sequence allocation by
        // commit across worlds, so a forward-only consumer cannot skip a
        // lower sequence that was still uncommitted at the time of its read.
        if(self::q($db,'SELECT singleton_id FROM wp_game_dataset_order WHERE singleton_id=1 FOR UPDATE')->fetchColumn()===false)
            throw new \DomainException('Dataset ordering fence unavailable');
        $e=self::project($world,$day,$revision,$dom);
        self::q($db,'INSERT INTO wp_game_dataset_outbox(world_id,committed_day,envelope_json) VALUES(?,?,?)',[$world,$day,self::json($e)]);
    }
    /** Explicit operator opt-in: treat a disabled release flag as permanent
     * destination withdrawal. Bound and validate the entire exclusion scan. */
    public static function excludedAliases(\PDO $db):array{
        $raw=self::q($db,'SELECT world_id FROM wp_game_dataset_consent WHERE enabled=0 ORDER BY world_id LIMIT 1001')->fetchAll(\PDO::FETCH_COLUMN);
        if(count($raw)>1000)throw new \DomainException('Dataset exclusion scan capacity');
        $aliases=[];foreach($raw as $world){
            if(!is_string($world)||$world==='')throw new \DomainException('Invalid excluded world');
            $aliases[]=hash_hmac('sha256','world|'.$world,self::key());
        }
        return $aliases;
    }
    public static function page(\PDO $db,int $after):array{
        if($after<0)throw new \DomainException('Invalid cursor');
        $raw=self::q($db,'SELECT o.seq,o.envelope_json FROM wp_game_dataset_outbox o JOIN wp_game_dataset_consent c USING(world_id) WHERE c.enabled=1 AND o.seq>? ORDER BY o.seq LIMIT 101',[$after])->fetchAll(\PDO::FETCH_ASSOC);
        $rows=[];$next=$after;
        foreach(array_slice($raw,0,100) as $r){$e=json_decode($r['envelope_json'],true,32,JSON_THROW_ON_ERROR);self::verify($e);$rows[]=$e;$next=(int)$r['seq'];}
        return ['version'=>self::VERSION,'rows'=>$rows,'next_after'=>$next,'has_more'=>count($raw)>100];
    }
}
