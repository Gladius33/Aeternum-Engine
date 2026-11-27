<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CareerInstitutionLedger.php';
require_once __DIR__.'/WorldMutationFence.php';

/** Server-only adapter; no public routes, no automatic migration or legacy principal escalation. */
final class PdoCareerInstitutionStore
{
    public function __construct(private readonly \PDO $db,private readonly CareerInstitutionEvidence $evidence){
        $db->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);$db->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
    }
    private function query(string $sql,array $args=[]):\PDOStatement{$q=$this->db->prepare($sql);$q->execute($args);return $q;}
    private function tx(callable $f):mixed{
        $this->db->beginTransaction();try{$r=$f();$this->db->commit();return $r;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function clock(string $world):array{
        $f=WorldMutationFence::lockCoordinator($this->db,$world);CareerInstitutionLedger::check($f['state']==='idle','world advancing');
        $r=$this->query('SELECT committed_day,revision,pending_day FROM wp_world_clock WHERE world_id=? FOR UPDATE',[$world])->fetch(\PDO::FETCH_ASSOC);
        CareerInstitutionLedger::check($r!==false&&$r['pending_day']===null,'uncommitted world');return $r;
    }
    public function seed(string $world,string $mode):void{
        $this->tx(function()use($world,$mode){$c=$this->clock($world);
            $scenario=$this->query('SELECT scenario FROM wp_alpha_worlds WHERE world_id=? FOR UPDATE',[$world])->fetchColumn();
            CareerInstitutionLedger::check($scenario==='wp-career-v1'||$scenario==='wp-career-research-v1','explicit opt-in scenario required');
            CareerInstitutionLedger::check(($scenario==='wp-career-research-v1')===($mode==='research'),'scenario mode mismatch');
            $s=CareerInstitutionLedger::initial($world,'FR',$mode,$c['committed_day']);$j=CareerInstitutionLedger::json($s);
            $this->query('INSERT INTO wp_career_ledgers(world_id,initial_json,state_json,state_sha) VALUES(?,?,?,?)',[$world,$j,$j,CareerInstitutionLedger::digest($s)]);});
    }
    private function row(string $world):array{
        $r=$this->query('SELECT * FROM wp_career_ledgers WHERE world_id=? FOR UPDATE',[$world])->fetch(\PDO::FETCH_ASSOC);CareerInstitutionLedger::check($r!==false,'career ledger not enabled');return $r;
    }
    private static function decode(string $j):array{return json_decode($j,true,64,JSON_THROW_ON_ERROR);}
    private function replay(string $world,array $r):array{
        $initial=self::decode($r['initial_json']);
        $s=CareerInstitutionLedger::initial($world,$initial['country'],$initial['mode'],$initial['day']);
        CareerInstitutionLedger::check(CareerInstitutionLedger::json($s)===$r['initial_json'],'initial state tampered');
        $events=$this->query('SELECT * FROM wp_career_events WHERE world_id=? ORDER BY sequence_no',[$world]);
        $priorRevision=-1;
        foreach($events as $x){
            $e=self::decode($x['event_json']);$this->evidence->verify($e,$x['signature_sha']);
            CareerInstitutionLedger::check((int)$x['sequence_no']===$s['sequence']+1&&$x['previous_sha']===$s['last_event_sha']&&$e['actor']===$x['actor_id']&&$e['command']===$x['command_id']&&$e['world_revision']>=$priorRevision,'journal chain');
            $s=CareerInstitutionLedger::apply($s,$e);$priorRevision=$e['world_revision'];
            CareerInstitutionLedger::check($x['event_sha']===$s['last_event_sha']&&$x['receipt_json']===CareerInstitutionLedger::json(self::receipt($s)),'journal receipt');
        }
        CareerInstitutionLedger::check((int)$r['sequence_no']===$s['sequence']&&$r['state_sha']===CareerInstitutionLedger::digest($s)&&$r['state_json']===CareerInstitutionLedger::json($s),'projection mismatch');
        $expected=[];foreach($s['active'] as $o=>$id){$m=$s['mandates'][$id];$expected[$o]=[$id,$m['career'],$m['generation']];}
        $actual=[];foreach($this->query('SELECT office_id,mandate_id,career_id,generation FROM wp_career_active_offices WHERE world_id=?',[$world]) as $v)$actual[$v['office_id']]=[$v['mandate_id'],$v['career_id'],(int)$v['generation']];
        CareerInstitutionLedger::check(CareerInstitutionLedger::json($expected)===CareerInstitutionLedger::json($actual),'active office projection mismatch');
        $expected=[];foreach($s['careers'] as $id=>$c)$expected[$c['account']]=[$id,$c['character']];
        $actual=[];foreach($this->query('SELECT account_id,career_id,character_id FROM wp_career_memberships WHERE world_id=?',[$world]) as $v)$actual[$v['account_id']]=[$v['career_id'],$v['character_id']];
        CareerInstitutionLedger::check(CareerInstitutionLedger::json($expected)===CareerInstitutionLedger::json($actual),'membership projection mismatch');return $s;
    }
    private static function receipt(array $s):array{return CareerInstitutionLedger::canonical(['sequence'=>$s['sequence'],'event_sha'=>$s['last_event_sha'],'state_sha'=>CareerInstitutionLedger::digest($s)]);}
    private function activeAccount(string $world,string $account):void{
        $r=$this->query('SELECT a.enabled FROM wp_accounts a JOIN wp_alpha_members m ON m.account_id=a.account_id WHERE m.world_id=? AND a.account_id=? FOR UPDATE',[$world,$account])->fetchColumn();
        CareerInstitutionLedger::check((int)$r===1,'no active world membership');
    }
    public function execute(array $e,string $signature):array{
        $this->evidence->verify($e,$signature);$world=$e['world'];
        return $this->tx(function()use($e,$signature,$world){
            $clock=$this->clock($world);$r=$this->row($world);$s=$this->replay($world,$r);
            $prior=$this->query('SELECT event_json,receipt_json FROM wp_career_events WHERE world_id=? AND actor_id=? AND command_id=?',[$world,$e['actor'],$e['command']])->fetch(\PDO::FETCH_ASSOC);
            if($prior!==false){CareerInstitutionLedger::check($prior['event_json']===CareerInstitutionLedger::json($e),'idempotency conflict');return self::decode($prior['receipt_json']);}
            CareerInstitutionLedger::check($clock['committed_day']===$e['day']&&(int)$clock['revision']===$e['world_revision'],'stale institutional evidence');
            if($e['operation']==='admit')$this->activeAccount($world,$e['payload']['account']);
            foreach($e['payload']['grants']??[] as $g){$account=$s['careers'][$g['career']]['account']??null;CareerInstitutionLedger::check(is_string($account),'unknown career');$this->activeAccount($world,$account);}
            $next=CareerInstitutionLedger::apply($s,$e);$receipt=self::receipt($next);
            $this->query('INSERT INTO wp_career_events(world_id,sequence_no,actor_id,command_id,event_json,signature_sha,previous_sha,event_sha,receipt_json) VALUES(?,?,?,?,?,?,?,?,?)',[$world,$next['sequence'],$e['actor'],$e['command'],CareerInstitutionLedger::json($e),$signature,$s['last_event_sha'],$next['last_event_sha'],CareerInstitutionLedger::json($receipt)]);
            if($e['operation']==='admit'){$p=$e['payload'];$this->query('INSERT INTO wp_career_memberships(world_id,account_id,career_id,character_id) VALUES(?,?,?,?)',[$world,$p['account'],$p['career'],$p['character']]);}
            $this->query('DELETE FROM wp_career_active_offices WHERE world_id=?',[$world]);
            foreach($next['active'] as $o=>$id){$m=$next['mandates'][$id];$this->query('INSERT INTO wp_career_active_offices(world_id,office_id,mandate_id,career_id,generation) VALUES(?,?,?,?,?)',[$world,$o,$id,$m['career'],$m['generation']]);}
            $this->query('UPDATE wp_career_ledgers SET state_json=?,state_sha=?,sequence_no=? WHERE world_id=?',[CareerInstitutionLedger::json($next),$receipt['state_sha'],$next['sequence'],$world]);return $receipt;
        });
    }
    /** Called by the clock with its fence and clock row already locked.
     * Equal expiry may be reached, but no later day until a signed closure/renewal.
     * Old schemas/worlds are unchanged; an opted-in row fails closed on projection corruption.
     */
    public static function assertClockAdvance(\PDO $db,string $world,string $target):void{
        CareerInstitutionLedger::check($db->inTransaction(),'clock guard outside transaction');
        try{$q=$db->prepare('SELECT state_json,state_sha,sequence_no FROM wp_career_ledgers WHERE world_id=? FOR UPDATE');$q->execute([$world]);$r=$q->fetch(\PDO::FETCH_ASSOC);}
        catch(\PDOException $e){if((int)($e->errorInfo[1]??0)===1146)return;throw $e;}
        if($r===false)return;
        $s=self::decode($r['state_json']);
        CareerInstitutionLedger::check($s['world']===$world&&$s['sequence']===(int)$r['sequence_no']&&hash_equals($r['state_sha'],CareerInstitutionLedger::digest($s)),'clock career projection mismatch');
        CareerInstitutionLedger::day($target);
        foreach($s['active'] as $id)CareerInstitutionLedger::check($s['mandates'][$id]['expires']>=$target,'certified mandate transition required before clock advances');
        if($s['assembly']==='dissolved')CareerInstitutionLedger::check(CareerScoreContract::elapsed($s['assembly_changed_on'],$target)<=40,'assembly election evidence required before clock advances');
    }
    public function audit(string $world):array{return $this->tx(function()use($world){$this->clock($world);return $this->replay($world,$this->row($world));});}
    /** Resolve a bearer token through the existing session DB; callback is trusted server code and runs under the fence. */
    public function withCapability(string $world,string $token,string $mandate,int $generation,string $ability,callable $action):mixed{
        return $this->tx(function()use($world,$token,$mandate,$generation,$ability,$action){
            $clock=$this->clock($world);$s=$this->replay($world,$this->row($world));
            $a=$this->query('SELECT account_id FROM wp_sessions WHERE token_sha=? AND expires_at>? FOR UPDATE',[hash('sha256',$token),time()])->fetchColumn();
            CareerInstitutionLedger::check(is_string($a),'invalid session');$this->activeAccount($world,$a);
            CareerInstitutionLedger::check(CareerInstitutionLedger::allows($s,$a,$mandate,$generation,$ability,$clock['committed_day']),'office capability denied');
            return $action($this->db,$s['mandates'][$mandate],$clock);
        });
    }
}
