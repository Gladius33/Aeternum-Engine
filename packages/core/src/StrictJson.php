<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Parse untrusted JSON with strict duplicate-key rejection.
 * json_decode validates all JSON grammar/depth/UTF-8 before this bounded lexical
 * pass; escaped key spellings are decoded so "\u0061" and "a" cannot coexist.
 */
final class StrictJson
{
    public static function decode(string $json, bool $associative=false, int $depth=8): mixed
    {
        $decoded=json_decode($json,$associative,$depth,JSON_THROW_ON_ERROR);
        $frames=[];
        $length=strlen($json);
        for ($i=0;$i<$length;$i++) {
            $char=$json[$i];
            if ($char==='"') {
                $from=$i;
                $i++;
                while ($i<$length) {
                    if ($json[$i]==='\\') {
                        $i+=2;
                        continue;
                    }
                    if ($json[$i]==='"') break;
                    $i++;
                }
                $last=count($frames)-1;
                if ($last>=0 && $frames[$last]['object'] && $frames[$last]['expect_key']) {
                    $key=json_decode(substr($json,$from,$i-$from+1),true,2,JSON_THROW_ON_ERROR);
                    // Use an exact string-keyed lookup, including escaped Unicode aliases.
                    $keyId=bin2hex($key);
                    if (isset($frames[$last]['keys'][$keyId])) {
                        throw new \JsonException('Duplicate JSON object key');
                    }
                    $frames[$last]['keys'][$keyId]=true;
                    $frames[$last]['expect_key']=false;
                }
                continue;
            }
            if ($char==='{' || $char==='[') {
                $frames[]=['object'=>$char==='{','expect_key'=>$char==='{','keys'=>[]];
                continue;
            }
            if ($char==='}' || $char===']') {
                array_pop($frames);
                continue;
            }
            if ($char===',' && $frames!==[]) {
                $last=count($frames)-1;
                if ($frames[$last]['object']) $frames[$last]['expect_key']=true;
            }
        }
        return $decoded;
    }
}
