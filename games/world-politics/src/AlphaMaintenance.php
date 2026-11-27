<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Bounded deletion of expired credentials and conservative historical quota
 * buckets. Does not touch worlds, accounts, active tokens or audit history. */
final class AlphaMaintenance
{
    public static function prune(\PDO $db,int $now,int $limit=500):array
    {
        if($db->inTransaction()||$now<1000||$now>time()||$limit<1||$limit>1000)
            throw new \InvalidArgumentException('Invalid maintenance bounds');
        // Quota windows are 60 or 300 seconds. Since the original hashed
        // bucket does not expose its period, use the conservative 300s
        // cutoff for every row and keep two additional windows.
        $cutoff=intdiv($now,300)-2;
        $db->beginTransaction();
        try{
            $q=$db->prepare('DELETE FROM wp_sessions WHERE expires_at<=? ORDER BY expires_at,token_sha LIMIT '.$limit);
            $q->execute([$now]);$sessions=$q->rowCount();
            $q=$db->prepare('DELETE FROM wp_http_limits WHERE window_id<? ORDER BY window_id,bucket_sha LIMIT '.$limit);
            $q->execute([$cutoff]);$quotas=$q->rowCount();
            $db->commit();
            return ['expired_sessions_deleted'=>$sessions,'old_quota_buckets_deleted'=>$quotas,'limit_per_table'=>$limit,
                'more_may_remain'=>$sessions===$limit||$quotas===$limit];
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
