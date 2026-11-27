<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/AlphaWorlds.php';
require_once __DIR__.'/PresidentialProgramme.php';
require_once __DIR__.'/ProgrammeProgress.php';
require_once __DIR__.'/UniverseIdentityReference.php';

/** Managed alpha universe pool. A claimed slot is control, not a new president. */
final class UniverseLobby
{
    // National institutional clocks keep their existing pinned seven-country roster.
    public const COUNTRIES=['DE','ES','FR','GB','IT','JP','US'];
    public const DIPLOMATIC_COUNTRIES=['AT','AU','BE','BG','BR','CA','CH','CN','CY','CZ','DE','DK','EE','ES','FI','FR','GB','GR','HR','HU','ID','IE','IN','IT','JP','KR','LT','LU','LV','MT','MX','NL','PL','PT','RO','RU','SA','SE','SI','SK','TR','US','ZA'];
    public const ROSTER_VERSION='wp111-diplomatic-roster-v2';
    public static function roster(string $version):array
    {
        return match($version){'wp102-pilot-v1'=>self::COUNTRIES,self::ROSTER_VERSION,UniverseIdentityReference::BOUND_ROSTER,UniverseIdentityReference::DOCUMENTARY_ROSTER=>self::DIPLOMATIC_COUNTRIES,default=>throw new \DomainException('Unknown universe roster')};
    }
    public function __construct(private readonly \PDO $db){}
    private function query(string $sql,array $args=[]):\PDOStatement
    {$q=$this->db->prepare($sql);$q->execute($args);return $q;}
    private function transaction(callable $fn):array
    {
        $this->db->beginTransaction();
        try{
            if((int)$this->query('SELECT pool_id FROM wp_universe_pool WHERE pool_id=1 FOR UPDATE')->fetchColumn()!==1)
                throw new \LogicException('Missing universe allocation lock');
            $r=$fn();$this->db->commit();return $r;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function list(AlphaSession $s,int $page=1):array
    {
        if(!$s->active())throw new AlphaHttpError(401,'authentication_required');
        if($page<1||$page>100000)throw new AlphaHttpError(422,'invalid_page');
        $offset=($page-1)*50;
        $rows=$this->query("SELECT u.universe_no,u.world_id,u.tick_seconds,u.next_tick_epoch,u.roster_version,c.committed_day,c.pending_day,c.game_over
            FROM wp_universes u JOIN wp_world_clock c ON c.world_id=u.world_id ORDER BY u.universe_no LIMIT 50 OFFSET ".$offset)->fetchAll(\PDO::FETCH_ASSOC);
        foreach($rows as &$r){
            $r['universe_no']=(int)$r['universe_no'];
            $slots=$this->query('SELECT country_code,account_id,diplomacy_programme,domestic_programme,closed_on FROM wp_universe_slots WHERE world_id=? ORDER BY country_code',[$r['world_id']])->fetchAll(\PDO::FETCH_ASSOC);
            $r['slots']=array_map(fn($v)=>['country'=>$v['country_code'],'controller'=>$v['closed_on']!==null?'succession_pending':($v['account_id']===null?'ai':'human'),'is_you'=>$v['account_id']===$s->id,'diplomacy_programme'=>$v['diplomacy_programme'],...($v['country_code']==='FR'?['domestic_programme'=>$v['domestic_programme']]:[])],$slots);
            $r['vacant']=count(array_filter($r['slots'],fn($v)=>$v['controller']==='ai'));
            $r['joinable']=$r['pending_day']===null&&(int)$r['game_over']===0&&$r['vacant']>0;
            unset($r['pending_day'],$r['game_over']);
        }unset($r);
        return ['universes'=>$rows,'page'=>$page,'playable_country_pilot'=>self::DIPLOMATIC_COUNTRIES,'complete_multiplayer_model'=>false,'domestic_programme_profiles'=>PresidentialProgramme::PROFILES,'executable_measure_catalog'=>PresidentialProgramme::measureCatalog(),'historical_social_catalog'=>SocialProgramme::definitions(),'social_lifecycle_catalog'=>SocialProgramme::objectives(),'primary_quantitative_catalog'=>PrimaryManifestoPolicy::definitions(),'target_2027_catalog'=>SocialProgramme::target2027(),'primary_constitutional_catalog'=>PresidentialProgramme::primaryRevisionCatalog(),'opening_candidate_selection'=>['country'=>'FR','day'=>FrenchElectionCalendar::PRESIDENTIAL_HANDOVER,'first_control_only'=>true,'options'=>CampaignCandidate::options()],'country_capabilities'=>array_map(fn($c)=>['country'=>$c,'diplomacy'=>true,'domestic_policy'=>$c==='FR','experimental_income_score'=>$c==='FR','national_institution_clock'=>in_array($c,self::COUNTRIES,true),'full_national_simulation'=>false],self::DIPLOMATIC_COUNTRIES)];
    }
    /** A coherent documentary view alongside the existing national slot.
     * Legacy slots are not silently rebound to a person or a new mandate. */
    public function characters(AlphaSession $s,int $no,string $country):array{
        if(!$s->active())throw new AlphaHttpError(401,'authentication_required');
        if(!in_array($country,self::DIPLOMATIC_COUNTRIES,true))throw new AlphaHttpError(404,'not_found');
        return $this->transaction(function()use($s,$no,$country){
            $world=$this->query('SELECT world_id FROM wp_universes WHERE universe_no=?',[$no])->fetchColumn();
            if(!is_string($world))throw new AlphaHttpError(404,'not_found');
            $clock=$this->lockWorld($world);
            $slot=$this->query('SELECT account_id,generation,closed_on FROM wp_universe_slots WHERE world_id=? AND country_code=? FOR UPDATE',[$world,$country])->fetch(\PDO::FETCH_ASSOC);
            if($slot===false)throw new AlphaHttpError(404,'not_found');
            $opening=null;$runtime=false;$challengers=[];$scope='documentary_only';
            $version=$this->query('SELECT roster_version FROM wp_universes WHERE world_id=?',[$world])->fetchColumn();
            if($country==='FR'){
                $r=$this->query("SELECT state_json,state_sha FROM wp_world_rosters WHERE world_id=? AND country_code='FR' FOR UPDATE",[$world])->fetch(\PDO::FETCH_ASSOC);
                if($r!==false){
                    $snapshot=json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR);
                    $roster=PoliticalRoster::fromSnapshot($snapshot);
                    if(!hash_equals($r['state_sha'],$roster->snapshotHash())||$snapshot['world_id']!==$world||$snapshot['country_code']!=='FR')throw new \DomainException('Invalid opening identity source');
                    $person=$snapshot['people'][CampaignLifecycle::PLAYER]??null;
                    if($person!==null&&$person['name']!=='Scenario president'){
                        $opening=['name'=>$person['name'],'kind'=>$person['kind'],'as_of'=>$person['as_of'],'sources'=>$person['sources']];
                        if(UniverseIdentityReference::isBound($version)){
                            $op=$this->query('SELECT state_json FROM wp_opinion_states WHERE world_id=?',[$world])->fetchColumn();
                            if(is_string($op)){
                                $os=json_decode($op,true,64,JSON_THROW_ON_ERROR);
                                $ordinal=count($os['managed_mandates']??[])+1;
                                $runtime=$slot['closed_on']===null;$scope='scenario_presidency';
                                $challengers=UniverseIdentityReference::frenchChallengers($world,$ordinal,$person['name']);
                            }
                        }
                    }
                }
            }
            if($country!=='FR'&&UniverseIdentityReference::isBound($version)){
                $p=UniverseIdentityReference::openingPerson($world,$country);
                if($p!==null){
                    $opening=['name'=>$p['name'],'kind'=>$p['kind'],'as_of'=>$p['as_of'],'sources'=>$p['sources']];
                    $scope='opening_observation_only';
                    if(in_array($country,self::COUNTRIES,true)){
                        $r=$this->query('SELECT state_json,state_sha FROM wp_institutional_states WHERE world_id=? AND country_code=?',[$world,$country])->fetch(\PDO::FETCH_ASSOC);
                        if($r!==false){
                            $sim=InstitutionalSimulation::restore(json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR));
                            if(!hash_equals($r['state_sha'],$sim->hash()))throw new \DomainException('Invalid national institution source');
                            $gov=$sim->snapshot()['accountability'];
                            $runtime=$gov['incumbent']===$p['id']&&$gov['status']==='in_office';
                            if($version===UniverseIdentityReference::DOCUMENTARY_ROSTER&&$gov['status']==='in_office'){
                                $person=DocumentaryForeignBallot::people($country)[$gov['incumbent']]??null;
                                if($person!==null){
                                    $opening=['name'=>$person['name'],'kind'=>'real','as_of'=>$person['as_of'],'sources'=>$person['sources']];$runtime=true;
                                }
                            }
                            $scope=$runtime?'scenario_government':'unresolved_current_identity';
                        }
                    }
                }
            }
            return ['universe_no'=>$no,'world_id'=>$world,'day'=>$clock['committed_day'],'country'=>$country,
                'controller'=>$slot['closed_on']!==null?'succession_pending':($slot['account_id']===null?'ai':'human'),
                'generation'=>(int)$slot['generation'],'is_you'=>$slot['account_id']===$s->id,
                'opening_identity'=>$opening,'runtime_identity_bound'=>$runtime,'identity_scope'=>$scope,'scenario_challengers'=>$challengers,
                'reference'=>UniverseIdentityReference::country($country),...($country!=='FR'&&in_array($country,self::COUNTRIES,true)&&$version===UniverseIdentityReference::DOCUMENTARY_ROSTER?['documentary_parties'=>DocumentaryForeignBallot::reference($country)]:[])];
        });
    }
    private function create():array
    {
        $bundle=GlobalReferenceBundle::published();$sha=(new PdoGlobalReferenceBundles($this->db))->install($bundle);
        $scenario=new AlphaScenario(AlphaScenario::INVESTITURE,$bundle,$sha);
        $r=(new AlphaWorlds($this->db,$scenario))->provisionUniverse(true);$world=$r['world_id'];
        $this->query('INSERT INTO wp_universes(world_id,roster_version) VALUES(?,?)',[$world,UniverseIdentityReference::DOCUMENTARY_ROSTER]);$no=(int)$this->db->lastInsertId();
        foreach(self::DIPLOMATIC_COUNTRIES as $country){
            if(!$scenario->actors->isHumanPlayable('state_'.strtolower($country)))
                throw new \LogicException('Unreviewed playable slot');
            $this->query('INSERT INTO wp_universe_slots(world_id,country_code,domestic_programme) VALUES(?,?,?)',[$world,$country,$country==='FR'?'governance':'manual']);
            if(!in_array($country,self::COUNTRIES,true)){
                $p=UniverseIdentityReference::openingPerson($world,$country);
                $roster=new PoliticalRoster($world,$country,['government']);if($p!==null)$roster->addPerson($p);
                $this->query('INSERT INTO wp_world_rosters(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)',
                    [$world,$country,json_encode($roster->snapshot(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$roster->snapshotHash()]);
            }
        }
        return ['universe_no'=>$no,'world_id'=>$world];
    }
    private function ensureSpare():?array
    {
        // Create immediately if every non-terminal universe is full; a pending
        // day is not a reason to allocate unbounded replacement universes.
        $q=$this->query('SELECT 1 FROM wp_universe_slots s JOIN wp_world_clock c ON c.world_id=s.world_id WHERE s.account_id IS NULL AND s.closed_on IS NULL AND c.game_over=0 LIMIT 1');
        return $q->fetchColumn()===false?$this->create():null;
    }
    private function lockWorld(string $world):array
    {
        $f=WorldMutationFence::lockCoordinator($this->db,$world);
        $c=$this->query('SELECT committed_day,pending_day,game_over FROM wp_world_clock WHERE world_id=? FOR UPDATE',[$world])->fetch(\PDO::FETCH_ASSOC);
        if($c===false||$f['state']!=='idle'||$c['pending_day']!==null)throw new AlphaHttpError(409,'world_advancing');
        return $c;
    }
    public function join(AlphaSession $s,array $body,string $key):array
    {
        AlphaAccounts::fields($body,['country',...(array_key_exists('universe_no',$body)?['universe_no']:[]),...(array_key_exists('candidate',$body)?['candidate']:[])]);
        $country=$body['country'];$no=$body['universe_no']??null;
        if(!is_string($country)||!in_array($country,self::DIPLOMATIC_COUNTRIES,true)||($no!==null&&(!is_int($no)||$no<1))
            ||!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key))throw new AlphaHttpError(422,'invalid_universe_join');
        $candidate=null;$openingProgramme=null;$candidateMayStart=true;
        if(array_key_exists('candidate',$body)){
            if($country!=='FR')throw new AlphaHttpError(422,'candidate_selection_france_only');
            if(CampaignCandidate::discoveryOnly($body['candidate']))throw new AlphaHttpError(409,'candidate_programme_unavailable');
            try{$candidate=CampaignCandidate::normalizeManaged($body['candidate']);
                $candidateMayStart=CampaignCandidate::mayStart($body['candidate']);
                if($body['candidate']['type']==='campaign2027')$openingProgramme=PresidentialProgramme::compileProfile(array_column($candidate['programme'],'id'));}
            catch(\InvalidArgumentException|\DomainException $e){throw new AlphaHttpError(422,'invalid_candidate');}
        }
        $key=hash('sha256',$key);$fingerprint=hash('sha256',json_encode([$country,$no,...($candidate===null?[]:[$candidate])],JSON_THROW_ON_ERROR));
        return $this->transaction(function()use($s,$country,$no,$key,$fingerprint,$candidate,$openingProgramme,$candidateMayStart){
            if(!$s->active())throw new AlphaHttpError(401,'authentication_required');
            $prior=$this->query('SELECT request_sha,response_json FROM wp_universe_join_receipts WHERE account_id=? AND receipt_key=?',[$s->id,$key])->fetch(\PDO::FETCH_ASSOC);
            if($prior!==false){
                if(!hash_equals($prior['request_sha'],$fingerprint))throw new AlphaHttpError(409,'idempotency_conflict');
                $receipt=json_decode($prior['response_json'],true,32,JSON_THROW_ON_ERROR);
                $this->lockWorld($receipt['world_id']);
                $slot=$this->query('SELECT account_id,generation,closed_on FROM wp_universe_slots WHERE world_id=? AND country_code=? FOR UPDATE',[$receipt['world_id'],$receipt['country']])->fetch(\PDO::FETCH_ASSOC);
                if($slot===false||$slot['account_id']!==$s->id||(int)$slot['generation']!==$receipt['generation']||$slot['closed_on']!==null||!$s->active())
                    throw new AlphaHttpError(409,'stale_join_receipt');
                return $receipt;
            }
            if(!$candidateMayStart)throw new AlphaHttpError(409,'candidate_programme_unavailable');
            if($no===null){
                $this->ensureSpare();
                // Legacy spare worlds have no expanded slots. Allocate one
                // expanded reserve if none exists, not on every failed claim.
                if(!in_array($country,self::COUNTRIES,true)&&$this->query('SELECT 1 FROM wp_universes WHERE roster_version IN (?,?,?) LIMIT 1',[self::ROSTER_VERSION,UniverseIdentityReference::BOUND_ROSTER,UniverseIdentityReference::DOCUMENTARY_ROSTER])->fetchColumn()===false)$this->create();
            }
            $args=[$country,$s->id];
            $sql="SELECT u.universe_no,u.world_id FROM wp_universes u JOIN wp_universe_slots s ON s.world_id=u.world_id
                JOIN wp_world_clock c ON c.world_id=u.world_id WHERE s.country_code=? AND s.closed_on IS NULL AND (s.account_id IS NULL OR s.account_id=?)
                AND c.game_over=0";
            if($no!==null){$sql.=' AND u.universe_no=?';$args[]=$no;}
            if($candidate!==null){$sql.=' AND s.generation=0 AND s.account_id IS NULL AND c.committed_day=?';$args[]=FrenchElectionCalendar::PRESIDENTIAL_HANDOVER;}
            $sql.=" AND NOT EXISTS(SELECT 1 FROM wp_universe_slots x WHERE x.world_id=u.world_id AND x.account_id=? AND x.country_code<>?) ORDER BY u.universe_no LIMIT 1";
            $args[]=$s->id;$args[]=$country;
            $u=$this->query($sql,$args)->fetch(\PDO::FETCH_ASSOC);
            if($u===false)throw new AlphaHttpError(409,'country_unavailable');
            $world=$u['world_id'];$c=$this->lockWorld($world);
            if((int)$c['game_over']===1)throw new AlphaHttpError(409,'game_over');
            $slot=$this->query('SELECT account_id,generation,closed_on FROM wp_universe_slots WHERE world_id=? AND country_code=? FOR UPDATE',[$world,$country])->fetch(\PDO::FETCH_ASSOC);
            if(!$s->active())throw new AlphaHttpError(401,'authentication_required');
            if($slot['closed_on']!==null)throw new AlphaHttpError(409,'succession_pending');
            if($slot['account_id']!==null&&$slot['account_id']!==$s->id)throw new AlphaHttpError(409,'country_unavailable');
            $gen=(int)$slot['generation'];$spare=null;
            if($candidate!==null){
                if($gen!==0||$slot['account_id']!==null||$c['committed_day']!==FrenchElectionCalendar::PRESIDENTIAL_HANDOVER)throw new AlphaHttpError(409,'candidate_requires_opening_slot');
                if($openingProgramme!==null){
                    $raw=$this->query('SELECT assumptions_json FROM wp_domestic_states WHERE world_id=?',[$world])->fetchColumn();
                    $cfg=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
                    foreach(PresidentialProgramme::socialIds($openingProgramme)??[] as $id)
                        if(SocialProgramme::assumption($id,$cfg['catalog'],isset($cfg['social_economy_from']))===null)
                            throw new AlphaHttpError(409,'social_programme_requires_new_universe');
                }
                $this->openingCandidate($world,$s->id,$candidate,$fingerprint);
                if($openingProgramme!==null)$this->query("UPDATE wp_universe_slots SET domestic_programme=?,programme_set_on=? WHERE world_id=? AND country_code='FR'",[$openingProgramme,$c['committed_day'],$world]);
            }
            if($slot['account_id']===null){
                ++$gen;
                $this->query('UPDATE wp_universe_slots SET account_id=?,generation=?,last_human_diplomacy_day=NULL,last_human_domestic_day=NULL WHERE world_id=? AND country_code=?',[$s->id,$gen,$world,$country]);
                $this->query('INSERT INTO wp_universe_control_periods(world_id,country_code,generation,account_id,joined_on) VALUES(?,?,?,?,?)',[$world,$country,$gen,$s->id,$c['committed_day']]);
                $this->query("INSERT INTO wp_alpha_members(world_id,account_id,role) VALUES(?,?,'viewer')",[$world,$s->id]);
                UniverseScore::claim($this->db,$world,$country,$gen,$c['committed_day']);
                $this->query('UPDATE wp_universes SET next_game_day=DATE_ADD(?,INTERVAL 1 DAY),next_tick_epoch=UNIX_TIMESTAMP()+tick_seconds WHERE world_id=? AND next_tick_epoch IS NULL',[$c['committed_day'],$world]);
                $spare=$this->ensureSpare();
            }
            $r=['universe_no'=>(int)$u['universe_no'],'world_id'=>$world,'country'=>$country,'generation'=>$gen,
                'handover_day'=>$c['committed_day'],'new_universe'=>$spare,'state_reset'=>false,...($candidate===null?[]:['opening_candidate'=>['name'=>$candidate['name'],'kind'=>$candidate['kind'],'programme_count'=>count($candidate['programme']),'historical_alternate_history'=>$candidate['kind']==='real'&&$openingProgramme===null,...($openingProgramme===null?[]:['campaign_2027_provisional'=>true,'domestic_programme'=>$openingProgramme,'legal_effect'=>false])]])];
            $this->query('INSERT INTO wp_universe_join_receipts(account_id,receipt_key,request_sha,response_json) VALUES(?,?,?,?)',
                [$s->id,$key,$fingerprint,json_encode($r,JSON_THROW_ON_ERROR)]);
            return $r;
        });
    }
    private function openingCandidate(string $world,string $actor,array $candidate,string $fingerprint):void
    {
        $row=$this->query("SELECT state_json,state_sha,revision FROM wp_world_rosters WHERE world_id=? AND country_code='FR' FOR UPDATE",[$world])->fetch(\PDO::FETCH_ASSOC);
        if($row===false)throw new \DomainException('Missing opening roster');
        $state=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);$old=PoliticalRoster::fromSnapshot($state);
        if(!hash_equals($row['state_sha'],$old->snapshotHash())||$state['world_id']!==$world||$state['country_code']!=='FR')throw new \DomainException('Invalid opening roster');
        $placeholder=$state['people'][CampaignLifecycle::PLAYER]??null;
        $legacy=($placeholder['name']??null)==='Scenario president'&&($placeholder['kind']??null)==='fictional';
        $version=$this->query('SELECT roster_version FROM wp_universes WHERE world_id=?',[$world])->fetchColumn();
        $bound=UniverseIdentityReference::isBound($version)&&$placeholder===UniverseIdentityReference::openingPerson($world,'FR');
        if((int)$row['revision']!==0||count($state['people'])!==1||$state['head_of_state']!==CampaignLifecycle::PLAYER
            ||$state['cabinet']!==[]||$state['pending_nominations']!==[]||(!$legacy&&!$bound)||$placeholder['programme']!==[])throw new AlphaHttpError(409,'candidate_requires_opening_slot');
        $state['people'][CampaignLifecycle::PLAYER]=$candidate;$new=PoliticalRoster::fromSnapshot($state);
        $after=$new->snapshotHash();$json=static fn(array $v):string=>json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $this->query("UPDATE wp_world_rosters SET state_json=?,state_sha=?,revision=1 WHERE world_id=? AND country_code='FR' AND revision=0",[$json($new->snapshot()),$after,$world]);
        $this->query("INSERT INTO wp_world_events(world_id,country_code,revision,actor_id,operation,request_sha,before_sha,after_sha,result_json) VALUES(?,'FR',1,?,'opening_candidate_selection',?,?,?,?)",[$world,$actor,$fingerprint,$row['state_sha'],$after,$json(['identity'=>$candidate,'selected_on'=>FrenchElectionCalendar::PRESIDENTIAL_HANDOVER,'score_rules_changed'=>false])]);
    }
    public function leave(AlphaSession $s,int $no):array
    {
        return $this->transaction(function()use($s,$no){
            if(!$s->active())throw new AlphaHttpError(401,'authentication_required');
            $world=$this->query('SELECT world_id FROM wp_universes WHERE universe_no=?',[$no])->fetchColumn();
            if(!is_string($world))throw new AlphaHttpError(404,'not_found');
            $c=$this->lockWorld($world);
            $slot=$this->query('SELECT country_code,generation FROM wp_universe_slots WHERE world_id=? AND account_id=? FOR UPDATE',[$world,$s->id])->fetch(\PDO::FETCH_ASSOC);
            if($slot===false)throw new AlphaHttpError(404,'not_found');
            $this->query('UPDATE wp_universe_control_periods SET left_on=? WHERE world_id=? AND country_code=? AND generation=? AND left_on IS NULL',[$c['committed_day'],$world,$slot['country_code'],$slot['generation']]);
            $this->query('UPDATE wp_universe_slots SET account_id=NULL,last_human_diplomacy_day=NULL,last_human_domestic_day=NULL WHERE world_id=? AND country_code=?',[$world,$slot['country_code']]);
            $this->query('DELETE FROM wp_alpha_members WHERE world_id=? AND account_id=?',[$world,$s->id]);
            return ['universe_no'=>$no,'country'=>$slot['country_code'],'controller'=>'ai','day'=>$c['committed_day'],'state_reset'=>false];
        });
    }
    /** A programme is inherited with the government, not a new campaign
     * or a claim about a real politician's preferences. */
    public function programme(AlphaSession $s,int $no,array $body):array
    {
        AlphaAccounts::fields($body,['diplomacy',...(array_key_exists('domestic',$body)?['domestic']:[]),...(array_key_exists('agenda',$body)?['agenda']:[]),...(array_key_exists('measure_ids',$body)?['measure_ids']:[]),...(array_key_exists('social_measure_ids',$body)?['social_measure_ids']:[])]);
        $social=null;
        if(array_key_exists('social_measure_ids',$body)){
            if(array_intersect(['domestic','agenda','measure_ids'],array_keys($body))!==[]||!is_array($body['social_measure_ids']))throw new AlphaHttpError(422,'invalid_programme');
            try{$social=SocialProgramme::encode($body['social_measure_ids']);}catch(\InvalidArgumentException $e){throw new AlphaHttpError(422,'invalid_programme');}
            unset($body['social_measure_ids']);
        }
        if(array_key_exists('measure_ids',$body)){
            if(array_key_exists('domestic',$body)||array_key_exists('agenda',$body)||!is_array($body['measure_ids']))throw new AlphaHttpError(422,'invalid_programme');
            try{$social=PresidentialProgramme::compileProfile($body['measure_ids']);}
            catch(\InvalidArgumentException $e){throw new AlphaHttpError(422,'invalid_programme');}
            unset($body['measure_ids']);
        }
        if(array_key_exists('agenda',$body)){
            if(array_key_exists('domestic',$body)||!is_array($body['agenda']))throw new AlphaHttpError(422,'invalid_programme');
            try{$body['domestic']=PresidentialProgramme::encodeAgenda($body['agenda']);}
            catch(\InvalidArgumentException $e){throw new AlphaHttpError(422,'invalid_programme');}
            unset($body['agenda']);
        }elseif(array_key_exists('domestic',$body)&&(!is_string($body['domestic'])||!in_array($body['domestic'],PresidentialProgramme::PROFILES,true)))throw new AlphaHttpError(422,'invalid_programme');
        if(!in_array($body['diplomacy'],['cooperative','balanced','cautious'],true)||(array_key_exists('domestic',$body)&&!PresidentialProgramme::known($body['domestic'])))
            throw new AlphaHttpError(422,'invalid_programme');
        if($social!==null)$body['domestic']=$social;
        return $this->transaction(function()use($s,$no,$body){
            if(!$s->active())throw new AlphaHttpError(401,'authentication_required');
            $world=$this->query('SELECT world_id FROM wp_universes WHERE universe_no=?',[$no])->fetchColumn();
            if(!is_string($world))throw new AlphaHttpError(404,'not_found');
            $c=$this->lockWorld($world);
            if((int)$c['game_over']===1)throw new AlphaHttpError(409,'game_over');
            $slot=$this->query('SELECT country_code FROM wp_universe_slots WHERE world_id=? AND account_id=? FOR UPDATE',[$world,$s->id])->fetchColumn();
            if(!is_string($slot)||!$s->active())throw new AlphaHttpError(404,'not_found');
            if(array_key_exists('domestic',$body)){
                if($slot!=='FR')throw new AlphaHttpError(403,'domestic_programme_not_available');
                if(PresidentialProgramme::socialIds($body['domestic'])!==null){
                    $raw=$this->query('SELECT assumptions_json FROM wp_domestic_states WHERE world_id=?',[$world])->fetchColumn();
                    $cfg=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
                    foreach(PresidentialProgramme::socialIds($body['domestic']) as $id)if(SocialProgramme::assumption($id,$cfg['catalog'],isset($cfg['social_economy_from']))===null)throw new AlphaHttpError(409,'social_programme_requires_new_universe');
                }
                $this->query('UPDATE wp_universe_slots SET programme_set_on=IF(domestic_programme<>?, ?, programme_set_on),domestic_programme=? WHERE world_id=? AND country_code=?',[$body['domestic'],$c['committed_day'],$body['domestic'],$world,$slot]);
            }
            $this->query('UPDATE wp_universe_slots SET diplomacy_programme=? WHERE world_id=? AND country_code=?',[$body['diplomacy'],$world,$slot]);
            return ['country'=>$slot,'diplomacy'=>$body['diplomacy'],'effective_day'=>$c['committed_day'],'inherited'=>true,
                ...(array_key_exists('domestic',$body)?['domestic'=>$body['domestic']]:[])];
        });
    }
    /** Member-only coherent report; a proposal is not a law or spending. */
    public function programmeStatus(AlphaSession $s,int $no):array
    {
        return $this->transaction(function()use($s,$no){
            if(!$s->active())throw new AlphaHttpError(401,'authentication_required');
            $world=$this->query('SELECT world_id FROM wp_universes WHERE universe_no=?',[$no])->fetchColumn();
            if(!is_string($world))throw new AlphaHttpError(404,'not_found');
            $c=$this->lockWorld($world);
            $slot=$this->query('SELECT country_code,diplomacy_programme,domestic_programme,last_human_domestic_day FROM wp_universe_slots WHERE world_id=? AND account_id=?',[$world,$s->id])->fetch(\PDO::FETCH_ASSOC);
            if($slot===false||!$s->active())throw new AlphaHttpError(404,'not_found');
            $report=['day'=>$c['committed_day'],'country'=>$slot['country_code'],'diplomacy'=>$slot['diplomacy_programme'],'inherited'=>true];
            if($slot['country_code']!=='FR')return $report;
            $d=$this->query('SELECT state_json,state_sha FROM wp_domestic_states WHERE world_id=?',[$world])->fetch(\PDO::FETCH_ASSOC);
            if($d===false)throw new \DomainException('Missing programme domestic state');
            $dom=json_decode($d['state_json'],true,64,JSON_THROW_ON_ERROR);
            if($dom['day']!==$c['committed_day']||$dom['world_id']!==$world||!hash_equals($d['state_sha'],hash('sha256',json_encode($dom,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES))))throw new \DomainException('Invalid programme source');
            $rows=$this->query('SELECT decision_day,programme,policy_id,effective_day FROM wp_universe_programme_actions WHERE world_id=? ORDER BY decision_day LIMIT 10001',[$world])->fetchAll(\PDO::FETCH_ASSOC);
            if(count($rows)>10000)throw new \DomainException('Programme report limit');
            $active=$dom['days']===[]?[]:($dom['days'][array_key_last($dom['days'])]['active_policies']??[]);
            foreach($rows as &$a){
                $p=$dom['policies'][$a['policy_id']]??null;
                if($p===null||!PresidentialProgramme::validAction($a)||$p['proposed_on']!==$a['decision_day']||$p['effective_on']!==$a['effective_day'])throw new \DomainException('Invalid programme journal');
                $a['authorized_on']=$p['authorized_on'];
                $a['state']=$p['authorized_on']!==null?(in_array($a['policy_id'],$active,true)?'active':'legally_authorized'):($a['effective_day']<=$c['committed_day']?'not_authorized_by_deadline':'proposed');
            }unset($a);
            $i=$this->query("SELECT state_json,state_sha FROM wp_institutional_states WHERE world_id=? AND country_code='FR'",[$world])->fetch(\PDO::FETCH_ASSOC);
            $dipl=$this->query('SELECT state_json,state_sha FROM wp_diplomatic_states WHERE world_id=?',[$world])->fetch(\PDO::FETCH_ASSOC);
            $objectives=[];$inst=null;
            if($i===false||$dipl===false)throw new \DomainException('Missing institutional programme source');
            foreach(['institutional'=>$i,'diplomatic'=>$dipl] as $domain=>$raw){
                if(!hash_equals($raw['state_sha'],hash('sha256',$raw['state_json'])))throw new \DomainException('Invalid programme source digest');
                $v=json_decode($raw['state_json'],true,64,JSON_THROW_ON_ERROR);
                if($v['world_id']!==$world||$v['day']!==$c['committed_day'])throw new \DomainException('Incoherent programme report');
                if($domain==='institutional'){
                    $inst=$v;
                    foreach(['cabinet_requests','revision_requests'] as $family)foreach($v[$family]??[] as $id=>$req)
                        if(str_starts_with($id,'pres-objective-'))$objectives[]=['family'=>$family,'id'=>$id,'request'=>$req];
                    foreach($v['events'] as $e)if(str_starts_with($e['id'],'pres-objective-')&&$e['kind']==='legislation_evidence'&&isset($e['result']['portfolio']))
                        $objectives[]=['family'=>'portfolio','id'=>$e['id'],'day'=>$e['on'],'result'=>$e['result']];
                }else foreach($v['commitments']['commands']??[] as $id=>$cmd)
                    if(str_starts_with($id,'pres-objective-'))$objectives[]=['family'=>'commitment','id'=>$id,'request'=>$cmd,
                        'current_record'=>$v['commitments']['records'][$cmd['id']]];
            }
            $org=$this->query('SELECT state_json,state_sha FROM wp_organization_states WHERE world_id=?',[$world])->fetch(\PDO::FETCH_ASSOC);
            if($org===false||!hash_equals($org['state_sha'],hash('sha256',$org['state_json'])))throw new \DomainException('Invalid treaty programme source');
            $os=json_decode($org['state_json'],true,64,JSON_THROW_ON_ERROR);
            if($os['world_id']!==$world||$os['day']!==$c['committed_day'])throw new \DomainException('Incoherent treaty programme source');
            $rr=$this->query("SELECT state_json,state_sha FROM wp_world_rosters WHERE world_id=? AND country_code='FR'",[$world])->fetch(\PDO::FETCH_ASSOC);
            if($rr===false)throw new \DomainException('Missing candidate programme roster');
            $rs=json_decode($rr['state_json'],true,64,JSON_THROW_ON_ERROR);
            if(!hash_equals($rr['state_sha'],PoliticalRoster::fromSnapshot($rs)->snapshotHash()))throw new \DomainException('Invalid candidate programme roster');
            $cfg=json_decode($this->query('SELECT assumptions_json FROM wp_domestic_states WHERE world_id=?',[$world])->fetchColumn(),true,64,JSON_THROW_ON_ERROR);
            return $report+['candidate_programme'=>ProgrammeProgress::managed($rs,$dom,$inst,$os,$cfg['catalog'],$slot['domestic_programme']),
                'civic_initiative'=>$inst['civic_initiative']??null,'nato_withdrawal'=>$os['nato_withdrawal']??null,'institutional_and_commitment_objectives'=>$objectives,'version'=>PresidentialProgramme::VERSION,'domestic'=>$slot['domestic_programme'],'agenda'=>PresidentialProgramme::agenda($slot['domestic_programme']),
                'direct_actions'=>$this->query('SELECT mandate_start,programme,decision_day,action_id,outcome FROM wp_universe_programme_direct WHERE world_id=? ORDER BY decision_day,programme LIMIT 10000',[$world])->fetchAll(\PDO::FETCH_ASSOC),
                'automatic_legal_route'=>PresidentialProgramme::executable($slot['domestic_programme']),
                'human_action_this_day'=>$slot['last_human_domestic_day']===$c['committed_day'],
                'actions'=>$rows,'executable_measure_catalog'=>PresidentialProgramme::measureCatalog(),
                'social_measure_ids'=>PresidentialProgramme::socialIds($slot['domestic_programme']), 'social_programme'=>SocialProgramme::report($dom),'social_economy'=>$dom['days']===[]?null:($dom['days'][array_key_last($dom['days'])]['social_economy']??null),
                'historical_real_programmes_fully_executable'=>false,'target_2027_catalog'=>SocialProgramme::target2027(),'target_2027_programmes_fully_executable'=>false];
        });
    }
    /** Interim standings: control periods, NOT certified national tenure or
     * purchasing power. Do not invent missing foreign economic metrics. */
    public function rankings(AlphaSession $s,?int $no,int $page=1):array
    {
        if(!$s->active())throw new AlphaHttpError(401,'authentication_required');
        if($page<1||$page>100000)throw new AlphaHttpError(422,'invalid_page');
        if($no!==null&&$this->query('SELECT 1 FROM wp_universes WHERE universe_no=?',[$no])->fetchColumn()===false)throw new AlphaHttpError(404,'not_found');
        $where=$no===null?'':' AND u.universe_no=?';$offset=($page-1)*50;
        $rows=$this->query("WITH periods AS (
          SELECT p.account_id,p.world_id,u.universe_no,p.country_code,p.generation,
             DATEDIFF(COALESCE(p.left_on,c.committed_day),p.joined_on) AS control_days
          FROM wp_universe_control_periods p JOIN wp_universes u ON u.world_id=p.world_id
          JOIN wp_world_clock c ON c.world_id=p.world_id JOIN wp_accounts a ON a.account_id=p.account_id
          WHERE a.enabled=1".$where."),
          choices AS (SELECT periods.*,ROW_NUMBER() OVER(PARTITION BY account_id ORDER BY control_days DESC,universe_no,country_code,generation) AS choice_no FROM periods),
          ranked AS (SELECT choices.*,RANK() OVER(ORDER BY control_days DESC) AS player_rank FROM choices WHERE choice_no=1)
          SELECT * FROM ranked ORDER BY player_rank,account_id LIMIT 50 OFFSET ".$offset,$no===null?[]:[$no])->fetchAll(\PDO::FETCH_ASSOC);
        $out=[];foreach($rows as $r)$out[]=['rank'=>(int)$r['player_rank'],'player'=>'joueur-'.substr(hash('sha256','wp102-universe/'.$r['account_id']),0,20),
            'is_you'=>$r['account_id']===$s->id,'universe_no'=>(int)$r['universe_no'],'country'=>$r['country_code'],'control_days'=>(int)$r['control_days'],'score'=>null];
        return ['scope'=>$no===null?'global':'universe','universe_no'=>$no,'page'=>$page,'rows'=>$out,
            'ranking_basis'=>'best_single_control_period_days','official_ranking_eligible'=>false,'composite_score_available'=>false,
            'pilot_score_cohorts'=>UniverseScore::cohorts($this->db,$no)];
    }
}
