<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Immediate non-destructive challenge; no delayed hostile effects or loot. */
final readonly class GuildChallengeV1 implements EngagementActorPackCommand
{
    public function supportedTypes(): array {return ['pack.guild.challenge'];}
    public function codeDigest(): string {return hash_file('sha256',__FILE__)?:throw new \RuntimeException('Challenge source missing');}
    public function ownedAccountFields(string $type): array {return ['account'];}
    public function targetAccountField(string $type): string {return 'target';}
    public function apply(Command $cmd,array $state,int $at): CommandResult
    {
        $a=$cmd->payload['account'];$b=$cmd->payload['target'];
        PdoMemberships::validateId($a);PdoMemberships::validateId($b);
        if ($a===$b||$cmd->type!=='pack.guild.challenge') throw new \DomainException('Invalid challenge');
        $key='guild:challenge:'.$a.':count';$n=$state[$key]??0;
        if (!is_int($n)||$n<0||$n>=100) throw new \DomainException('Challenge limit');
        $state[$key]=$n+1;
        return new CommandResult($state);
    }
}
