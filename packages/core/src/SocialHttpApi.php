<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/**
 * Authenticated M09 social HTTP boundary: no caller-provided actor identity
 * is trusted. Mutations accept a strict JSON object and require a bearer.
 * Persistent writes remain authoritative in PdoAlliances.
 */
final class SocialHttpApi
{
    /** @param array<string,mixed> $approvedWorlds */
    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
        private readonly array $approvedWorlds
    ) {}

    public function handle(string $method,string $path,string $authorization,string $body=''): array
    {
        if (strlen($path)>512 || !preg_match(
            '~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/social/(.+)$~D',
            $path,$match
        )) return [404,['error'=>'not_found']];
        [$world,$suffix]=[$match[1],$match[2]];
        $action=null;
        $alliance=null;
        if ($suffix==='alliances') {
            $action=$method==='GET'?'list':($method==='POST'?'create':null);
        } elseif ($suffix==='invitations') {
            $action=$method==='GET'?'inbox':null;
        } elseif (preg_match(
            '~^alliances/([A-Za-z0-9][A-Za-z0-9_-]{0,63})(?:/(invite|respond|revoke|role|leave|remove|owner))?$~D',
            $suffix,$resource
        )) {
            $alliance=$resource[1];
            $sub=$resource[2]??'';
            $action=$sub===''?($method==='GET'?'details':null):($method==='POST'?$sub:null);
        } else {
            return [404,['error'=>'not_found']];
        }
        if ($action===null) return [405,['error'=>'method_not_allowed']];
        if (!array_key_exists($world,$this->approvedWorlds)) return [404,['error'=>'not_found']];
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$token))
            return [401,['error'=>'unauthorized']];
        $actor=(new PdoSessions($this->pdo,$this->clock))->resolve($token[1]);
        if ($actor===null) return [401,['error'=>'unauthorized']];
        $isWrite=$method==='POST';
        $rate=new PdoRateLimiter($this->pdo,$this->clock);
        if (!$rate->admit('social:'.($isWrite?'write:':'read:').$actor,$isWrite?30:120,60))
            return [429,['error'=>'rate_limited']];
        $data=[];
        if ($isWrite) {
            if (strlen($body)>4096) return [413,['error'=>'too_large']];
            try { $data=json_decode($body,true,8,JSON_THROW_ON_ERROR); }
            catch (\JsonException) { return [400,['error'=>'invalid_json']]; }
            if (!is_array($data) || array_is_list($data)) return [400,['error'=>'invalid_payload']];
        }
        $expected=match($action) {
            'create'=>['id','name'],
            'invite','revoke','remove'=>['actor'],
            'respond'=>['accept'],
            'role'=>['actor','role'],
            'owner'=>['actor'],
            default=>[]
        };
        if ($isWrite) {
            $keys=array_keys($data);
            sort($keys);
            $wanted=$expected;
            sort($wanted);
            if ($keys!==$wanted) return [400,['error'=>'invalid_payload']];
            foreach ($data as $key=>$value) {
                if (!is_string($key) || (!is_string($value) && !($key==='accept' && is_bool($value))))
                    return [400,['error'=>'invalid_payload']];
            }
        }
        try {
            $social=new PdoAlliances($this->pdo,$world);
            $result=match($action) {
                'list'=>$social->listFor($actor),
                'inbox'=>['invitations'=>$social->invitationsFor($actor)],
                'details'=>$social->detailsFor($actor,$alliance),
                'create'=>$this->created($social,$actor,$data),
                'invite'=>$this->mutate(fn()=> $social->invite($actor,$alliance,$data['actor'])),
                'respond'=>$this->mutate(fn()=> $social->respond($actor,$alliance,$data['accept'])),
                'revoke'=>$this->mutate(fn()=> $social->revokeInvite($actor,$alliance,$data['actor'])),
                'role'=>$this->mutate(fn()=> $social->setRole($actor,$alliance,$data['actor'],$data['role'])),
                'leave'=>$this->mutate(fn()=> $social->leave($actor,$alliance)),
                'remove'=>$this->mutate(fn()=> $social->remove($actor,$alliance,$data['actor'])),
                'owner'=>$this->mutate(fn()=> $social->transferOwner($actor,$alliance,$data['actor']))
            };
            return [200,['data'=>$result]];
        } catch (\InvalidArgumentException) {
            return [400,['error'=>'invalid_payload']];
        } catch (\DomainException) {
            return [$isWrite?409:404,['error'=>$isWrite?'social_conflict':'not_found']];
        }
    }

    private function mutate(callable $write): array
    {
        $write();
        return ['updated'=>true];
    }

    private function created(PdoAlliances $social,string $actor,array $data): array
    {
        $social->create($actor,$data['id'],$data['name']);
        return ['id'=>$data['id']];
    }
}
