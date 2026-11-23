<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Immutable operator-created routing table for trusted pack commands.
 * Nothing here loads or interprets executable code from a game manifest.
 *
 * Initial M14 support is for trusted internal invocations only. Public
 * and actor admission remain rejected by existing API and SQL guards.
 */
final class TrustedPackCommands
{
    /** @var array<string,ApprovedPackCommand> */
    private array $handlers=[];
    /** @var array<string,PackFields> */
    private array $schemas=[];
    private string $fingerprint;

    /**
     * @param list<ApprovedPackCommand> $handlers
     * @param array<string,PackFields> $declaredActions
     */
    public function __construct(array $handlers,array $declaredActions)
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
                $identities[$type]=$digest;
            }
        }
        ksort($identities,SORT_STRING);
        $this->fingerprint=hash('sha256',json_encode($identities,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }

    public function fingerprint(): string { return $this->fingerprint; }

    public function handles(string $type): bool { return isset($this->handlers[$type]); }

    public function execute(Command $command,array $state,int $effectiveAt): CommandResult
    {
        $handler=$this->handlers[$command->type]??throw new \DomainException('Unapproved pack command');
        $this->schemas[$command->type]->assertPayload($command->payload);
        // Normal pinned scheduler, receipts and replay remain authoritative.
        // This method is NOT an actor-authorization shortcut.
        return $handler->apply($command,$state,$effectiveAt);
    }
}
