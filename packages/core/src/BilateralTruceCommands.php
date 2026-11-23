<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Operator-registered generic mutual truce protocol.
 * The proposer cannot establish a truce unilaterally: the target must
 * actively accept an unexpired proposal using its own actor credential.
 * This module is NOT loaded from a data-only manifest by name.
 */
final readonly class BilateralTruceCommands implements DiplomacyActorPackCommand
{
    public function supportedTypes(): array
    {
        return ['pack.diplomacy.offer','pack.diplomacy.accept'];
    }

    public function codeDigest(): string
    {
        return hash_file('sha256',__FILE__)
            ?: throw new \RuntimeException('Truce implementation digest unavailable');
    }

    public function ownedAccountFields(string $type): array { return ['account']; }

    public function targetAccountField(string $type): string { return 'target'; }

    public function apply(Command $command,array $state,int $at): CommandResult
    {
        $source=$command->payload['account']??null;
        $target=$command->payload['target']??null;
        if (!is_string($source) || !is_string($target) || $source===$target
            || $at<0 || $at>PHP_INT_MAX-604800) {
            throw new \DomainException('Invalid diplomacy participants or time');
        }
        PdoMemberships::validateId($source);
        PdoMemberships::validateId($target);
        if ($command->type==='pack.diplomacy.offer') {
            $duration=$command->payload['duration']??null;
            if (!is_int($duration) || $duration<60 || $duration>604800) {
                throw new \DomainException('Invalid truce duration');
            }
            $prefix='diplomacy:proposal:'.$source.':'.$target.':';
            $state[$prefix.'duration']=$duration;
            $state[$prefix.'expires']=$at+3600;
            return new CommandResult($state);
        }
        if ($command->type!=='pack.diplomacy.accept') {
            throw new \DomainException('Unapproved diplomacy action');
        }
        // The accepting target is the original proposer; a proposal in
        // the other direction is neither equivalent nor an authorization.
        $proposal='diplomacy:proposal:'.$target.':'.$source.':';
        $expires=$state[$proposal.'expires']??null;
        $duration=$state[$proposal.'duration']??null;
        if (!is_int($expires) || $expires<$at || !is_int($duration)
            || $duration<60 || $duration>604800) {
            throw new \DomainException('No valid mutual truce proposal');
        }
        $pair=[$source,$target]; sort($pair,SORT_STRING);
        $key='diplomacy:truce:'.$pair[0].':'.$pair[1].':until';
        $previous=$state[$key]??0;
        if (!is_int($previous) || $previous<0) {
            throw new \DomainException('Invalid truce history');
        }
        $state[$key]=max($previous,$at+$duration);
        unset($state[$proposal.'duration'],$state[$proposal.'expires']);
        return new CommandResult($state);
    }
}
