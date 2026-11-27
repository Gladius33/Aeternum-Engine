<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/UniverseLobby.php';

final class UniverseClockPrincipal implements InstitutionalActorAuthorizer,EconomicPolicyPrincipal
{
    public function __construct(private readonly \PDO $db,private readonly string $world){}
    public function actorId():string{return 'alpha-clock-'.$this->world;}
    public function allows(string $world,string $actor,string $ability):bool
    {
        if($world!==$this->world)return false;
        $q=$this->db->prepare('SELECT 1 FROM wp_universes WHERE world_id=? AND next_tick_epoch IS NOT NULL');$q->execute([$world]);
        if($q->fetchColumn()===false)return false;
        if(in_array($actor,UniverseLobby::COUNTRIES,true))return in_array($ability,['read_institutions','advance_clock'],true);
        return $actor===$this->actorId()&&in_array($ability,['world.clock.read','world.clock.advance','organizations.read','organizations.clock','diplomacy.read','diplomacy.clock','economy.read','economy.clock'],true);
    }
    public function roleFor(string $world,string $country,string $action):string{throw new \DomainException('No executive authority');}
    public function legalApproval(string $world,string $state,string $instrument):bool{return false;}
}
final class UniverseDomesticClockPrincipal implements PersistentDomesticPolicyAuthority
{
    public function __construct(private readonly \PDO $db,private readonly UniverseClockPrincipal $principal){}
    public function actorId():string{return $this->principal->actorId();}
    public function allows(string $world,string $ability):bool
    {return in_array($ability,['domestic.read','domestic.clock'],true)&&$this->principal->allows($world,$this->actorId(),'world.clock.advance');}
    public function enactedInstrument(string $world,string $policy):?string{return null;}
    public function clockDay(string $world):string{
        if(!$this->allows($world,'domestic.clock'))throw new \DomainException('Clock denied');
        $q=$this->db->prepare('SELECT pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$r=$q->fetchColumn();
        if(!is_string($r))throw new \DomainException('Missing pending clock day');return $r;
    }
}
final class UniverseScheduler
{
    private readonly \Closure $connect;
    public function __construct(callable $connect){$this->connect=\Closure::fromCallable($connect);}
    private function db():\PDO{return ($this->connect)();}
    public function runDue(int $limit=20):array
    {
        if($limit<1||$limit>100)throw new \InvalidArgumentException('Invalid tick limit');
        $db=$this->db();
        $rows=$db->query('SELECT u.world_id FROM wp_universes u JOIN wp_world_clock c ON c.world_id=u.world_id WHERE u.next_tick_epoch<=UNIX_TIMESTAMP() AND c.game_over=0 ORDER BY u.next_tick_epoch,u.universe_no LIMIT '.$limit)->fetchAll(\PDO::FETCH_COLUMN);
        $out=[];foreach($rows as $world){
            try{$out[]=$this->tick($world);}catch(\Throwable $e){$out[]=['world_id'=>$world,'status'=>'failed','error_class'=>get_class($e)];}
        }
        return $out;
    }
    private function clock(string $world,string $sha):WorldClockCoordinator
    {
        $bundle=(new PdoGlobalReferenceBundles($this->db()))->load($sha);
        $p=new UniverseClockPrincipal($this->db(),$world);
        $dom=new PdoDomesticPolicyStore($this->db(),new UniverseDomesticClockPrincipal($this->db(),$p));
        $inst=new PdoInstitutionalStore($this->db(),$p);
        $op=new PdoPublicOpinionStore($this->db(),$p,$dom,$inst);
        return new WorldClockCoordinator($this->db(),new PdoOrganizationStore($this->db(),$bundle->actors,[],$p),
            new PdoDiplomaticStore($this->db(),$bundle->actors,$p),new PdoEconomicStore($this->db(),$bundle->actors,$bundle->references,$p),$p,
            $inst,UniverseLobby::COUNTRIES,null,$dom,$op);
    }
    public function tick(string $world):array
    {
        if(!preg_match('/^world-[a-f0-9]{32}$/D',$world))throw new \InvalidArgumentException('Invalid managed world');
        $db=$this->db();$lock='wp-clock-'.$world;
        $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);
        if((int)$q->fetchColumn()!==1)return ['world_id'=>$world,'status'=>'busy'];
        try{
            $q=$db->prepare('SELECT u.next_game_day,u.next_tick_epoch,u.tick_seconds,c.committed_day,c.pending_day,c.game_over,w.reference_bundle_sha,
                UNIX_TIMESTAMP() AS now_epoch FROM wp_universes u JOIN wp_world_clock c ON c.world_id=u.world_id JOIN wp_alpha_worlds w ON w.world_id=u.world_id WHERE u.world_id=?');
            $q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);
            if($r===false)throw new \DomainException('Unknown managed universe');
            if($r['next_tick_epoch']===null||(int)$r['game_over']===1||(int)$r['next_tick_epoch']>(int)$r['now_epoch'])
                return ['world_id'=>$world,'status'=>'not_due'];
            $target=$r['next_game_day'];
            if(!is_string($target))throw new \DomainException('Missing scheduled game day');
            $clock=$this->clock($world,$r['reference_bundle_sha']);
            if($r['committed_day']!==$target){
                $expected=(new \DateTimeImmutable($r['committed_day']))->modify('+1 day')->format('Y-m-d');
                if($target!==$expected||($r['pending_day']!==null&&$r['pending_day']!==$target))throw new \DomainException('Schedule and game clock disagree');
                if($r['pending_day']!==null)$clock->resumePending($world);
                else{$state=$clock->read($world);$clock->advance($world,$target,'universe-day-'.$target,$state['etag']);}
            }elseif($r['pending_day']!==null)throw new \DomainException('Unexpected pending clock after publication');
            // If a worker crashes after the world commit, the next invocation
            // sees the scheduled day already published and only updates schedule.
            $q=$db->prepare('UPDATE wp_universes u JOIN wp_world_clock c ON c.world_id=u.world_id SET
                u.next_game_day=DATE_ADD(u.next_game_day,INTERVAL 1 DAY),u.next_tick_epoch=u.next_tick_epoch+u.tick_seconds
                WHERE u.world_id=? AND u.next_game_day=? AND c.committed_day=? AND c.pending_day IS NULL');
            $q->execute([$world,$target,$target]);
            if($q->rowCount()!==1)throw new \DomainException('Schedule checkpoint lost');
            return ['world_id'=>$world,'status'=>'committed','day'=>$target];
        }finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
    }
}
