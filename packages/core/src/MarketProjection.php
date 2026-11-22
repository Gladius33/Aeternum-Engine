<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Public fields only; offer state and membership are read under the world lock. */
final class MarketProjection
{
    public static function validatePage(?string $after,int $limit): void
    {
        if ($limit<1 || $limit>100 || ($after!==null && !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$after))) {
            throw new \InvalidArgumentException('Invalid market page');
        }
    }

    public static function openOffers(array $state): array
    {
        $offers=[];
        foreach ($state as $key=>$value) {
            if ($value!=='open' || !preg_match('/^market:([A-Za-z0-9][A-Za-z0-9_-]{0,63}):status$/D',(string)$key,$m)) continue;
            $prefix='market:'.$m[1].':';
            $offer=['id'=>$m[1]];
            foreach (['seller','giveResource','wantResource'] as $field) {
                $v=$state[$prefix.$field]??null;
                if (!is_string($v) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D',$v)) throw new \LogicException('Invalid stored offer');
                $offer[$field]=$v;
            }
            foreach (['giveUnits','wantUnits'] as $field) {
                $v=$state[$prefix.$field]??null;
                if (!is_int($v) || $v<1 || $v>1000000000000) throw new \LogicException('Invalid stored offer');
                $offer[$field]=$v;
            }
            $offers[]=$offer;
        }
        usort($offers,static fn(array $a,array $b): int=>strcmp($a['id'],$b['id']));
        return $offers;
    }

    public static function page(array $offers,array $activeAccounts,?string $after=null,int $limit=50): array
    {
        self::validatePage($after,$limit);
        $active=[];
        foreach ($activeAccounts as $account) $active['a:'.$account]=true;
        $page=[];
        foreach ($offers as $offer) {
            if (($after!==null && strcmp($offer['id'],$after)<=0) || !isset($active['a:'.$offer['seller']])) continue;
            $page[]=$offer;
            if (count($page)>$limit) break;
        }
        $more=count($page)>$limit;
        if ($more) array_pop($page);
        return ['offers'=>$page,'nextAfter'=>$more?$page[count($page)-1]['id']:null];
    }
}
