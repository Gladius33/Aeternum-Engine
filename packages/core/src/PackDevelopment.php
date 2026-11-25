<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Offline developer tools. A validation report never authorizes a runtime. */
final class PackDevelopment
{
    public static function scaffold(string $id,string $version,string $name): string
    {
        $json=json_encode([
            'engine_api'=>GamePackLoader::ENGINE_API,'id'=>$id,'version'=>$version,'name'=>$name,
            'resources'=>[['id'=>'supplies','initial_stock'=>10,'capacity'=>100,'amount'=>1,'interval_seconds'=>60]],
            'upgrades'=>[],
            'components'=>[['id'=>'outpost','fields'=>['owner'=>'string','level'=>'integer']]],
            'actions'=>[],'event_hooks'=>[]
        ],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
        GamePackLoader::load($json);
        return $json;
    }

    public static function inspect(GamePack $pack,array $ids=[]): array
    {
        if (!array_is_list($ids) || count($ids)>16) throw new \InvalidArgumentException('Invalid module list');
        $commands=[];$events=[];$modules=[];$seen=[];
        foreach ($ids as $id) {
            if (!is_string($id) || isset($seen[$id])) throw new \InvalidArgumentException('Invalid or duplicate module');
            $seen[$id]=true;
            $module=ReviewedModuleCatalog::module($id,$pack);
            $commands=array_merge($commands,$module['commands']);
            $events=array_merge($events,$module['events']);
            $modules[]=['id'=>$id,'sha256'=>$module['sha256']];
        }
        $rules=$pack->rules($commands,$events);
        return ['pack'=>$pack->id,'version'=>$pack->version,'pack_sha256'=>$pack->digest,
            'rules_key'=>$rules->key,'modules'=>$modules];
    }

    public static function upgradePlan(GamePack $source,GamePack $target,array $sourceModules=[],array $targetModules=[]): array
    {
        if ($source->id!==$target->id) throw new \InvalidArgumentException('Cannot upgrade to another pack identity');
        if ($source->version===$target->version && !hash_equals($source->digest,$target->digest))
            throw new \DomainException('Immutable version changed');
        $from=self::inspect($source,$sourceModules);$to=self::inspect($target,$targetModules);
        $same=hash_equals($from['rules_key'],$to['rules_key']);
        return ['source'=>$from,'target'=>$to,'strategy'=>$same?'retain-existing-world':'provision-new-world',
            'in_place_allowed'=>$same,'copy_state_allowed'=>false,
            'rollback'=>'retain-original-world-and-artifacts'];
    }
}
