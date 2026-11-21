<?php
declare(strict_types=1);
namespace Aeternum\Core;
use PDO;
final class PdoIdentity
{
    public function __construct(private readonly PDO $pdo)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql' || $pdo->getAttribute(PDO::ATTR_ERRMODE)!==PDO::ERRMODE_EXCEPTION || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) throw new \InvalidArgumentException('Require native PDO MySQL exceptions');
    }
    public function register(string $login,string $password): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login) || strlen($password)<12 || strlen($password)>1024) throw new \InvalidArgumentException('Invalid credentials');
        $actor=bin2hex(random_bytes(32));
        $hash=password_hash($password,PASSWORD_ARGON2ID);
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
        if ($row===false) return null;
        return password_verify($password,$row['password_hash']) ? $row['actor_id'] : null;
    }
}
