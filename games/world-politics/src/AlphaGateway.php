<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

require_once __DIR__.'/AlphaPrincipals.php';
require_once __DIR__.'/PdoWorldPoliticsApi.php';
require_once __DIR__.'/PdoInstitutionalApi.php';
require_once __DIR__.'/PdoDiplomaticApi.php';
require_once __DIR__.'/PdoOrganizationApi.php';
require_once __DIR__.'/PdoEconomicApi.php';
require_once __DIR__.'/MacroeconomicProjection.php';
require_once __DIR__.'/DomesticPolicyApi.php';
require_once __DIR__.'/WorldClockApi.php';
require_once __DIR__.'/EuropeanSpillovers.php';
require_once __DIR__.'/ReferenceDataAudit.php';
require_once __DIR__.'/NationalRenewalCalendar.php';
require_once __DIR__.'/InternationalFinanceDisclosure.php';
require_once __DIR__.'/StrategicDefensePlanning.php';
require_once __DIR__.'/CivilContinuityReport.php';
require_once __DIR__.'/HouseholdIncomeReference.php';
require_once __DIR__.'/HistoricalCandidateReference.php';
require_once __DIR__.'/HouseholdPurchasingPowerProxy.php';
require_once __DIR__.'/HouseholdHistoricalBacktest.php';
require_once __DIR__.'/HouseholdAccount.php';
require_once __DIR__.'/ProgrammeProgress.php';
require_once __DIR__.'/PdoCampaignScores.php';
require_once __DIR__.'/UniverseLobby.php';
require_once __DIR__.'/PdoCareerInstitutionStore.php';

/** Explicit HTTP allowlist. Only the private alpha subset is mounted. */
final class AlphaGateway
{
    private readonly \Closure $connect;
    public function __construct(callable $connect,private readonly string $invite) {$this->connect=\Closure::fromCallable($connect);}
    private function db():\PDO{return ($this->connect)();}

    public function handle(string $method,string $path,string $raw,array $headers,string $peer):array
    {
        try {
            if (strlen($path)>512 || !preg_match('~^/api/v1(?:/[A-Za-z0-9_-]+)*$~D',$path)) throw new AlphaHttpError(404,'not_found');
            if (!in_array($method,['GET','POST','DELETE'],true)) throw new AlphaHttpError(405,'method_not_allowed');
            if (strlen($raw)>32768) throw new AlphaHttpError(413,'body_too_large');
            $h=array_change_key_case($headers,CASE_LOWER);
            $body=null;
            if ($method==='POST') {
                if (!preg_match('~^application/json(?:\s*;\s*charset=utf-8)?$~iD',$h['content-type']??'')) throw new AlphaHttpError(415,'json_required');
                $obj=json_decode($raw,false,32,JSON_THROW_ON_ERROR);
                if (!$obj instanceof \stdClass) throw new AlphaHttpError(400,'json_object_required');
                $body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            } elseif ($raw!=='') throw new AlphaHttpError(400,'body_forbidden');
            if ($path==='/api/v1/health' && $method==='GET') return self::ok(200,['status'=>'ok','profile'=>'private-alpha']);
            $accounts=new AlphaAccounts($this->db(),$this->invite);
            // Never trust X-Forwarded-For supplied by clients.
            $accounts->limit('http-peer',$peer,240,60);
            if ($method==='POST' && $path==='/api/v1/accounts') {
                $accounts->limit('register-peer',$peer,6,300);
                return self::ok(201,$accounts->register($body));
            }
            if ($method==='POST' && $path==='/api/v1/sessions') {
                $accounts->limit('login-peer',$peer,20,300);
                return self::ok(200,$accounts->login($body));
            }
            if($method==='POST'&&$path==='/api/v1/account-recovery'){
                $accounts->limit('recovery-peer',$peer,10,300);
                return self::ok(200,$accounts->recover($body));
            }
            if(str_starts_with($path,'/api/v1/player-data/')){
                require_once __DIR__.'/PlayerDataAccess.php';
                if($method!=='GET')throw new AlphaHttpError(405,'method_not_allowed');
                $header=$h['authorization']??'';$id=PlayerDataAccess::authenticate($this->db(),$header);
                $accounts->limit('player-data-account',$id,60,60);
                if($path==='/api/v1/player-data/worlds')return self::ok(200,PlayerDataAccess::worlds($this->db(),$header));
                if(preg_match('~^/api/v1/player-data/worlds/([a-z][a-z0-9_-]{0,63})$~D',$path,$d)){
                    $secret=getenv('WP_CAREER_EVIDENCE_KEY');
                    if(!is_string($secret)||strlen($secret)<32)throw new AlphaHttpError(404,'not_found');
                    return self::ok(200,(new PdoCareerInstitutionStore($this->db(),new CareerInstitutionEvidence($secret)))->exportForDataToken($d[1],$header));
                }
                throw new AlphaHttpError(404,'not_found');
            }
            $session=$accounts->authenticate($h['authorization']??'');
            $accounts->limit('http-account',$session->id,120,60);
            if ($path==='/api/v1/session' && $method==='DELETE') {
                $session->revoke();return self::ok(200,['revoked'=>true]);
            }
            if ($path==='/api/v1/me/recovery-code' && $method==='POST') return self::ok(201,$accounts->createRecovery($session,$body));
            if ($path==='/api/v1/me/password' && $method==='POST') return self::ok(200,$accounts->changePassword($session,$body));
            if(in_array($path,['/api/v1/me/billing/checkout','/api/v1/me/billing/portal'],true)){
                if($method!=='POST')throw new AlphaHttpError(405,'method_not_allowed');
                AlphaAccounts::fields($body,[]);
                if(getenv('WP_STRIPE_ENABLED')!=='YES')throw new AlphaHttpError(503,'stripe_disabled');
                $accounts->limit('billing-peer',$peer,15,60);$accounts->limit('billing-account',$session->id,5,60);
                require_once __DIR__.'/GameStripe.php';
                StripeConfiguration::ready();$client=new StripeTestClient();
                $stripe=new GameStripe($this->db(),$client->request(...));
                if($path==='/api/v1/me/billing/portal')return self::ok(200,$stripe->portal($session->id));
                if(!isset($h['idempotency-key']))throw new AlphaHttpError(428,'precondition_required');
                return self::ok(201,$stripe->checkout($session->id,$h['idempotency-key']));
            }
            if($path==='/api/v1/me/data-token'){
                require_once __DIR__.'/PlayerDataAccess.php';
                if($method==='POST'){AlphaAccounts::fields($body,[]);return self::ok(201,PlayerDataAccess::issue($this->db(),$session));}
                if($method==='DELETE'){PlayerDataAccess::revoke($this->db(),$session);return self::ok(200,['revoked'=>true]);}
                throw new AlphaHttpError(405,'method_not_allowed');
            }
            if ($path==='/api/v1/me' && $method==='GET') return self::ok(200,['account_id'=>$session->id,'username'=>$session->username]);
            if($path==='/api/v1/career-worlds'||str_starts_with($path,'/api/v1/career-worlds/')){
                $secret=getenv('WP_CAREER_EVIDENCE_KEY');
                if(!is_string($secret)||strlen($secret)<32)throw new AlphaHttpError(404,'not_found');
                $token=substr($h['authorization'],7);
                $cs=new PdoCareerInstitutionStore($this->db(),new CareerInstitutionEvidence($secret));
                try{
                    if($method==='GET'&&$path==='/api/v1/career-worlds/capabilities'){
                        require_once __DIR__.'/PlayableScope.php';
                        return self::ok(200,PlayableScope::report());
                    }
                    if($method==='GET'&&preg_match('~^/api/v1/career-worlds/rankings/(global|[1-9][0-9]{0,8})/cohorts$~D',$path,$rk)){
                        require_once __DIR__.'/CareerScorePublication.php';
                        return self::ok(200,CareerScorePublication::cohorts($this->db(),$rk[1]==='global'?null:(int)$rk[1]));
                    }
                    if($method==='GET'&&preg_match('~^/api/v1/career-worlds/rankings/(global|[1-9][0-9]{0,8})/([a-f0-9]{64})(?:/([1-9][0-9]{0,2})/([a-f0-9]{64}))?$~D',$path,$rk)){
                        require_once __DIR__.'/CareerScorePublication.php';
                        return self::ok(200,CareerScorePublication::rankings($this->db(),$session->id,$rk[1]==='global'?null:(int)$rk[1],$rk[2],isset($rk[3])?(int)$rk[3]:1,$rk[4]??null));
                    }
                    if($method==='GET'&&preg_match('~^/api/v1/career-worlds/public(?:/([1-9][0-9]{0,5}))?$~D',$path,$pg))
                        return self::ok(200,$cs->publicDirectory($token,isset($pg[1])?(int)$pg[1]:1));
                    if($path==='/api/v1/career-worlds/join'&&$method==='POST'){
                        AlphaAccounts::fields($body,[...(array_key_exists('world_id',$body)?['world_id']:[]),...(array_key_exists('reference_id',$body)?['reference_id']:[]),...(array_key_exists('fictional',$body)?['fictional']:[])]);
                        foreach(['world_id','reference_id'] as $k)if(array_key_exists($k,$body)&&!is_string($body[$k]))throw new AlphaHttpError(422,'invalid_public_career_join');
                        if(array_key_exists('fictional',$body)&&!is_array($body['fictional']))throw new AlphaHttpError(422,'invalid_fictional');
                        if(!isset($h['idempotency-key']))throw new AlphaHttpError(428,'precondition_required');
                        return self::ok(201,$cs->joinPublic($token,$h['idempotency-key'],$secret,$body['world_id']??null,$body['reference_id']??null,$body['fictional']??null));
                    }
                    if($path==='/api/v1/career-worlds'&&$method==='POST'){
                        AlphaAccounts::fields($body,['label',...(array_key_exists('profile',$body)?['profile']:[]),...(array_key_exists('country',$body)?['country']:[]),...(array_key_exists('reference_id',$body)?['reference_id']:[]),...(array_key_exists('fictional',$body)?['fictional']:[])]);if(array_key_exists('fictional',$body)&&!is_array($body['fictional']))throw new AlphaHttpError(422,'invalid_fictional');if(array_key_exists('reference_id',$body)&&!is_string($body['reference_id']))throw new AlphaHttpError(422,'invalid_reference');if(array_key_exists('country',$body)&&!is_string($body['country']))throw new AlphaHttpError(422,'invalid_country');if(isset($body['profile'])&&!is_string($body['profile']))throw new AlphaHttpError(422,'invalid_profile');if(array_key_exists('profile',$body)&&$body['profile']===null)throw new AlphaHttpError(422,'invalid_profile');if(!is_string($body['label']))throw new AlphaHttpError(422,'invalid_label');
                        if(!isset($h['idempotency-key']))throw new AlphaHttpError(428,'precondition_required');
                        return self::ok(201,$cs->createForSession($token,$body['label'],$h['idempotency-key'],$secret,$body['profile']??'institutional',$body['country']??'FR',$body['reference_id']??null,$body['fictional']??null));
                    }
                    if(preg_match('~^/api/v1/career-worlds/([a-z][a-z0-9_-]{0,63})/(career|characters|admission|members|economy|cabinet|policy-proposals|institutions|bills)$~D',$path,$cm)){
                        if($session->role($cm[1])===null)throw new AlphaHttpError(404,'not_found');
                        if($cm[2]==='institutions'&&$method==='GET')return self::ok(200,$cs->institutionsForSession($cm[1],$token));
                        if($cm[2]==='bills'&&$method==='POST'){
                            if(!isset($h['idempotency-key'],$h['if-match']))throw new AlphaHttpError(428,'precondition_required');
                            $result=$cs->submitBillForSession($cm[1],$token,$body,$h['idempotency-key'],$h['if-match']);
                            $response=self::ok($result['status'],$result['result']);$response['headers']['ETag']=$result['etag'];return $response;
                        }
                        if($cm[2]==='economy'&&$method==='GET')return self::ok(200,$cs->economicForSession($cm[1],$token));
                        if($cm[2]==='career'&&$method==='GET')return self::ok(200,$cs->readForSession($cm[1],$token));
                        if($cm[2]==='characters'&&$method==='GET')return self::ok(200,$cs->charactersForSession($cm[1],$token));
                        if($cm[2]==='policy-proposals'&&$method==='POST'){
                            if(!isset($h['idempotency-key'],$h['if-match']))throw new AlphaHttpError(428,'precondition_required');
                            $result=$cs->proposePolicyForSession($cm[1],$token,$body,$h['idempotency-key'],$h['if-match']);
                            $response=self::ok($result['status'],$result['result']);$response['headers']['ETag']=$result['etag'];return $response;
                        }
                        if($cm[2]==='cabinet'&&$method==='POST'){
                            if(!isset($h['idempotency-key']))throw new AlphaHttpError(428,'precondition_required');
                            return self::ok(200,$cs->cabinetForSession($cm[1],$token,$body,$h['idempotency-key'],$secret));
                        }
                        if($cm[2]==='members'&&$method==='POST'){AlphaAccounts::fields($body,['account_id']);if(!is_string($body['account_id']))throw new AlphaHttpError(422,'invalid_account');return self::ok(200,$cs->inviteForSession($cm[1],$token,$body['account_id']));}
                        if($cm[2]==='admission'&&$method==='POST'){AlphaAccounts::fields($body,[...(array_key_exists('reference_id',$body)?['reference_id']:[]),...(array_key_exists('fictional',$body)?['fictional']:[])]);if(array_key_exists('fictional',$body)&&!is_array($body['fictional']))throw new AlphaHttpError(422,'invalid_fictional');if(array_key_exists('reference_id',$body)&&!is_string($body['reference_id']))throw new AlphaHttpError(422,'invalid_reference');return self::ok(200,$cs->admitForSession($cm[1],$token,$secret,$body['reference_id']??null,$body['fictional']??null));}
                    }
                }catch(\DomainException $e){throw new AlphaHttpError(409,'career_request_rejected');}
                catch(\InvalidArgumentException $e){throw new AlphaHttpError(422,'invalid_career_request');}
                throw new AlphaHttpError(404,'not_found');
            }
            if($method==='GET'&&preg_match('~^/api/v1/universes/([1-9][0-9]{0,8})/characters/([A-Z]{2})$~D',$path,$uc))
                return self::ok(200,(new UniverseLobby($this->db()))->characters($session,(int)$uc[1],$uc[2]));
            if($path==='/api/v1/universes'&&$method==='GET')return self::ok(200,(new UniverseLobby($this->db()))->list($session));
            if($path==='/api/v1/universes/join'&&$method==='POST'){
                if(!isset($h['idempotency-key']))throw new AlphaHttpError(428,'precondition_required');
                return self::ok(201,(new UniverseLobby($this->db()))->join($session,$body,$h['idempotency-key']));
            }
            if($method==='GET'&&preg_match('~^/api/v1/universe-rankings/global/score/([a-f0-9]{64})(?:/([1-9][0-9]{0,5}))?$~D',$path,$sr))
                return self::ok(200,UniverseScore::rankings($this->db(),$session,null,$sr[1],isset($sr[2])?(int)$sr[2]:1));
            if($method==='GET'&&preg_match('~^/api/v1/universes/([1-9][0-9]{0,8})/rankings/score/([a-f0-9]{64})(?:/([1-9][0-9]{0,5}))?$~D',$path,$sr))
                return self::ok(200,UniverseScore::rankings($this->db(),$session,(int)$sr[1],$sr[2],isset($sr[3])?(int)$sr[3]:1));
            if($method==='GET'&&preg_match('~^/api/v1/universe-rankings/global/([1-9][0-9]{0,5})$~D',$path,$pg))
                return self::ok(200,(new UniverseLobby($this->db()))->rankings($session,null,(int)$pg[1]));
            if($method==='GET'&&preg_match('~^/api/v1/universes/([1-9][0-9]{0,8})/rankings/([1-9][0-9]{0,5})$~D',$path,$pg))
                return self::ok(200,(new UniverseLobby($this->db()))->rankings($session,(int)$pg[1],(int)$pg[2]));
            if($method==='GET'&&preg_match('~^/api/v1/universes/page/([1-9][0-9]{0,5})$~D',$path,$pg))
                return self::ok(200,(new UniverseLobby($this->db()))->list($session,(int)$pg[1]));
            if($path==='/api/v1/universe-rankings/global'&&$method==='GET')
                return self::ok(200,(new UniverseLobby($this->db()))->rankings($session,null));
            if(preg_match('~^/api/v1/universes/([1-9][0-9]{0,8})/(rankings|membership|programme)$~D',$path,$um)){
                $lobby=new UniverseLobby($this->db());
                if($um[2]==='rankings'&&$method==='GET')return self::ok(200,$lobby->rankings($session,(int)$um[1]));
                if($um[2]==='membership'&&$method==='DELETE')return self::ok(200,$lobby->leave($session,(int)$um[1]));
                if($um[2]==='programme'&&$method==='GET')return self::ok(200,$lobby->programmeStatus($session,(int)$um[1]));
                if($um[2]==='programme'&&$method==='POST')return self::ok(200,$lobby->programme($session,(int)$um[1],$body));
                throw new AlphaHttpError(405,'method_not_allowed');
            }
            if ($path==='/api/v1/national-renewal-calendar' && $method==='GET') return self::ok(200,NationalRenewalCalendar::report());
            if($path==='/api/v1/leaderboards'||str_starts_with($path,'/api/v1/leaderboards/')){
                if($method!=='GET')throw new AlphaHttpError(405,'method_not_allowed');
                $scores=new PdoCampaignScores($this->db());
                if($path==='/api/v1/leaderboards')return self::ok(200,$scores->cohorts(1));
                if(preg_match('~^/api/v1/leaderboards/cohorts/([1-9][0-9]{0,5})$~D',$path,$lm)&&((int)$lm[1])<=100000)return self::ok(200,$scores->cohorts((int)$lm[1]));
                if(preg_match('~^/api/v1/leaderboards/([a-f0-9]{64})/([1-9][0-9]{0,5})$~D',$path,$lm)&&((int)$lm[2])<=100000)return self::ok(200,$scores->leaderboard($lm[1],(int)$lm[2],$session->id));
                throw new AlphaHttpError(404,'not_found');
            }
            if($path==='/api/v1/candidate-options'){
                if($method!=='GET')throw new AlphaHttpError(405,'method_not_allowed');
                return self::ok(200,CampaignCandidate::options());
            }
            $scenario=new AlphaScenario();
            if ($path==='/api/v1/scenarios' && $method==='GET') {
                $base=GlobalReferenceBundle::published();
                return self::ok(200,['scenarios'=>[['id'=>AlphaScenario::ID,'start_day'=>AlphaScenario::DAY],
                    ['id'=>AlphaScenario::GLOBAL,'start_day'=>GlobalReferenceBundle::DATE,'reference_sha'=>$base->sha(),
                    'coverage'=>$base->coverage()],['id'=>AlphaScenario::COMPARATIVE,'start_day'=>GlobalReferenceBundle::DATE,
                    'institutional_countries'=>['DE','ES','FR','GB','IT','JP','US'],'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::ELECTORAL,'start_day'=>GlobalReferenceBundle::DATE,'institutional_countries'=>['DE','ES','FR','GB','IT','JP','US'],
                    'electoral_data'=>'fictional_scenario','reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::POLITICAL,'start_day'=>GlobalReferenceBundle::DATE,'institutional_countries'=>['DE','ES','FR','GB','IT','JP','US'],
                    'electoral_data'=>'fictional_scenario','political_ai'=>PoliticalNegotiator::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::OPINION,'start_day'=>GlobalReferenceBundle::DATE,'institutional_countries'=>['DE','ES','FR','GB','IT','JP','US'],
                    'public_opinion'=>PublicOpinion::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::DIALOGUE,'start_day'=>GlobalReferenceBundle::DATE,'institutional_countries'=>['DE','ES','FR','GB','IT','JP','US'],
                    'public_opinion'=>PublicOpinion::VERSION,'civic_dialogue'=>CivicDialogue::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::SURVIVAL,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::REVOLUTION_CASE,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,'scripted_case'=>true,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::PRESIDENTIAL,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'presidential_calendar'=>PresidentialMandate::VERSION,'electoral_data'=>'fictional_scenario','reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::REVISION,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'constitutional_revision'=>ConstitutionalRevision::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::LEGISLATIVE,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'legislation'=>BicameralLegislation::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::RESPONSIBILITY,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'responsibility'=>LegislativeResponsibility::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::ASSEMBLY,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'assembly'=>AssemblyRollCalls::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::PARLIAMENT,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'parliamentary_lifecycle'=>ParliamentaryLifecycle::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::CABINET,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'cabinet'=>FrenchCabinet::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::EXECUTIVE,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'executive'=>ExecutiveActs::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::FINANCE,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'finance'=>FinanceBudget::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::MACRO,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'macro'=>MacroeconomicProjection::VERSION,'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::FISCAL_LOOP,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'macro'=>MacroFiscalLoop::VERSION,'playable_countries'=>['FR'],'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::DEBT,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'debt'=>SovereignDebtLedger::VERSION,'playable_countries'=>['FR'],'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::FINANCIAL_CRISIS,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'crisis'=>FinancialCrisis::VERSION,'playable_countries'=>['FR'],'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::POPULATION,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'population'=>FrenchDemography::VERSION,'playable_countries'=>['FR'],'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::SERVICES,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'services'=>FrenchPublicServices::VERSION,'playable_countries'=>['FR'],'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::PARTNERS,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'social_partners'=>SocialPartners::VERSION,'playable_countries'=>['FR'],'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::CORPORATIONS,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'corporations'=>ProfessionalCorporations::VERSION,'playable_countries'=>['FR'],'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::CIVIL_CRISIS,'start_day'=>GlobalReferenceBundle::DATE,'campaign'=>CampaignLifecycle::VERSION,
                    'civil_mobilisation'=>CivilMobilisation::VERSION,'playable_countries'=>['FR'],'reference_sha'=>$base->sha()],
                    ['id'=>AlphaScenario::INVESTITURE,'start_day'=>FrenchElectionCalendar::PRESIDENTIAL_HANDOVER,'campaign'=>CampaignLifecycle::VERSION,'canonical_campaign'=>true,'opening_observation_vintage'=>GlobalReferenceBundle::DATE,'opening_stock_status'=>'historical_anchor_not_2027_observation','playable_countries'=>['FR'],'reference_sha'=>$base->sha()]]]);
            }
            $worlds=new AlphaWorlds($this->db(),$scenario);
            if ($path==='/api/v1/worlds') {
                if ($method==='GET') return self::ok(200,$worlds->list($session));
                if ($method==='POST') {
                    $key=$h['idempotency-key']??'';
                    if ($key==='') throw new AlphaHttpError(428,'precondition_required');
                    if (in_array($body['scenario']??null,[AlphaScenario::GLOBAL,AlphaScenario::COMPARATIVE,AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)) {
                        $sha=$body['reference_bundle_sha']??null;
                        if (array_key_exists('reference_bundle_sha',$body) && (!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)))
                            throw new AlphaHttpError(422,'invalid_reference_bundle');
                        try {
                            $store=new PdoGlobalReferenceBundles($this->db());$bundled=GlobalReferenceBundle::published();
                            if($sha===null||$sha===$bundled->sha()){$bundle=$bundled;$sha=$store->install($bundle);}
                            else $bundle=$store->load($sha);
                        }
                        catch (\DomainException $e) {throw new AlphaHttpError(422,'unknown_reference_bundle');}
                        $scenario=new AlphaScenario($body['scenario'],$bundle,$sha);
                        $worlds=new AlphaWorlds($this->db(),$scenario);
                    }
                    return self::ok(201,$worlds->create($session,$body,$key));
                }
                throw new AlphaHttpError(405,'method_not_allowed');
            }
            if (!preg_match('~^/api/v1/worlds/([a-z][a-z0-9_-]{0,63})(/.*)$~D',$path,$m)) throw new AlphaHttpError(404,'not_found');
            $world=$m[1];$suffix=$m[2];$role=$session->role($world);
            if ($role===null) throw new AlphaHttpError(404,'not_found');
            $careerMarker=$this->db()->prepare('SELECT scenario FROM wp_alpha_worlds WHERE world_id=?');
            $careerMarker->execute([$world]);
            if(in_array($careerMarker->fetchColumn(),['wp-career-v1','wp-career-research-v1'],true))throw new AlphaHttpError(404,'not_found');
            if($method!=='GET'&&$role!=='owner'){
                $country=$session->country($world);
                // Only existing country-scoped command APIs; never grant
                // clock, sharing or generic ownership to a national slot.
                if($country===null||in_array($suffix,['/members','/clock/advances','/countries/FR/politics/head-of-state'],true)||
                    ($country!=='FR'&&!in_array($suffix,['/diplomacy/proposals','/diplomacy/responses'],true)))
                    throw new AlphaHttpError(403,'forbidden');
            }
            if ($suffix==='/members' && $method==='POST') return self::ok(200,$worlds->share($session,$world,$body));
            $q=$this->db()->prepare('SELECT scenario,reference_bundle_sha FROM wp_alpha_worlds WHERE world_id=?');
            $q->execute([$world]);$definition=$q->fetch(\PDO::FETCH_ASSOC);
            if ($definition===false) throw new AlphaHttpError(404,'not_found');
            if (in_array($definition['scenario'],[AlphaScenario::GLOBAL,AlphaScenario::COMPARATIVE,AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)) {
                $sha=$definition['reference_bundle_sha'];
                $bundle=$sha===null?new GlobalReferenceBundle():(new PdoGlobalReferenceBundles($this->db()))->load($sha);
                $scenario=new AlphaScenario($definition['scenario'],$bundle,$sha);
                if ($suffix==='/references' && $method==='GET') {
                    // Verify the same hashes as the durable economy before exposing data.
                    $q=$this->db()->prepare('SELECT actor_registry_sha,reference_sha FROM wp_economic_states WHERE world_id=?');
                    $q->execute([$world]);$pins=$q->fetch(\PDO::FETCH_ASSOC);
                    if ($pins===false || $pins['actor_registry_sha']!==$bundle->actors->hash() || $pins['reference_sha']!==$bundle->references->hash())
                        throw new \UnexpectedValueException('Pinned references differ');
                    return self::ok(200,['bundle_sha'=>$bundle->sha(),'manifest'=>$bundle->manifest,'coverage'=>$bundle->coverage(),
                        'countries'=>$bundle->countries->all(),'observations'=>$bundle->references->all(),
                        'reference_data_audit'=>ReferenceDataAudit::report()]);
                }
            } elseif ($definition['scenario']!==AlphaScenario::ID) throw new \UnexpectedValueException('Unknown saved scenario');
            $principal=new AlphaPrincipal($session,$world,false,$scenario->countries,in_array($scenario->id,[AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true),in_array($scenario->id,[AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true),in_array($scenario->id,[AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true));
            $service=new AlphaPrincipal($session,$world,true,$scenario->countries);
            if ($suffix==='/clock' || $suffix==='/clock/advances') {
                $dom=new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db(),true));
                $inst=new PdoInstitutionalStore($this->db(),$service);
                $opinion=in_array($scenario->id,[AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)?new PdoPublicOpinionStore($this->db(),$service,$dom,$inst):null;
                $clock=new WorldClockCoordinator($this->db(),
                    new PdoOrganizationStore($this->db(),$scenario->actors,$scenario->charters,$service),
                    new PdoDiplomaticStore($this->db(),$scenario->actors,$service),
                    new PdoEconomicStore($this->db(),$scenario->actors,$scenario->references,$service),$service,
                    $inst,$scenario->countries,null,$dom,$opinion);
                return (new WorldClockApi($clock))->handle($method,$path,$body,$h);
            }
            // Partial cross-domain days are not advertised as a coherent view.
            $q=$this->db()->prepare('SELECT pending_day,game_over FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$current=$q->fetch(\PDO::FETCH_ASSOC);
            if($current===false)throw new AlphaHttpError(404,'not_found');
            if($current['pending_day']!==null)throw new AlphaHttpError(409,'world_advancing');
            if($method!=='GET'&&(int)$current['game_over']===1)throw new AlphaHttpError(409,'game_over');
            if($method==='GET'&&(
                ($suffix==='/countries/FR/opinion'&&in_array($scenario->id,[AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))
                ||($suffix==='/countries/FR/dialogue'&&in_array($scenario->id,[AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))
                ||($suffix==='/countries/FR/social-partners'&&in_array($scenario->id,[AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))
                ||($suffix==='/countries/FR/party-responses'&&in_array($scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))
                ||($suffix==='/countries/FR/civil-mobilisation'&&in_array($scenario->id,[AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))
                ||($suffix==='/campaign'&&$scenario->campaign())
                ||($suffix==='/countries/FR/presidency'&&in_array($scenario->id,[AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)))){

                // Authorize reads with a request-local scheduler identity that
                // may read, but cannot execute through this GET route.
                $dom=new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db(),true));
                $op=new PdoPublicOpinionStore($this->db(),$service,$dom,new PdoInstitutionalStore($this->db(),$service));
                $r=$op->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');
                $q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                if($suffix==='/countries/FR/presidency'){
                    if(!isset($r['state']['presidency']))throw new \UnexpectedValueException('Missing pinned presidency');
                    $body=$r['state']['presidency'];
                    // Internal immutable tally fixture is not a published result.
                    unset($body['config']);
                    if(array_key_exists('managed_vacancy',$r['state']))$body['managed_vacancy']=$r['state']['managed_vacancy'];
                }elseif($suffix==='/countries/FR/party-responses'){
                    if(!isset($r['state']['party_responses']))throw new AlphaHttpError(404,'not_found');
                    $body=['world_id'=>$world,'country'=>'FR','party_responses'=>$r['state']['party_responses']];
                }elseif($suffix==='/countries/FR/civil-mobilisation'){
                    if(!isset($r['state']['civil_mobilisation']))throw new \UnexpectedValueException('Missing pinned mobilisation');
                    $body=['world_id'=>$world,'country'=>'FR','civil_mobilisation'=>$r['state']['civil_mobilisation']];
                }elseif($suffix==='/campaign'){
                    if(!isset($r['state']['campaign']))throw new \UnexpectedValueException('Missing pinned campaign');
                    $body=$r['state']['campaign'];
                    if($scenario->id===AlphaScenario::INVESTITURE)$body['tenure']=CampaignLifecycle::tenure($body);
                }elseif($suffix==='/countries/FR/social-partners'){
                    if(!isset($r['state']['social_partners']))throw new \UnexpectedValueException('Missing pinned social partners');
                    $body=['world_id'=>$world,'country'=>'FR','social_partners'=>$r['state']['social_partners']];
                }elseif($suffix==='/countries/FR/dialogue'){
                    if(!isset($r['state']['dialogue']))throw new \UnexpectedValueException('Missing pinned civic dialogue');
                    $body=['world_id'=>$world,'country'=>'FR','dialogue'=>$r['state']['dialogue']];
                }else $body=$r['state'];
                $response=self::ok(200,$body);$response['headers']['ETag']=$r['etag'];return $response;
            }
            if (preg_match('~^/countries/FR/politics(?:/person-template|/people(?:/[a-z][a-z0-9_-]{0,63})?|/head-of-state)?$~D',$suffix)) {
                if ($method==='POST' && !in_array($suffix,['/countries/FR/politics/people','/countries/FR/politics/head-of-state'],true))
                    throw new AlphaHttpError(405,'method_not_allowed');
                if ($method==='POST' && $suffix==='/countries/FR/politics/people' && array_key_exists('id',$body))
                    throw new AlphaHttpError(422,'server_generated_identity');
                return (new PdoWorldPoliticsApi(new PdoPoliticalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            }
            if($method==='GET'&&$suffix==='/countries/FR/services'&&in_array($scenario->id,[AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$dr['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,FrenchPublicServices::project($dr['state']));$response['headers']['ETag']=$dr['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/population'&&in_array($scenario->id,[AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$dr['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,FrenchDemography::project($dr['state']));$response['headers']['ETag']=$dr['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/debt'&&in_array($scenario->id,[AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$dr['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,SovereignDebtLedger::project($dr['state']));$response['headers']['ETag']=$dr['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/macro'&&in_array($scenario->id,[AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $er=(new PdoEconomicStore($this->db(),$scenario->actors,$scenario->references,$principal))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day']||$c['committed_day']!==$dr['state']['day']||$c['committed_day']!==$er['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $m=in_array($scenario->id,[AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)?MacroFiscalLoop::project($dr['state']):MacroeconomicProjection::project($dr['state'],$er['state'],$r['state']['legislation']['finance']);
                $m['source_hashes']=['domestic'=>trim($dr['etag'],'"'),'economic'=>trim($er['etag'],'"'),'institutional'=>trim($r['etag'],'"')];
                $response=self::ok(200,$m);
                $response['headers']['ETag']='"'.hash('sha256',json_encode($m,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)).'"';return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/finance'&&in_array($scenario->id,[AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day']||$c['committed_day']!==$dr['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $f=$r['state']['legislation']['finance'];
                $response=self::ok(200,['day'=>$r['state']['day'],'finance'=>$f,'catalog'=>FinanceBudget::catalog(FinanceBudget::filingYear($f,$dr['state']['day'])),...($f['version']===FinanceBudget::SERIES?['filing_year'=>FinanceBudget::filingYear($f,$dr['state']['day']),'last_supported_year'=>FinanceBudget::LAST_YEAR]:[]),...(($f['corrective_enabled']??false)?['corrective_catalog'=>FinanceBudget::correctiveCatalog((int)substr($dr['state']['day'],0,4)),'corrective_effective_on'=>substr($dr['state']['day'],0,4).'-10-01','corrective_eligible'=>FinanceBudget::eligibleCorrective($f,(int)substr($dr['state']['day'],0,4))]:[]),...(isset($r['state']['finance_requests'])?['autonomous_requests'=>$r['state']['finance_requests']]:[]),'treasury'=>FinanceBudget::project($f,$dr['state'])]);
                $response['headers']['ETag']='"'.hash('sha256',$r['etag'].'/'.$dr['etag']).'"';return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/executive'&&in_array($scenario->id,[AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,['day'=>$r['state']['day'],'executive'=>$r['state']['legislation']['executive'],'catalog'=>ExecutiveActs::catalog()]);
                $response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/cabinet'&&in_array($scenario->id,[AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $g=$r['state']['legislation']['responsibility'];
                $response=self::ok(200,['day'=>$r['state']['day'],...FrenchCabinet::projection($g['cabinet'],$g['assembly']),'catalog'=>FrenchCabinet::catalog(),...(isset($r['state']['cabinet_requests'])?['requests'=>$r['state']['cabinet_requests']]:[])]);
                $response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/assembly'&&in_array($scenario->id,[AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,['assembly'=>$r['state']['legislation']['responsibility']['assembly'],'day'=>$r['state']['day'],... (isset($r['state']['legislation']['responsibility']['lifecycle'])?['lifecycle'=>$r['state']['legislation']['responsibility']['lifecycle']]:[])]);
                $response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/legislation'&&in_array($scenario->id,[AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,['legislation'=>$r['state']['legislation'],'catalog'=>BicameralLegislation::catalog(isset($r['state']['legislation']['minor_units']),isset($r['state']['legislation']['national_service']),isset($r['state']['legislation']['social_programme']))]);
                $response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='POST'&&in_array($scenario->id,[AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)&&$suffix==='/countries/FR/institutions/decisions'
                &&($body['action']??null)==='legislation_draft')
                return (new PdoInstitutionalApi(new PdoInstitutionalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            if($method==='GET'&&$suffix==='/countries/FR/constitution'&&in_array($scenario->id,[AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);
                $c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,['revision'=>$r['state']['revision'],'catalog'=>ConstitutionalRevision::catalog(),'civic_initiative'=>$r['state']['civic_initiative']??null,...(isset($r['state']['revision_requests'])?['autonomous_requests'=>$r['state']['revision_requests']]:[])]);
                $response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='POST'&&in_array($scenario->id,[AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)&&$suffix==='/countries/FR/institutions/decisions'
                &&($body['action']??null)==='revision_draft')
                return (new PdoInstitutionalApi(new PdoInstitutionalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            if($method==='POST'&&in_array($scenario->id,[AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)&&$suffix==='/countries/FR/institutions/decisions'
                &&($body['action']??null)==='electoral_coalition')
                return (new PdoInstitutionalApi(new PdoInstitutionalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            if($method==='POST'&&$scenario->id===AlphaScenario::INVESTITURE&&$suffix==='/countries/FR/institutions/decisions'&&in_array($body['action']??null,['request_dissolution','request_cabinet_appointment','request_annual_budget','request_corrective_budget'],true))
                return (new PdoInstitutionalApi(new PdoInstitutionalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            if ($method==='GET' && preg_match('~^/countries/([A-Z]{2})/institutions(?:/rulebook)?$~D',$suffix,$countryMatch)
                && in_array($countryMatch[1],$scenario->countries,true))
                return (new PdoInstitutionalApi(new PdoInstitutionalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            if($method==='GET'&&$suffix==='/countries/FR/historical-candidates'&&$scenario->id===AlphaScenario::INVESTITURE){
                $dp=(new PdoDiplomaticStore($this->db(),$scenario->actors,$principal))->read($world);
                if(!isset($dp['state']['candidate_reference']))throw new AlphaHttpError(404,'not_found');
                $report=HistoricalCandidateReference::report($dp['state']['candidate_reference'])+['world_id'=>$world];$response=self::ok(200,$report);$response['headers']['ETag']='"'.hash('sha256',json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)).'"';return $response;
            }
            if($suffix==='/score'){
                if($method!=='GET')throw new AlphaHttpError(405,'method_not_allowed');
                if($scenario->id!==AlphaScenario::INVESTITURE)throw new AlphaHttpError(404,'not_found');
                $report=(new PdoCampaignScores($this->db()))->read($world);
                if($report===null)throw new AlphaHttpError(404,'not_found');
                if($report['world_advancing'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,$report);$response['headers']['ETag']='"'.CampaignScore::digest($report).'"';return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/programme-progress'&&$scenario->id===AlphaScenario::INVESTITURE){
                $db=$this->db();$db->beginTransaction();
                try{
                    $q=$db->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=? FOR UPDATE');$q->execute([$world]);$clock=$q->fetch(\PDO::FETCH_ASSOC);
                    if($clock===false||$clock['pending_day']!==null)throw new AlphaHttpError(409,'world_advancing');
                    $roster=(new PdoPoliticalStore($db,$principal))->read($world,'FR');
                    $dom=(new PdoDomesticPolicyStore($db,new AlphaDomesticPrincipal($session,$world,$db)))->read($world);
                    if($dom['state']['day']!==$clock['committed_day'])throw new AlphaHttpError(409,'world_advancing');
                    $report=ProgrammeProgress::report($roster,$dom);$response=self::ok(200,$report);$response['headers']['ETag']='"'.CampaignScore::digest($report).'"';$db->commit();return $response;
                }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            }
            if($method==='GET'&&in_array($suffix,['/countries/FR/purchasing-power-proxy','/countries/FR/household-account'],true)&&$scenario->id===AlphaScenario::INVESTITURE){
                $db=$this->db();$db->beginTransaction();
                try{
                    $q=$db->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=? FOR UPDATE');$q->execute([$world]);$clock=$q->fetch(\PDO::FETCH_ASSOC);
                    if($clock===false||$clock['pending_day']!==null)throw new AlphaHttpError(409,'world_advancing');
                    $dr=(new PdoDomesticPolicyStore($db,new AlphaDomesticPrincipal($session,$world,$db)))->read($world);
                    $dp=(new PdoDiplomaticStore($db,$scenario->actors,$principal))->read($world);
                    if(!isset($dp['state']['household_reference'],$dr['state']['population_model']))throw new AlphaHttpError(404,'not_found');
                    if($dr['state']['day']!==$clock['committed_day']||$dp['state']['day']!==$clock['committed_day'])throw new AlphaHttpError(409,'world_advancing');
                    $report=$suffix==='/countries/FR/household-account'?HouseholdAccount::report($dr,$dp):HouseholdPurchasingPowerProxy::report($dr,$dp);$response=self::ok(200,$report);$response['headers']['ETag']='"'.hash('sha256',json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)).'"';$db->commit();return $response;
                }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            }
            if($method==='GET'&&$suffix==='/countries/FR/household-backtest'&&$scenario->id===AlphaScenario::INVESTITURE){
                $dp=(new PdoDiplomaticStore($this->db(),$scenario->actors,$principal))->read($world);
                if(!isset($dp['state']['household_reference']))throw new AlphaHttpError(404,'not_found');
                $report=HouseholdHistoricalBacktest::report($dp['state']['household_reference'])+['world_id'=>$world];$response=self::ok(200,$report);$response['headers']['ETag']='"'.CampaignScore::digest($report).'"';return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/household-reference'&&$scenario->id===AlphaScenario::INVESTITURE){
                $dp=(new PdoDiplomaticStore($this->db(),$scenario->actors,$principal))->read($world);
                if(!isset($dp['state']['household_reference']))throw new AlphaHttpError(404,'not_found');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$clock=$q->fetch(\PDO::FETCH_ASSOC);
                if($clock===false||$clock['pending_day']!==null||$clock['committed_day']!==$dp['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $report=HouseholdIncomeReference::report($dp['state']['household_reference'])+['world_id'=>$world,'day'=>$clock['committed_day']];$response=self::ok(200,$report);$response['headers']['ETag']='"'.hash('sha256',json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)).'"';return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/civil-continuity'&&$scenario->id===AlphaScenario::INVESTITURE){
                $db=$this->db();$db->beginTransaction();
                try{
                    $q=$db->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=? FOR UPDATE');$q->execute([$world]);$clock=$q->fetch(\PDO::FETCH_ASSOC);
                    if($clock===false||$clock['pending_day']!==null)throw new AlphaHttpError(409,'world_advancing');
                    $dr=(new PdoDomesticPolicyStore($db,new AlphaDomesticPrincipal($session,$world,$db)))->read($world);
                    $dp=(new PdoDiplomaticStore($db,$scenario->actors,$principal))->read($world);
                    if(!isset($dp['state']['defense_reference'],$dr['state']['services_model']))throw new AlphaHttpError(404,'not_found');
                    if($dr['state']['day']!==$clock['committed_day']||$dp['state']['day']!==$clock['committed_day'])throw new AlphaHttpError(409,'world_advancing');
                    $report=CivilContinuityReport::report($dr,$dp);$response=self::ok(200,$report);$response['headers']['ETag']='"'.hash('sha256',json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)).'"';$db->commit();return $response;
                }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            }
            if($method==='GET'&&$suffix==='/countries/FR/national-service'&&$scenario->id===AlphaScenario::INVESTITURE){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                if(!isset($r['state']['legislation']['national_service']))throw new AlphaHttpError(404,'not_found');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $report=NationalServiceLaw::report($r['state']['legislation']);$response=self::ok(200,$report);$response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/defense-planning'&&$scenario->id===AlphaScenario::INVESTITURE){
                $dp=(new PdoDiplomaticStore($this->db(),$scenario->actors,$principal))->read($world);
                if(!isset($dp['state']['defense_reference']))throw new AlphaHttpError(404,'not_found');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$clock=$q->fetch(\PDO::FETCH_ASSOC);
                if($clock===false||$clock['pending_day']!==null||$clock['committed_day']!==$dp['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $report=StrategicDefensePlanning::report($dp['state']['defense_reference'],$world,$clock['committed_day']);$response=self::ok(200,$report);$response['headers']['ETag']='"'.hash('sha256',json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)).'"';return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/international-finance'&&$scenario->id===AlphaScenario::INVESTITURE){
                $db=$this->db();$db->beginTransaction();
                try{
                    $q=$db->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=? FOR UPDATE');$q->execute([$world]);$clock=$q->fetch(\PDO::FETCH_ASSOC);
                    if($clock===false||$clock['pending_day']!==null)throw new AlphaHttpError(409,'world_advancing');
                    $dr=(new PdoDomesticPolicyStore($db,new AlphaDomesticPrincipal($session,$world,$db)))->read($world);
                    $dp=(new PdoDiplomaticStore($db,$scenario->actors,$principal))->read($world);
                    if(!isset($dp['state']['commitments'],$dr['state']['macro_baseline']['aid_reference_v2']))throw new AlphaHttpError(404,'not_found');
                    if($dr['state']['day']!==$clock['committed_day']||$dp['state']['day']!==$clock['committed_day'])throw new AlphaHttpError(409,'world_advancing');
                    $report=InternationalFinanceDisclosure::report($dr,$dp);$response=self::ok(200,$report);
                    $response['headers']['ETag']='"'.hash('sha256',json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)).'"';$db->commit();return $response;
                }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            }
            if($suffix==='/diplomacy/commitments'&&$scenario->id===AlphaScenario::INVESTITURE&&in_array($method,['GET','POST'],true))
                return (new PdoDiplomaticApi(new PdoDiplomaticStore($this->db(),$scenario->actors,$principal)))->handle($method,$path,$body,$h);
            if (($method==='GET' && in_array($suffix,['/diplomacy','/diplomacy/actors','/diplomacy/proposals'],true))
                || ($method==='POST' && in_array($suffix,['/diplomacy/proposals','/diplomacy/responses'],true)))
                return (new PdoDiplomaticApi(new PdoDiplomaticStore($this->db(),$scenario->actors,$principal)))->handle($method,$path,$body,$h);
            if($method==='GET'&&$suffix==='/organizations/eu/spillovers'&&in_array($scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $db=$this->db();$db->beginTransaction();
                try{
                    $q=$db->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=? FOR UPDATE');
                    $q->execute([$world]);$clock=$q->fetch(\PDO::FETCH_ASSOC);
                    if($clock===false||$clock['pending_day']!==null)throw new AlphaHttpError(409,'world_advancing');
                    $org=(new PdoOrganizationStore($db,$scenario->actors,$scenario->charters,$principal))->read($world);
                    $dom=(new PdoDomesticPolicyStore($db,new AlphaDomesticPrincipal($session,$world,$db)))->read($world);
                    if(!isset($org['state']['eu_withdrawal']['spillovers_version']))throw new AlphaHttpError(404,'not_found');
                    if($org['state']['day']!==$clock['committed_day']||$dom['state']['day']!==$clock['committed_day'])throw new AlphaHttpError(409,'world_advancing');
                    $result=EuropeanSpillovers::project($org,$dom);
                    $response=self::ok(200,$result);$response['headers']['ETag']='"'.hash('sha256',json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)).'"';
                    $db->commit();return $response;
                }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
            }
            if (($method==='GET' && $suffix==='/organizations')||($method==='POST'&&$suffix==='/organizations/eu/withdrawal-intents'&&in_array($scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)))
                return (new PdoOrganizationApi(new PdoOrganizationStore($this->db(),$scenario->actors,$scenario->charters,$principal)))->handle($method,$path,$body,$h);
            if ($method==='GET' && in_array($suffix,['/economy','/economy/indicators'],true))
                return (new PdoEconomicApi(new PdoEconomicStore($this->db(),$scenario->actors,$scenario->references,$principal)))->handle($method,$path,$body,$h);
            if($method==='GET'&&in_array($suffix,['/countries/FR/corporations','/countries/FR/programme-packs','/countries/FR/companies','/countries/FR/industrial'],true)&&in_array($scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)){
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$dr['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $days=$dr['state']['days'];$v=$days===[]?ProfessionalCorporations::initial($dr['state']['day']):$days[array_key_last($days)]['corporations'];
                $industrial=isset($dr['state']['industrial_version'])?($days===[]?IndustrialCapital::initial($dr['state']['day']):$days[array_key_last($days)]['industrial']):null;
                if($suffix==='/countries/FR/industrial'){
                    if($industrial===null)throw new AlphaHttpError(404,'not_found');
                    $response=self::ok(200,['world_id'=>$world,'state'=>$industrial]);$response['headers']['ETag']=$dr['etag'];return $response;
                }
                if($suffix==='/countries/FR/companies'){
                    if(!isset($dr['state']['firm_model']))throw new AlphaHttpError(404,'not_found');
                    $firmState=$days===[]?FirmEconomy::initial($dr['state']['day'],$dr['state']['firm_model']):$days[array_key_last($days)]['firms'];
                    $payload=['world_id'=>$world,'state'=>$firmState,'identity_catalog'=>IndustrialProgrammeCatalog::companies()];
                    if(isset($dr['state']['public_equity_version'])){
                        $payload['public_equity']=$days===[]?PublicEquityLedger::initial($dr['state']['day']):$days[array_key_last($days)]['public_equity'];
                        $quotes=[];foreach($dr['state']['firm_model'] as $id=>$unused)$quotes[$id]=PublicEquityLedger::quote($id,$dr['state']['firm_model']);
                        $payload['voluntary_equity_quotes']=$quotes;
                    }
                    $response=self::ok(200,$payload);
                    $response['headers']['ETag']=$dr['etag'];return $response;
                }
                $payload=$suffix==='/countries/FR/programme-packs'?
                    ['world_id'=>$world,'day'=>$dr['state']['day'],'packs'=>IndustrialProgrammeCatalog::packs($v,$industrial),'company_catalog'=>IndustrialProgrammeCatalog::companies()]:
                    ['world_id'=>$world,'state'=>$v,'decisions'=>$dr['state']['corporation_decisions']];
                $response=self::ok(200,$payload);$response['headers']['ETag']=$dr['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/ukraine-aid'&&$scenario->id===AlphaScenario::INVESTITURE){
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                if(!isset($dr['state']['ukraine_aid_decisions']))throw new AlphaHttpError(404,'not_found');
                $days=$dr['state']['days'];$response=self::ok(200,['world_id'=>$world,'day'=>$dr['state']['day'],'reference'=>$dr['state']['macro_baseline']['opening_projection'],'aid_reference'=>$dr['state']['macro_baseline']['aid_reference_v2']??null,'decisions'=>$dr['state']['ukraine_aid_decisions'],'latest'=>$days===[]?null:$days[array_key_last($days)]['ukraine_aid']]);$response['headers']['ETag']=$dr['etag'];return $response;
            }
            if($method==='POST'&&$suffix==='/domestic/ukraine-aid'&&$scenario->id===AlphaScenario::INVESTITURE){
                if(!is_string($body['action']??null)||!isset(UkraineAidPolicy::ACTIONS[$body['action']]))throw new AlphaHttpError(422,'invalid_aid_action');
                return (new DomesticPolicyApi(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db()))))->handle($method,str_replace('/domestic/ukraine-aid','/domestic/corporation',$path),$body,$h);
            }
            if (($method==='POST'&&$suffix==='/domestic/corporation'&&in_array($scenario->id,[AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true))
                || ($method==='GET' && in_array($suffix,['/domestic','/domestic/summary'],true))
                || ($method==='POST' && $suffix==='/domestic/proposals')
                || ($method==='POST' && $suffix==='/domestic/crisis'&&in_array($scenario->id,[AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES,AlphaScenario::PARTNERS,AlphaScenario::CORPORATIONS,AlphaScenario::CIVIL_CRISIS,AlphaScenario::INVESTITURE],true)))
                return (new DomesticPolicyApi(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db()))))->handle($method,$path,$body,$h);
            throw new AlphaHttpError(404,'not_found');
        } catch (AlphaHttpError $e) {return self::problem($e->status,$e->reason);}
        catch (\JsonException $e) {return self::problem(400,'invalid_json');}
        catch (\Throwable $e) {
            // Log only the error class, never credentials, bodies or raw SQL.
            error_log('World Politics alpha failure: '.get_class($e));
            return self::problem(503,'backend_unavailable');
        }
    }

    private static function ok(int $status,array $body):array{return ['status'=>$status,'headers'=>['Content-Type'=>'application/json','Cache-Control'=>'no-store'],'body'=>$body];}
    public static function problem(int $status,string $code):array {
        return ['status'=>$status,'headers'=>['Content-Type'=>'application/problem+json','Cache-Control'=>'no-store'],'body'=>['status'=>$status,'code'=>$code]];
    }
}
