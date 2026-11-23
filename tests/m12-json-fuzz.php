<?php
declare(strict_types=1);
// M12: deterministic, repeatable parser fuzzing with no database or network.
require_once __DIR__.'/../packages/core/src/StrictJson.php';
use Aeternum\Core\StrictJson;
$seed=0x12A37E;
$next=static function(int $max) use (&$seed): int {
    $seed=(($seed*1103515245+12345)&0x7fffffff);
    return $seed%$max;
};
$assert=static function(bool $ok,string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$opts=JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION;
$keys=['a','b','zero','slash/name','quote"key','back\\slash','é','𝄞','__proto__','0',"line\nkey"];
$scalars=[null,true,false,0,17,-999,1.25,-0.5,'',"hello\\\" world",'é','𝄞',"null\x00byte"];
$build=null;
$build=static function(int $depth) use (&$build,$next,$keys,$scalars,$opts): string {
    if ($depth>=5 || $next(4)===0) return json_encode($scalars[$next(count($scalars))],$opts);
    if ($next(2)===0) {
        $items=[];
        for ($n=$next(5);$n>0;$n--) $items[]=$build($depth+1);
        return '['.implode(',',$items).']';
    }
    $available=$keys;
    $parts=[];
    for ($n=$next(6);$n>0;$n--) {
        $index=$next(count($available));
        $key=array_splice($available,$index,1)[0];
        $parts[]=json_encode($key,$opts).':'.$build($depth+1);
    }
    return '{'.implode(',',$parts).'}';
};
$valid=0;
for ($i=0;$i<512;$i++) {
    $document=$build(0);
    $assert(StrictJson::decode($document,false,32)==json_decode($document,false,32,JSON_THROW_ON_ERROR),
        'Object decode drift at case '.$i);
    $assert(StrictJson::decode($document,true,32)===json_decode($document,true,32,JSON_THROW_ON_ERROR),
        'Associative decode drift at case '.$i);
    $valid++;
}
$aliases=[
    ['a','\u0061'],['slash/name','slash\/name'],['é','\u00e9'],
    ['𝄞','\ud834\udd1e'],['quote"key','quote\"key'],['back\\slash','back\\\\slash'],
    ['0','\u0030'],["line\nkey",'line\nkey']
];
$duplicateRejected=0;
$siblingValid=0;
for ($i=0;$i<512;$i++) {
    [$plain,$encoded]=$aliases[$next(count($aliases))];
    $normal=json_encode($plain,$opts);
    $escaped='"'.$encoded.'"';
    $assert(json_decode($normal,true,8,JSON_THROW_ON_ERROR)===json_decode($escaped,true,8,JSON_THROW_ON_ERROR),
        'Invalid alias fixture at case '.$i);
    $duplicate='{'.$normal.':1,'.$escaped.':2}';
    $independent='[{'.$normal.':1},{'.$escaped.':2}]';
    for ($n=$next(8);$n>0;$n--) {
        if ($next(2)===0) {
            $suffix=','.json_encode('literal,colon:{}\"',$opts).',0]';
            $duplicate='['.$duplicate.$suffix;
            $independent='['.$independent.$suffix;
        } else {
            $duplicate='{"outer":'.$duplicate.',"safe":true}';
            $independent='{"outer":'.$independent.',"safe":true}';
        }
    }
    foreach ([false,true] as $assoc) {
        try {
            StrictJson::decode($duplicate,$assoc,32);
            throw new RuntimeException('Duplicate accepted at case '.$i);
        } catch (JsonException $expected) { $duplicateRejected++; }
        $assert(StrictJson::decode($independent,$assoc,32)==json_decode($independent,$assoc,32,JSON_THROW_ON_ERROR),
            'Independent sibling keys rejected at case '.$i);
        $siblingValid++;
    }
}
$invalid=[
    '{"a":1,}', '[1,]', '{"a":}', '{"a" 1}', '"\uD800"',
    '"\uDC00"', "\xC3\x28", '{"a": /*comment*/ 1}', '{"a": NaN}',
    '[true false]', '{"a":"unterminated}', '"bad'."\x00".'char"',
    str_repeat('[',33).'0'.str_repeat(']',33)
];
foreach ($invalid as $case=>$document) {
    try {
        StrictJson::decode($document,false,32);
        throw new RuntimeException('Invalid JSON accepted at case '.$case);
    } catch (JsonException $expected) {}
}
$long='{"payload":'.json_encode(str_repeat('\\\"{}:,',4096),$opts).',"ok":true}';
$assert(StrictJson::decode($long,false,8)==json_decode($long,false,8,JSON_THROW_ON_ERROR),
    'Long escaped string scanner drift');
echo "PASS M12 deterministic JSON fuzz seed=0x12A37E valid=$valid duplicate_rejected=$duplicateRejected sibling_valid=$siblingValid invalid=".
     count($invalid)." escaped_long=1\n";
