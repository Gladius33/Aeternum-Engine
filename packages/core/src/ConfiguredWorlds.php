<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Operator-approved reference worlds. No untrusted pack paths or world creation. */
final class ConfiguredWorlds
{
    /** @return array<string,GamePack> */
    public static function load(): array
    {
        $packs=[];
        foreach (['AETERNUM_MEDIEVAL_WORLD'=>'reference-medieval','AETERNUM_INDUSTRIAL_WORLD'=>'reference-industrial'] as $key=>$packId) {
            $world=getenv($key);
            if ($world===false || $world==='') continue;
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world) || isset($packs[$world])) {
                throw new \RuntimeException('Invalid or duplicate configured world');
            }
            $json=file_get_contents(__DIR__.'/../../../games/'.$packId.'/manifest.json');
            if ($json===false) throw new \RuntimeException('Missing trusted game pack');
            $packs[$world]=GamePackLoader::load($json);
        }
        return $packs;
    }
}
