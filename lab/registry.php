<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PackDevelopment,WorldModuleRegistry};
if(PHP_SAPI!=='cli'||$argc!==3||$argv[1][0]!=='/'||$argv[2][0]!=='/')exit(2);
$root=dirname(__DIR__);require $root.'/vendor/autoload.php';
$worlds=[];$modules=[];$packs=[];
foreach([
    ['lab-medieval-f04','reference-medieval','0.4.0',['campaign-v1']],
    ['lab-industrial-f04','reference-industrial','0.4.0',['campaign-v1']],
    ['lab-guild-f04','reference-guild','0.5.0',['campaign-v1','guild-challenge-v1','bilateral-truce-v1']],
] as [$world,$pack,$version,$ids]){
    $path="$root/games/$pack/versions/$version/manifest.json";
    $manifest=GamePackLoader::load(file_get_contents($path));
    if($manifest->id!==$pack||$manifest->version!==$version)throw new RuntimeException('Unexpected manifest identity');
    $packs[$world]=$manifest;
    $worlds[]=['world'=>$world,'pack'=>$pack,'version'=>$version,'sha256'=>$manifest->digest];
    $entry=PackDevelopment::inspect($manifest,$ids);
    unset($entry['pack'],$entry['version']);$modules[]=['world'=>$world]+$entry;
}
$json=json_encode(['module_api'=>'1','worlds'=>$modules],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
WorldModuleRegistry::load($json,$packs);
file_put_contents($argv[1],json_encode(['registry_api'=>'1','worlds'=>$worlds],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL);
file_put_contents($argv[2],$json.PHP_EOL);
