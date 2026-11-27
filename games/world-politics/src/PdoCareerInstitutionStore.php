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
        $expected=[];foreach($s['careers'] as $id=>$c)if($c['account']!==null)$expected[$c['account']]=[$id,$c['character']];
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
        return $this->tx(fn()=>$this->applyLocked($e,$signature));
    }
    private function applyLocked(array $e,string $signature):array{
            $world=$e['world'];$this->evidence->verify($e,$signature);
            $clock=$this->clock($world);$r=$this->row($world);$s=$this->replay($world,$r);
            $prior=$this->query('SELECT event_json,receipt_json FROM wp_career_events WHERE world_id=? AND actor_id=? AND command_id=?',[$world,$e['actor'],$e['command']])->fetch(\PDO::FETCH_ASSOC);
            if($prior!==false){CareerInstitutionLedger::check($prior['event_json']===CareerInstitutionLedger::json($e),'idempotency conflict');return self::decode($prior['receipt_json']);}
            CareerInstitutionLedger::check($clock['committed_day']===$e['day']&&(int)$clock['revision']===$e['world_revision'],'stale institutional evidence');
            if(in_array($e['operation'],['dissolve_assembly','restore_assembly','constitution_revision'],true)){
                require_once __DIR__.'/FrenchCareerInstitutionBridge.php';
                $source=$this->query("SELECT state_json,state_sha,revision FROM wp_institutional_states WHERE world_id=? AND country_code='FR' FOR UPDATE",[$world])->fetch(\PDO::FETCH_ASSOC);
                CareerInstitutionLedger::check($source!==false,'missing committed institution proof');
                $digest=FrenchCareerInstitutionBridge::evidence(self::decode($source['state_json']),(int)$source['revision'],$e,$source['state_sha']);
                CareerInstitutionLedger::check(hash_equals($digest,$e['evidence_sha']),'institution evidence digest mismatch');
            }
            CareerInstitutionLedger::check($e['operation']!=='admit_ai_roster','fictional AI admission retired');
            if(in_array($e['operation'],['admit','claim_real_career'],true))$this->activeAccount($world,$e['payload']['account']);
            if($e['operation']==='claim_real_career'){
                require_once __DIR__.'/CampaignCandidate.php';
                $selection=['type'=>'campaign2027','reference_id'=>$e['payload']['edition']];
                CareerInstitutionLedger::check(CampaignCandidate::mayStart($selection),'Programme encore non disponible');
                $approved=ProgrammeSourceInventory::report()['approved_editions'][$e['payload']['edition']];
                CareerInstitutionLedger::check($approved['document_sha256']===$e['payload']['programme_sha'],'programme edition mismatch');
            }
            $grants=$e['payload']['grants']??[];
            if($e['operation']==='presidential_ballot')$grants=CareerInstitutionLedger::ballotTransition($s,$e)['grants'];
            if(in_array($e['operation'],['cabinet_act','automatic_cabinet'],true)){$tmp=$s;$grants=CareerInstitutionLedger::cabinetTransition($tmp,$e)['grants']??[];}
            foreach($grants as $g){$career=$s['careers'][$g['career']]??null;CareerInstitutionLedger::check(is_array($career),'unknown career');if(($career['controller']??null)==='ai'){CareerInstitutionLedger::check($career['account']===null,'AI account forbidden');}else{$this->activeAccount($world,$career['account']);}}
            if(in_array($e['operation'],['foreign_opening','foreign_government'],true)){
                require_once __DIR__.'/InstitutionalSimulation.php';
                $source=$this->query('SELECT state_json,state_sha FROM wp_institutional_states WHERE world_id=? AND country_code=? FOR UPDATE',[$world,$s['country']])->fetch(\PDO::FETCH_ASSOC);
                CareerInstitutionLedger::check($source!==false,'missing committed comparative institution');
                $inst=InstitutionalSimulation::restore(self::decode($source['state_json']));$is=$inst->snapshot();
                CareerInstitutionLedger::check($is['world_id']===$world&&$is['country_code']===$s['country']&&$is['day']===$e['day']&&hash_equals($source['state_sha'],$inst->hash())&&isset($is['accountability']),'comparative committed source mismatch');
                $gov=self::decode($e['payload']['government_json']);
                CareerInstitutionLedger::check($gov===$is['accountability'],'comparative source differs');
                if($gov['status']==='in_office'){
                    $career=$s['careers'][$gov['incumbent']]??null;
                    CareerInstitutionLedger::check(is_array($career),'unknown successor career');
                    if(($career['controller']??null)==='ai'){
                        CareerInstitutionLedger::check($career['account']===null&&isset($career['person_reference']),'documented AI successor required');
                    }else{$this->activeAccount($world,$career['account']);}
                }
            }
            $next=CareerInstitutionLedger::apply($s,$e);$receipt=self::receipt($next);
            $this->query('INSERT INTO wp_career_events(world_id,sequence_no,actor_id,command_id,event_json,signature_sha,previous_sha,event_sha,receipt_json) VALUES(?,?,?,?,?,?,?,?,?)',[$world,$next['sequence'],$e['actor'],$e['command'],CareerInstitutionLedger::json($e),$signature,$s['last_event_sha'],$next['last_event_sha'],CareerInstitutionLedger::json($receipt)]);
            if(in_array($e['operation'],['admit','claim_real_career'],true)){$p=$e['payload'];$this->query('INSERT INTO wp_career_memberships(world_id,account_id,career_id,character_id) VALUES(?,?,?,?)',[$world,$p['account'],$p['career'],$next['careers'][$p['career']]['character']]);}
            $this->query('DELETE FROM wp_career_active_offices WHERE world_id=?',[$world]);
            foreach($next['active'] as $o=>$id){$m=$next['mandates'][$id];$this->query('INSERT INTO wp_career_active_offices(world_id,office_id,mandate_id,career_id,generation) VALUES(?,?,?,?,?)',[$world,$o,$id,$m['career'],$m['generation']]);}
            $this->query('UPDATE wp_career_ledgers SET state_json=?,state_sha=?,sequence_no=? WHERE world_id=?',[CareerInstitutionLedger::json($next),$receipt['state_sha'],$next['sequence'],$world]);return $receipt;
    }
    private function sessionAccount(string $token):string{
        CareerInstitutionLedger::check(preg_match('/^[a-f0-9]{64}$/D',$token)===1,'invalid session');
        $a=$this->query('SELECT s.account_id FROM wp_sessions s JOIN wp_accounts a ON a.account_id=s.account_id WHERE s.token_sha=? AND s.expires_at>? AND a.enabled=1 FOR UPDATE',[hash('sha256',$token),time()])->fetchColumn();
        CareerInstitutionLedger::check(is_string($a),'invalid session');return $a;
    }
    private function viewLocked(string $world,string $account,array $clock):array{
        $s=$this->replay($world,$this->row($world));$career=null;$mandates=[];$offices=[];
        foreach($s['careers'] as $id=>$c)if($c['account']===$account)$career=['id'=>$id,'character'=>$c['character'],'admitted_on'=>$c['admitted_on'],'mode'=>$c['mode'],... (isset($c['person_reference'])?['identity'=>$c['identity'],'claimed_on'=>$c['claimed_on']??null]:[]),...(isset($c['fictional_profile'])?['fictional_profile'=>$c['fictional_profile']]:[])];
        foreach($s['active'] as $office=>$id){
            $m=$s['mandates'][$id];$offices[]=['office'=>$office,'expires'=>$m['expires'],'interim'=>$m['interim'],... (isset($s['careers'][$m['career']]['person_reference'])?['identity'=>$s['careers'][$m['career']]['identity'],'controller'=>$s['careers'][$m['career']]['controller']]:[])];
            if($career!==null&&$m['career']===$career['id'])$mandates[]=['id'=>$id,'office'=>$office,'generation'=>$m['generation'],'expires'=>$m['expires'],'powers'=>$clock['committed_day']<$m['expires']?$m['powers']:[]];
        }
        return ['world_id'=>$world,'country'=>$s['country'],'day'=>$clock['committed_day'],'revision'=>(int)$clock['revision'],'career'=>$career,'mandates'=>$mandates,'occupied_offices'=>$offices,
            'institution'=>['state_id'=>$s['state_id'],'regime'=>$s['regime'],'assembly'=>$s['assembly'],'senate'=>$s['senate'],'government'=>$s['government'],'constitution_revision'=>$s['constitution_revision']],
            'appointment_proposals'=>array_values(array_filter($s['appointment_proposals']??[],fn($p)=>$p['status']==='pending'&&($s['active']['prime-minister']??null)===$p['pm_mandate'])),'rankable'=>false,'profile'=>'isolated-career-alpha'];
    }
    public function readForSession(string $world,string $token):array{
        return $this->tx(function()use($world,$token){$clock=$this->clock($world);$account=$this->sessionAccount($token);$this->activeAccount($world,$account);return $this->viewLocked($world,$account,$clock);});
    }
    /** Member-visible documentary directory, never a list of authenticated accounts. */
    public function charactersForSession(string $world,string $token):array{
        return $this->tx(function()use($world,$token){
            $clock=$this->clock($world);$a=$this->sessionAccount($token);$this->activeAccount($world,$a);
            $state=$this->replay($world,$this->row($world));$out=[];$choices=[];
            if($state['country']==='FR'){
                require_once __DIR__.'/CampaignCandidate.php';
                foreach(CampaignCandidate::candidateDirectory2027() as $choice)$choices[$choice['reference_id']]=$choice;
            }
            foreach($state['careers'] as $id=>$c){
                if(!isset($c['person_reference']))continue;
                $available=null;
                foreach($choices as $reference=>$choice)if(($reference===$c['person_reference']||str_starts_with($reference,$c['person_reference'].'-v'))&&$choice['playable']){$available=$reference;break;}
                $offices=[];foreach($state['active'] as $office=>$mid)if($state['mandates'][$mid]['career']===$id)$offices[]=$office;
                $out[]=['career_id'=>$id,'identity'=>$c['identity'],'controller'=>$c['controller'],'offices'=>$offices,
                    'claim_reference'=>$available,'playable'=>$available!==null&&$c['account']===null,
                    'availability_code'=>$available===null?'programme_incomplete':($c['account']===null?'available':'occupied')];
            }
            return ['world_id'=>$world,'country'=>$state['country'],'day'=>$clock['committed_day'],'characters'=>$out,'fictional_creation_available'=>true,'final_ballot_verified'=>false];
        });
    }
    private function admitLocked(string $world,string $account,array $clock,string $secret,?string $reference=null,?array $fictional=null):array{
        $s=$this->replay($world,$this->row($world));
        require_once __DIR__.'/CareerProgrammeAutonomy.php';
        CareerInstitutionLedger::check($reference===null||$fictional===null,'identity choices are exclusive');
        if($fictional!==null)$fictional=CareerProgrammeAutonomy::normalizeFictional($fictional,$s['country']);
        foreach($s['careers'] as $c)if($c['account']===$account){
            CareerInstitutionLedger::check(($reference===null||($c['programme_edition']??null)===$reference)&&($fictional===null||CareerInstitutionLedger::json($fictional)===CareerInstitutionLedger::json($c['fictional_profile']??null)),'account already controls another character');
            return $this->viewLocked($world,$account,$clock);
        }
        if($reference!==null){
            require_once __DIR__.'/CampaignCandidate.php';
            CareerInstitutionLedger::check($s['country']==='FR'&&CampaignCandidate::mayStart(['type'=>'campaign2027','reference_id'=>$reference]),'Programme encore non disponible');
            $target=null;foreach($s['careers'] as $id=>$c)if(isset($c['person_reference'])&&($reference===$c['person_reference']||str_starts_with($reference,$c['person_reference'].'-v'))){$target=$id;break;}
            CareerInstitutionLedger::check($target!==null,'real identity not in world');
            $approved=ProgrammeSourceInventory::report()['approved_editions'][$reference];
            $p=['career'=>$target,'account'=>$account,'edition'=>$reference,'programme_sha'=>$approved['document_sha256']];
            $day=$clock['committed_day'];
            $e=['world'=>$world,'country'=>'FR','day'=>$day,'world_revision'=>(int)$clock['revision'],'command'=>'claim-'.substr(hash('sha256',$account.'|'.$reference),0,48),'actor'=>'career-admission','operation'=>'claim_real_career','payload'=>$p,
                'evidence_sha'=>CareerInstitutionLedger::digest(['kind'=>'approved-real-career-claim-v1','world'=>$world,'day'=>$day,'payload'=>$p])];
            $this->applyLocked($e,hash_hmac('sha256',CareerInstitutionLedger::json($e),$secret));
            return $this->viewLocked($world,$account,$clock);
        }
        CareerInstitutionLedger::check($s['mode']==='career','research admission forbidden');
        $hash=substr(hash('sha256',$world."\n".$account),0,40);
        $e=['world'=>$world,'country'=>$s['country'],'day'=>$clock['committed_day'],'world_revision'=>(int)$clock['revision'],'command'=>'admit-'.$hash,'actor'=>'career-admission','operation'=>'admit',
            'payload'=>['career'=>'career-'.$hash,'account'=>$account,'character'=>'fiction-'.$hash,'mode'=>$clock['committed_day']===CareerScoreContract::START?'opening':'late_takeover',...($fictional!==null?['fictional_profile'=>$fictional]:[])],
            'evidence_sha'=>CareerInstitutionLedger::digest(['kind'=>'authenticated-fictional-admission','world'=>$world,'account'=>$account,'day'=>$clock['committed_day']])];
        $this->applyLocked($e,hash_hmac('sha256',CareerInstitutionLedger::json($e),$secret));
        return $this->viewLocked($world,$account,$clock);
    }
    public function admitForSession(string $world,string $token,string $secret,?string $reference=null,?array $fictional=null):array{
        return $this->tx(function()use($world,$token,$secret,$reference,$fictional){$clock=$this->clock($world);$a=$this->sessionAccount($token);$this->activeAccount($world,$a);return $this->admitLocked($world,$a,$clock,$secret,$reference,$fictional);});
    }
    /** A verified president/PM may perform bounded cabinet acts. No client
     * can choose the actor, date, institutional signature or expiry. */
    public function cabinetForSession(string $world,string $token,array $p,string $key,string $secret):array{
        CareerInstitutionLedger::fields($p,['kind','mandate','generation','target','portfolio','proposal']);
        CareerInstitutionLedger::check(is_string($p['kind'])&&is_string($p['mandate'])&&is_int($p['generation'])&&$p['generation']>=1&&preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key)===1,'invalid cabinet request');
        foreach(['target','portfolio','proposal'] as $k)CareerInstitutionLedger::check($p[$k]===null||is_string($p[$k]),'invalid cabinet fields');
        $command='cabinet-'.substr(hash('sha256',$key),0,48);
        return $this->tx(function()use($world,$token,$p,$command,$secret){
            $clock=$this->clock($world);$a=$this->sessionAccount($token);$this->activeAccount($world,$a);$this->replay($world,$this->row($world));
            $prior=$this->query('SELECT event_json,receipt_json FROM wp_career_events WHERE world_id=? AND actor_id=? AND command_id=?',[$world,$a,$command])->fetch(\PDO::FETCH_ASSOC);
            if($prior!==false){$e=self::decode($prior['event_json']);CareerInstitutionLedger::check($e['operation']==='cabinet_act'&&CareerInstitutionLedger::json($e['payload'])===CareerInstitutionLedger::json($p),'idempotency conflict');return self::decode($prior['receipt_json']);}
            $day=$clock['committed_day'];$e=['world'=>$world,'country'=>'FR','day'=>$day,'world_revision'=>(int)$clock['revision'],'command'=>$command,'actor'=>$a,'operation'=>'cabinet_act','payload'=>$p,
                'evidence_sha'=>CareerInstitutionLedger::digest(['kind'=>'session-cabinet-act-v1','world'=>$world,'day'=>$day,'actor'=>$a,'payload'=>$p])];
            return $this->applyLocked($e,hash_hmac('sha256',CareerInstitutionLedger::json($e),$secret));
        });
    }
    /** Server-side fictional ballot importer. No public route or competitive status. */
    public function certifyBallot(string $world,array $p,string $key,string $secret):array{
        CareerInstitutionLedger::check(preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key)===1,'invalid ballot command');
        $command='ballot-'.substr(hash('sha256',$key),0,48);
        return $this->tx(function()use($world,$p,$command,$secret){
            $clock=$this->clock($world);$this->replay($world,$this->row($world));
            $prior=$this->query("SELECT event_json,receipt_json FROM wp_career_events WHERE world_id=? AND actor_id='career-election-clerk' AND command_id=?",[$world,$command])->fetch(\PDO::FETCH_ASSOC);
            if($prior!==false){CareerInstitutionLedger::check(CareerInstitutionLedger::json(self::decode($prior['event_json'])['payload'])===CareerInstitutionLedger::json($p),'ballot idempotency conflict');return self::decode($prior['receipt_json']);}
            $day=$clock['committed_day'];$e=['world'=>$world,'country'=>'FR','day'=>$day,'world_revision'=>(int)$clock['revision'],'command'=>$command,'actor'=>'career-election-clerk','operation'=>'presidential_ballot','payload'=>$p,
                'evidence_sha'=>CareerInstitutionLedger::digest(['kind'=>'fictional-presidential-ballot-v1','world'=>$world,'day'=>$day,'payload'=>$p])];
            return $this->applyLocked($e,hash_hmac('sha256',CareerInstitutionLedger::json($e),$secret));
        });
    }
    /** No public signing route; admission and replay share the world fence. */
    private function seedAutonomousLocked(string $world,array $clock,string $secret):?array{
        $state=$this->replay($world,$this->row($world));$people=CareerInstitutionLedger::realAutonomousRoster($state);$ids=array_keys($people);
        $present=0;foreach($ids as $id)if(isset($state['careers'][$id])){
            CareerInstitutionLedger::check(isset($state['careers'][$id]['person_reference'])&&CareerInstitutionLedger::json($state['careers'][$id]['identity'])===CareerInstitutionLedger::json($people[$id]),'reserved real identity collision');$present++;
        }
        if($present===count($ids))return null;
        CareerInstitutionLedger::check($present===0,'partial autonomous roster');
        $day=$clock['committed_day'];$p=['people'=>$people];
        $e=['world'=>$world,'country'=>$state['country'],'day'=>$day,'world_revision'=>(int)$clock['revision'],'command'=>'seed-real-ai-roster-v1','actor'=>'career-ai-clerk','operation'=>'admit_real_ai_roster','payload'=>$p,
            'evidence_sha'=>CareerInstitutionLedger::digest(['kind'=>'real-ai-roster-v1','world'=>$world,'day'=>$day,'payload'=>$p])];
        return $this->applyLocked($e,hash_hmac('sha256',CareerInstitutionLedger::json($e),$secret));
    }
    public function seedAutonomousCareers(string $world,string $secret):?array{
        return $this->tx(fn()=>$this->seedAutonomousLocked($world,$this->clock($world),$secret));
    }
    /** Called by the operator scheduler on a committed day. The tally is
     * generated inside the same fence transaction as its certification. */
    public function settleDuePresidency(string $world,string $secret):?array{
        return $this->tx(function()use($world,$secret){
            $clock=$this->clock($world);$state=$this->replay($world,$this->row($world));$day=$clock['committed_day'];
            $p=CareerInstitutionLedger::automaticBallot($state,$day);if($p===null)return null;
            $event=['world'=>$world,'country'=>'FR','day'=>$day,'world_revision'=>(int)$clock['revision'],'command'=>'auto-election-'.$day,'actor'=>'career-election-clerk','operation'=>'presidential_ballot','payload'=>$p,
                'evidence_sha'=>CareerInstitutionLedger::digest(['kind'=>'fictional-presidential-ballot-v1','world'=>$world,'day'=>$day,'payload'=>$p])];
            return $this->applyLocked($event,hash_hmac('sha256',CareerInstitutionLedger::json($event),$secret));
        });
    }
    private function seedForeignOpening(string $world,string $secret):void{
        require_once __DIR__.'/CareerForeignWorld.php';
        $clock=$this->clock($world);$state=$this->replay($world,$this->row($world));$sim=CareerForeignWorld::opening($state);
        $this->query('INSERT INTO wp_institutional_states(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)',
            [$world,$state['country'],CareerForeignWorld::json($sim->snapshot()),$sim->hash()]);
        $p=['government_json'=>CareerForeignWorld::json($sim->snapshot()['accountability']),'expires'=>CareerForeignWorld::boundary($state['country'])];
        $e=['world'=>$world,'country'=>$state['country'],'day'=>$clock['committed_day'],'world_revision'=>(int)$clock['revision'],'command'=>'opening-scenario-v1','actor'=>'career-comparative-clerk','operation'=>'foreign_opening','payload'=>$p,
            'evidence_sha'=>CareerInstitutionLedger::digest(['kind'=>'comparative-opening-scenario-v1','world'=>$world,'country'=>$state['country'],'payload'=>$p])];
        $this->applyLocked($e,hash_hmac('sha256',CareerInstitutionLedger::json($e),$secret));
    }
    private function foreignInstitutionLocked(string $world,array $state,array $clock):array{
        require_once __DIR__.'/CareerForeignWorld.php';
        CareerInstitutionLedger::check($state['country']!=='FR'&&$state['mode']==='career','foreign career only');
        $profile=$this->query("SELECT scenario,reference_bundle_sha FROM wp_alpha_worlds WHERE world_id=? FOR UPDATE",[$world])->fetch(\PDO::FETCH_ASSOC);
        CareerInstitutionLedger::check($profile!==false&&$profile['scenario']==='wp-career-v1'&&$profile['reference_bundle_sha']===null,'isolated institutional profile only');
        $r=$this->query('SELECT state_json,state_sha,revision FROM wp_institutional_states WHERE world_id=? AND country_code=? FOR UPDATE',[$world,$state['country']])->fetch(\PDO::FETCH_ASSOC);
        CareerInstitutionLedger::check($r!==false,'no comparative institution');
        $sim=InstitutionalSimulation::restore(self::decode($r['state_json']));$snap=$sim->snapshot();
        CareerInstitutionLedger::check(hash_equals($r['state_sha'],$sim->hash())&&$snap['world_id']===$world&&$snap['country_code']===$state['country']&&$snap['day']===$clock['committed_day']&&isset($snap['accountability']),'foreign institutional source mismatch');
        return [$sim,$r];
    }
    private function publishForeignInstitution(string $world,string $country,InstitutionalSimulation $sim,array $prior,string $op,array $payload,array $result):array{
        $revision=(int)$prior['revision']+1;$sha=$sim->hash();
        $this->query('UPDATE wp_institutional_states SET state_json=?,state_sha=?,revision=? WHERE world_id=? AND country_code=?',
            [CareerForeignWorld::json($sim->snapshot()),$sha,$revision,$world,$country]);
        $this->query('INSERT INTO wp_institutional_journal(world_id,country_code,revision,actor_id,actor_role,operation,request_sha,before_sha,after_sha,result_json) VALUES(?,?,?,?,?,?,?,?,?,?)',
            [$world,$country,$revision,'career-comparative-clerk','simulation_clock',$op,CareerInstitutionLedger::digest($payload),$prior['state_sha'],$sha,CareerForeignWorld::json($result)]);
        return ['state'=>$sim->snapshot(),'etag'=>'"'.$sha.'"','revision'=>$revision];
    }
    /** No other subsystem runs in this isolated foreign profile. Atomically
     * commit the single institutional aggregate and the authoritative date. */
    public function tickForeignInstitution(string $world,string $target):array{
        CareerInstitutionLedger::day($target);
        return $this->tx(function()use($world,$target){
            $clock=$this->clock($world);$state=$this->replay($world,$this->row($world));
            [$sim,$prior]=$this->foreignInstitutionLocked($world,$state,$clock);
            if($target===$clock['committed_day'])return ['state'=>$sim->snapshot(),'etag'=>'"'.$prior['state_sha'].'"','revision'=>(int)$prior['revision']];
            CareerInstitutionLedger::check($target===(new \DateTimeImmutable($clock['committed_day']))->modify('+1 day')->format('Y-m-d'),'daily foreign tick required');
            self::assertClockAdvance($this->db,$world,$target);
            $result=$sim->advanceTo($target);
            $out=$this->publishForeignInstitution($world,$state['country'],$sim,$prior,'advance_clock',['target_day'=>$target],$result);
            $this->query('UPDATE wp_world_clock SET committed_day=?,revision=revision+1 WHERE world_id=?',[$target,$world]);
            return $out;
        });
    }
    /** Operator-only evidence; a raw session cannot certify votes. The
     * comparative rulebook verifies timing, chamber and succession before
     * the career projection is updated in the same transaction. */
    public function applyForeignEvidence(string $world,string $action,array $proof,string $secret):array{
        return $this->tx(function()use($world,$action,$proof,$secret){
            $clock=$this->clock($world);$state=$this->replay($world,$this->row($world));
            [$sim,$prior]=$this->foreignInstitutionLocked($world,$state,$clock);
            CareerInstitutionLedger::check(in_array($action,['accountability_file','accountability_vote','accountability_resolve'],true),'unsupported comparative evidence');
            foreach(['candidate','successor'] as $k)if(isset($proof[$k])){
                $career=$state['careers'][$proof[$k]]??null;
                CareerInstitutionLedger::check(is_array($career)&&($career['account']!==null||isset($career['person_reference'])),'unknown or fictional AI successor');
                if($career['account']!==null)$this->activeAccount($world,$career['account']);
            }
            $before=$sim->snapshot()['accountability'];$role=$action==='accountability_resolve'?'constitutional_clerk':'parliament_clerk';
            $result=$sim->accountability($role,$action,$proof,$proof['evidence_id']);
            $after=$sim->snapshot()['accountability'];
            $out=$this->publishForeignInstitution($world,$state['country'],$sim,$prior,$action,$proof,$result);
            $transition=($after['status']==='in_office'&&$after['incumbent']!==$before['incumbent'])
                ||(in_array($after['status'],['caretaker','election_pending','removed_successor_pending'],true)&&$after['status']!==$before['status']);
            if($transition){
                $p=['government_json'=>CareerForeignWorld::json($after),'expires'=>$after['status']==='in_office'?CareerForeignWorld::boundary($state['country']):null];
                $digest=CareerInstitutionLedger::digest(['kind'=>'comparative-government-replay-v1','world'=>$world,'country'=>$state['country'],'payload'=>$p]);
                $e=['world'=>$world,'country'=>$state['country'],'day'=>$clock['committed_day'],'world_revision'=>(int)$clock['revision'],'command'=>'foreign-'.substr($digest,0,48),'actor'=>'career-comparative-clerk','operation'=>'foreign_government','payload'=>$p,'evidence_sha'=>$digest];
                $out['career_receipt']=$this->applyLocked($e,hash_hmac('sha256',CareerInstitutionLedger::json($e),$secret));
            }
            return $out;
        });
    }
    /** Trusted service imports the exact committed comparative government
     * state. No foreign power is inferred from party labels or ownership. */
    public function synchronizeForeignGovernment(string $world,?string $expires,string $secret):array{
        return $this->tx(function()use($world,$expires,$secret){
            $clock=$this->clock($world);$state=$this->replay($world,$this->row($world));
            CareerInstitutionLedger::check($state['country']!=='FR'&&$state['mode']==='career','comparative world required');
            $r=$this->query('SELECT state_json,state_sha FROM wp_institutional_states WHERE world_id=? AND country_code=? FOR UPDATE',[$world,$state['country']])->fetch(\PDO::FETCH_ASSOC);
            CareerInstitutionLedger::check($r!==false,'no comparative institution');
            $inst=self::decode($r['state_json']);$gov=$inst['accountability']??throw new \DomainException('No comparative government');
            $p=['government_json'=>json_encode($gov,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'expires'=>$expires];
            $digest=CareerInstitutionLedger::digest(['kind'=>'comparative-government-replay-v1','world'=>$world,'country'=>$state['country'],'payload'=>$p]);
            $e=['world'=>$world,'country'=>$state['country'],'day'=>$clock['committed_day'],'world_revision'=>(int)$clock['revision'],'command'=>'foreign-'.substr($digest,0,48),'actor'=>'career-comparative-clerk','operation'=>'foreign_government','payload'=>$p,'evidence_sha'=>$digest];
            return $this->applyLocked($e,hash_hmac('sha256',CareerInstitutionLedger::json($e),$secret));
        });
    }
    /** A private career-world owner may invite an account as a member, not confer an office. */
    public function inviteForSession(string $world,string $token,string $target):array{
        CareerInstitutionLedger::id($target);
        return $this->tx(function()use($world,$token,$target){
            $this->clock($world);$a=$this->sessionAccount($token);$this->activeAccount($world,$a);
            $this->replay($world,$this->row($world));
            $w=$this->query('SELECT owner_id,scenario FROM wp_alpha_worlds WHERE world_id=? FOR UPDATE',[$world])->fetch(\PDO::FETCH_ASSOC);
            CareerInstitutionLedger::check($w!==false&&$w['scenario']==='wp-career-v1'&&$w['owner_id']===$a&&$target!==$a,'invitation denied');
            CareerInstitutionLedger::check((int)$this->query('SELECT enabled FROM wp_accounts WHERE account_id=? FOR UPDATE',[$target])->fetchColumn()===1,'target unavailable');
            $exists=$this->query('SELECT role FROM wp_alpha_members WHERE world_id=? AND account_id=?',[$world,$target])->fetchColumn();
            if($exists===false){
                CareerInstitutionLedger::check((int)$this->query('SELECT COUNT(*) FROM wp_alpha_members WHERE world_id=?',[$world])->fetchColumn()<64,'member limit');
                $this->query("INSERT INTO wp_alpha_members(world_id,account_id,role) VALUES(?,?,'viewer')",[$world,$target]);
            }
            return ['world_id'=>$world,'account_id'=>$target,'membership'=>'viewer','office_granted'=>false];
        });
    }
    /** Private institution-only sandbox; never grants legacy ownership powers. */
    public function createForSession(string $token,string $label,string $key,string $secret,string $profile='institutional',string $country='FR',?string $reference=null,?array $fictional=null):array{
        CareerInstitutionLedger::check(in_array($profile,['institutional','economic'],true)&&InstitutionalRulebook::hasRules($country)&&($country==='FR'||$profile==='institutional'),'unknown or unsupported career profile');
        CareerInstitutionLedger::check(trim($label)!==''&&strlen($label)<=100&&!preg_match('/[\x00-\x1f\x7f]/',$label)&&preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key)===1,'invalid career world');
        require_once __DIR__.'/CareerProgrammeAutonomy.php';
        CareerInstitutionLedger::check($reference===null||$fictional===null,'identity choices are exclusive');
        if($fictional!==null)$fictional=CareerProgrammeAutonomy::normalizeFictional($fictional,$country);
        $label=trim($label);$key=hash('sha256',"career-world-v1\n".$key);$fingerprint=CareerInstitutionLedger::digest(['label'=>$label,'scenario'=>'wp-career-v1',...($profile==='economic'?['profile'=>$profile]:[]),...($country!=='FR'?['country'=>$country]:[]),...($reference!==null?['reference_id'=>$reference]:[]),...($fictional!==null?['fictional_profile'=>$fictional]:[])]);
        return $this->tx(function()use($token,$label,$key,$fingerprint,$secret,$profile,$country,$reference,$fictional){
            $a=$this->sessionAccount($token);
            $prior=$this->query('SELECT world_id,request_sha,scenario FROM wp_alpha_worlds WHERE owner_id=? AND create_key=?',[$a,$key])->fetch(\PDO::FETCH_ASSOC);
            if($prior!==false){CareerInstitutionLedger::check($prior['scenario']==='wp-career-v1'&&hash_equals($prior['request_sha'],$fingerprint),'idempotency conflict');return ['world_id'=>$prior['world_id'],'scenario'=>'wp-career-v1','label'=>$label];}
            CareerInstitutionLedger::check((int)$this->query('SELECT COUNT(*) FROM wp_alpha_worlds WHERE owner_id=?',[$a])->fetchColumn()<10,'world limit');
            $world='world-'.bin2hex(random_bytes(16));$day=CareerScoreContract::START;
            $this->query('INSERT INTO wp_alpha_worlds(world_id,owner_id,create_key,request_sha,label,scenario) VALUES(?,?,?,?,?,?)',[$world,$a,$key,$fingerprint,$label,'wp-career-v1']);
            $this->query("INSERT INTO wp_alpha_members(world_id,account_id,role) VALUES(?,?,'owner')",[$world,$a]);
            $this->query('INSERT INTO wp_world_clock(world_id,committed_day,revision) VALUES(?,?,0)',[$world,$day]);
            WorldMutationFence::seed($this->db,$world);
            $s=CareerInstitutionLedger::initial($world,$country,'career',$day);$j=CareerInstitutionLedger::json($s);
            $this->query('INSERT INTO wp_career_ledgers(world_id,initial_json,state_json,state_sha) VALUES(?,?,?,?)',[$world,$j,$j,CareerInstitutionLedger::digest($s)]);
            if($profile==='economic'){require_once __DIR__.'/CareerEconomicWorld.php';CareerEconomicWorld::seed($this->db,$world,$day);}
            $this->seedAutonomousLocked($world,['committed_day'=>$day,'revision'=>0],$secret);
            if($country!=='FR')$this->seedForeignOpening($world,$secret);
            $this->admitLocked($world,$a,['committed_day'=>$day,'revision'=>0],$secret,$reference,$fictional);
            return ['world_id'=>$world,'scenario'=>'wp-career-v1','label'=>$label];
        });
    }
    public function proposePolicyForSession(string $world,string $token,array $p,string $key,string $etag):array{
        CareerInstitutionLedger::fields($p,['mandate','generation','policy_id','effective_day']);
        CareerInstitutionLedger::check(is_string($p['mandate'])&&is_int($p['generation'])&&$p['generation']>=1&&is_string($p['policy_id'])&&is_string($p['effective_day']),'invalid proposal fields');
        // Explicit reviewed portfolio mapping; no implicit right to arbitrary
        // budgets, industrial acts or policy enactments.
        $portfolio=match($p['policy_id']){'education-reform'=>'education','administration-report'=>null,default=>throw new \DomainException('Policy not mapped to career powers')};
        return $this->tx(function()use($world,$token,$p,$key,$etag,$portfolio){
            $clock=$this->clock($world);$a=$this->sessionAccount($token);$this->activeAccount($world,$a);$s=$this->replay($world,$this->row($world));
            $day=$clock['committed_day'];$ok=CareerInstitutionLedger::allows($s,$a,$p['mandate'],$p['generation'],'government.coordinate',$day);
            if($portfolio!==null)$ok=$ok||CareerInstitutionLedger::allows($s,$a,$p['mandate'],$p['generation'],'ministry.'.$portfolio.'.propose',$day);
            CareerInstitutionLedger::check($ok,'policy proposal capability denied');
            $sha=$this->query("SELECT reference_bundle_sha FROM wp_alpha_worlds WHERE world_id=? AND scenario='wp-career-v1'",[$world])->fetchColumn();
            CareerInstitutionLedger::check(is_string($sha),'economic profile required');
            require_once __DIR__.'/CareerEconomicWorld.php';
            $principal=new CareerEconomicProposalPrincipal($this->db,$world,$p['mandate']);
            $dom=new PdoDomesticPolicyStore($this->db,$principal);
            return $dom->proposeInCareerTransaction($world,['policy_id'=>$p['policy_id'],'effective_day'=>$p['effective_day']],$key,$etag);
        });
    }
    public function institutionsForSession(string $world,string $token):array{
        return $this->tx(function()use($world,$token){
            $clock=$this->clock($world);$account=$this->sessionAccount($token);$this->activeAccount($world,$account);$state=$this->replay($world,$this->row($world));
            if($state['country']!=='FR'){
                [$sim,$prior]=$this->foreignInstitutionLocked($world,$state,$clock);
                return ['state'=>$sim->snapshot(),'etag'=>'"'.$prior['state_sha'].'"','revision'=>(int)$prior['revision']];
            }
            require_once __DIR__.'/CareerEconomicWorld.php';
            $r=(new PdoInstitutionalStore($this->db,new CareerEconomicClockPrincipal($this->db,$world)))->read($world,'FR');
            CareerInstitutionLedger::check($r['state']['day']===$clock['committed_day'],'institution publication mismatch');return $r;
        });
    }
    public function submitBillForSession(string $world,string $token,array $p,string $key,string $etag):array{
        CareerInstitutionLedger::fields($p,['mandate','generation','title']);
        CareerInstitutionLedger::check(is_string($p['mandate'])&&is_int($p['generation'])&&$p['generation']>=1&&is_string($p['title']),'invalid bill fields');
        return $this->tx(function()use($world,$token,$p,$key,$etag){
            $clock=$this->clock($world);$account=$this->sessionAccount($token);$this->activeAccount($world,$account);$state=$this->replay($world,$this->row($world));
            $day=$clock['committed_day'];$role=null;
            if(CareerInstitutionLedger::allows($state,$account,$p['mandate'],$p['generation'],'government.coordinate',$day))$role='prime_minister';
            foreach(['assembly.propose','senate.propose'] as $ability)if(CareerInstitutionLedger::allows($state,$account,$p['mandate'],$p['generation'],$ability,$day))$role='member_of_parliament';
            CareerInstitutionLedger::check($role!==null&&$state['assembly']==='active','legislative mandate required');
            $sha=$this->query("SELECT reference_bundle_sha FROM wp_alpha_worlds WHERE world_id=? AND scenario='wp-career-v1'",[$world])->fetchColumn();CareerInstitutionLedger::check(is_string($sha),'economic profile required');
            require_once __DIR__.'/CareerEconomicWorld.php';
            return (new PdoInstitutionalStore($this->db,new CareerLegislativePrincipal($this->db,$world,$p['mandate'],$role)))->submitInCareerTransaction($world,['title'=>$p['title']],$key,$etag);
        });
    }
    /** One pre-tick proposal under the same world fence as player commands.
     * Durable command journals suppress same-day human replacement. An
     * autonomous proposal is not enactment and has a distinct service actor. */
    /** Fenced scheduler, never exposed as a session action. */
    public function settleAutomaticCabinet(string $world,string $secret):?array{
        return $this->tx(function()use($world,$secret){
            $clock=$this->clock($world);$state=$this->replay($world,$this->row($world));$day=$clock['committed_day'];
            $p=CareerInstitutionLedger::automaticCabinet($state,$day);if($p===null)return null;
            $e=['world'=>$world,'country'=>'FR','day'=>$day,'world_revision'=>(int)$clock['revision'],'command'=>'automatic-cabinet-'.$day,'actor'=>'career-ai-clerk','operation'=>'automatic_cabinet','payload'=>$p,
                'evidence_sha'=>CareerInstitutionLedger::digest(['kind'=>'automatic-cabinet-v1','world'=>$world,'day'=>$day,'actor'=>'career-ai-clerk','payload'=>$p])];
            return $this->applyLocked($e,hash_hmac('sha256',CareerInstitutionLedger::json($e),$secret));
        });
    }
    public function settleProgrammeProposal(string $world):?array{
        return $this->tx(function()use($world){
            $clock=$this->clock($world);$state=$this->replay($world,$this->row($world));$day=$clock['committed_day'];
            if($state['country']!=='FR'||$state['mode']!=='career')return null;
            $row=$this->query('SELECT assumptions_json FROM wp_domestic_states WHERE world_id=? FOR UPDATE',[$world])->fetch(\PDO::FETCH_ASSOC);
            if($row===false)return null;
            require_once __DIR__.'/CareerEconomicWorld.php';
            require_once __DIR__.'/CareerProgrammeAutonomy.php';
            $acted=[];$accounts=[];
            foreach($state['careers'] as $id=>$career)if($career['account']!==null)$accounts[$career['account']]=$id;
            foreach($this->query('SELECT actor_id,event_json FROM wp_career_events WHERE world_id=?',[$world]) as $event){
                if(!isset($accounts[$event['actor_id']]))continue;
                $e=self::decode($event['event_json']);
                if($e['day']===$day&&$e['operation']==='cabinet_act')$acted[$accounts[$event['actor_id']]]=true;
            }
            foreach($this->query("SELECT principal_id,result_json FROM wp_domestic_journal WHERE world_id=? AND operation='propose'",[$world]) as $entry){
                $result=self::decode($entry['result_json']);
                if(($result['policy']['proposed_on']??null)!==$day)continue;
                $actor=$entry['principal_id'];
                if(str_starts_with($actor,'career-auto-'))return null;
                if(str_starts_with($actor,'career-')&&isset($state['mandates'][substr($actor,7)]))$acted[$state['mandates'][substr($actor,7)]['career']]=true;
            }
            $ir=$this->query("SELECT state_json,state_sha FROM wp_institutional_states WHERE world_id=? AND country_code='FR' FOR UPDATE",[$world])->fetch(\PDO::FETCH_ASSOC);
            CareerInstitutionLedger::check($ir!==false,'institutional source required');
            $inst=InstitutionalSimulation::restore(self::decode($ir['state_json']));
            CareerInstitutionLedger::check(hash_equals($ir['state_sha'],$inst->hash())&&$inst->snapshot()['day']===$day,'institutional source mismatch');
            $bills=$inst->snapshot()['bills'];
            foreach($this->query("SELECT actor_id,result_json FROM wp_institutional_journal WHERE world_id=? AND country_code='FR' AND operation='submit_bill'",[$world]) as $entry){
                $r=self::decode($entry['result_json']);$mid=substr($entry['actor_id'],7);
                if(str_starts_with($entry['actor_id'],'career-')&&isset($state['mandates'][$mid])&&($bills[$r['bill_id']]['filed_on']??null)===$day)
                    $acted[$state['mandates'][$mid]['career']]=true;
            }
            $reader=new CareerEconomicProposalPrincipal($this->db,$world,'reader',true);
            $d=(new PdoDomesticPolicyStore($this->db,$reader))->read($world);
            CareerInstitutionLedger::check($d['state']['day']===$day,'domestic source mismatch');
            $proposal=CareerProgrammeAutonomy::next($state,$day,$d['state']['policies'],self::decode($row['assumptions_json'])['catalog'],$acted);
            if($proposal===null)return null;
            $principal=new CareerEconomicProposalPrincipal($this->db,$world,$proposal['mandate'],true);
            $key='career-auto-'.substr(hash('sha256',$world.'|'.$day.'|'.$proposal['mandate'].'|'.$proposal['policy_id']),0,48);
            return (new PdoDomesticPolicyStore($this->db,$principal))->proposeInCareerTransaction($world,
                ['policy_id'=>$proposal['policy_id'],'effective_day'=>$proposal['effective_day']],$key,$d['etag']);
        });
    }
    public function economicForSession(string $world,string $token):array{
        return $this->tx(function()use($world,$token){
            $clock=$this->clock($world);$a=$this->sessionAccount($token);$this->activeAccount($world,$a);$this->replay($world,$this->row($world));
            require_once __DIR__.'/CareerEconomicWorld.php';return CareerEconomicWorld::readLocked($this->db,$world,$clock['committed_day']);
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
