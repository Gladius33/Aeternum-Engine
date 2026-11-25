<?php
declare(strict_types=1);
namespace Aeternum\Core;

final readonly class CampaignEventV1 implements ApprovedPackEvent, PrivatePackEffects
{
    public function __construct(private CampaignModuleV1 $module) {}
    public function stateNamespace(): string {return 'campaign';}
    public function supportedTypes(): array {return ['pack.campaign.complete'];}
    public function codeDigest(): string
    {
        return hash('sha256',(hash_file('sha256',__FILE__)?:throw new \RuntimeException('Campaign event source missing'))."\0".$this->module->codeDigest());
    }
    public function resolve(ScheduledEvent $event,array $state): EventResult {return $this->module->resolve($event,$state);}
    public function apply(ScheduledEvent $event,array $state): array {return $this->resolve($event,$state)->state;}
}
