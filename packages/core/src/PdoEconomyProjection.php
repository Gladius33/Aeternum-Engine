<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Transactional, indexed SQL projection of immutable world replay state.
 * Called ONLY while PdoScheduler owns the world row FOR UPDATE. All writes,
 * command receipts, state digests and ledger movements share one transaction.
 * A new database may be initialized from a populated legacy JSON world once.
 */
final class PdoEconomyProjection
{
    public function __construct(private readonly PDO $pdo,private readonly string $worldId) {}

    private function sql(string $query,array $values): void
    {
        $q=$this->pdo->prepare($query);
        $q->execute($values);
    }

    /** Called with the world row lock held. Failure rolls back entire backfill. */
    public function bootstrap(array $state): void
    {
        $balances=[]; $offers=[]; $refunds=[];
        foreach ($state as $key=>$value) {
            if (preg_match('/^econ:([A-Za-z0-9_-]{1,64}):([A-Za-z0-9_-]{1,64}):stock$/D',$key,$m)) {
                $balances[$m[1].':'.$m[2]]=[$m[1],$m[2]];
            } elseif (preg_match('/^market:([A-Za-z0-9_-]{1,64}):status$/D',$key,$m)) {
                $offers[$m[1]]=$m[1];
            } elseif (preg_match('/^market:refund:([A-Za-z0-9_-]{1,64}):([A-Za-z0-9_-]{1,64})$/D',$key,$m)) {
                $refunds[$m[1].':'.$m[2]]=[$m[1],$m[2]];
            }
        }
        foreach ($balances as [$account,$resource]) $this->balance($state,$account,$resource);
        foreach ($offers as $id) $this->offer($state,$id);
        foreach ($refunds as [$account,$resource]) $this->refund($state,$account,$resource);
    }

    /** The O(n) diff may be prepared before entering the world write lock. */
    public static function deltaKeys(array $before,array $after): array
    {
        $changed=[];
        foreach ($before as $key=>$value) {
            if (!array_key_exists($key,$after) || $after[$key]!==$value) $changed[]=$key;
        }
        foreach ($after as $key=>$value) {
            if (!array_key_exists($key,$before)) $changed[]=$key;
        }
        return $changed;
    }

    /** Apply changed keys without scanning and rewriting every SQL row. */
    public function sync(array $before,array $after,?array $changedKeys=null): void
    {
        $keys=$changedKeys??self::deltaKeys($before,$after);
        $balances=[]; $offers=[]; $refunds=[];
        foreach ($keys as $key) {
            if (($before[$key]??null)===($after[$key]??null) && array_key_exists($key,$before)===array_key_exists($key,$after)) continue;
            if (preg_match('/^econ:([A-Za-z0-9_-]{1,64}):([A-Za-z0-9_-]{1,64}):(stock|at|remainder|level|pending)$/D',$key,$m)) {
                $balances[$m[1].':'.$m[2]]=[$m[1],$m[2]];
            } elseif (preg_match('/^market:([A-Za-z0-9_-]{1,64}):(status|seller|giveResource|giveUnits|wantResource|wantUnits|buyer|expiresAt)$/D',$key,$m)) {
                $offers[$m[1]]=$m[1];
            } elseif (preg_match('/^market:refund:([A-Za-z0-9_-]{1,64}):([A-Za-z0-9_-]{1,64})$/D',$key,$m)) {
                $refunds[$m[1].':'.$m[2]]=[$m[1],$m[2]];
            }
        }
        foreach ($balances as [$account,$resource]) $this->balance($after,$account,$resource);
        foreach ($offers as $id) $this->offer($after,$id);
        foreach ($refunds as [$account,$resource]) $this->refund($after,$account,$resource);
    }

    private function balance(array $state,string $account,string $resource): void
    {
        $key="econ:$account:$resource:";
        $stock=$state[$key.'stock']??null;
        $at=$state[$key.'at']??null;
        $rem=$state[$key.'remainder']??null;
        $level=$state[$key.'level']??0;
        $pending=$state[$key.'pending']??null;
        if (!is_int($stock) || $stock<0 || $stock>ProductionState::MAX_UNITS
            || !is_int($at) || $at<0 || !is_int($rem) || $rem<0
            || !is_int($level) || $level<0 || $level>100
            || ($pending!==null && (!is_string($pending) || strlen($pending)>128))) {
            throw new \UnexpectedValueException('Invalid economy projection source');
        }
        $this->sql('INSERT INTO engine_economy_balances
          (world_id,account_id,resource_id,stock_units,settled_at,remainder,level_no,pending_event_id)
          VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
          stock_units=VALUES(stock_units),settled_at=VALUES(settled_at),remainder=VALUES(remainder),
          level_no=VALUES(level_no),pending_event_id=VALUES(pending_event_id)',
          [$this->worldId,$account,$resource,$stock,$at,$rem,$level,$pending]);
    }

    private function offer(array $state,string $id): void
    {
        $key="market:$id:";
        $status=$state[$key.'status']??null;
        $seller=$state[$key.'seller']??null;
        $give=$state[$key.'giveResource']??null; $want=$state[$key.'wantResource']??null;
        $giveUnits=$state[$key.'giveUnits']??null; $wantUnits=$state[$key.'wantUnits']??null;
        $buyer=$state[$key.'buyer']??null; $expiry=$state[$key.'expiresAt']??null;
        $validId=static fn(mixed $id):bool=>is_string($id) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$id)===1;
        if (!in_array($status,['open','filled','cancelled','expired'],true)
            || !$validId($seller) || !$validId($give) || !$validId($want)
            || !is_int($giveUnits) || $giveUnits<1 || $giveUnits>ProductionState::MAX_UNITS
            || !is_int($wantUnits) || $wantUnits<1 || $wantUnits>ProductionState::MAX_UNITS
            || ($buyer!==null && !$validId($buyer))
            || ($expiry!==null && (!is_int($expiry) || $expiry<0))) {
            throw new \UnexpectedValueException('Invalid market projection source');
        }
        $this->sql('INSERT INTO engine_market_offers
          (world_id,offer_id,seller_id,give_resource,give_units,want_resource,want_units,status,buyer_id,expires_at)
          VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
          seller_id=VALUES(seller_id),give_resource=VALUES(give_resource),give_units=VALUES(give_units),
          want_resource=VALUES(want_resource),want_units=VALUES(want_units),status=VALUES(status),
          buyer_id=VALUES(buyer_id),expires_at=VALUES(expires_at)',
          [$this->worldId,$id,$seller,$give,$giveUnits,$want,$wantUnits,$status,$buyer,$expiry]);
    }

    private function refund(array $state,string $account,string $resource): void
    {
        $key="market:refund:$account:$resource";
        $units=$state[$key]??null;
        if ($units===null) {
            $this->sql('DELETE FROM engine_market_refunds WHERE world_id=? AND account_id=? AND resource_id=?',[$this->worldId,$account,$resource]);
        } elseif (is_int($units) && $units>0) {
            $this->sql('INSERT INTO engine_market_refunds (world_id,account_id,resource_id,claimable_units)
                VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE claimable_units=VALUES(claimable_units)',
                [$this->worldId,$account,$resource,$units]);
        } else {
            throw new \UnexpectedValueException('Invalid refundable escrow');
        }
    }

    /**
     * Full operator-only audit against replay-canonical JSON.
     * This O(world size) verification is deliberately NOT on HTTP reads.
     * It detects SQL projection drift, orphan rows and ledger-view divergence.
     */
    public function assertConsistent(array $state): array
    {
        $expectedBalances=0;$expectedOffers=0;$expectedRefunds=0;
        foreach ($state as $key=>$value) {
            if (preg_match('/^econ:([A-Za-z0-9_-]{1,64}):([A-Za-z0-9_-]{1,64}):stock$/D',$key)) $expectedBalances++;
            elseif (preg_match('/^market:([A-Za-z0-9_-]{1,64}):status$/D',$key)) $expectedOffers++;
            elseif (preg_match('/^market:refund:([A-Za-z0-9_-]{1,64}):([A-Za-z0-9_-]{1,64})$/D',$key)) $expectedRefunds++;
        }
        $q=$this->pdo->prepare('SELECT account_id,resource_id,stock_units,settled_at,remainder,level_no,pending_event_id
            FROM engine_economy_balances WHERE world_id=?');
        $q->execute([$this->worldId]);$found=0;
        while ($row=$q->fetch(PDO::FETCH_ASSOC)) {
            $prefix='econ:'.$row['account_id'].':'.$row['resource_id'].':';
            if (($state[$prefix.'stock']??null)!==(int)$row['stock_units']
                || ($state[$prefix.'at']??null)!==(int)$row['settled_at']
                || ($state[$prefix.'remainder']??null)!==(int)$row['remainder']
                || ($state[$prefix.'level']??0)!==(int)$row['level_no']
                || ($state[$prefix.'pending']??null)!==$row['pending_event_id']) {
                throw new \UnexpectedValueException('Relational economy balance divergence');
            }
            $found++;
        }
        if ($found!==$expectedBalances) throw new \UnexpectedValueException('Relational balance row count mismatch');
        $q=$this->pdo->prepare('SELECT offer_id,seller_id,give_resource,give_units,want_resource,want_units,status,buyer_id,expires_at
            FROM engine_market_offers WHERE world_id=?');
        $q->execute([$this->worldId]);$found=0;
        while ($row=$q->fetch(PDO::FETCH_ASSOC)) {
            $prefix='market:'.$row['offer_id'].':';
            if (($state[$prefix.'seller']??null)!==$row['seller_id']
                || ($state[$prefix.'giveResource']??null)!==$row['give_resource']
                || ($state[$prefix.'giveUnits']??null)!==(int)$row['give_units']
                || ($state[$prefix.'wantResource']??null)!==$row['want_resource']
                || ($state[$prefix.'wantUnits']??null)!==(int)$row['want_units']
                || ($state[$prefix.'status']??null)!==$row['status']
                || ($state[$prefix.'buyer']??null)!==$row['buyer_id']
                || ($state[$prefix.'expiresAt']??null)!==($row['expires_at']===null?null:(int)$row['expires_at'])) {
                throw new \UnexpectedValueException('Relational market offer divergence');
            }
            $found++;
        }
        if ($found!==$expectedOffers) throw new \UnexpectedValueException('Relational offer row count mismatch');
        $q=$this->pdo->prepare('SELECT account_id,resource_id,claimable_units
            FROM engine_market_refunds WHERE world_id=?');
        $q->execute([$this->worldId]);$found=0;
        while ($row=$q->fetch(PDO::FETCH_ASSOC)) {
            $key='market:refund:'.$row['account_id'].':'.$row['resource_id'];
            if (($state[$key]??null)!==(int)$row['claimable_units']) {
                throw new \UnexpectedValueException('Relational escrow refund divergence');
            }
            $found++;
        }
        if ($found!==$expectedRefunds) throw new \UnexpectedValueException('Relational escrow refund count mismatch');
        return ['balances'=>$expectedBalances,'offers'=>$expectedOffers,'refunds'=>$expectedRefunds];
    }

    /** Read-only per-page SQL listing. Caller enforces actor's active membership. */
    public function marketPage(?string $after,int $limit,int $now,?string $give=null,?string $want=null): array
    {
        MarketProjection::validatePage($after,$limit);
        MarketProjection::validateResourceFilter($give);
        MarketProjection::validateResourceFilter($want);
        $q=$this->pdo->prepare('SELECT o.offer_id, o.seller_id, o.give_resource, o.want_resource,
                o.give_units, o.want_units
            FROM engine_market_offers o
            JOIN engine_account_owners own ON own.world_id=o.world_id AND own.account_id=o.seller_id
            JOIN engine_memberships m ON m.world_id=own.world_id AND m.actor_id=own.actor_id AND m.active=1
            WHERE o.world_id=? AND o.status=? AND o.offer_id>?
              AND (o.expires_at IS NULL OR o.expires_at>?)
              AND (? IS NULL OR o.give_resource=?) AND (? IS NULL OR o.want_resource=?)
            ORDER BY o.offer_id LIMIT '.($limit+1));
        $q->execute([$this->worldId,'open',$after??'',$now,$give,$give,$want,$want]);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $more=count($rows)>$limit;
        if ($more) array_pop($rows);
        $offers=[];
        foreach ($rows as $r) {
            $offers[]=[
                'id'=>$r['offer_id'],'seller'=>$r['seller_id'],'giveResource'=>$r['give_resource'],
                'wantResource'=>$r['want_resource'],'giveUnits'=>(int)$r['give_units'],'wantUnits'=>(int)$r['want_units']
            ];
        }
        return ['offers'=>$offers,'nextAfter'=>$more?$offers[count($offers)-1]['id']:null];
    }
    /**
     * Committed private history, including closed and expired escrows.
     * Membership and account ownership are checked by PdoScheduler.
     */
    public function historyPage(string $account,?string $after=null,int $limit=50): array
    {
        MarketProjection::validatePage($after,$limit);
        MarketProjection::validateResourceFilter($account);
        $q=$this->pdo->prepare('SELECT offer_id,seller_id,buyer_id,status,
                give_resource,want_resource,give_units,want_units
            FROM engine_market_offers
            WHERE world_id=? AND status IN (?,?,?) AND (seller_id=? OR buyer_id=?)
              AND offer_id>?
            ORDER BY offer_id LIMIT '.($limit+1));
        $q->execute([$this->worldId,'filled','cancelled','expired',$account,$account,$after??'']);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $more=count($rows)>$limit;
        if ($more) array_pop($rows);
        $history=[];
        foreach ($rows as $row) {
            $history[]=[
                'id'=>$row['offer_id'],'seller'=>$row['seller_id'],'buyer'=>$row['buyer_id'],
                'status'=>$row['status'],'giveResource'=>$row['give_resource'],
                'wantResource'=>$row['want_resource'],'giveUnits'=>(int)$row['give_units'],
                'wantUnits'=>(int)$row['want_units']
            ];
        }
        return ['transactions'=>$history,'nextAfter'=>$more?$history[count($history)-1]['id']:null];
    }
}
