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
        $cursorTime=null;
        $cursorId=null;
        $afterId=null;
        $memberAfter=null;
        $rankAfterCount=null;
        $rankAfterId=null;
        if ($suffix==='alliances') {
            $action=$method==='GET'?'list':($method==='POST'?'create':null);
        } elseif (preg_match('~^alliances/page/([A-Za-z0-9][A-Za-z0-9_-]{0,63})$~D',$suffix,$page)) {
            $action=$method==='GET'?'list':null;
            $afterId=$page[1];
        } elseif ($suffix==='invitations') {
            $action=$method==='GET'?'inbox':null;
        } elseif (preg_match('~^invitations/page/([A-Za-z0-9][A-Za-z0-9_-]{0,63})$~D',$suffix,$page)) {
            $action=$method==='GET'?'inbox':null;
            $afterId=$page[1];
        } elseif ($suffix==='messages') {
            $action=$method==='GET'?'messages':($method==='POST'?'send':null);
        } elseif ($suffix==='blocks') {
            $action=$method==='GET'?'blocks':($method==='POST'?'block':null);
        } elseif (preg_match('~^blocks/page/([A-Za-z0-9][A-Za-z0-9_-]{0,63})$~D',$suffix,$page)) {
            $action=$method==='GET'?'blocks':null;
            $afterId=$page[1];
        } elseif ($suffix==='blocks/remove') {
            $action=$method==='POST'?'unblock':null;
        } elseif ($suffix==='notifications') {
            $action=$method==='GET'?'notifications':null;
        } elseif (preg_match(
            '~^(messages|notifications)/page/(0|[1-9][0-9]{0,18})/([A-Za-z0-9][A-Za-z0-9_-]{0,63})$~D',
            $suffix,$page
        )) {
            $action=$method==='GET'?$page[1]:null;
            $cursorTime=filter_var($page[2],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if ($cursorTime===false) return [400,['error'=>'invalid_cursor']];
            $cursorId=$page[3];
        } elseif ($suffix==='reports') {
            $action=$method==='POST'?'report':null;
        } elseif ($suffix==='leaderboard') {
            $action=$method==='GET'?'leaderboard':null;
        } elseif (preg_match(
            '~^leaderboard/page/(0|[1-9][0-9]{0,18})/([A-Za-z0-9][A-Za-z0-9_-]{0,63})$~D',
            $suffix,$rankPage
        )) {
            $action=$method==='GET'?'leaderboard':null;
            $rankAfterCount=filter_var($rankPage[1],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            if ($rankAfterCount===false) return [400,['error'=>'invalid_cursor']];
            $rankAfterId=$rankPage[2];
        } elseif (preg_match('~^notifications/([A-Za-z0-9][A-Za-z0-9_-]{0,63})/read$~D',$suffix,$note)) {
            $action=$method==='POST'?'ack':null;
            $noticeId=$note[1];
        } elseif (preg_match(
            '~^alliances/([A-Za-z0-9][A-Za-z0-9_-]{0,63})/members/page/([A-Za-z0-9][A-Za-z0-9_-]{0,63})$~D',
            $suffix,$roster
        )) {
            $action=$method==='GET'?'details':null;
            $alliance=$roster[1];
            $memberAfter=$roster[2];
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
            try { $decoded=json_decode($body,false,8,JSON_THROW_ON_ERROR); }
            catch (\JsonException) { return [400,['error'=>'invalid_json']]; }
            if (!is_object($decoded)) return [400,['error'=>'invalid_payload']];
            $data=(array)$decoded;
        }
        $expected=match($action) {
            'create'=>['id','name'],
            'invite','revoke','remove'=>['actor'],
            'respond'=>['accept'],
            'role'=>['actor','role'],
            'owner'=>['actor'],
            'send'=>['id','recipient','text'],
            'block','unblock'=>['actor'],
            'report'=>['id','message','reason'],
            default=>[]
        };
        if ($isWrite) {
            $keys=array_keys($data);
            sort($keys);
            $wanted=$expected;
            sort($wanted);
            if ($keys!==$wanted) return [400,['error'=>'invalid_payload']];
            foreach ($data as $key=>$value) {
                // No implicit string-to-bool coercion on invitation acceptance.
                if (!is_string($key) || ($key==='accept'
                    ? !is_bool($value) : !is_string($value)))
                    return [400,['error'=>'invalid_payload']];
            }
        }
        try {
            $social=new PdoAlliances($this->pdo,$world,$this->clock);
            $messages=new PdoSocialMessages($this->pdo,$world,$this->clock);
            $result=match($action) {
                'list'=>$social->listFor($actor,50,$afterId),
                'inbox'=>$social->invitationsPageFor($actor,$afterId),
                'details'=>$social->detailsFor($actor,$alliance,$memberAfter),
                'create'=>$this->created($social,$actor,$data),
                'invite'=>$this->mutate(fn()=> $social->invite($actor,$alliance,$data['actor'])),
                'respond'=>$this->mutate(fn()=> $social->respond($actor,$alliance,$data['accept'])),
                'revoke'=>$this->mutate(fn()=> $social->revokeInvite($actor,$alliance,$data['actor'])),
                'role'=>$this->mutate(fn()=> $social->setRole($actor,$alliance,$data['actor'],$data['role'])),
                'leave'=>$this->mutate(fn()=> $social->leave($actor,$alliance)),
                'remove'=>$this->mutate(fn()=> $social->remove($actor,$alliance,$data['actor'])),
                'owner'=>$this->mutate(fn()=> $social->transferOwner($actor,$alliance,$data['actor'])),
                'messages'=>$messages->inbox($actor,$cursorTime,$cursorId),
                'send'=>$this->mutate(fn()=> $messages->send($actor,$data['recipient'],$data['id'],$data['text'])),
                'blocks'=>$messages->blocksPageFor($actor,$afterId),
                'block'=>$this->mutate(fn()=> $messages->block($actor,$data['actor'])),
                'unblock'=>$this->mutate(fn()=> $messages->unblock($actor,$data['actor'])),
                'notifications'=>$messages->notifications($actor,$cursorTime,$cursorId),
                'ack'=>$this->mutate(fn()=> $messages->acknowledge($actor,$noticeId)),
                'report'=>$this->mutate(fn()=> $messages->report($actor,$data['id'],$data['message'],$data['reason'])),
                'leaderboard'=>$social->leaderboardFor($actor,$rankAfterCount,$rankAfterId)
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
