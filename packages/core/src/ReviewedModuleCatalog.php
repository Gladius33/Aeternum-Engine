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
            'survey-v1'=>self::survey($pack),
            'campaign-v1'=>self::campaign($pack),
            'guild-challenge-v1'=>self::challenge($pack),
            default=>throw new \InvalidArgumentException('Unknown reviewed module'),
        };
    }

    private static function campaign(GamePack $pack): array
    {
        $command=new CampaignModuleV1($pack->id,$pack->version);
        $event=new CampaignEventV1($command);
        $schema=PackFields::load((object)['account'=>'string'])->types;
        foreach ($command->supportedTypes() as $type)
            if (($pack->actions[$type]->types??null)!==$schema) throw new \InvalidArgumentException('Campaign action schema mismatch');
        if (($pack->eventHooks['pack.campaign.complete']->types??null)!==PackFields::load((object)['account'=>'string','job'=>'string','level'=>'integer'])->types)
            throw new \InvalidArgumentException('Campaign event schema mismatch');
        return ['commands'=>[$command],'events'=>[$event],'sha256'=>hash('sha256',$command->codeDigest()."\0".$event->codeDigest())];
    }

    private static function challenge(GamePack $pack): array
    {
        if ($pack->id!=='reference-guild'||$pack->version!=='0.5.0'||$pack->engagement?->diplomacyEnabled!==true
            ||($pack->actions['pack.guild.challenge']->types??null)!==PackFields::load((object)['account'=>'string','target'=>'string'])->types)
            throw new \InvalidArgumentException('Challenge policy mismatch');
        $handler=new GuildChallengeV1();
        return ['commands'=>[$handler],'events'=>[],'sha256'=>$handler->codeDigest()];
    }

    private static function survey(GamePack $pack): array
    {
        $schema=PackFields::load((object)['account'=>'string'])->types;
        if (($pack->actions['pack.survey.start']->types??null)!==$schema
            || ($pack->eventHooks['pack.survey.complete']->types??null)!==$schema) {
            throw new \InvalidArgumentException('Survey module requires exact action and event schemas');
        }
        $command=new SurveyModuleV1();$event=new SurveyEventV1();
        return ['commands'=>[$command],'events'=>[$event],
            'sha256'=>hash('sha256',$command->codeDigest()."\0".$event->codeDigest())];
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
