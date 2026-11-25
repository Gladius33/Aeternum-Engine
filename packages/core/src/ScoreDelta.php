<?php
declare(strict_types=1);
namespace Aeternum\Core;

/** Exact sparse differences in weighted canonical score sources, not gameplay mutations. */
final readonly class ScoreDelta
{
    public function __construct(private ScoreRules $rules) {}

    private static function level(array $state,string $key,int $max): int
    {
        $v=$state[$key]??0;
        if (!is_int($v) || $v<0 || $v>$max) throw new \UnexpectedValueException('Invalid score source level');
        return $v;
    }

    private static function bump(array &$delta,string $account,int $development,int $military): void
    {
        PdoMemberships::validateId($account);
        $old=$delta[$account]??[0,0];
        $next=[$old[0]+$development,$old[1]+$military];
        if (abs($next[0])>ScoreProjector::MAX_SCORE || abs($next[1])>ScoreProjector::MAX_SCORE)
            throw new \OverflowException('Score delta overflow');
        if ($next===[0,0]) unset($delta[$account]); else $delta[$account]=$next;
    }

    /** @return list<string> Distinct parties to a completed canonical trade. */
    private static function parties(array $state,string $prefix): array
    {
        if (($state[$prefix.'status']??null)!=='filled') return [];
        $seller=$state[$prefix.'seller']??null;$buyer=$state[$prefix.'buyer']??null;
        if (!is_string($seller) || !is_string($buyer)) throw new \UnexpectedValueException('Missing filled trade participants');
        return array_values(array_unique([$seller,$buyer]));
    }

    /** @return array<string,array{0:int,1:int}> */
    public function between(array $before,array $after,?array $keys=null): array
    {
        $keys=$keys??PdoEconomyProjection::deltaKeys($before,$after);
        $delta=[];$offers=[];
        foreach ($keys as $key) {
            if (preg_match('/^build:max:([A-Za-z0-9_-]{1,64}):([A-Za-z0-9_-]{1,64})$/D',$key,$m)) {
                $d=$this->rules->buildingPoints[$m[2]]??0;$mil=$this->rules->militaryBuildingPoints[$m[2]]??0;
                if ($d===0 && $mil===0) continue;
                $n=self::level($after,$key,16)-self::level($before,$key,16);
                self::bump($delta,$m[1],$n*$d,$n*$mil);
            } elseif (preg_match('/^econ:([A-Za-z0-9_-]{1,64}):([A-Za-z0-9_-]{1,64}):level$/D',$key,$m)) {
                $d=$this->rules->upgradePoints[$m[2]]??0;
                if ($d!==0) self::bump($delta,$m[1],(self::level($after,$key,100)-self::level($before,$key,100))*$d,0);
            } elseif ($this->rules->tradePoints!==0
                && preg_match('/^market:([A-Za-z0-9_-]{1,64}):(status|seller|buyer)$/D',$key,$m)) {
                $offers[$m[1]]=true;
            }
        }
        foreach ($offers as $id=>$_) {
            $prefix='market:'.$id.':';
            foreach (self::parties($before,$prefix) as $a) self::bump($delta,$a,-$this->rules->tradePoints,0);
            foreach (self::parties($after,$prefix) as $a) self::bump($delta,$a,$this->rules->tradePoints,0);
        }
        ksort($delta,SORT_STRING);
        return $delta;
    }
}
