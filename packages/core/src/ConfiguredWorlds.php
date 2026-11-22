<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Operator-approved worlds; all public/CLI entrypoints share one registry. */
final class ConfiguredWorlds
{
    /** @return array<string,GamePack> */
    public static function load(): array
    {
        $file=getenv('AETERNUM_WORLD_REGISTRY');
        $legacy=[
            'AETERNUM_MEDIEVAL_WORLD'=>'reference-medieval',
            'AETERNUM_INDUSTRIAL_WORLD'=>'reference-industrial',
        ];
        if ($file!==false) {
            foreach (array_keys($legacy) as $name) {
                if (($value=getenv($name))!==false && $value!=='') {
                    throw new \RuntimeException('World registry and legacy bindings must not be mixed');
                }
            }
            return WorldPackRegistry::loadFile($file,__DIR__.'/../../../games');
        }

        // Compatibility for existing pinned reference worlds. They retain the
        // exact original manifest bytes and rules keys until explicitly migrated.
        $worlds=[];
        foreach ($legacy as $env=>$id) {
            $world=getenv($env);
            if ($world===false || $world==='') continue;
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world) || isset($worlds[$world])) {
                throw new \RuntimeException('Invalid or duplicate configured world');
            }
            $json=file_get_contents(__DIR__.'/../../../games/'.$id.'/manifest.json');
            if ($json===false) throw new \RuntimeException('Missing trusted game pack');
            $pack=GamePackLoader::load($json);
            if ($pack->id!==$id) throw new \RuntimeException('Legacy pack identity mismatch');
            $worlds[$world]=$pack;
        }
        return $worlds;
    }
}
