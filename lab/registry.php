<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||$argc!==2||$argv[1][0]!=='/')exit(2);
$root=dirname(__DIR__);
$worlds=[];
foreach([
    ['lab-medieval','reference-medieval','0.3.0'],
    ['lab-industrial','reference-industrial','0.3.0'],
    ['lab-guild','reference-guild','0.1.0'],
] as [$world,$pack,$version]){
    $versioned="$root/games/$pack/versions/$version/manifest.json";
    $path=is_file($versioned)?$versioned:"$root/games/$pack/manifest.json";
    $manifest=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    if($manifest['id']!==$pack||$manifest['version']!==$version)throw new RuntimeException('Unexpected manifest identity');
    $worlds[]=['world'=>$world,'pack'=>$pack,'version'=>$version,'sha256'=>hash_file('sha256',$path)];
}
file_put_contents($argv[1],json_encode(['registry_api'=>'1','worlds'=>$worlds],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL);
