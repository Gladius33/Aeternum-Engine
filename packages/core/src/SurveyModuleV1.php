<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Independent SDK example. Pure, bounded, own-account survey progress.
 * A single pending job per account; no economy writes or foreign effects.
 * This class deliberately has no IO, wall clock, randomness or PDO.
 */
final class SurveyModuleV1 implements ActorPackCommand
{
    public function supportedTypes(): array
    {
        return ['pack.survey.start'];
    }

    public function codeDigest(): string
    {
        return hash_file('sha256',__FILE__)?:throw new \RuntimeException('Survey digest unavailable');
    }

    public function ownedAccountFields(string $type): array {return ['account'];}

    private static function prefix(array $payload): string
    {
        $account=$payload['account']??null;
        if (!is_string($account)) throw new \InvalidArgumentException('Missing survey account');
        PdoMemberships::validateId($account);
        return 'pack:survey:'.$account.':';
    }

    public function apply(Command $input,array $state,int $at): CommandResult
    {
        if ($input->type!=='pack.survey.start' || $at<0 || $at>PHP_INT_MAX-10)
            throw new \DomainException('Invalid survey start');
        $prefix=self::prefix($input->payload);
        $done=$state[$prefix.'completed']??0;
        if (!is_int($done) || $done<0 || $done>=100 || isset($state[$prefix.'pending']))
            throw new \DomainException('Survey unavailable');
        $id='survey:'.hash('sha256',$input->id);
        $state[$prefix.'pending']=$id;
        $state[$prefix.'due']=$at+10;
        return new CommandResult($state,[new ScheduledEvent($input->worldId,$id,'pack.survey.complete',$at+10,
            ['account'=>$input->payload['account']])]);
    }

    public function resolve(ScheduledEvent $event,array $state): EventResult
    {
        $prefix=self::prefix($event->payload);
        $done=$state[$prefix.'completed']??0;
        if ($event->type!=='pack.survey.complete' || ($state[$prefix.'pending']??null)!==$event->id
            || ($state[$prefix.'due']??null)!==$event->dueAt || !is_int($done) || $done<0 || $done>=100)
            throw new \DomainException('Invalid pending survey');
        unset($state[$prefix.'pending'],$state[$prefix.'due']);
        $state[$prefix.'completed']=$done+1;
        return new EventResult($state);
    }
}
