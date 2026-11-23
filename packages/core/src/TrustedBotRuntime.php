<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Explicitly operator-controlled automated actor tick: up to two reads
 * (own receipt and overview) and at most one command through EconomyHttpApi.
 *
 * This is NOT a sandbox for user-uploaded scripts or a bot account manager.
 */
final readonly class TrustedBotRuntime
{
    public const TICK_SECONDS=60;

    /** @var array<string,true> Reviewed SHA-256 digests accepted by the operator. */
    private array $approvedSources;

    /** @param list<string> $approvedSourceDigests */
    public function __construct(
        private EconomyHttpApi $api,private Clock $clock,array $approvedSourceDigests
    ) {
        if (!array_is_list($approvedSourceDigests) || count($approvedSourceDigests)>64) {
            throw new \InvalidArgumentException('Invalid approved bot strategy registry');
        }
        $sources=[];
        foreach ($approvedSourceDigests as $digest) {
            if (!is_string($digest) || !preg_match('/^[a-f0-9]{64}$/D',$digest)
                || isset($sources[$digest])) {
                throw new \InvalidArgumentException('Invalid or duplicate approved strategy SHA-256');
            }
            $sources[$digest]=true;
        }
        $this->approvedSources=$sources;
    }

    /**
     * @return array{status:string,code?:int,commandId?:string}
     */
    public function tick(
        string $world,string $account,string $resource,string $bearer,
        ApprovedBotStrategy $strategy,bool $operatorEnabled=false,
    ):array {
        if (!$operatorEnabled) return ['status'=>'paused'];
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$world)) {
            throw new \InvalidArgumentException('Invalid bot world');
        }
        PdoMemberships::validateId($account);
        PdoMemberships::validateId($resource);
        if (!preg_match('/^Bearer [a-f0-9]{64}$/D',$bearer)) {
            throw new \DomainException('Missing bot actor session');
        }
        $digest=$strategy->codeDigest();
        if (!preg_match('/^[a-f0-9]{64}$/D',$digest)
            || !isset($this->approvedSources[$digest])) {
            throw new \DomainException('Bot strategy source not approved by operator');
        }
        // A strategy cannot approve itself by merely claiming a trusted hash:
        // independently hash the PHP class file actually loaded into this process.
        $source=(new \ReflectionClass($strategy))->getFileName();
        $actual=is_string($source) && is_file($source)
            ? hash_file('sha256',$source) : false;
        if (!is_string($actual) || !hash_equals($digest,$actual)) {
            throw new \DomainException('Bot strategy source differs from reviewed digest');
        }
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid logical bot clock');
        $tick=intdiv($now,self::TICK_SECONDS);
        $prefix='/v1/worlds/'.$world.'/economy/';
        // Check durable command identity before choosing from mutable state.
        // Survives restarts and strategy changes; preserves same-tick quotas.
        $commandId='bot-'.hash('sha256',implode("\0",[
            $world,$account,$resource,(string)$tick
        ]));
        [$receiptCode,]=$this->api->handle('GET',
            $prefix.'bot-receipts/'.$account.'/'.$resource.'/'.$commandId,$bearer);
        if ($receiptCode===200) {
            return ['status'=>'submitted','code'=>200,'commandId'=>$commandId];
        }
        if ($receiptCode!==404) return ['status'=>'blocked','code'=>$receiptCode];
        [$readCode,$read]=$this->api->handle('GET',
            $prefix.'overview/'.$account.'/'.$resource,$bearer);
        if ($readCode!==200) return ['status'=>'blocked','code'=>$readCode];
        $plan=$strategy->choose($read['data'],$account,$resource,$tick);
        if ($plan===null) return ['status'=>'idle'];
        if (!$plan instanceof BotPlan || $plan->payload['account']!==$account
            || $plan->payload['resource']!==$resource) {
            throw new \DomainException('Bot attempted foreign or unapproved action');
        }
        // Concurrent runners share this ID: the journal deduplicates their POST.
        [$code,]=$this->api->handle('POST',$prefix.'commands',$bearer,
            json_encode([
                'id'=>$commandId,
                'type'=>$plan->type,
                'payload'=>$plan->payload
            ],JSON_THROW_ON_ERROR));
        return ['status'=>$code===200?'submitted':'blocked',
            'code'=>$code,'commandId'=>$commandId];
    }
}
