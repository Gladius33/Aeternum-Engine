<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Explicit event adapter for the reviewed survey transition. */
final readonly class SurveyEventV1 implements ApprovedPackEvent
{
    public function supportedTypes(): array {return ['pack.survey.complete'];}
    public function codeDigest(): string
    {
        $adapter=hash_file('sha256',__FILE__)?:throw new \RuntimeException('Survey event digest unavailable');
        return hash('sha256',$adapter."\0".(new SurveyModuleV1())->codeDigest());
    }
    public function resolve(ScheduledEvent $event,array $state): EventResult
    {
        return (new SurveyModuleV1())->resolve($event,$state);
    }
    public function apply(ScheduledEvent $event,array $state): array
    {
        return $this->resolve($event,$state)->state;
    }
}
