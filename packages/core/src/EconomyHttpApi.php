<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;
final class EconomyHttpApi
{
    /** Rules registry must be constructed by trusted server configuration. */
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock,private readonly array $rules) {}
    public function handle(string $method,string $path,string $authorization,string $body=''): array
    {
        if (!preg_match('~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/economy/(commands|market(?:/([A-Za-z0-9][A-Za-z0-9_-]{0,63}))?|(?:balances|overview)/([A-Za-z0-9][A-Za-z0-9_-]{0,63})/([A-Za-z0-9][A-Za-z0-9_-]{0,63})|market/search/([A-Za-z0-9][A-Za-z0-9_-]{0,63})/([A-Za-z0-9][A-Za-z0-9_-]{0,63})(?:/([A-Za-z0-9][A-Za-z0-9_-]{0,63}))?|market/history/([A-Za-z0-9][A-Za-z0-9_-]{0,63})(?:/([A-Za-z0-9][A-Za-z0-9_-]{0,63}))?|bot-receipts/([A-Za-z0-9][A-Za-z0-9_-]{0,63})/([A-Za-z0-9][A-Za-z0-9_-]{0,63})/(bot-[a-f0-9]{64}))$~D',$path,$m,PREG_UNMATCHED_AS_NULL)) return [404,['error'=>'not_found']];
        $command=$m[2]==='commands';
        if ($method!==($command?'POST':'GET')) return [405,['error'=>'method_not_allowed']];
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$token)) return [401,['error'=>'unauthorized']];
        $actor=(new PdoSessions($this->pdo,$this->clock))->resolve($token[1]);
        if ($actor===null) return [401,['error'=>'unauthorized']];
        $limiter=new PdoRateLimiter($this->pdo,$this->clock);
        if (!$limiter->admit('economy:'.$actor,120,60)) return [429,['error'=>'rate_limited']];
        $rules=$this->rules[$m[1]]??null;
        if (!$rules instanceof RuleSet || !$rules->commands instanceof EconomyCommandHandler) return [404,['error'=>'not_found']];
        $scheduler=new PdoScheduler($this->pdo,$m[1],$this->clock,$rules);
        try {
            // Authenticated, actor-scoped durable receipt; never public world state.
            if (str_starts_with($m[2],'bot-receipts/')) {
                $receipt=$scheduler->botReceiptForActor($actor,$m[11],$m[12],$m[13]);
                return $receipt===null
                    ? [404,['error'=>'not_found']]
                    : [200,['data'=>$receipt]];
            }
            if (str_starts_with($m[2],'market/history/')) return [200,['data'=>$scheduler->marketHistoryForActor($actor,$m[9],$m[10]??null)]];
            if (str_starts_with($m[2],'market')) return [200,['data'=>$scheduler->marketForActor($actor,$m[8]??$m[3]??null,50,$m[6]??null,$m[7]??null)]];
            if (!$command) return [200,['data'=>str_starts_with($m[2],'overview/')?$scheduler->overviewForActor($actor,$m[4],$m[5],$rules->commands):$scheduler->balanceForActor($actor,$m[4],$m[5],$rules->commands)]];
            if (strlen($body)>8192) return [413,['error'=>'too_large']];
            $data=StrictJson::decode($body,false,8);
            if (!$data instanceof \stdClass || count(get_object_vars($data))!==3 || !is_string($data->id??null) || !is_string($data->type??null) || !($data->payload??null) instanceof \stdClass) return [400,['error'=>'invalid_command']];
            if (!in_array($data->type,['economy.open','economy.settle','economy.upgrade','economy.transfer','market.create','market.accept','market.cancel','market.claim','building.place','building.start','building.cancel','building.claim','settlement.feed','settlement.work','industry.generate','industry.refine','industry.ship'],true)
                && !$rules->commands->allowsActorPackCommand($data->type)) return [400,['error'=>'invalid_command']];
            if (str_starts_with($data->type,'market.') && !$limiter->admit('market-write:'.$actor,20,60)) return [429,['error'=>'rate_limited']];
            if ((str_starts_with($data->type,'building.') || ReferenceGameplayCompatibility::isLegacyNamespace($data->type)) && !$limiter->admit('build-write:'.$actor,20,60)) return [429,['error'=>'rate_limited']];
            if (str_starts_with($data->type,'pack.') && !$limiter->admit('pack-write:'.$actor,20,60)) return [429,['error'=>'rate_limited']];
            $receipt=$scheduler->executeForActor($actor,new Command($m[1],$data->id,$data->type,(array)$data->payload),$rules->commands);
            return [200,['data'=>$receipt]];
        } catch (DueEventsPending) {
            return [409,['error'=>'command_or_access_conflict','retryable'=>true]];
        } catch (\DomainException) {
            return [409,['error'=>'command_or_access_conflict']];
        } catch (\InvalidArgumentException|\UnexpectedValueException|\JsonException) {
            return [400,['error'=>'invalid_command']];
        }
    }
}
