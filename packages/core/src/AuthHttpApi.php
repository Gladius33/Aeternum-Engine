<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

final class AuthHttpApi
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock,private readonly int $globalLoginLimit=120,private readonly array $registrationWorlds=[],private readonly bool $requireInvitation=false,private readonly int $registrationWorldHourlyLimit=30,private readonly int $parallelSignupLimit=8,private readonly bool $requireEmailActivation=false,private readonly ?ActivationMailer $activationMailer=null)
    {
        if ($globalLoginLimit<1 || $globalLoginLimit>100000) throw new \InvalidArgumentException('Invalid global verification limit');
        if ($registrationWorldHourlyLimit<1 || $registrationWorldHourlyLimit>100000) throw new \InvalidArgumentException('Invalid world registration limit');
        if ($parallelSignupLimit<1 || $parallelSignupLimit>32) throw new \InvalidArgumentException('Invalid parallel signup limit');
    }
    public function handle(string $method,string $path,string $authorization,string $body,string $peer): array
    {
        if (!in_array($path,['/v1/auth/login','/v1/auth/logout','/v1/auth/logout/all','/v1/auth/register','/v1/auth/recover','/v1/auth/recovery/rotate','/v1/auth/password/change','/v1/auth/activate','/v1/auth/activation/resend'],true)) return [404,['error'=>'not_found']];
        if ($method!=='POST') return [405,['error'=>'method_not_allowed']];
        $peerKey=PeerRateKey::fromIp($peer);
        if ($peerKey===null) return [400,['error'=>'invalid_peer']];
        $rate=new PdoRateLimiter($this->pdo,$this->clock);
        if (!$rate->admit('auth:peer:'.$peerKey,10,60)) return [429,['error'=>'rate_limited']];
        $sessions=new PdoSessions($this->pdo,$this->clock);
        if ($path==='/v1/auth/logout/all') {
            if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m)) return [401,['error'=>'unauthorized']];
            $actor=$sessions->resolve($m[1]);
            if ($actor===null) return [401,['error'=>'unauthorized']];
            $sessions->revokeAllForActor($actor);
            return [200,['data'=>['revokedAll'=>true]]];
        }
        if ($path==='/v1/auth/logout') {
            if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m)) return [401,['error'=>'unauthorized']];
            $sessions->revoke($m[1]);
            return [200,['data'=>['revoked'=>true]]];
        }
        if ($path==='/v1/auth/activation/resend') {
            if (!$this->requireEmailActivation || $this->registrationWorlds===[]) return [404,['error'=>'not_found']];
            if (strlen($body)>4096) return [413,['error'=>'too_large']];
            try { $data=StrictJson::decode($body,true,8); }
            catch (\JsonException) { return [400,['error'=>'invalid_json']]; }
            if (!is_array($data) || count($data)!==3 || !is_string($data['login']??null)
                || !is_string($data['password']??null) || !is_string($data['email']??null)
                || strlen($data['password'])>1024) return [400,['error'=>'invalid_activation']];
            if (!$rate->admit('auth:activation-resend-peer:'.$peerKey,3,3600)
                || !$rate->admit('auth:activation-resend-global',100,3600)) return [429,['error'=>'rate_limited']];
            try {
                $delivery=$this->withCredentialSlot(fn()=>(new PdoEmailActivation($this->pdo,$this->clock))->resend(
                    $data['login'],$data['password'],$data['email']));
                if ($delivery!==null) ($this->activationMailer??new ActivationMailer())->deliver($delivery['email'],$data['login'],$delivery['code']);
            } catch (SignupBusy) { return [503,['error'=>'authentication_busy']]; }
            catch (\InvalidArgumentException) { return [400,['error'=>'invalid_activation']]; }
            catch (\Throwable) { return [503,['error'=>'activation_retry']]; }
            return [202,['data'=>['activationRequired'=>true]]];
        }
        if ($path==='/v1/auth/activate') {
            if (!$this->requireEmailActivation || $this->registrationWorlds===[]) return [404,['error'=>'not_found']];
            if (strlen($body)>4096) return [413,['error'=>'too_large']];
            try { $data=StrictJson::decode($body,true,8); }
            catch (\JsonException) { return [400,['error'=>'invalid_json']]; }
            if (!is_array($data) || count($data)!==3 || !is_string($data['login']??null)
                || !is_string($data['password']??null) || !is_string($data['code']??null)
                || strlen($data['password'])>1024) return [400,['error'=>'invalid_activation']];
            if (!$rate->admit('auth:activation-peer:'.$peerKey,5,3600)
                || !$rate->admit('auth:activation-global',100,3600)) return [429,['error'=>'rate_limited']];
            try {
                $result=$this->withCredentialSlot(function() use($data,$sessions): ?array {
                    $proof=new PdoEmailActivation($this->pdo,$this->clock);
                    return $proof->exclusive($data['login'],function() use($proof,$data,$sessions):?array {
                    $verified=$proof->verify($data['login'],$data['password'],$data['code']);
                    if ($verified===null) return null;
                    if (!isset($this->registrationWorlds[$verified['world']])) throw new \DomainException('World no longer approved');
                    $claim=(new PdoOnboarding($this->pdo,$this->clock))->enroll(
                        $verified['world'],$this->registrationWorlds[$verified['world']],$data['login'],$data['password']);
                    $proof->consume($claim['actor']);
                    $token=$sessions->login($data['login'],$data['password']);
                    if ($token===null) throw new \RuntimeException('Activation session unavailable');
                    return ['token'=>$token,'expires_in'=>3600,'world'=>$verified['world'],
                        'account'=>$claim['actor'],'recoveryCode'=>$claim['recoveryCode']];
                    });
                });
            } catch (SignupBusy) { return [503,['error'=>'authentication_busy']]; }
            catch (\DomainException) { return [409,['error'=>'activation_unavailable']]; }
            catch (\Throwable) { return [503,['error'=>'activation_retry']]; }
            return $result===null ? [401,['error'=>'invalid_activation']] : [200,['data'=>$result]];
        }
        if ($path==='/v1/auth/register') {
            if ($this->registrationWorlds===[]) return [404,['error'=>'not_found']];
            if (strlen($body)>4096) return [413,['error'=>'too_large']];
            try { $data=StrictJson::decode($body,true,8); }
            catch (\JsonException) { return [400,['error'=>'invalid_json']]; }
            if (!is_array($data) || !array_key_exists('login',$data) || !array_key_exists('password',$data)
                || !array_key_exists('world',$data) || count($data)!==($this->requireInvitation?4:3)+($this->requireEmailActivation?1:0)
                || ($this->requireEmailActivation && (!is_string($data['email']??null)
                    || strlen($data['email'])>254
                    || filter_var(strtolower(trim($data['email'])),FILTER_VALIDATE_EMAIL)===false))
                || ($this->requireInvitation && !is_string($data['inviteCode']??null))
                || (!$this->requireInvitation && array_key_exists('inviteCode',$data))
                || !is_string($data['login']) || !is_string($data['password']) || !is_string($data['world'])
                || !preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$data['login'])
                || strlen($data['password'])<12 || strlen($data['password'])>1024
                || ($this->requireInvitation && !preg_match('/^[a-f0-9]{64}$/D',$data['inviteCode']))) {
                return [400,['error'=>'invalid_registration']];
            }
            if (!isset($this->registrationWorlds[$data['world']])) return [404,['error'=>'not_found']];
            if (!$rate->admit('auth:registration-peer:'.$peerKey,6,3600)
                || !$rate->admit('auth:registration-global',100,3600)
                || !$rate->admit('auth:registration-world:'.hash('sha256',$data['world']),$this->registrationWorldHourlyLimit,3600)) {
                return [429,['error'=>'rate_limited']];
            }
            try {
                if ($this->requireEmailActivation) {
                    $delivery=$this->withCredentialSlot(fn()=>(new PdoEmailActivation($this->pdo,$this->clock))->begin(
                        $data['world'],$data['login'],$data['password'],$data['email'],
                        $this->requireInvitation?$data['inviteCode']:null));
                    if ($delivery!==null) ($this->activationMailer??new ActivationMailer())->deliver(
                        $delivery['email'],$data['login'],$delivery['code']);
                    // Generic 202 whether new, already registered or reused mailbox.
                    // No bearer, actor ID or recovery code before mailbox proof.
                    return [202,['data'=>['activationRequired'=>true]]];
                }
                [$claim,$token]=(new PdoSignupSlots($this->pdo,$this->parallelSignupLimit))->run(function() use($data,$sessions):array {
                    $claim=(new PdoOnboarding($this->pdo,$this->clock))->enroll(
                        $data['world'],$this->registrationWorlds[$data['world']],$data['login'],$data['password'],
                        $this->requireInvitation?$data['inviteCode']:null
                    );
                    // Include the second Argon2id verification and token creation
                    // in the same resource budget. No expensive post-slot gap.
                    $token=$sessions->login($data['login'],$data['password']);
                    return [$claim,$token];
                });
            } catch (SignupBusy) {
                return [503,['error'=>'registration_busy']];
            } catch (\DomainException) {
                return [409,['error'=>'registration_unavailable']];
            } catch (\Throwable) {
                // A partial world provision remains pending and can be resumed by
                // repeating this same registration after temporary failure.
                return [503,['error'=>'registration_retry']];
            }
            if ($token===null) return [503,['error'=>'registration_retry']];
            return [$claim['created']?201:200,['data'=>[
                'token'=>$token,'expires_in'=>3600,'world'=>$data['world'],'account'=>$claim['actor'],'recoveryCode'=>$claim['recoveryCode']
            ]]];
        }
        if ($path==='/v1/auth/recover') {
            if (strlen($body)>4096) return [413,['error'=>'too_large']];
            try { $data=StrictJson::decode($body,true,8); }
            catch (\JsonException) { return [400,['error'=>'invalid_json']]; }
            if (!is_array($data) || count($data)!==3
                || !is_string($data['login']??null) || !is_string($data['code']??null)
                || !is_string($data['newPassword']??null)) return [400,['error'=>'invalid_recovery']];
            if (!$rate->admit('auth:recovery-peer:'.$peerKey,5,3600)
                || !$rate->admit('auth:recovery-global',100,3600)) return [429,['error'=>'rate_limited']];
            try { $next=$this->withCredentialSlot(fn()=>(new PdoRecovery($this->pdo,$this->clock))->reset($data['login'],$data['code'],$data['newPassword'])); }
            catch (SignupBusy) { return [503,['error'=>'authentication_busy']]; }
            return $next===null ? [401,['error'=>'unauthorized']] : [200,['data'=>['recoveryCode'=>$next,'sessionsRevoked'=>true]]];
        }
        if ($path==='/v1/auth/password/change') {
            if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m)) return [401,['error'=>'unauthorized']];
            $actor=$sessions->resolve($m[1]);
            if ($actor===null) return [401,['error'=>'unauthorized']];
            if (strlen($body)>4096) return [413,['error'=>'too_large']];
            try { $data=StrictJson::decode($body,true,8); }
            catch (\JsonException) { return [400,['error'=>'invalid_json']]; }
            if (!is_array($data) || count($data)!==2
                || !is_string($data['currentPassword']??null)
                || !is_string($data['newPassword']??null)
                || strlen($data['currentPassword'])>1024
                || strlen($data['newPassword'])<12 || strlen($data['newPassword'])>1024
                || $data['currentPassword']===$data['newPassword']) return [400,['error'=>'invalid_password_change']];
            if (!$rate->admit('auth:verification-global',$this->globalLoginLimit,60)) return [429,['error'=>'rate_limited']];
            try { $next=$this->withCredentialSlot(fn()=>(new PdoRecovery($this->pdo,$this->clock))->changePassword($actor,$data['currentPassword'],$data['newPassword'])); }
            catch (SignupBusy) { return [503,['error'=>'authentication_busy']]; }
            return $next===null ? [403,['error'=>'invalid_current_password']] : [200,['data'=>['recoveryCode'=>$next,'sessionsRevoked'=>true]]];
        }
        if ($path==='/v1/auth/recovery/rotate') {
            if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m)) return [401,['error'=>'unauthorized']];
            $actor=$sessions->resolve($m[1]);
            if ($actor===null) return [401,['error'=>'unauthorized']];
            if (strlen($body)>4096) return [413,['error'=>'too_large']];
            try { $data=StrictJson::decode($body,true,8); }
            catch (\JsonException) { return [400,['error'=>'invalid_json']]; }
            if (!is_array($data) || count($data)!==1 || !is_string($data['password']??null)) return [400,['error'=>'invalid_credentials']];
            if (!$rate->admit('auth:verification-global',$this->globalLoginLimit,60)) return [429,['error'=>'rate_limited']];
            try { $next=$this->withCredentialSlot(fn()=>(new PdoRecovery($this->pdo,$this->clock))->rotate($actor,$data['password'])); }
            catch (SignupBusy) { return [503,['error'=>'authentication_busy']]; }
            return $next===null ? [403,['error'=>'invalid_current_password']] : [200,['data'=>['recoveryCode'=>$next]]];
        }
        if (strlen($body)>4096) return [413,['error'=>'too_large']];
        try { $data=StrictJson::decode($body,true,8); }
        catch (\JsonException) { return [400,['error'=>'invalid_json']]; }
        if (!is_array($data)||count($data)!==2||!is_string($data['login']??null)||!is_string($data['password']??null)) return [400,['error'=>'invalid_credentials']];
        $login=$data['login'];
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login)||strlen($data['password'])>1024) return [401,['error'=>'unauthorized']];
        if (!$rate->admit('auth:login:'.$login,10,60)) return [429,['error'=>'rate_limited']];
        if (!$rate->admit('auth:verification-global',$this->globalLoginLimit,60)) return [429,['error'=>'rate_limited']];
        try { $token=$this->withCredentialSlot(fn()=>$sessions->login($login,$data['password'])); }
        catch (SignupBusy) { return [503,['error'=>'authentication_busy']]; }
        return $token===null ? [401,['error'=>'unauthorized']] : [200,['data'=>['token'=>$token,'expires_in'=>3600]]];
    }
    /** Share the registration memory ceiling with every expensive Argon2id path. */
    private function withCredentialSlot(callable $operation): mixed
    {
        return (new PdoSignupSlots($this->pdo,$this->parallelSignupLimit))->run($operation);
    }
}
