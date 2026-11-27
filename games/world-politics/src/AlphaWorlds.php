<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

require_once __DIR__.'/AlphaAccounts.php';
require_once __DIR__.'/WorldClockCoordinator.php';
require_once __DIR__.'/PoliticalRoster.php';
require_once __DIR__.'/GlobalReferenceBundle.php';
require_once __DIR__.'/ComparativeElectionCalendar.php';
require_once __DIR__.'/PdoCampaignScores.php';
require_once __DIR__.'/CampaignCandidate.php';
require_once __DIR__.'/UniverseScore.php';
require_once __DIR__.'/UniverseIdentityReference.php';

/** Immutable private alpha scenario. No real economic data is invented. */
final class AlphaScenario
{
    public const ID='fr-sandbox-v1';
    public const DAY='2026-11-24';
    public const GLOBAL='fr-global-v1';
    public const COMPARATIVE='fr-comparative-v1';
    public const ELECTORAL='fr-electoral-v1';
    public const POLITICAL='fr-political-v1';
    public const OPINION='fr-opinion-v1';
    public const DIALOGUE='fr-dialogue-v1';
    public const SURVIVAL='fr-survival-v1';
    public const REVOLUTION_CASE='fr-revolution-case-v1';
    public const PRESIDENTIAL='fr-presidential-v1';
    public const REVISION='fr-revision-v1';
    public const LEGISLATIVE='fr-legislative-v1';
    public const RESPONSIBILITY='fr-responsibility-v1';
    public const ASSEMBLY='fr-assembly-v1';
    public const PARLIAMENT='fr-parliament-v1';
    public const CABINET='fr-cabinet-v1';
    public const EXECUTIVE='fr-executive-v1';
    public const FINANCE='fr-finance-v1';
    public const MACRO='fr-macro-v1';
    public const FISCAL_LOOP='fr-fiscal-loop-v1';
    public const DEBT='fr-debt-v1';
    public const FINANCIAL_CRISIS='fr-financial-crisis-v1';
    public const POPULATION='fr-population-v1';
    public const SERVICES='fr-services-v1';
    public const PARTNERS='fr-social-partners-v1';
    public const CORPORATIONS='fr-corporations-v1';
    public const CIVIL_CRISIS='fr-civil-crisis-v1';
    public const INVESTITURE='fr-investiture-2027-v1';
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
        $this->countries=in_array($id,[self::COMPARATIVE,self::ELECTORAL,self::POLITICAL,self::OPINION,self::DIALOGUE,self::SURVIVAL,self::REVOLUTION_CASE,self::PRESIDENTIAL,self::REVISION,self::LEGISLATIVE,self::RESPONSIBILITY,self::ASSEMBLY,self::PARLIAMENT,self::CABINET,self::EXECUTIVE,self::FINANCE,self::MACRO,self::FISCAL_LOOP,self::DEBT,self::FINANCIAL_CRISIS,self::POPULATION,self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true)?['DE','ES','FR','GB','IT','JP','US']:['FR'];
        $this->day=$id===self::INVESTITURE?FrenchElectionCalendar::PRESIDENTIAL_HANDOVER:(in_array($id,[self::GLOBAL,self::COMPARATIVE,self::ELECTORAL,self::POLITICAL,self::OPINION,self::DIALOGUE,self::SURVIVAL,self::REVOLUTION_CASE,self::PRESIDENTIAL,self::REVISION,self::LEGISLATIVE,self::RESPONSIBILITY,self::ASSEMBLY,self::PARLIAMENT,self::CABINET,self::EXECUTIVE,self::FINANCE,self::MACRO,self::FISCAL_LOOP,self::DEBT,self::FINANCIAL_CRISIS,self::POPULATION,self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true)?GlobalReferenceBundle::DATE:self::DAY);
        if (in_array($id,[self::GLOBAL,self::COMPARATIVE,self::ELECTORAL,self::POLITICAL,self::OPINION,self::DIALOGUE,self::SURVIVAL,self::REVOLUTION_CASE,self::PRESIDENTIAL,self::REVISION,self::LEGISLATIVE,self::RESPONSIBILITY,self::ASSEMBLY,self::PARLIAMENT,self::CABINET,self::EXECUTIVE,self::FINANCE,self::MACRO,self::FISCAL_LOOP,self::DEBT,self::FINANCIAL_CRISIS,self::POPULATION,self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true) && $bundle!==null) {
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
    public function campaign():bool{return in_array($this->id,[self::SURVIVAL,self::REVOLUTION_CASE,self::PRESIDENTIAL,self::REVISION,self::LEGISLATIVE,self::RESPONSIBILITY,self::ASSEMBLY,self::PARLIAMENT,self::CABINET,self::EXECUTIVE,self::FINANCE,self::MACRO,self::FISCAL_LOOP,self::DEBT,self::FINANCIAL_CRISIS,self::POPULATION,self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true);}
    public function domestic():array
    {
        $c=['start_day'=>$this->day,'currency'=>'EUR','opening_balance'=>1000000000,
            'baseline_revenue'=>10000000,'baseline_expense'=>11000000,
            'cohorts'=>['workers'=>400,'retired'=>300],
            'catalog'=>['education-reform'=>['revenue_delta'=>0,'expense_delta'=>200000,
                'pressure_delta'=>['workers'=>-50,'retired'=>0],'duration_days'=>30,
                'concessions'=>['workers'=>0,'retired'=>0],'concession_daily_cost'=>0,
                'production_delta_bps'=>0,'employment_delta_bps'=>0],...(in_array($this->id,[self::EXECUTIVE,self::FINANCE,self::MACRO,self::FISCAL_LOOP,self::DEBT,self::FINANCIAL_CRISIS,self::POPULATION,self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true)?['administration-report'=>['revenue_delta'=>0,'expense_delta'=>50000,'pressure_delta'=>['workers'=>0,'retired'=>0],'duration_days'=>10]]:[]),...(in_array($this->id,[self::FINANCE,self::MACRO,self::FISCAL_LOOP,self::DEBT,self::FINANCIAL_CRISIS,self::POPULATION,self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true)?($this->id===self::INVESTITURE?(FinanceBudget::seriesPolicies()+FinanceBudget::correctivePolicies()):FinanceBudget::policies()):[])]];
        if(in_array($this->id,[self::FISCAL_LOOP,self::DEBT,self::FINANCIAL_CRISIS,self::POPULATION,self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true)){
            $b=FrenchMacroBaseline::load($this->id===self::INVESTITURE);$c['macro_baseline']=$b;
            $c['opening_balance']=$b['assumed_opening_cash'];$c['baseline_revenue']=$b['daily_revenue'];$c['baseline_expense']=$b['daily_primary_expense'];
            if(in_array($this->id,[self::DEBT,self::FINANCIAL_CRISIS,self::POPULATION,self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true))$c['debt_model']=SovereignDebtLedger::load($b,$this->day,in_array($this->id,[self::FINANCIAL_CRISIS,self::POPULATION,self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true));
            if(in_array($this->id,[self::POPULATION,self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true))$c['population_model']=FrenchDemography::load($this->day,$b['initial_unemployment_bps'],isset($b['opening_supplement']));
            if(in_array($this->id,[self::SERVICES,self::PARTNERS,self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true))$c['services_model']=FrenchPublicServices::load($this->day,$c['population_model'],$b);
        }
        if(in_array($this->id,[self::CORPORATIONS,self::CIVIL_CRISIS,self::INVESTITURE],true)){$c['corporations_enabled']=true;$c['wage_feedback_enabled']=true;$c['trade_feedback_enabled']=true;$c['firm_model']=FirmEconomy::config();$c['public_equity_enabled']=true;$c['industrial_enabled']=true;$c['daily_legal_enabled']=true;$c['eu_trade_enabled']=true;$c['eu_trade_v2']=true;$c['eu_trade_v3']=true;$c['eu_trade_v4']=true;}
        if($this->id===self::INVESTITURE)$c['finance_autonomy']=true;
        return $c;
    }
}

final class AlphaWorlds
{
    public function __construct(private readonly \PDO $db,private readonly AlphaScenario $scenario) {}
    public function list(AlphaSession $session):array
    {
        if (!$session->active()) throw new AlphaHttpError(401,'authentication_required');
        $q=$this->db->prepare('SELECT w.world_id,w.label,w.scenario,w.reference_bundle_sha,m.role,c.committed_day,c.pending_day,c.campaign_enabled,c.game_over
            FROM wp_alpha_members m JOIN wp_alpha_worlds w ON w.world_id=m.world_id
            JOIN wp_world_clock c ON c.world_id=w.world_id WHERE m.account_id=? ORDER BY w.world_id LIMIT 100');
        $q->execute([$session->id]);return ['worlds'=>$q->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function create(AlphaSession $session,array $body,string $key):array
    {return $this->provision($session,$body,$key,false);}

    /** Server-only; the universe pool owns the outer transaction. */
    public function provisionUniverse(bool $bindIdentity=false):array
    {
        if(!$this->db->inTransaction()||$this->scenario->id!==AlphaScenario::INVESTITURE)
            throw new \LogicException('Universe allocation transaction required');
        return $this->provision(null,['label'=>'Univers '.bin2hex(random_bytes(5)),'scenario'=>$this->scenario->id],bin2hex(random_bytes(16)),true,$bindIdentity);
    }
    private function provision(?AlphaSession $session,array $body,string $key,bool $managed,bool $bindIdentity=false):array
    {
        $owner=$managed?'acct-00000000000000000000000000000000':$session->id;
        $fields=in_array($this->scenario->id,[AlphaScenario::GLOBAL,AlphaScenario::COMPARATIVE,AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true) && array_key_exists('reference_bundle_sha',$body)?['label','scenario','reference_bundle_sha']:['label','scenario'];
        if($this->scenario->id===AlphaScenario::INVESTITURE&&array_key_exists('candidate',$body))$fields[]='candidate';
        AlphaAccounts::fields($body,$fields);
        $candidate=null;$candidateMayStart=true;
        if(array_key_exists('candidate',$body)){
            if(CampaignCandidate::discoveryOnly($body['candidate']))throw new AlphaHttpError(409,'candidate_programme_unavailable');
            try{$candidate=CampaignCandidate::normalize($body['candidate']);$candidateMayStart=CampaignCandidate::mayStart($body['candidate']);}
            catch(\InvalidArgumentException|\DomainException $e){throw new AlphaHttpError(422,'invalid_candidate');}
        }
        if (!is_string($body['label']) || trim($body['label'])==='' || mb_strlen($body['label'])>100
            || preg_match('/[\x00-\x1f\x7f]/',$body['label']) || $body['scenario']!==$this->scenario->id
            || !preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key)) throw new AlphaHttpError(422,'invalid_world');
        $label=trim($body['label']);
        $request=[$label,$this->scenario->id];
        if (in_array($this->scenario->id,[AlphaScenario::GLOBAL,AlphaScenario::COMPARATIVE,AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)) $request[]=$body['reference_bundle_sha']??null;
        if($candidate!==null)$request[]=$candidate;
        $fingerprint=hash('sha256',self::json($request));
        $key=hash('sha256',$key);
        if(!$managed)$this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT account_id FROM wp_accounts WHERE account_id=? FOR UPDATE');
            $q->execute([$owner]);
            if ($q->fetchColumn()===false || (!$managed&&!$session->active())) throw new AlphaHttpError(401,'authentication_required');
            $q=$this->db->prepare('SELECT world_id,request_sha FROM wp_alpha_worlds WHERE owner_id=? AND create_key=?');
            $q->execute([$owner,$key]);$prior=$q->fetch(\PDO::FETCH_ASSOC);
            if ($prior!==false) {
                if (!hash_equals($prior['request_sha'],$fingerprint)) throw new AlphaHttpError(409,'idempotency_conflict');
                if(!$managed)$this->db->commit();return ['world_id'=>$prior['world_id'],'scenario'=>$this->scenario->id,'label'=>$label,'role'=>'owner'];
            }
            if(!$candidateMayStart)throw new AlphaHttpError(409,'candidate_programme_unavailable');
            $q=$this->db->prepare('SELECT COUNT(*) FROM wp_alpha_worlds WHERE owner_id=?');
            $q->execute([$owner]);
            if ((int)$q->fetchColumn()>=10&&!$managed) throw new AlphaHttpError(409,'world_limit');
            $world='world-'.bin2hex(random_bytes(16));$day=$this->scenario->day;
            $this->insert('INSERT INTO wp_alpha_worlds(world_id,owner_id,create_key,request_sha,label,scenario,reference_bundle_sha) VALUES(?,?,?,?,?,?,?)',
                [$world,$owner,$key,$fingerprint,$label,$this->scenario->id,$this->scenario->bundleSha]);
            if(!$managed)$this->insert("INSERT INTO wp_alpha_members(world_id,account_id,role) VALUES(?,?,'owner')",[$world,$owner]);

            // One transaction for the entire initial world. The domain classes
            // generate and validate snapshots; individual store seed methods
            // commit separately and cannot provide atomic provisioning.
            foreach ($this->scenario->countries as $country) {
                $roster=new PoliticalRoster($world,$country,$country==='FR'?['prime_minister','economy','education']:['government']);
                $openingIdentity=$managed&&$bindIdentity?UniverseIdentityReference::openingPerson($world,$country):null;
                if($country==='FR'&&$this->scenario->campaign()){
                    $roster->addPerson($candidate??$openingIdentity??['id'=>CampaignLifecycle::PLAYER,'kind'=>'fictional','name'=>'Scenario president',
                        'country'=>'FR','biography'=>'Fictional player officeholder','as_of'=>null,'sources'=>[],'programme'=>[]]);
                    $roster->setHeadOfState(CampaignLifecycle::PLAYER);
                }elseif($openingIdentity!==null){
                    $roster->addPerson($openingIdentity);
                    if($managed&&$bindIdentity)foreach(($country==='US'?UsPresidentialElection::people():[])+DocumentaryForeignBallot::people($country) as $id=>$person)if($id!==$openingIdentity['id'])$roster->addPerson($person);
                }
                $this->insert('INSERT INTO wp_world_rosters(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)',
                    [$world,$country,self::json($roster->snapshot()),$roster->snapshotHash()]);
                $inst=new InstitutionalSimulation($world,$country,$day);
                // Fictional chamber sizes, not contemporary election results.
                if ($country!=='FR') {
                    $seats=match($country) {
                        'DE'=>['bundestag'=>100],'ES'=>['congress'=>100],'GB'=>['commons'=>100],
                        'IT'=>['chamber'=>100,'senate'=>50],'JP'=>['representatives'=>100],'US'=>['house'=>100,'senate'=>50]
                    };
                    $inst->enableAccountability($openingIdentity['id']??'initial-government',$seats);
                }
                if(in_array($this->scenario->id,[AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                    $electoralConfig=$country==='FR'&&in_array($this->scenario->id,[AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)?(in_array($this->scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)?ParliamentaryLifecycle::publishedElectionConfig($day):ParliamentaryLifecycle::electionConfig($day)):($country!=='FR'&&in_array($this->scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)?ComparativeElectionCalendar::config($country,$day):ElectoralScenario::config($country,$day));
                    if($managed&&$bindIdentity&&$country!=='FR')$electoralConfig=DocumentaryForeignBallot::config($country,$electoralConfig);
                    $inst->enableElectoral($electoralConfig);
                }
                if(in_array($this->scenario->id,[AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$inst->enablePoliticalAI();
                if($country==='FR'&&in_array($this->scenario->id,[AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$inst->enableRevision();
                if($country==='FR'&&in_array($this->scenario->id,[AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$inst->enableLegislation(in_array($this->scenario->id,[AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true),in_array($this->scenario->id,[AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true),in_array($this->scenario->id,[AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true),in_array($this->scenario->id,[AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true),in_array($this->scenario->id,[AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true),in_array($this->scenario->id,[AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true),in_array($this->scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true),$this->scenario->id===AlphaScenario::INVESTITURE?2028:2027,$this->scenario->id===AlphaScenario::INVESTITURE,$this->scenario->id===AlphaScenario::INVESTITURE);
                if($country==='FR'&&$this->scenario->id===AlphaScenario::INVESTITURE){$inst->enableContinuityLot();$inst->enableCabinetSenateLot();$inst->enableFinanceRequests();$inst->enableNationalService();if($managed)$inst->enableSocialProgramme();}
                if($country!=='FR'&&$this->scenario->id===AlphaScenario::INVESTITURE){$inst->enableForeignRecurring();$inst->enableFormationProtocol();$inst->enableAutonomousFormation($managed&&$bindIdentity&&$country==='JP',$managed&&$bindIdentity&&$country==='JP',$managed&&$bindIdentity);if($managed&&$bindIdentity&&$country==='US')$inst->enableUsPresidential(true,true);}
                $this->insert('INSERT INTO wp_institutional_states(world_id,country_code,state_json,state_sha,revision) VALUES(?,?,?,?,0)',
                    [$world,$country,self::json($inst->snapshot()),$inst->hash()]);
            }
            $dip=new DiplomaticSimulation($world,$day,$this->scenario->actors);
            if(in_array($this->scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$dip->enableDailyReplies();
            if($this->scenario->id===AlphaScenario::INVESTITURE){$dip->enableAidBridge();$dip->enableCommitments();$dip->enableCommitmentRisk();$dip->enableDefenseReference();$dip->enableHouseholdReference();$dip->enableCandidateReference();}
            $this->insert('INSERT INTO wp_diplomatic_states(world_id,state_json,state_sha,actor_registry_sha,revision) VALUES(?,?,?,?,0)',
                [$world,self::json($dip->snapshot()),$dip->hash(),$this->scenario->actors->hash()]);
            $org=new OrganizationSimulation($world,$day,$this->scenario->actors,$this->scenario->charters);
            if(in_array($this->scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$org->enableEuropeanWithdrawal(true,true,true,true,true,true,true,true,true,true,true,true);
            $os=$org->snapshot();
            $this->insert('INSERT INTO wp_organization_states(world_id,state_json,state_sha,actors_sha,charters_sha,revision) VALUES(?,?,?,?,?,0)',
                [$world,self::json($os),$org->hash(),$os['actors_sha'],$os['charters_sha']]);
            $econ=new WorldEconomicSimulation($world,$day,$this->scenario->actors,$this->scenario->references);
            if(in_array($this->scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$econ->enableDailyBridge();
            $this->insert('INSERT INTO wp_economic_states(world_id,state_json,state_sha,reference_sha,actor_registry_sha,revision) VALUES(?,?,?,?,?,0)',
                [$world,self::json($econ->snapshot()),$econ->hash(),$this->scenario->references->hash(),$this->scenario->actors->hash()]);
            $c=$this->scenario->domestic();
            if($managed){$c['catalog']+=SocialProgramme::policies($c['macro_baseline']);$c['social_economy_from']=$day;}
            $dom=new DomesticPolicySimulation($world,$day,$c['currency'],$c['opening_balance'],$c['baseline_revenue'],$c['baseline_expense'],$c['cohorts'],$c['catalog'],$c['macro_baseline']??null,$c['debt_model']??null,$c['population_model']??null,$c['services_model']??null,$c['corporations_enabled']??false,$c['wage_feedback_enabled']??false,$c['trade_feedback_enabled']??false,$c['firm_model']??null,$c['public_equity_enabled']??false,$c['industrial_enabled']??false,$c['daily_legal_enabled']??false,$c['eu_trade_enabled']??false,$c['eu_trade_v2']??false,$c['eu_trade_v3']??false,$c['eu_trade_v4']??false,$c['finance_autonomy']??false,$c['social_economy_from']??null);
            $this->insert('INSERT INTO wp_domestic_states(world_id,assumptions_json,assumptions_sha,state_json,state_sha,revision) VALUES(?,?,?,?,?,0)',
                [$world,self::json($c),$dom->assumptionsHash(),self::json($dom->snapshot()),$dom->hash()]);
            $this->insert('INSERT INTO wp_world_clock(world_id,committed_day,revision,domestic_enabled) VALUES(?,?,0,1)',[$world,$day]);
            if(in_array($this->scenario->id,[AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $op=new PublicOpinion($world,$day,PublicOpinion::fixture());
                if(in_array($this->scenario->id,[AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$op->enableDialogue();
                if(in_array($this->scenario->id,[AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$op->enableSocialPartners();
                if($this->scenario->campaign())$op->enableCampaign();
                if(in_array($this->scenario->id,[AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$op->enableCivilCrisis();
                if($this->scenario->id===AlphaScenario::INVESTITURE)$op->enableOrderReview();
                if(in_array($this->scenario->id,[AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$op->enablePresidentialMandate(in_array($this->scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)?($this->scenario->id===AlphaScenario::INVESTITURE?PresidentialMandate::investitureFixture():PresidentialMandate::publishedFixture()):PresidentialMandate::fixture(),in_array($this->scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true));
                if(in_array($this->scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))$op->enablePartyResponses();
                if($managed)$op->enableManagedContinuity(true,true);
                $this->insert('INSERT INTO wp_opinion_states(world_id,state_json,state_sha,revision) VALUES(?,?,?,0)',
                    [$world,PublicOpinion::json($op->snapshot()),$op->hash()]);
                $this->insert('UPDATE wp_world_clock SET opinion_enabled=1 WHERE world_id=?',[$world]);
                if($this->scenario->campaign())$this->insert('UPDATE wp_world_clock SET campaign_enabled=1 WHERE world_id=?',[$world]);
                if($managed)UniverseScore::seed($this->db,$world,$this->scenario->bundleSha,$c,
                    ['state'=>$dom->snapshot(),'etag'=>'"'.$dom->hash().'"'],['state'=>$dip->snapshot(),'etag'=>'"'.$dip->hash().'"'],true,true);
                if($this->scenario->id===AlphaScenario::INVESTITURE&&!$managed){
                    (new PdoCampaignScores($this->db))->seed($world,$this->scenario->bundleSha??throw new \DomainException('Ranked world needs pinned bundle'),$c,
                        ['state'=>$dom->snapshot(),'etag'=>'"'.$dom->hash().'"'],['state'=>$dip->snapshot(),'etag'=>'"'.$dip->hash().'"'],['state'=>$op->snapshot(),'etag'=>'"'.$op->hash().'"']);
                    $this->insert('UPDATE wp_world_clock SET score_enabled=1 WHERE world_id=?',[$world]);
                }
                if($this->scenario->id===AlphaScenario::REVOLUTION_CASE){
                    // Explicitly authored regression/narrative case, not a prediction.
                    $events=[];$date=new \DateTimeImmutable($day);
                    foreach(['cabinet_resigned','constitutional_crisis_opened','revolution_attempted','revolution_succeeded'] as $n=>$type)
                        $events[]=CampaignLifecycle::event($world,$date->modify('+'.($n+1).' days')->format('Y-m-d'),'case-event-'.($n+1),$type);
                    CampaignLifecycle::validateQueue($op->snapshot()['campaign'],$events);
                    foreach($events as $event)CampaignEventQueue::insert($this->db,$event);
                }
            }
            WorldMutationFence::seed($this->db,$world);
            foreach ($this->scenario->countries as $country) $this->insert('INSERT INTO wp_world_clock_countries(world_id,country_code) VALUES(?,?)',[$world,$country]);
            if(!$managed)$this->db->commit();
            return ['world_id'=>$world,'scenario'=>$this->scenario->id,'label'=>$label,'role'=>'owner'];
        } catch (\Throwable $e) {
            if (!$managed&&$this->db->inTransaction()) $this->db->rollBack();
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
