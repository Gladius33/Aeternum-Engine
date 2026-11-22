<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Trusted developer SDK. Metadata is declarative; this does not grant a player
 * permission or execute custom PHP. Normal HTTP dispatch remains allowlisted.
 */
final readonly class PackSdk
{
    public function __construct(private GamePack $pack) {}

    /** @param array<string,mixed> $payload */
    public function component(string $type,array $payload): array
    {
        $schema=$this->pack->components[$type]??throw new \DomainException('Unknown pack component');
        $schema->assertPayload($payload);
        return $payload;
    }

    /** @param array<string,mixed> $payload */
    public function command(string $world,string $id,string $type,array $payload): Command
    {
        $schema=$this->pack->actions[$type]??throw new \DomainException('Unknown pack action');
        $schema->assertPayload($payload);
        return new Command($world,$id,$type,$payload);
    }

    /** @param array<string,mixed> $payload */
    public function event(string $world,string $id,string $type,int $at,array $payload): ScheduledEvent
    {
        $schema=$this->pack->eventHooks[$type]??throw new \DomainException('Unknown pack event hook');
        $schema->assertPayload($payload);
        return new ScheduledEvent($world,$id,$type,$at,$payload);
    }
}
