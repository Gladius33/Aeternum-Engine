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

// Deterministic property corpus: escaped aliases must collide at any nesting level,
// whereas identical names in sibling objects are independent. No random seed or
// external fuzzer is required to reproduce a failing case in CI.
$aliases=[
    ['plain'=>'a','escaped'=>'\u0061'],
    ['plain'=>'slash/name','escaped'=>'slash\/name'],
    ['plain'=>'é','escaped'=>'\u00e9'],
    ['plain'=>'𝄞','escaped'=>'\ud834\udd1e'],
    ['plain'=>'quote"key','escaped'=>'quote\"key'],
    ['plain'=>'back\\slash','escaped'=>'back\\\\slash'],
];
$propertyCases=0;
foreach ($aliases as $alias) {
    $plain=json_encode($alias['plain'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $escaped='"'.$alias['escaped'].'"';
    // Check that the fixture itself represents the same decoded JSON key.
    check(json_decode($plain,true,8,JSON_THROW_ON_ERROR)===json_decode($escaped,true,8,JSON_THROW_ON_ERROR),
        'M12 escaped alias fixture decodes to identical key');
    foreach ([0,1,2,3] as $nesting) {
        $duplicate='{'.$plain.':1,'.$escaped.':2}';
        $distinct='[{'.$plain.':1},{'.$escaped.':2}]';
        for ($n=0;$n<$nesting;$n++) {
            $duplicate='{"container":['.$duplicate.']}';
            $distinct='{"container":['.$distinct.']}';
        }
        rejects(fn()=>StrictJson::decode($duplicate,true,16),JsonException::class,
            'M12 property corpus rejects escaped alias at every nesting level');
        check(StrictJson::decode($distinct,true,16)!==null,
            'M12 property corpus accepts repeated keys in distinct objects');
        $propertyCases+=2;
    }
}
check($propertyCases===48,'M12 deterministic Unicode/escape/nesting property corpus completed');
