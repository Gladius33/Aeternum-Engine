<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Deterministic alert policy for private CLI monitoring only. No external I/O. */
final class OperationsAlerts
{
    public function evaluate(array $snapshot,int $dueLimit=1000,int $ageLimit=900,int $outboxLimit=1000): array
    {
        if (($snapshot['schemaVersion']??null)!==1 || !is_array($snapshot['worlds']??null)
            || $dueLimit<1 || $ageLimit<1 || $outboxLimit<1)
            throw new \InvalidArgumentException('Invalid operational alert input');
        $alerts=[];
        if (($snapshot['migrations']['verified']??false)!==true)
            $alerts[]=['code'=>'schema_not_verified','world'=>null];
        if (($snapshot['healthy']??false)!==true
            && ($snapshot['migrations']['verified']??false)===true)
            $alerts[]=['code'=>'operations_unhealthy','world'=>null];
        if (($snapshot['sessions']['capped']??false)===true)
            $alerts[]=['code'=>'session_counter_capped','world'=>null];
        foreach ($snapshot['worlds'] as $row) {
            if (!is_array($row) || !is_string($row['world']??null)
                || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$row['world']))
                throw new \InvalidArgumentException('Invalid monitored world');
            $world=$row['world'];
            if (($row['initialized']??false)!==true) {
                $alerts[]=['code'=>'world_uninitialized','world'=>$world];
                continue;
            }
            if (($row['dueEventsCapped']??false)===true ||
                (int)($row['dueEvents']??0)>=$dueLimit)
                $alerts[]=['code'=>'event_backlog','world'=>$world];
            if ($row['oldestDueSeconds']!==null &&
                (int)$row['oldestDueSeconds'] >= $ageLimit)
                $alerts[]=['code'=>'event_lag','world'=>$world];
            if (($row['outboxPendingCapped']??false)===true ||
                (int)($row['outboxPending']??0)>=$outboxLimit)
                $alerts[]=['code'=>'outbox_backlog','world'=>$world];
        }
        return ['schemaVersion'=>1,'requiresAttention'=>$alerts!==[],'alerts'=>$alerts];
    }
}
