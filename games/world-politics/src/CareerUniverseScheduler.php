<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CareerEconomicWorld.php';
require_once __DIR__.'/PdoCareerInstitutionStore.php';

/** Opt-in public career worlds use the same fenced economic coordinator
 * as private career worlds. An unused reserve does not accrue game time. */
final class CareerUniverseScheduler
{
    private readonly \Closure $connect;
    public function __construct(callable $connect){$this->connect=\Closure::fromCallable($connect);}
    public function runDue(int $limit=20):array{
        if($limit<1||$limit>100)throw new \InvalidArgumentException('Invalid limit');
        $db=($this->connect)();
        $rows=$db->query('SELECT p.world_id FROM wp_career_public_worlds p JOIN wp_world_clock c USING(world_id)
            WHERE p.next_tick_epoch<=UNIX_TIMESTAMP() AND c.game_over=0 ORDER BY p.next_tick_epoch,p.universe_no LIMIT '.$limit)->fetchAll(\PDO::FETCH_COLUMN);
        $out=[];foreach($rows as $world){
            try{$out[]=$this->tick($world);}
            catch(\Throwable $e){$out[]=['world_id'=>$world,'status'=>'failed','error_class'=>get_class($e)];}
        }
        return $out;
    }
    public function tick(string $world):array{
        CareerInstitutionLedger::id($world);
        $db=($this->connect)();$lock='wp-clock-'.$world;
        $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);
        if((int)$q->fetchColumn()!==1)return ['world_id'=>$world,'status'=>'busy'];
        try{
            $q=$db->prepare('SELECT p.next_game_day,p.next_tick_epoch,c.committed_day,c.pending_day,c.game_over,UNIX_TIMESTAMP() now_epoch
                FROM wp_career_public_worlds p JOIN wp_world_clock c USING(world_id) WHERE p.world_id=?');
            $q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);
            CareerInstitutionLedger::check($r!==false,'not a public career universe');
            if($r['next_tick_epoch']===null||(int)$r['game_over']===1||(int)$r['next_tick_epoch']>(int)$r['now_epoch'])
                return ['world_id'=>$world,'status'=>'not_due'];
            $horizon=(new \DateTimeImmutable(CareerScoreContract::START))->modify('+'.CareerScoreContract::HORIZON.' days')->format('Y-m-d');
            $target=$r['next_game_day'];
            CareerInstitutionLedger::check(is_string($target)&&$target<=$horizon,'invalid scheduled day');
            if($r['committed_day']!==$target){
                CareerInstitutionLedger::check($target===(new \DateTimeImmutable($r['committed_day']))->modify('+1 day')->format('Y-m-d')
                    &&($r['pending_day']===null||$r['pending_day']===$target),'schedule clock mismatch');
                CareerEconomicWorld::tick($this->connect,$world,$target);
            }else CareerInstitutionLedger::check($r['pending_day']===null,'unexpected pending day after publication');
            // A crash after world publication only leaves the schedule behind.
            // The committed game day is never advanced twice.
            $q=$db->prepare('UPDATE wp_career_public_worlds p JOIN wp_world_clock c USING(world_id)
                SET p.next_game_day=IF(?= ?,NULL,DATE_ADD(p.next_game_day,INTERVAL 1 DAY)),
                    p.next_tick_epoch=IF(?= ?,NULL,p.next_tick_epoch+p.tick_seconds)
                WHERE p.world_id=? AND p.next_game_day=? AND c.committed_day=? AND c.pending_day IS NULL');
            $q->execute([$target,$horizon,$target,$horizon,$world,$target,$target]);
            CareerInstitutionLedger::check($q->rowCount()===1,'schedule checkpoint lost');
            return ['world_id'=>$world,'status'=>'committed','day'=>$target];
        }finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }
}
