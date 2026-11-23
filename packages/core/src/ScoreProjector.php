<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Derived scores from the same canonical server state that is journaled and
 * replayed. No mutable points counter and no per-command bonus to farm by
 * retrying an idempotent request.
 */
final readonly class ScoreProjector
{
    public const MAX_SCORE=1000000000000;
    public const MAX_STATE_KEYS=100000;

    public function __construct(private ScoreRules $rules) {}

    private static function add(int $sum,int $units,int $weight): int
    {
        if ($units<0 || $weight<0 || $units>self::MAX_SCORE
            || ($weight!==0 && $units>intdiv(self::MAX_SCORE-$sum,$weight))) {
            throw new \OverflowException('Score exceeds authorized bound');
        }
        return $sum+$units*$weight;
    }

    /** @return array{development:int,military:int,global:int} */
    public function forAccount(array $state,string $account): array
    {
        PdoMemberships::validateId($account);
        if (count($state)>self::MAX_STATE_KEYS) {
            throw new \DomainException('Progression snapshot exceeds bounded projection');
        }
        $development=0;$military=0;
        foreach ($this->rules->buildingPoints as $building=>$weight) {
            $level=$state['build:max:'.$account.':'.$building]??0;
            if (!is_int($level) || $level<0 || $level>16) {
                throw new \UnexpectedValueException('Invalid building progression state');
            }
            $development=self::add($development,$level,$weight);
        }
        foreach ($this->rules->upgradePoints as $resource=>$weight) {
            $level=$state['econ:'.$account.':'.$resource.':level']??0;
            if (!is_int($level) || $level<0 || $level>100) {
                throw new \UnexpectedValueException('Invalid resource progression state');
            }
            $development=self::add($development,$level,$weight);
        }
        foreach ($this->rules->militaryBuildingPoints as $building=>$weight) {
            $level=$state['build:max:'.$account.':'.$building]??0;
            if (!is_int($level) || $level<0 || $level>16) {
                throw new \UnexpectedValueException('Invalid military progression state');
            }
            $military=self::add($military,$level,$weight);
        }
        if ($this->rules->tradePoints!==0) {
            $trades=0;
            foreach ($state as $key=>$value) {
                if (!is_string($key) || !str_starts_with($key,'market:')
                    || !str_ends_with($key,':status') || $value!=='filled') continue;
                $prefix=substr($key,0,-strlen('status'));
                // Only complete, server-committed filled offers count. An
                // idempotent buyer retry cannot create another offer prefix.
                if (($state[$prefix.'seller']??null)===$account
                    || ($state[$prefix.'buyer']??null)===$account) {
                    if (++$trades>10000) {
                        throw new \DomainException('Progression trade projection limit');
                    }
                }
            }
            $development=self::add($development,$trades,$this->rules->tradePoints);
        }
        return [
            'development'=>$development,
            'military'=>$military,
            'global'=>self::add($development,$military,1),
        ];
    }
}
