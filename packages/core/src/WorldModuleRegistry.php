<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Separate operator-owned selection of reviewed modules; never executable configuration. */
final class WorldModuleRegistry
{
    public const MAX_BYTES=65536;

    private static function fields(mixed $value,array $expected): array
    {
        if (!$value instanceof \stdClass) throw new \InvalidArgumentException('Expected module registry object');
        $fields=get_object_vars($value);$keys=array_keys($fields);
        sort($keys,SORT_STRING);sort($expected,SORT_STRING);
        if ($keys!==$expected) throw new \InvalidArgumentException('Unexpected module registry fields');
        return $fields;
    }

    private static function digest(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D',$value)===1;
    }

    public static function loadFile(string $path,array $packs): WorldRuntime
    {
        if ($path==='' || strlen($path)>1024 || $path[0]!=='/' || str_contains($path,"\0")) {
            throw new \InvalidArgumentException('Module registry requires an absolute operator path');
        }
        $real=realpath($path);$public=realpath(__DIR__.'/../../../public');
        if ($real===false || !is_file($real) || ($public!==false && str_starts_with($real,$public.DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Missing or public module registry');
        }
        $data=file_get_contents($real,false,null,0,self::MAX_BYTES+1);
        if ($data===false) throw new \RuntimeException('Cannot read module registry');
        return self::load($data,$packs);
    }

    public static function load(string $json,array $packs): WorldRuntime
    {
        if ($json==='' || strlen($json)>self::MAX_BYTES) throw new \InvalidArgumentException('Invalid module registry size');
        $root=self::fields(StrictJson::decode($json,false,16),['module_api','worlds']);
        if ($root['module_api']!=='1' || !is_array($root['worlds']) || count($root['worlds'])>256) {
            throw new \InvalidArgumentException('Unsupported module registry');
        }
        $commands=[];$events=[];$expected=[];
        foreach ($root['worlds'] as $raw) {
            $entry=self::fields($raw,['world','pack_sha256','modules','rules_key']);
            $world=$entry['world'];
            if (!is_string($world) || isset($expected[$world]) || !isset($packs[$world])
                || !$packs[$world] instanceof GamePack || !self::digest($entry['pack_sha256'])
                || !self::digest($entry['rules_key']) || !is_array($entry['modules'])
                || $entry['modules']===[] || count($entry['modules'])>16) {
                throw new \InvalidArgumentException('Invalid or duplicate module world binding');
            }
            $pack=$packs[$world];
            if (!hash_equals($pack->digest,$entry['pack_sha256'])) throw new \DomainException('Module pack digest mismatch');
            $expected[$world]=$entry['rules_key'];$seen=[];
            foreach ($entry['modules'] as $rawModule) {
                $selection=self::fields($rawModule,['id','sha256']);
                $id=$selection['id'];
                if (!is_string($id) || isset($seen[$id]) || !self::digest($selection['sha256'])) {
                    throw new \InvalidArgumentException('Invalid or duplicate module selection');
                }
                $seen[$id]=true;
                $module=ReviewedModuleCatalog::module($id,$pack);
                if (!hash_equals($module['sha256'],$selection['sha256'])) throw new \DomainException('Reviewed module digest mismatch');
                $commands[$world]=array_merge($commands[$world]??[],$module['commands']);
                $events[$world]=array_merge($events[$world]??[],$module['events']);
            }
        }
        $runtime=new WorldRuntime($packs,$commands,$events);
        foreach ($expected as $world=>$key) {
            if (!hash_equals($runtime->rule($world)->key,$key)) throw new \DomainException('Effective rules key mismatch');
        }
        return $runtime;
    }
}
