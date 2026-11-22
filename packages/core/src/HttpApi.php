<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;

/** Read-only HTTP boundary. Session issuance remains internal. */
final class HttpApi
{
    public function __construct(private readonly PDO $pdo,private readonly Clock $clock) {}
    public function handle(string $method,string $path,string $authorization): array
    {
        if ($method!=='GET') return [405,['error'=>'method_not_allowed']];
        if (strlen($path)>512 || !preg_match('~^/v1/worlds/([A-Za-z0-9][A-Za-z0-9_.:-]{0,127})/entities/([A-Za-z0-9][A-Za-z0-9_-]{0,63})(?:/components/([A-Za-z0-9][A-Za-z0-9_-]{0,63})|/(development))?$~D',$path,$m)) return [404,['error'=>'not_found']];
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$token)) return [401,['error'=>'unauthorized']];
        $actor=(new PdoSessions($this->pdo,$this->clock))->resolve($token[1]);
        if ($actor===null) return [401,['error'=>'unauthorized']];
        try {
            $entities=new PdoEntities($this->pdo,$m[1]);
            $data=isset($m[4]) && $m[4]==='development'
                ? $entities->ownDevelopment($actor,$m[2])
                : (isset($m[3]) ? $entities->ownComponent($actor,$m[2],$m[3])
                    : $entities->own($actor,$m[2]));
            return [200,['data'=>(object)$data]];
        } catch (\DomainException) {
            return [404,['error'=>'not_found']];
        }
    }
}
