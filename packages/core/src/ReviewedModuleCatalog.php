<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Application-owned allowlist. Adding executable code requires a reviewed release. */
final class ReviewedModuleCatalog
{
    /** @return array{commands:list<ApprovedPackCommand>,events:list<ApprovedPackEvent>,sha256:string} */
    public static function module(string $id,GamePack $pack): array
    {
        return match ($id) {
            'bilateral-truce-v1'=>self::truce($pack),
            default=>throw new \InvalidArgumentException('Unknown reviewed module'),
        };
    }

    private static function truce(GamePack $pack): array
    {
        if ($pack->engagement?->diplomacyEnabled!==true
            || ($pack->actions['pack.diplomacy.offer']->types??null)!==PackFields::load((object)['account'=>'string','target'=>'string','duration'=>'integer'])->types
            || ($pack->actions['pack.diplomacy.accept']->types??null)!==PackFields::load((object)['account'=>'string','target'=>'string'])->types) {
            throw new \InvalidArgumentException('Module incompatible with pinned pack policy or schema');
        }
        $handler=new BilateralTruceCommands();
        return ['commands'=>[$handler],'events'=>[],'sha256'=>$handler->codeDigest()];
    }
}
