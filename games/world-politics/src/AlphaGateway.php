<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

require_once __DIR__.'/AlphaPrincipals.php';
require_once __DIR__.'/PdoWorldPoliticsApi.php';
require_once __DIR__.'/PdoInstitutionalApi.php';
require_once __DIR__.'/PdoDiplomaticApi.php';
require_once __DIR__.'/PdoOrganizationApi.php';
require_once __DIR__.'/PdoEconomicApi.php';
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
                    'coverage'=>$base->coverage()]]]);
            }
            $worlds=new AlphaWorlds($this->db(),$scenario);
            if ($path==='/api/v1/worlds') {
                if ($method==='GET') return self::ok(200,$worlds->list($session));
                if ($method==='POST') {
                    $key=$h['idempotency-key']??'';
                    if ($key==='') throw new AlphaHttpError(428,'precondition_required');
                    if (($body['scenario']??null)===AlphaScenario::GLOBAL) {
                        $sha=$body['reference_bundle_sha']??null;
                        if (array_key_exists('reference_bundle_sha',$body) && (!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)))
                            throw new AlphaHttpError(422,'invalid_reference_bundle');
                        try {$bundle=$sha===null?new GlobalReferenceBundle():(new PdoGlobalReferenceBundles($this->db()))->load($sha);}
                        catch (\DomainException $e) {throw new AlphaHttpError(422,'unknown_reference_bundle');}
                        $scenario=new AlphaScenario(AlphaScenario::GLOBAL,$bundle,$sha);
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
            if ($definition['scenario']===AlphaScenario::GLOBAL) {
                $sha=$definition['reference_bundle_sha'];
                $bundle=$sha===null?new GlobalReferenceBundle():(new PdoGlobalReferenceBundles($this->db()))->load($sha);
                $scenario=new AlphaScenario(AlphaScenario::GLOBAL,$bundle,$sha);
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
            $principal=new AlphaPrincipal($session,$world);
            $service=new AlphaPrincipal($session,$world,true);
            if ($suffix==='/clock' || $suffix==='/clock/advances') {
                $dom=new PdoDomesticPolicyStore($this->db(),new AlphaDomesticPrincipal($session,$world,$this->db(),true));
                $clock=new WorldClockCoordinator($this->db(),
                    new PdoOrganizationStore($this->db(),$scenario->actors,$scenario->charters,$service),
                    new PdoDiplomaticStore($this->db(),$scenario->actors,$service),
                    new PdoEconomicStore($this->db(),$scenario->actors,$scenario->references,$service),$service,
                    new PdoInstitutionalStore($this->db(),$service),['FR'],null,$dom);
                return (new WorldClockApi($clock))->handle($method,$path,$body,$h);
            }
            // Partial cross-domain days are not advertised as a coherent view.
            $q=$this->db()->prepare('SELECT pending_day FROM wp_world_clock WHERE world_id=?');$q->execute([$world]);
            if ($q->fetchColumn()!==null) throw new AlphaHttpError(409,'world_advancing');
            if (preg_match('~^/countries/FR/politics(?:/person-template|/people(?:/[a-z][a-z0-9_-]{0,63})?|/head-of-state)?$~D',$suffix)) {
                if ($method==='POST' && !in_array($suffix,['/countries/FR/politics/people','/countries/FR/politics/head-of-state'],true))
                    throw new AlphaHttpError(405,'method_not_allowed');
                if ($method==='POST' && $suffix==='/countries/FR/politics/people' && array_key_exists('id',$body))
                    throw new AlphaHttpError(422,'server_generated_identity');
                return (new PdoWorldPoliticsApi(new PdoPoliticalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            }
            if ($method==='GET' && in_array($suffix,['/countries/FR/institutions','/countries/FR/institutions/rulebook'],true))
                return (new PdoInstitutionalApi(new PdoInstitutionalStore($this->db(),$principal)))->handle($method,$path,$body,$h);
            if (($method==='GET' && in_array($suffix,['/diplomacy','/diplomacy/actors','/diplomacy/proposals'],true))
                || ($method==='POST' && in_array($suffix,['/diplomacy/proposals','/diplomacy/responses'],true)))
                return (new PdoDiplomaticApi(new PdoDiplomaticStore($this->db(),$scenario->actors,$principal)))->handle($method,$path,$body,$h);
            if ($method==='GET' && $suffix==='/organizations')
                return (new PdoOrganizationApi(new PdoOrganizationStore($this->db(),$scenario->actors,$scenario->charters,$principal)))->handle($method,$path,$body,$h);
            if ($method==='GET' && in_array($suffix,['/economy','/economy/indicators'],true))
                return (new PdoEconomicApi(new PdoEconomicStore($this->db(),$scenario->actors,$scenario->references,$principal)))->handle($method,$path,$body,$h);
            if (($method==='GET' && in_array($suffix,['/domestic','/domestic/summary'],true))
                || ($method==='POST' && $suffix==='/domestic/proposals'))
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
