<?php
declare(strict_types=1);
namespace Aeternum\Core;

final class PrivatePackEffectGuard
{
    /** @param non-empty-list<string> $accounts */
    public static function state(PrivatePackEffects $module,array $accounts,array $before,array $after,array $movements): void
    {
        $namespace=$module->stateNamespace();
        if (!preg_match('/^[a-z][a-z0-9_-]{0,31}$/D',$namespace)
            || $accounts===[] || count($accounts)>8 || $movements!==[]) {
            throw new \DomainException('Invalid private module effect policy');
        }
        $prefixes=[];
        foreach ($accounts as $account) {
            PdoMemberships::validateId($account);
            $prefixes[]='pack:'.$namespace.':'.$account.':';
        }
        $allowed=static function(string $key) use($prefixes):bool {
            foreach ($prefixes as $prefix) if (str_starts_with($key,$prefix)) return true;
            return false;
        };
        foreach ($before as $key=>$value) {
            if ((!array_key_exists($key,$after) || $after[$key]!==$value) && !$allowed($key))
                throw new \DomainException('Private module changed foreign state');
        }
        foreach ($after as $key=>$value) {
            if ((!array_key_exists($key,$before) || $before[$key]!==$value) && !$allowed($key))
                throw new \DomainException('Private module changed foreign state');
        }
    }

    /** @param array<string,PackFields> $hooks @param array<string,ApprovedPackEvent> $approved */
    public static function events(PrivatePackEffects $module,Command $command,int $at,array $accounts,array $events,array $hooks,array $approved): void
    {
        if (count($events)>1) throw new \DomainException('Private module event limit exceeded');
        foreach ($events as $event) {
            $handler=$approved[$event->type]??null;
            $schema=$hooks[$event->type]??null;
            if (!$handler instanceof PrivatePackEffects || $handler->stateNamespace()!==$module->stateNamespace()
                || !$schema instanceof PackFields || $event->worldId!==$command->worldId
                || $event->dueAt<=$at || $at>PHP_INT_MAX-3600 || $event->dueAt>$at+3600
                || !in_array($event->payload['account']??null,$accounts,true)) {
                throw new \DomainException('Private module event outside approved scope');
            }
            $schema->assertPayload($event->payload);
        }
    }
}
