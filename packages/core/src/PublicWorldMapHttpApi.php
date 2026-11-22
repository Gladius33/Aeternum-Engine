<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Public, bounded and metadata-only terrain discovery. Never selects actor
 * identities, territory owners, resources, plans, or private entity data.
 */
final class PublicWorldMapHttpApi
{
    /** @param array<string,mixed> $approvedWorlds */
    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
        private readonly array $approvedWorlds
    ) {}

    public function handle(string $method,string $path,string $peer): array
    {
        if (strlen($path)>512 || !preg_match(
            '~^/v1/public/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/map/territories(?:/([A-Za-z0-9][A-Za-z0-9_-]{0,63}))?$~D',
            $path,$match
        )) return [404,['error'=>'not_found']];
        if ($method!=='GET') return [405,['error'=>'method_not_allowed']];
        if (!array_key_exists($match[1],$this->approvedWorlds)) return [404,['error'=>'not_found']];
        if (strlen($peer)>128 || $peer==='') return [429,['error'=>'rate_limited']];
        if (!(new PdoRateLimiter($this->pdo,$this->clock))->admit(
            'public-map:'.hash('sha256',$peer),60,60
        )) return [429,['error'=>'rate_limited']];
        $s=$this->pdo->prepare('SELECT territory_id,location_id,kind FROM engine_territories
            WHERE world_id=? AND territory_id>?
            ORDER BY territory_id LIMIT 51');
        $s->execute([$match[1],$match[2]??'']);
        $rows=$s->fetchAll(PDO::FETCH_ASSOC);
        $more=count($rows)>50;
        if ($more) array_pop($rows);
        $items=[];
        foreach($rows as $row) {
            $items[]=['id'=>$row['territory_id'],'location'=>$row['location_id'],
                'kind'=>$row['kind']];
        }
        return [200,['data'=>[
            'territories'=>$items,
            'nextAfter'=>$more?$items[count($items)-1]['id']:null
        ]]];
    }
}
