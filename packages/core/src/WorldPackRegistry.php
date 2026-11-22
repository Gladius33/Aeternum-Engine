<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Operator-owned, data-only map of worlds to immutable bundled game packs.
 * Nothing in a request can choose a filesystem path or a rules artifact.
 */
final class WorldPackRegistry
{
    public const MAX_BYTES = 65536;

    private static function fields(mixed $value, array $keys): array
    {
        if (!$value instanceof \stdClass) throw new \InvalidArgumentException('Expected registry object');
        $result=get_object_vars($value);
        $actual=array_keys($result);
        sort($actual,SORT_STRING);
        sort($keys,SORT_STRING);
        if ($actual!==$keys) throw new \InvalidArgumentException('Unexpected registry fields');
        return $result;
    }

    /** @return array<string,GamePack> */
    public static function loadFile(string $path,string $packsRoot): array
    {
        if ($path==='' || strlen($path)>1024 || $path[0]!=='/' || str_contains($path,"\0")) {
            throw new \InvalidArgumentException('Registry requires an absolute operator-controlled file path');
        }
        $real=realpath($path);
        $public=realpath(__DIR__.'/../../../public');
        if ($real===false || !is_file($real) || ($public!==false && str_starts_with($real,$public.DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Missing or public registry configuration');
        }
        $data=file_get_contents($real,false,null,0,self::MAX_BYTES+1);
        if ($data===false) throw new \RuntimeException('Unable to read world registry');
        return self::load($data,$packsRoot);
    }

    /** @return array<string,GamePack> */
    public static function load(string $json,string $packsRoot): array
    {
        if ($json==='' || strlen($json)>self::MAX_BYTES) throw new \InvalidArgumentException('Invalid registry size');
        $root=self::fields(json_decode($json,false,16,JSON_THROW_ON_ERROR),['registry_api','worlds']);
        if ($root['registry_api']!=='1' || !is_array($root['worlds'])
            || $root['worlds']===[] || count($root['worlds'])>256) {
            throw new \InvalidArgumentException('Unsupported world registry');
        }
        $rootDir=realpath($packsRoot);
        if ($rootDir===false || !is_dir($rootDir)) throw new \RuntimeException('Missing trusted packs directory');
        $worlds=[];
        $cache=[];
        foreach ($root['worlds'] as $raw) {
            $entry=self::fields($raw,['world','pack','version','sha256']);
            [$world,$packId,$version,$digest]=[$entry['world'],$entry['pack'],$entry['version'],$entry['sha256']];
            if (!is_string($world) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world)
                || !is_string($packId) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/D',$packId)
                || !is_string($version) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.+-]{0,63}$/D',$version)
                || !is_string($digest) || !preg_match('/^[0-9a-f]{64}$/D',$digest)
                || isset($worlds[$world])) {
                throw new \InvalidArgumentException('Invalid or duplicate world pack binding');
            }
            // Immutable version folders permit two revisions of one pack to
            // serve different pinned worlds without mutating historical bytes.
            // The original unversioned manifest is preserved for API-1 worlds.
            // Neither filename nor root can originate from a request.
            $packDir=$rootDir.DIRECTORY_SEPARATOR.$packId;
            $versioned=$packDir.DIRECTORY_SEPARATOR.'versions'.DIRECTORY_SEPARATOR.$version.DIRECTORY_SEPARATOR.'manifest.json';
            $expected=is_dir(dirname($versioned)) || is_link(dirname($versioned))
                ? $versioned
                : $packDir.DIRECTORY_SEPARATOR.'manifest.json';
            $resolved=realpath($expected);
            if ($resolved===false || $resolved!==$expected || !is_file($resolved)) {
                throw new \RuntimeException('Unapproved or missing pack artifact');
            }
            $identity=$packId.'@'.$version.'#'.$digest;
            if (!isset($cache[$identity])) {
                $bytes=file_get_contents($resolved,false,null,0,GamePackLoader::MAX_BYTES+1);
                if ($bytes===false || !hash_equals($digest,hash('sha256',$bytes))) {
                    throw new \DomainException('Pack digest mismatch');
                }
                $pack=GamePackLoader::load($bytes);
                if ($pack->id!==$packId || $pack->version!==$version) {
                    throw new \DomainException('Pack identity or version mismatch');
                }
                $cache[$identity]=$pack;
            }
            $worlds[$world]=$cache[$identity];
        }
        return $worlds;
    }
}
