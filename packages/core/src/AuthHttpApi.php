<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

final class AuthHttpApi
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock,private readonly int $globalLoginLimit=120)
    {
        if ($globalLoginLimit<1 || $globalLoginLimit>100000) throw new \InvalidArgumentException('Invalid global verification limit');
    }
    public function handle(string $method,string $path,string $authorization,string $body,string $peer): array
    {
        if (!in_array($path,['/v1/auth/login','/v1/auth/logout'],true)) return [404,['error'=>'not_found']];
        if ($method!=='POST') return [405,['error'=>'method_not_allowed']];
        $packed=inet_pton($peer);
        if ($packed===false) return [400,['error'=>'invalid_peer']];
        $rate=new PdoRateLimiter($this->pdo,$this->clock);
        if (!$rate->admit('auth:peer:'.bin2hex($packed),10,60)) return [429,['error'=>'rate_limited']];
        $sessions=new PdoSessions($this->pdo,$this->clock);
        if ($path==='/v1/auth/logout') {
            if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$m)) return [401,['error'=>'unauthorized']];
            $sessions->revoke($m[1]);
            return [200,['data'=>['revoked'=>true]]];
        }
        if (strlen($body)>4096) return [413,['error'=>'too_large']];
        try { $data=json_decode($body,true,8,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return [400,['error'=>'invalid_json']]; }
        if (!is_array($data)||count($data)!==2||!is_string($data['login']??null)||!is_string($data['password']??null)) return [400,['error'=>'invalid_credentials']];
        $login=$data['login'];
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login)||strlen($data['password'])>1024) return [401,['error'=>'unauthorized']];
        if (!$rate->admit('auth:login:'.$login,10,60)) return [429,['error'=>'rate_limited']];
        if (!$rate->admit('auth:verification-global',$this->globalLoginLimit,60)) return [429,['error'=>'rate_limited']];
        $token=$sessions->login($login,$data['password']);
        return $token===null ? [401,['error'=>'unauthorized']] : [200,['data'=>['token'=>$token,'expires_in'=>3600]]];
    }
}
