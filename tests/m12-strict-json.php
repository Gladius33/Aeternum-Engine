<?php
declare(strict_types=1);
use Aeternum\Core\{StrictJson,GamePackLoader,WorldPackRegistry};

check(StrictJson::decode('{"a":1,"b":[{"a":2},{"a":3}]}',true,8)['b'][1]['a']===3,
    'M12 independently repeated keys across distinct nested objects remain valid');
check(StrictJson::decode('["a",{"value":"quote: \\" and brace: }"}]',true,8)[1]['value']==='quote: " and brace: }',
    'M12 escaped strings and braces are not confused with JSON structure');
foreach (['{"a":1,"a":2}','{"a":1,"\u0061":2}','{"a":{"x":1,"x":2}}',
          '{"a":[{"x":1,"x":2}]}','[{"key":1},{"key":2,"key":3}]'] as $sample) {
    rejects(fn()=>StrictJson::decode($sample,true,8),JsonException::class,
        'M12 rejects ambiguous JSON object keys');
}
$verified=0;
for ($i=0;$i<128;$i++) {
    $name='field_'.$i;
    $key=json_encode($name,JSON_THROW_ON_ERROR);
    $candidate='{"outer":{"x":1,'.$key.':2,'.$key.':3}}';
    try { StrictJson::decode($candidate,false,8); }
    catch (JsonException) { $verified++; }
}
check($verified===128,'M12 bounded generated corpus rejects every duplicated nested JSON key');
$packBytes=file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json');
$ambiguous=str_replace('"engine_api": "1"','"engine_api": "1", "engine_api": "1"',$packBytes);
check($ambiguous!==$packBytes,'M12 pack fixture contains a top-level API declaration');
rejects(fn()=>GamePackLoader::load($ambiguous),JsonException::class,
    'M12 approved pack loading rejects ambiguous top-level rule identity');
rejects(fn()=>WorldPackRegistry::load('{"registry_api":"1","registry_api":"1","worlds":[]}',__DIR__.'/fixtures/m02-packs'),
    JsonException::class,'M12 world registry rejects ambiguous configuration');
