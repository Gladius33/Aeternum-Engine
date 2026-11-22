<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;
final class PdoIdentity
{
    private const HASH_POLICY=['memory_cost'=>65536,'time_cost'=>4,'threads'=>1];
    // Public dummy hash, not an account credential; same verification policy for unknown valid logins.
    private const DUMMY_HASH='$argon2id$v=19$m=65536,t=4,p=1$S0pRcUx0Lkd2Ym1NWDZmNQ$7njSTtopCnM78xlWp8V+zl8CXq3kQQCEETdVQ7G2E6w';
    public function __construct(private readonly PDO $pdo)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Require native PDO MySQL exceptions');
    }
    public static function hashNewPassword(string $password): string
    {
        if (strlen($password)<12 || strlen($password)>1024) throw new \InvalidArgumentException('Invalid password');
        return password_hash($password,PASSWORD_ARGON2ID,self::HASH_POLICY);
    }
    public function register(string $login,string $password): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login) || strlen($password)<12 || strlen($password)>1024) throw new \InvalidArgumentException('Invalid credentials');
        $actor=bin2hex(random_bytes(32));
        $hash=self::hashNewPassword($password);
        $s=$this->pdo->prepare('INSERT INTO engine_identities(actor_id,login,password_hash) VALUES (?,?,?)');
        $s->execute([$actor,$login,$hash]);
        return $actor;
    }
    public function authenticate(string $login,string $password): ?string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login) || strlen($password)>1024) return null;
        $s=$this->pdo->prepare('SELECT actor_id,password_hash FROM engine_identities WHERE login=?');
        $s->execute([$login]);
        $row=$s->fetch(PDO::FETCH_ASSOC);
        $verified=password_verify($password,$row===false?self::DUMMY_HASH:$row['password_hash']);
        return $row!==false && $verified ? $row['actor_id'] : null;
    }
}
