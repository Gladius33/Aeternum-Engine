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
            $session=$accounts->authenticate($h['authorization']??'');
            $accounts->limit('http-account',$session->id,120,60);
            if ($path==='/api/v1/session' && $method==='DELETE') {
                $session->revoke();return self::ok(200,['revoked'=>true]);
            }
            if ($path==='/api/v1/me' && $method==='GET') return self::ok(200,['account_id'=>$session->id,'username'=>$session->username]);
            $scenario=new AlphaScenario();
            if ($path==='/api/v1/scenarios' && $method==='GET') {
                $base=new GlobalReferenceBundle();
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
                    'services'=>FrenchPublicServices::VERSION,'playable_countries'=>['FR'],'reference_sha'=>$base->sha()]]]);
            }
            $worlds=new AlphaWorlds($this->db(),$scenario);
            if ($path==='/api/v1/worlds') {
                if ($method==='GET') return self::ok(200,$worlds->list($session));
                if ($method==='POST') {
                    $key=$h['idempotency-key']??'';
                    if ($key==='') throw new AlphaHttpError(428,'precondition_required');
                    if (in_array($body['scenario']??null,[AlphaScenario::GLOBAL,AlphaScenario::COMPARATIVE,AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)) {
                        $sha=$body['reference_bundle_sha']??null;
                        if (array_key_exists('reference_bundle_sha',$body) && (!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)))
                            throw new AlphaHttpError(422,'invalid_reference_bundle');
                        try {$bundle=$sha===null?new GlobalReferenceBundle():(new PdoGlobalReferenceBundles($this->db()))->load($sha);}
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
            if ($method!=='GET' && $role!=='owner') throw new AlphaHttpError(403,'forbidden');
            if ($suffix==='/members' && $method==='POST') return self::ok(200,$worlds->share($session,$world,$body));
            $q=$this->db()->prepare('SELECT scenario,reference_bundle_sha FROM wp_alpha_worlds WHERE world_id=?');
            $q->execute([$world]);$definition=$q->fetch(\PDO::FETCH_ASSOC);
            if ($definition===false) throw new AlphaHttpError(404,'not_found');
            if (in_array($definition['scenario'],[AlphaScenario::GLOBAL,AlphaScenario::COMPARATIVE,AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)) {
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
                        'countries'=>$bundle->countries->all()]);
                }
            } elseif ($definition['scenario']!==AlphaScenario::ID) throw new \UnexpectedValueException('Unknown saved scenario');
            $principal=new AlphaPrincipal($session,$world,false,$scenario->countries,in_array($scenario->id,[AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true),in_array($scenario->id,[AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true),in_array($scenario->id,[AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true));
            $service=new AlphaPrincipal($session,$world,true,$scenario->countries);
            if ($suffix==='/clock' || $suffix==='/clock/advances') {
                $dom=new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db(),true));
                $inst=new PdoInstitutionalStore($this->db(),$service);
                $opinion=in_array($scenario->id,[AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)?new PdoPublicOpinionStore($this->db(),$service,$dom,$inst):null;
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
                ($suffix==='/countries/FR/opinion'&&in_array($scenario->id,[AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true))
                ||($suffix==='/countries/FR/dialogue'&&in_array($scenario->id,[AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true))
                ||($suffix==='/campaign'&&$scenario->campaign())
                ||($suffix==='/countries/FR/presidency'&&in_array($scenario->id,[AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)))){

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
                }elseif($suffix==='/campaign'){
                    if(!isset($r['state']['campaign']))throw new \UnexpectedValueException('Missing pinned campaign');
                    $body=$r['state']['campaign'];
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
            if($method==='GET'&&$suffix==='/countries/FR/services'&&$scenario->id===AlphaScenario::SERVICES){
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$dr['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,FrenchPublicServices::project($dr['state']));$response['headers']['ETag']=$dr['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/population'&&in_array($scenario->id,[AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)){
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$dr['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,FrenchDemography::project($dr['state']));$response['headers']['ETag']=$dr['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/debt'&&in_array($scenario->id,[AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)){
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$dr['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,SovereignDebtLedger::project($dr['state']));$response['headers']['ETag']=$dr['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/macro'&&in_array($scenario->id,[AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $er=(new PdoEconomicStore($this->db(),$scenario->actors,$scenario->references,$principal))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day']||$c['committed_day']!==$dr['state']['day']||$c['committed_day']!==$er['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $m=in_array($scenario->id,[AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)?MacroFiscalLoop::project($dr['state']):MacroeconomicProjection::project($dr['state'],$er['state'],$r['state']['legislation']['finance']);
                $m['source_hashes']=['domestic'=>trim($dr['etag'],'"'),'economic'=>trim($er['etag'],'"'),'institutional'=>trim($r['etag'],'"')];
                $response=self::ok(200,$m);
                $response['headers']['ETag']='"'.hash('sha256',json_encode($m,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)).'"';return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/finance'&&in_array($scenario->id,[AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $dr=(new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db())))->read($world);
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day']||$c['committed_day']!==$dr['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $f=$r['state']['legislation']['finance'];
                $response=self::ok(200,['day'=>$r['state']['day'],'finance'=>$f,'catalog'=>FinanceBudget::catalog(),'treasury'=>FinanceBudget::project($f,$dr['state'])]);
                $response['headers']['ETag']='"'.hash('sha256',$r['etag'].'/'.$dr['etag']).'"';return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/executive'&&in_array($scenario->id,[AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,['day'=>$r['state']['day'],'executive'=>$r['state']['legislation']['executive'],'catalog'=>ExecutiveActs::catalog()]);
                $response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/cabinet'&&in_array($scenario->id,[AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $g=$r['state']['legislation']['responsibility'];
                $response=self::ok(200,['day'=>$r['state']['day'],...FrenchCabinet::projection($g['cabinet'],$g['assembly']),'catalog'=>FrenchCabinet::catalog()]);
                $response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/assembly'&&in_array($scenario->id,[AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,['assembly'=>$r['state']['legislation']['responsibility']['assembly'],'day'=>$r['state']['day'],... (isset($r['state']['legislation']['responsibility']['lifecycle'])?['lifecycle'=>$r['state']['legislation']['responsibility']['lifecycle']]:[])]);
                $response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='GET'&&$suffix==='/countries/FR/legislation'&&in_array($scenario->id,[AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);$c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,['legislation'=>$r['state']['legislation'],'catalog'=>BicameralLegislation::catalog()]);
                $response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='POST'&&in_array($scenario->id,[AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)&&$suffix==='/countries/FR/institutions/decisions'
                &&($body['action']??null)==='legislation_draft')
                return (new PdoInstitutionalApi(new PdoInstitutionalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            if($method==='GET'&&$suffix==='/countries/FR/constitution'&&in_array($scenario->id,[AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)){
                $r=(new PdoInstitutionalStore($this->db(),$principal))->read($world,'FR');
                $q=$this->db()->prepare('SELECT committed_day,pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);
                $c=$q->fetch(\PDO::FETCH_ASSOC);
                if($c===false||$c['pending_day']!==null||$c['committed_day']!==$r['state']['day'])throw new AlphaHttpError(409,'world_advancing');
                $response=self::ok(200,['revision'=>$r['state']['revision'],'catalog'=>ConstitutionalRevision::catalog()]);
                $response['headers']['ETag']=$r['etag'];return $response;
            }
            if($method==='POST'&&in_array($scenario->id,[AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)&&$suffix==='/countries/FR/institutions/decisions'
                &&($body['action']??null)==='revision_draft')
                return (new PdoInstitutionalApi(new PdoInstitutionalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            if($method==='POST'&&in_array($scenario->id,[AlphaScenario::ELECTORAL,AlphaScenario::POLITICAL,AlphaScenario::OPINION,AlphaScenario::DIALOGUE,AlphaScenario::SURVIVAL,AlphaScenario::REVOLUTION_CASE,AlphaScenario::PRESIDENTIAL,AlphaScenario::REVISION,AlphaScenario::LEGISLATIVE,AlphaScenario::RESPONSIBILITY,AlphaScenario::ASSEMBLY,AlphaScenario::PARLIAMENT,AlphaScenario::CABINET,AlphaScenario::EXECUTIVE,AlphaScenario::FINANCE,AlphaScenario::MACRO,AlphaScenario::FISCAL_LOOP,AlphaScenario::DEBT,AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)&&$suffix==='/countries/FR/institutions/decisions'
                &&($body['action']??null)==='electoral_coalition')
                return (new PdoInstitutionalApi(new PdoInstitutionalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            if ($method==='GET' && preg_match('~^/countries/([A-Z]{2})/institutions(?:/rulebook)?$~D',$suffix,$countryMatch)
                && in_array($countryMatch[1],$scenario->countries,true))
                return (new PdoInstitutionalApi(new PdoInstitutionalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            if (($method==='GET' && in_array($suffix,['/diplomacy','/diplomacy/actors','/diplomacy/proposals'],true))
                || ($method==='POST' && in_array($suffix,['/diplomacy/proposals','/diplomacy/responses'],true)))
                return (new PdoDiplomaticApi(new PdoDiplomaticStore($this->db(),$scenario->actors,$principal)))->handle($method,$path,$body,$h);
            if ($method==='GET' && $suffix==='/organizations')
                return (new PdoOrganizationApi(new PdoOrganizationStore($this->db(),$scenario->actors,$scenario->charters,$principal)))->handle($method,$path,$body,$h);
            if ($method==='GET' && in_array($suffix,['/economy','/economy/indicators'],true))
                return (new PdoEconomicApi(new PdoEconomicStore($this->db(),$scenario->actors,$scenario->references,$principal)))->handle($method,$path,$body,$h);
            if (($method==='GET' && in_array($suffix,['/domestic','/domestic/summary'],true))
                || ($method==='POST' && $suffix==='/domestic/proposals')
                || ($method==='POST' && $suffix==='/domestic/crisis'&&in_array($scenario->id,[AlphaScenario::FINANCIAL_CRISIS,AlphaScenario::POPULATION,AlphaScenario::SERVICES],true)))
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
