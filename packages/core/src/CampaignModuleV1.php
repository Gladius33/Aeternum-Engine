<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Reviewed, bounded research and expedition progression. Expeditions record
 * completed surveys/logistical studies, not physical cargo or hostile claims.
 * Economic effects remain exclusively in existing ledger-backed commands.
 */
final readonly class CampaignModuleV1 implements ActorPackCommand, PrivatePackEffects, PlayerPackView
{
    private array $policy;
    public function __construct(string $pack,string $version)
    {
        $this->policy=match ($pack.'@'.$version) {
            'reference-medieval@0.4.0'=>['researchBuilding'=>'lumberyard','expeditionBuilding'=>'keep','researchSeconds'=>4,'expeditionSeconds'=>6,'guild'=>false],
            'reference-industrial@0.4.0'=>['researchBuilding'=>'power_plant','expeditionBuilding'=>'freight_terminal','researchSeconds'=>4,'expeditionSeconds'=>6,'guild'=>false],
            'reference-guild@0.5.0'=>['researchBuilding'=>'market_hall','expeditionBuilding'=>'caravan_hub','researchSeconds'=>4,'expeditionSeconds'=>6,'guild'=>true],
            default=>throw new \InvalidArgumentException('Unsupported campaign policy version'),
        };
    }
    public function codeDigest(): string
    {
        return hash('sha256',(hash_file('sha256',__FILE__)?:throw new \RuntimeException('Campaign source missing'))."\0".json_encode($this->policy,JSON_THROW_ON_ERROR));
    }
    public function stateNamespace(): string {return 'campaign';}
    public function viewId(): string {return 'campaign';}
    public function supportedTypes(): array {return ['pack.campaign.research','pack.campaign.expedition'];}
    public function ownedAccountFields(string $type): array {return ['account'];}
    private static function prefix(string $account): string
    {
        PdoMemberships::validateId($account);return 'pack:campaign:'.$account.':';
    }
    private static function counter(array $state,string $key,int $max): int
    {
        $n=$state[$key]??0;
        if (!is_int($n)||$n<0||$n>$max) throw new \DomainException('Invalid campaign counter');
        return $n;
    }
    private static function building(array $state,string $account,string $type): int
    {
        return self::counter($state,'build:max:'.$account.':'.$type,16);
    }
    private function eligible(array $state,string $account,string $job): bool
    {
        $p=self::prefix($account);
        $research=self::counter($state,$p.'research',2);
        $expeditions=self::counter($state,$p.'expedition',3);
        if ($job==='research') return $research<2 && self::building($state,$account,$this->policy['researchBuilding']) >= $research+1;
        if ($job==='expedition') return $expeditions<3 && $research>=1 && self::building($state,$account,$this->policy['expeditionBuilding'])>=1;
        throw new \DomainException('Unknown campaign job');
    }
    public function apply(Command $cmd,array $state,int $at): CommandResult
    {
        $job=match ($cmd->type) {'pack.campaign.research'=>'research','pack.campaign.expedition'=>'expedition',default=>throw new \DomainException('Unknown campaign command')};
        $account=$cmd->payload['account'];$p=self::prefix($account);
        if (isset($state[$p.'pending'])||!$this->eligible($state,$account,$job)||$at<0||$at>PHP_INT_MAX-6)
            throw new \DomainException('Campaign job unavailable');
        $level=self::counter($state,$p.$job,$job==='research'?2:3)+1;
        $id='campaign:'.hash('sha256',$cmd->id);
        $due=$at+$this->policy[$job.'Seconds'];
        $state[$p.'pending']=$id;$state[$p.'job']=$job;$state[$p.'due']=$due;
        return new CommandResult($state,[new ScheduledEvent($cmd->worldId,$id,'pack.campaign.complete',$due,
            ['account'=>$account,'job'=>$job,'level'=>$level])]);
    }
    public function resolve(ScheduledEvent $event,array $state): EventResult
    {
        $account=$event->payload['account'];$job=$event->payload['job'];$p=self::prefix($account);
        if ($event->type!=='pack.campaign.complete'||!in_array($job,['research','expedition'],true)
            ||($state[$p.'pending']??null)!==$event->id||($state[$p.'job']??null)!==$job
            ||($state[$p.'due']??null)!==$event->dueAt||!$this->eligible($state,$account,$job)
            ||$event->payload['level']!==self::counter($state,$p.$job,$job==='research'?2:3)+1)
            throw new \DomainException('Invalid campaign completion');
        $state[$p.$job]=$event->payload['level'];
        unset($state[$p.'pending'],$state[$p.'job'],$state[$p.'due']);
        return new EventResult($state);
    }
    public function ownView(array $state,string $account): array
    {
        $p=self::prefix($account);
        $research=self::counter($state,$p.'research',2);$expeditions=self::counter($state,$p.'expedition',3);
        $pending=$state[$p.'job']??null;
        $result=['research'=>$research,'expeditions'=>$expeditions,'pending'=>$pending,
            'dueAt'=>$state[$p.'due']??null,'canResearch'=>$pending===null&&$this->eligible($state,$account,'research'),
            'canExpedition'=>$pending===null&&$this->eligible($state,$account,'expedition'),
            'researchBuilding'=>$this->policy['researchBuilding'],'expeditionBuilding'=>$this->policy['expeditionBuilding']];
        if ($this->policy['guild']) {
            $trades=0;
            // Stop at the goal, so the result exposes no counterparty or
            // unbounded history. Open/cancelled/expired offers never count.
            foreach ($state as $key=>$value) {
                if ($value!=='filled'||!preg_match('/^market:([A-Za-z0-9_-]{1,64}):status$/D',$key,$m)) continue;
                $prefix='market:'.$m[1].':';
                if (($state[$prefix.'seller']??null)===$account||($state[$prefix.'buyer']??null)===$account) {
                    if (++$trades>=3) break;
                }
            }
            $buildings=[];
            foreach (['market_hall'=>2,'caravan_hub'=>2,'watchtower'=>1] as $type=>$required)
                $buildings[$type]=['current'=>self::building($state,$account,$type),'required'=>$required];
            $achieved=$research===2&&$expeditions>=2&&$trades>=3;
            foreach ($buildings as $goal) $achieved=$achieved&&$goal['current']>=$goal['required'];
            $result['trades']=['current'=>$trades,'required'=>3];
            $result['victory']=['achieved'=>$achieved,'buildings'=>$buildings,'researchRequired'=>2,'expeditionsRequired'=>2];
        }
        return $result;
    }
}
