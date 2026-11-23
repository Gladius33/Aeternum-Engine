<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Explicitly operator-controlled automated actor tick: one read + at most
 * one command, both through EconomyHttpApi using a normal actor session.
 *
 * This is NOT a sandbox for user-uploaded scripts or a bot account manager.
 */
final readonly class TrustedBotRuntime
{
    public const TICK_SECONDS=60;

    public function __construct(private EconomyHttpApi $api,private Clock $clock) {}

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
        if (!preg_match('/^[a-f0-9]{64}$/D',$strategy->codeDigest())) {
            throw new \InvalidArgumentException('Unpinned bot strategy');
        }
        $now=$this->clock->now();
        if ($now<0) throw new \DomainException('Invalid logical bot clock');
        $tick=intdiv($now,self::TICK_SECONDS);
        $prefix='/v1/worlds/'.$world.'/economy/';
        [$readCode,$read]=$this->api->handle('GET',
            $prefix.'overview/'.$account.'/'.$resource,$bearer);
        if ($readCode!==200) return ['status'=>'blocked','code'=>$readCode];
        $plan=$strategy->choose($read['data'],$account,$resource,$tick);
        if ($plan===null) return ['status'=>'idle'];
        if (!$plan instanceof BotPlan || $plan->payload['account']!==$account
            || $plan->payload['resource']!==$resource) {
            throw new \DomainException('Bot attempted foreign or unapproved action');
        }
        // Replay-stable ID does NOT include strategy code. A changed strategy
        // during one tick cannot obtain a second command id to bypass quotas.
        $commandId='bot-'.hash('sha256',implode("\0",[
            $world,$account,$resource,(string)$tick
        ]));
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
