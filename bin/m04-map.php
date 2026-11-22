<?php
declare(strict_types=1);
use Aeternum\Core\{ConfiguredWorlds,PdoEntities,PdoTopology,PdoTerritories};
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    require __DIR__.'/../vendor/autoload.php';
    if ($argc<4 || $argc>8 || !isset(ConfiguredWorlds::load()[$argv[1]])) {
        throw new InvalidArgumentException('Usage: php bin/m04-map.php APPROVED_WORLD location|edge|territory|owner|inspect|building-type|foundation|building-inspect ...');
    }
    $world=$argv[1];$action=$argv[2];
    $dsn=getenv('AETERNUM_DSN');
    if (!is_string($dsn) || $dsn==='') throw new RuntimeException('Database configuration unavailable');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER') ?: '',getenv('AETERNUM_DB_PASSWORD') ?: '',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    $output=['status'=>'ok','action'=>$action,'world'=>$world];
    switch ($action) {
        case 'location':
            if ($argc!==4) throw new InvalidArgumentException('Expected LOCATION');
            (new PdoEntities($pdo,$world))->addLocation($argv[3]);
            break;
        case 'edge':
            if ($argc!==6 || !preg_match('/^[1-9][0-9]{0,6}$/D',$argv[5])
                || (int)$argv[5]>1000000) throw new InvalidArgumentException('Expected FROM TO POSITIVE_COST');
            (new PdoTopology($pdo,$world))->connect($argv[3],$argv[4],(int)$argv[5]);
            break;
        case 'territory':
            if ($argc!==6) throw new InvalidArgumentException('Expected TERRITORY LOCATION KIND');
            (new PdoTerritories($pdo,$world))->define($argv[3],$argv[4],$argv[5]);
            break;
        case 'owner':
            if ($argc!==5) throw new InvalidArgumentException('Expected TERRITORY EXISTING_ACTIVE_ACTOR');
            (new PdoTerritories($pdo,$world))->assign($argv[3],$argv[4]);
            break;
        case 'building-type':
            if ($argc!==4) throw new InvalidArgumentException('Expected PINNED_BUILDING_TYPE');
            $pack=ConfiguredWorlds::load()[$world];
            $stages=$pack->buildings[$argv[3]]??null;
            if ($stages===null) throw new DomainException('Unknown pinned building');
            (new Aeternum\Core\PdoBuildings($pdo,$world))->define($argv[3],$stages);
            break;
        case 'foundation':
            if ($argc!==8) throw new InvalidArgumentException('Expected BUILDING TERRITORY ENTITY ACTOR TYPE');
            $pack=ConfiguredWorlds::load()[$world];
            if (!isset($pack->buildings[$argv[7]])) throw new DomainException('Building not approved for world');
            (new Aeternum\Core\PdoBuildings($pdo,$world))->establish(
                $argv[3],$argv[4],$argv[5],$argv[6],$argv[7]);
            break;
        case 'building-inspect':
            if ($argc!==5) throw new InvalidArgumentException('Expected ACTOR OWN_BUILDING');
            $output['data']=(new Aeternum\Core\PdoBuildings($pdo,$world))->own($argv[3],$argv[4]);
            break;
        case 'inspect':
            if ($argc!==4 && $argc!==5) throw new InvalidArgumentException('Expected ACTOR [AFTER]');
            $output['data']=(new PdoTerritories($pdo,$world))->pageFor($argv[3],50,$argv[4]??null);
            break;
        default:
            throw new InvalidArgumentException('Unknown map operation');
    }
    echo json_encode($output,JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Map operation refused. Verify world approval, inputs, database and membership.\n");
    exit(1);
}
