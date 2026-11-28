<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ResearchQueue.php';
require_once __DIR__.'/GameDataset.php';

/** Bounded at-least-once import. Cursor is advanced only after the signed
 * batch commits. A crash between import and checkpoint is an idempotent replay.
 * All workers for one stream share a MariaDB advisory lock. */
final class GameDatasetSync
{
    private static function q(\PDO $db,string $sql,array $args=[]):\PDOStatement{
        $q=$db->prepare($sql);$q->execute($args);return $q;
    }
    public static function run(\PDO $db,ResearchQueue $queue,string $stream,int $maxPages,callable $fetch,?callable $exclusions=null):array{
        if($db->inTransaction()||!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$stream)||$maxPages<1||$maxPages>1000)
            throw new \DomainException('Invalid sync settings');
        $lock='wp-dataset-'.substr(hash('sha256',$stream),0,48);
        if((int)self::q($db,'SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()!==1)
            return ['status'=>'already_running'];
        try{
            self::q($db,'INSERT IGNORE INTO wp_research_dataset_sync(stream_id) VALUES(?)',[$stream]);
            $cursor=(int)self::q($db,'SELECT last_seq FROM wp_research_dataset_sync WHERE stream_id=?',[$stream])->fetchColumn();
            self::q($db,'UPDATE wp_research_dataset_sync SET last_run_epoch=? WHERE stream_id=?',[time(),$stream]);
            $withdrawals=0;$deleted=0;
            if($exclusions!==null){
                $aliases=$exclusions();
                if(!is_array($aliases)||!array_is_list($aliases)||count($aliases)>1000)throw new \DomainException('Invalid exclusion scan');
                foreach($aliases as $alias)if(!is_string($alias)||!preg_match('/^[a-f0-9]{64}$/D',$alias))throw new \DomainException('Invalid exclusion alias');
                foreach(array_unique($aliases) as $alias){$receipt=$queue->withdrawGameDataset($alias);$withdrawals++;$deleted+=$receipt['deleted_rows'];}
            }
            $start=$cursor;$inserted=0;$skipped=0;$pages=0;$more=true;
            while($more&&$pages<$maxPages){
                $pack=$fetch($cursor);
                if(!is_array($pack)||($pack['version']??null)!==GameDataset::VERSION||!is_array($pack['rows']??null)||!array_is_list($pack['rows'])
                    ||count($pack['rows'])>100||!is_int($pack['next_after']??null)||!is_bool($pack['has_more']??null)
                    ||($pack['rows']===[]?($pack['next_after']!==$cursor||$pack['has_more']):$pack['next_after']<=$cursor))
                    throw new \DomainException('Invalid source page');
                $receipt=$queue->importGameDataset($pack,true);
                $next=$pack['next_after'];
                if($next!==$cursor){
                    $n=self::q($db,'UPDATE wp_research_dataset_sync SET last_seq=? WHERE stream_id=? AND last_seq=?',[$next,$stream,$cursor])->rowCount();
                    if($n!==1)throw new \DomainException('Sync cursor conflict');
                }
                $cursor=$next;$pages++;$inserted+=$receipt['inserted'];$skipped+=$receipt['withdrawn_skipped'];$more=$pack['has_more'];
            }
            if(!$more)self::q($db,'UPDATE wp_research_dataset_sync SET last_success_epoch=? WHERE stream_id=?',[time(),$stream]);
            return ['status'=>$more?'backlog_remaining':'complete','from_seq'=>$start,'through_seq'=>$cursor,
                'pages'=>$pages,'inserted'=>$inserted,'withdrawn_skipped'=>$skipped,'has_more'=>$more,'exclusions_processed'=>$withdrawals,'excluded_rows_deleted'=>$deleted];
        }finally{self::q($db,'SELECT RELEASE_LOCK(?)',[$lock]);}
    }
}
