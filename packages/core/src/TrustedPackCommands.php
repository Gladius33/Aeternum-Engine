<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Immutable operator-created routing table for trusted pack commands.
 * Nothing here loads or interprets executable code from a game manifest.
 *
 * Actor admission is a distinct opt-in contract: only ActorPackCommand
 * instances pass the world-locked ownership checks in PdoScheduler.
 * All other handlers remain operator-only.
 */
final class TrustedPackCommands
{
    /** @var array<string,ApprovedPackCommand> */
    private array $handlers=[];
    /** @var array<string,PackFields> */
    private array $schemas=[];
    /** @var array<string,list<string>> */
    private array $actorOwnedFields=[];
    /** @var array<string,string> */
    private array $engagementTargets=[];
    /** @var array<string,string> */
    private array $diplomacyTargets=[];
    private string $fingerprint;

    /**
     * @param list<ApprovedPackCommand> $handlers
     * @param array<string,PackFields> $declaredActions
     */
    public function __construct(array $handlers,array $declaredActions,private readonly array $declaredHooks=[],private readonly array $approvedEvents=[])
    {
        if (!array_is_list($handlers) || $handlers===[] || count($handlers)>16) {
            throw new \InvalidArgumentException('Invalid trusted handler list');
        }
        $identities=[];
        foreach ($handlers as $handler) {
            if (!$handler instanceof ApprovedPackCommand) {
                throw new \InvalidArgumentException('Only approved PHP handlers are accepted');
            }
            $digest=$handler->codeDigest();
            if (!preg_match('/^[a-f0-9]{64}$/D',$digest)) {
                throw new \InvalidArgumentException('Invalid reviewed code digest');
            }
            $types=$handler->supportedTypes();
            if (!is_array($types) || !array_is_list($types) || $types===[] || count($types)>16) {
                throw new \InvalidArgumentException('Invalid trusted command type list');
            }
            foreach ($types as $type) {
                if (!is_string($type)
                    || !preg_match('/^pack\.[a-z][a-z0-9_-]{0,31}\.[a-z][a-z0-9_-]{0,31}$/D',$type)
                    || !isset($declaredActions[$type]) || !$declaredActions[$type] instanceof PackFields
                    || isset($this->handlers[$type]) || count($this->handlers)>=64) {
                    throw new \InvalidArgumentException('Unapproved, duplicate or reserved command type');
                }
                $this->handlers[$type]=$handler;
                $this->schemas[$type]=$declaredActions[$type];
                // Player admission is a separate explicit privilege. Trusted
                // operator-side handlers stay private unless opted in.
                if ($handler instanceof ActorPackCommand) {
                    $owned=$handler->ownedAccountFields($type);
                    if (!is_array($owned) || !array_is_list($owned)
                        || $owned===[] || count($owned)>8 || count(array_unique($owned))!==count($owned)) {
                        throw new \InvalidArgumentException('Invalid actor account ownership policy');
                    }
                    foreach ($owned as $field) {
                        if (!is_string($field) || ($declaredActions[$type]->types[$field]??null)!=='string') {
                            throw new \InvalidArgumentException('Actor policy must name declared string fields');
                        }
                    }
                    $this->actorOwnedFields[$type]=$owned;
                    if ($handler instanceof EngagementActorPackCommand) {
                        // A player-to-player interaction must have one
                        // authenticated source, plus a distinct declared
                        // string target. Never infer targets from player JSON.
                        $target=$handler->targetAccountField($type);
                        if (count($owned)!==1 || !is_string($target)
                            || ($declaredActions[$type]->types[$target]??null)!=='string'
                            || in_array($target,$owned,true)) {
                            throw new \InvalidArgumentException('Invalid engagement target policy');
                        }
                        $this->engagementTargets[$type]=$target;
                    }
                    if ($handler instanceof DiplomacyActorPackCommand) {
                        $target=$handler->targetAccountField($type);
                        if (count($owned)!==1 || !is_string($target)
                            || ($declaredActions[$type]->types[$target]??null)!=='string'
                            || in_array($target,$owned,true)
                            || $handler instanceof EngagementActorPackCommand) {
                            throw new \InvalidArgumentException('Invalid diplomacy target policy');
                        }
                        $this->diplomacyTargets[$type]=$target;
                    }
                }
                $identities[$type]=$digest;
            }
        }
        ksort($identities,SORT_STRING);
        $this->fingerprint=hash('sha256',json_encode($identities,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }

    public function fingerprint(): string { return $this->fingerprint; }

    public function handles(string $type): bool { return isset($this->handlers[$type]); }

    /** False unless the application operator approved an actor-capable handler. */
    public function allowsActor(string $type): bool
    {
        return isset($this->actorOwnedFields[$type]);
    }

    /**
     * @return ?list<string> Owner accounts to check under the world lock.
     * Null means there is no approved actor policy for this command.
     */
    public function actorOwnedAccounts(Command $command): ?array
    {
        $fields=$this->actorOwnedFields[$command->type]??null;
        if ($fields===null) return null;
        $this->schemas[$command->type]->assertPayload($command->payload);
        $accounts=[];
        foreach ($fields as $field) {
            $account=$command->payload[$field];
            PdoMemberships::validateId($account);
            $accounts[$account]=$account;
        }
        return array_values($accounts);
    }

    /** Null for all non-engagement commands, including ordinary private packs. */
    public function engagementTarget(Command $command): ?string
    {
        $field=$this->engagementTargets[$command->type]??null;
        if ($field===null) return null;
        $this->schemas[$command->type]->assertPayload($command->payload);
        $account=$command->payload[$field];
        PdoMemberships::validateId($account);
        return $account;
    }

    /** Target account for mutually acknowledged diplomacy, not engagement. */
    public function diplomacyTarget(Command $command): ?string
    {
        $field=$this->diplomacyTargets[$command->type]??null;
        if ($field===null) return null;
        $this->schemas[$command->type]->assertPayload($command->payload);
        $target=$command->payload[$field];
        PdoMemberships::validateId($target);
        return $target;
    }

    public function execute(Command $command,array $state,int $effectiveAt): CommandResult
    {
        $handler=$this->handlers[$command->type]??throw new \DomainException('Unapproved pack command');
        $this->schemas[$command->type]->assertPayload($command->payload);
        // Normal pinned scheduler, receipts and replay remain authoritative.
        // This method is NOT an actor-authorization shortcut.
        $result=$handler->apply($command,$state,$effectiveAt);
        if ($handler instanceof PrivatePackEffects) {
            $accounts=$this->actorOwnedAccounts($command);
            if ($accounts===null) throw new \DomainException('Private module requires owned accounts');
            PrivatePackEffectGuard::state($handler,$accounts,$state,$result->state,$result->movements);
            PrivatePackEffectGuard::events($handler,$command,$effectiveAt,$accounts,$result->events,
                $this->declaredHooks,$this->approvedEvents);
        }
        // No delayed effects can evade due-time re-evaluation of protection.
        // Such commands require a separately audited event admission hook.
        if (isset($this->engagementTargets[$command->type]) && $result->events!==[]) {
            throw new \DomainException('Delayed engagement effects not yet approved');
        }
        return $result;
    }
}
