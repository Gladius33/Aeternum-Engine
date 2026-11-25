<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

require_once __DIR__.'/AlphaAccounts.php';
require_once __DIR__.'/WorldClockCoordinator.php';
require_once __DIR__.'/PoliticalRoster.php';
require_once __DIR__.'/GlobalReferenceBundle.php';

/** Immutable private alpha scenario. No real economic data is invented. */
final class AlphaScenario
{
    public const ID='fr-sandbox-v1';
    public const DAY='2026-11-24';
    public const GLOBAL='fr-global-v1';
    public const COMPARATIVE='fr-comparative-v1';
    public readonly array $countries;
    public readonly string $id;
    public readonly string $day;
    public readonly ?GlobalReferenceBundle $bundle;
    public readonly ?string $bundleSha;
    public readonly GeopoliticalActorRegistry $actors;
    public readonly EconomicReferenceRegistry $references;
    public readonly array $charters;
    public function __construct(string $id=self::ID,?GlobalReferenceBundle $bundle=null,?string $bundleSha=null)
    {
        $this->id=$id;$this->bundle=$bundle;$this->bundleSha=$bundleSha;
        $this->countries=$id===self::COMPARATIVE?['DE','ES','FR','GB','IT','JP','US']:['FR'];
        $this->day=in_array($id,[self::GLOBAL,self::COMPARATIVE],true)?GlobalReferenceBundle::DATE:self::DAY;
        if (in_array($id,[self::GLOBAL,self::COMPARATIVE],true) && $bundle!==null) {
            $this->actors=$bundle->actors;$this->references=$bundle->references;$this->charters=[];return;
        }
        if ($id!==self::ID || $bundle!==null || $bundleSha!==null) throw new \DomainException('Unknown scenario configuration');
        $data=json_decode(file_get_contents(__DIR__.'/../data/2026-11-24-special-actors.json'),true,32,JSON_THROW_ON_ERROR);
        $data['entities'][]=['id'=>'state_fr','label'=>'France','iso2'=>'FR','kind'=>'state','un_status'=>'un_member',
            'parent_id'=>null,'controller'=>'human_or_ai','representation'=>'international_state_actor',
            'sources'=>['https://www.un.org/en/about-us/member-states'],'strategic_programmes'=>[]];
        $this->actors=new GeopoliticalActorRegistry($data);
        $this->references=new EconomicReferenceRegistry(self::DAY,[],$this->actors);
        $this->charters=[];
    }
    public function domestic():array
    {
        return ['start_day'=>$this->day,'currency'=>'EUR','opening_balance'=>1000000000,
            'baseline_revenue'=>10000000,'baseline_expense'=>11000000,
            'cohorts'=>['workers'=>400,'retired'=>300],
            'catalog'=>['education-reform'=>['revenue_delta'=>0,'expense_delta'=>200000,
                'pressure_delta'=>['workers'=>-50,'retired'=>0],'duration_days'=>30,
                'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,
                'production_delta_bps'=>0,'employment_delta_bps'=>0]]];
    }
}

final class AlphaWorlds
{
    public function __construct(private readonly \PDO $db,private readonly AlphaScenario $scenario) {}
    public function list(AlphaSession $session):array
    {
        if (!$session->active()) throw new AlphaHttpError(401,'authentication_required');
        $q=$this->db->prepare('SELECT w.world_id,w.label,w.scenario,w.reference_bundle_sha,m.role,c.committed_day,c.pending_day
            FROM wp_alpha_members m JOIN wp_alpha_worlds w ON w.world_id=m.world_id
            JOIN wp_world_clock c ON c.world_id=w.world_id WHERE m.account_id=? ORDER BY w.world_id LIMIT 100');
        $q->execute([$session->id]);return ['worlds'=>$q->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function create(AlphaSession $session,array $body,string $key):array
    {
        AlphaAccounts::fields($body,in_array($this->scenario->id,[AlphaScenario::GLOBAL,AlphaScenario::COMPARATIVE],true) && array_key_exists('reference_bundle_sha',$body)?['label','scenario','reference_bundle_sha']:['label','scenario']);
        if (!is_string($body['label']) || trim($body['label'])==='' || mb_strlen($body['label'])>100
            || preg_match('/[\x00-\x1f\x7f]/',$body['label']) || $body['scenario']!==$this->scenario->id
            || !preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key)) throw new AlphaHttpError(422,'invalid_world');
        $label=trim($body['label']);
        $request=[$label,$this->scenario->id];
        if (in_array($this->scenario->id,[AlphaScenario::GLOBAL,AlphaScenario::COMPARATIVE],true)) $request[]=$this->scenario->bundleSha;
        $fingerprint=hash('sha256',self::json($request));
        $key=hash('sha256',$key);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT account_id FROM wp_accounts WHERE account_id=? FOR UPDATE');
            $q->execute([$session->id]);
            if ($q->fetchColumn()===false || !$session->active()) throw new AlphaHttpError(401,'authentication_required');
            $q=$this->db->prepare('SELECT world_id,request_sha FROM wp_alpha_worlds WHERE owner_id=? AND create_key=?');
            $q->execute([$session->id,$key]);$prior=$q->fetch(\PDO::FETCH_ASSOC);
            if ($prior!==false) {
                if (!hash_equals($prior['request_sha'],$fingerprint)) throw new AlphaHttpError(409,'idempotency_conflict');
                $this->db->commit();return ['world_id'=>$prior['world_id'],'scenario'=>$this->scenario->id,'label'=>$label,'role'=>'owner'];
            }
            $q=$this->db->prepare('SELECT COUNT(*) FROM wp_alpha_worlds WHERE owner_id=?');
            $q->execute([$session->id]);
            if ((int)$q->fetchColumn()>=10) throw new AlphaHttpError(409,'world_limit');
            $world='world-'.bin2hex(random_bytes(16));$day=$this->scenario->day;
            $this->insert('INSERT INTO wp_alpha_worlds(world_id,owner_id,create_key,request_sha,label,scenario,reference_bundle_sha) VALUES(?,?,?,?,?,?,?)',
                [$world,$session->id,$key,$fingerprint,$label,$this->scenario->id,$this->scenario->bundleSha]);
            $this->insert("INSERT INTO wp_alpha_members(world_id,account_id,role) VALUES(?,?,'owner')",[$world,$session->id]);

            // One transaction for the entire initial world. The domain classes
            // generate and validate snapshots; individual store seed methods
            // commit separately and cannot provide atomic provisioning.
            foreach ($this->scenario->countries as $country) {
                $roster=new PoliticalRoster($world,$country,$country==='FR'?['prime_minister','economy','education']:['government']);
                $this->insert('INSERT INTO wp_world_rosters(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)',
                    [$world,$country,self::json($roster->snapshot()),$roster->snapshotHash()]);
                $inst=new InstitutionalSimulation($world,$country,$day);
                // Fictional chamber sizes, not contemporary election results.
                if ($country!=='FR') {
                    $seats=match($country) {
                        'DE'=>['bundestag'=>100],'ES'=>['congress'=>100],'GB'=>['commons'=>100],
                        'IT'=>['chamber'=>100,'senate'=>50],'JP'=>['representatives'=>100],'US'=>['house'=>100,'senate'=>50]
                    };
                    $inst->enableAccountability('initial-government',$seats);
                }
                $this->insert('INSERT INTO wp_institutional_states(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)',
                    [$world,$country,self::json($inst->snapshot()),$inst->hash()]);
            }
            $dip=new DiplomaticSimulation($world,$day,$this->scenario->actors);
            $this->insert('INSERT INTO wp_diplomatic_states(world_id,state_json,state_sha,actor_registry_sha,revision) VALUES(?,?,?,?,0)',
                [$world,self::json($dip->snapshot()),$dip->hash(),$this->scenario->actors->hash()]);
            $org=new OrganizationSimulation($world,$day,$this->scenario->actors,$this->scenario->charters);
            $os=$org->snapshot();
            $this->insert('INSERT INTO wp_organization_states(world_id,state_json,state_sha,actors_sha,charters_sha,revision) VALUES(?,?,?,?,?,0)',
                [$world,self::json($os),$org->hash(),$os['actors_sha'],$os['charters_sha']]);
            $econ=new WorldEconomicSimulation($world,$day,$this->scenario->actors,$this->scenario->references);
            $this->insert('INSERT INTO wp_economic_states(world_id,state_json,state_sha,reference_sha,actor_registry_sha,revision) VALUES(?,?,?,?,?,0)',
                [$world,self::json($econ->snapshot()),$econ->hash(),$this->scenario->references->hash(),$this->scenario->actors->hash()]);
            $c=$this->scenario->domestic();
            $dom=new DomesticPolicySimulation($world,$day,$c['currency'],$c['opening_balance'],$c['baseline_revenue'],$c['baseline_expense'],$c['cohorts'],$c['catalog']);
            $this->insert('INSERT INTO wp_domestic_states(world_id,assumptions_json,assumptions_sha,state_json,state_sha,revision) VALUES(?,?,?,?,?,0)',
                [$world,self::json($c),$dom->assumptionsHash(),self::json($dom->snapshot()),$dom->hash()]);
            $this->insert('INSERT INTO wp_world_clock(world_id,committed_day,revision,domestic_enabled) VALUES(?,?,0,1)',[$world,$day]);
            WorldMutationFence::seed($this->db,$world);
            foreach ($this->scenario->countries as $country) $this->insert('INSERT INTO wp_world_clock_countries(world_id,country_code) VALUES(?,?)',[$world,$country]);
            $this->db->commit();
            return ['world_id'=>$world,'scenario'=>$this->scenario->id,'label'=>$label,'role'=>'owner'];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function share(AlphaSession $s,string $world,array $body):array
    {
        AlphaAccounts::fields($body,['account_id','access']);
        if (!is_string($body['account_id']) || !preg_match('/^acct-[a-f0-9]{32}$/D',$body['account_id'])
            || !in_array($body['access'],['viewer','none'],true) || $body['account_id']===$s->id)
            throw new AlphaHttpError(422,'invalid_membership');
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT owner_id FROM wp_alpha_worlds WHERE world_id=? FOR UPDATE');
            $q->execute([$world]);
            if ($q->fetchColumn()!==$s->id || $s->role($world)!=='owner') throw new AlphaHttpError(403,'forbidden');
            if ($body['access']==='viewer') {
                $q=$this->db->prepare('SELECT COUNT(*) FROM wp_alpha_members WHERE world_id=?');$q->execute([$world]);
                if ((int)$q->fetchColumn()>=20) throw new AlphaHttpError(409,'member_limit');
                $q=$this->db->prepare('SELECT 1 FROM wp_accounts WHERE account_id=? AND enabled=1');$q->execute([$body['account_id']]);
                if ($q->fetchColumn()===false) throw new AlphaHttpError(404,'account_unavailable');
                $this->insert("INSERT INTO wp_alpha_members(world_id,account_id,role) VALUES(?,?,'viewer') ON DUPLICATE KEY UPDATE role=role",
                    [$world,$body['account_id']]);
            } else {
                $this->insert("DELETE FROM wp_alpha_members WHERE world_id=? AND account_id=? AND role='viewer'",[$world,$body['account_id']]);
            }
            $this->db->commit();return ['account_id'=>$body['account_id'],'access'=>$body['access']];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();throw $e;
        }
    }

    private function insert(string $sql,array $args):void {$q=$this->db->prepare($sql);$q->execute($args);}
    private static function json(array $x):string {return json_encode($x,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
}
