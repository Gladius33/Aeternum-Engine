<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

final class AlphaHttpError extends \RuntimeException
{
    public function __construct(public readonly int $status,public readonly string $reason) {parent::__construct($reason);}
}

final class AlphaAccounts
{
    public function __construct(private readonly \PDO $db,private readonly string $invite)
    {
        if (strlen($invite)<32) throw new \LogicException('Private alpha requires a strong enrollment secret');
    }

    public function limit(string $scope,string $identity,int $max,int $period):void
    {
        $window=intdiv(time(),$period);
        $key=hash('sha256',$scope."\n".$identity);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('INSERT INTO wp_http_limits(bucket_sha,window_id,hits) VALUES(?,?,1)
                ON DUPLICATE KEY UPDATE hits=LEAST(hits+1,1000000)');
            $q->execute([$key,$window]);
            $q=$this->db->prepare('SELECT hits FROM wp_http_limits WHERE bucket_sha=? AND window_id=?');
            $q->execute([$key,$window]);$hits=(int)$q->fetchColumn();
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        if ($hits>$max) throw new AlphaHttpError(429,'rate_limited');
    }

    public static function fields(array $body,array $expected):void
    {
        $keys=array_keys($body);sort($keys);sort($expected);
        if ($keys!==$expected) throw new AlphaHttpError(422,'invalid_fields');
    }

    private static function username(mixed $name):string
    {
        if (!is_string($name) || !preg_match('/^[a-z0-9][a-z0-9_-]{2,31}$/D',$name)) throw new AlphaHttpError(422,'invalid_username');
        return $name;
    }

    private static function password(mixed $value):string
    {
        if (!is_string($value) || strlen($value)<12 || strlen($value)>128 || str_contains($value,"\0"))
            throw new AlphaHttpError(422,'invalid_password');
        return $value;
    }

    public function register(array $body):array
    {
        self::fields($body,['username','password','invite']);
        if (!is_string($body['invite']) || !hash_equals($this->invite,$body['invite'])) throw new AlphaHttpError(403,'enrollment_denied');
        $name=self::username($body['username']);$password=self::password($body['password']);
        $id='acct-'.bin2hex(random_bytes(16));
        $hash=password_hash($password,PASSWORD_ARGON2ID);
        try {
            $q=$this->db->prepare('INSERT INTO wp_accounts(account_id,username,password_hash) VALUES(?,?,?)');
            $q->execute([$id,$name,$hash]);
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1]??0)===1062) throw new AlphaHttpError(409,'account_unavailable');
            throw $e;
        }
        return ['account_id'=>$id,'username'=>$name];
    }

    public function login(array $body):array
    {
        self::fields($body,['username','password']);
        $name=self::username($body['username']);$password=self::password($body['password']);
        $this->limit('login-account',$name,10,300);
        $q=$this->db->prepare('SELECT account_id,password_hash,enabled FROM wp_accounts WHERE username=?');
        $q->execute([$name]);$row=$q->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) {
            // Spend comparable password-hashing work for unknown names.
            password_hash($password,PASSWORD_ARGON2ID);
            throw new AlphaHttpError(401,'invalid_credentials');
        }
        if (!password_verify($password,$row['password_hash']) || (int)$row['enabled']!==1)
            throw new AlphaHttpError(401,'invalid_credentials');
        $token=bin2hex(random_bytes(32));$expires=time()+43200;
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT enabled,password_hash FROM wp_accounts WHERE account_id=? FOR UPDATE');
            $q->execute([$row['account_id']]);
            $locked=$q->fetch(\PDO::FETCH_ASSOC);
            if($locked===false||(int)$locked['enabled']!==1||!hash_equals($row['password_hash'],$locked['password_hash']))
                throw new AlphaHttpError(401,'invalid_credentials');
            $q=$this->db->prepare('DELETE FROM wp_sessions WHERE account_id=? AND expires_at<=?');
            $q->execute([$row['account_id'],time()]);
            $q=$this->db->prepare('SELECT COUNT(*) FROM wp_sessions WHERE account_id=?');
            $q->execute([$row['account_id']]);
            if ((int)$q->fetchColumn()>=10) throw new AlphaHttpError(409,'session_limit');
            $q=$this->db->prepare('INSERT INTO wp_sessions(token_sha,account_id,expires_at) VALUES(?,?,?)');
            $q->execute([hash('sha256',$token),$row['account_id'],$expires]);
            if (password_needs_rehash($row['password_hash'],PASSWORD_ARGON2ID)) {
                $q=$this->db->prepare('UPDATE wp_accounts SET password_hash=? WHERE account_id=? AND password_hash=?');
                $q->execute([password_hash($password,PASSWORD_ARGON2ID),$row['account_id'],$row['password_hash']]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return ['access_token'=>$token,'token_type'=>'Bearer','expires_in'=>43200];
    }

    /** Password change requires the live session AND current password.
     * The account lock serializes with session issuance. All tokens revoked.
     * No recovery, e-mail or MFA mechanism is implied. */
    public function changePassword(AlphaSession $session,array $body):array
    {
        self::fields($body,['current_password','new_password']);
        $old=self::password($body['current_password']);$new=self::password($body['new_password']);
        $this->limit('password-change-account',$session->id,5,300);
        if(hash_equals($old,$new))throw new AlphaHttpError(422,'password_unchanged');
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT enabled,password_hash FROM wp_accounts WHERE account_id=? FOR UPDATE');
            $q->execute([$session->id]);$row=$q->fetch(\PDO::FETCH_ASSOC);
            if($row===false||(int)$row['enabled']!==1||!$session->active()||!password_verify($old,$row['password_hash']))
                throw new AlphaHttpError(401,'invalid_credentials');
            $q=$this->db->prepare('UPDATE wp_accounts SET password_hash=? WHERE account_id=?');
            $q->execute([password_hash($new,PASSWORD_ARGON2ID),$session->id]);
            $q=$this->db->prepare('DELETE FROM wp_account_recovery WHERE account_id=?');$q->execute([$session->id]);
            $q=$this->db->prepare('DELETE FROM wp_sessions WHERE account_id=?');
            $q->execute([$session->id]);$count=$q->rowCount();
            $this->db->commit();
            return ['password_changed'=>true,'revoked_sessions'=>$count,'login_required'=>true];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** One optional high-entropy recovery credential, displayed once.
     * Generation requires a live session and the current password. */
    public function createRecovery(AlphaSession $session,array $body):array
    {
        self::fields($body,['current_password']);
        $password=self::password($body['current_password']);
        $this->limit('recovery-create-account',$session->id,5,300);
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT enabled,password_hash FROM wp_accounts WHERE account_id=? FOR UPDATE');
            $q->execute([$session->id]);$row=$q->fetch(\PDO::FETCH_ASSOC);
            if($row===false||(int)$row['enabled']!==1||!$session->active()||!password_verify($password,$row['password_hash']))
                throw new AlphaHttpError(401,'invalid_credentials');
            $secret=bin2hex(random_bytes(32));$expires=time()+31536000;
            $q=$this->db->prepare('INSERT INTO wp_account_recovery(account_id,secret_sha,expires_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE secret_sha=VALUES(secret_sha),expires_at=VALUES(expires_at)');
            $q->execute([$session->id,hash('sha256',$secret),$expires]);
            $this->db->commit();
            return ['recovery_code'=>$secret,'expires_at'=>$expires,'single_use'=>true];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    /** Reset only with a pre-enrolled recovery credential. No email or
     * operator bypass. Account lock serializes with login and rotation. */
    public function recover(array $body):array
    {
        self::fields($body,['username','recovery_code','new_password']);
        $name=self::username($body['username']);$password=self::password($body['new_password']);
        $secret=$body['recovery_code'];
        if(!is_string($secret)||!preg_match('/^[a-f0-9]{64}$/D',$secret))throw new AlphaHttpError(401,'invalid_recovery');
        $this->limit('recovery-account',$name,5,300);
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT account_id,enabled FROM wp_accounts WHERE username=? FOR UPDATE');
            $q->execute([$name]);$row=$q->fetch(\PDO::FETCH_ASSOC);
            if($row===false||(int)$row['enabled']!==1)throw new AlphaHttpError(401,'invalid_recovery');
            $id=$row['account_id'];
            $q=$this->db->prepare('SELECT secret_sha,expires_at FROM wp_account_recovery WHERE account_id=? FOR UPDATE');
            $q->execute([$id]);$record=$q->fetch(\PDO::FETCH_ASSOC);
            if($record===false||(int)$record['expires_at']<=time()||!hash_equals($record['secret_sha'],hash('sha256',$secret)))
                throw new AlphaHttpError(401,'invalid_recovery');
            $q=$this->db->prepare('UPDATE wp_accounts SET password_hash=? WHERE account_id=?');
            $q->execute([password_hash($password,PASSWORD_ARGON2ID),$id]);
            $q=$this->db->prepare('DELETE FROM wp_account_recovery WHERE account_id=?');$q->execute([$id]);
            $q=$this->db->prepare('DELETE FROM wp_sessions WHERE account_id=?');$q->execute([$id]);
            $this->db->commit();return ['password_changed'=>true,'login_required'=>true];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function authenticate(string $header):AlphaSession
    {
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$header,$m)) throw new AlphaHttpError(401,'authentication_required');
        $sha=hash('sha256',$m[1]);
        $q=$this->db->prepare('SELECT a.account_id,a.username FROM wp_sessions s JOIN wp_accounts a ON a.account_id=s.account_id
            WHERE s.token_sha=? AND s.expires_at>? AND a.enabled=1');
        $q->execute([$sha,time()]);$row=$q->fetch(\PDO::FETCH_ASSOC);
        if ($row===false) throw new AlphaHttpError(401,'authentication_required');
        return new AlphaSession($this->db,$row['account_id'],$row['username'],$sha);
    }
}

/** Request-local session. Every permission query checks live account/session membership. */
final class AlphaSession
{
    public function __construct(private readonly \PDO $db,public readonly string $id,
        public readonly string $username,private readonly string $sha) {}
    public function active():bool
    {
        $q=$this->db->prepare('SELECT 1 FROM wp_sessions s JOIN wp_accounts a ON a.account_id=s.account_id
            WHERE s.token_sha=? AND s.account_id=? AND s.expires_at>? AND a.enabled=1');
        $q->execute([$this->sha,$this->id,time()]);return $q->fetchColumn()!==false;
    }
    public function role(string $world):?string
    {
        $q=$this->db->prepare('SELECT m.role FROM wp_alpha_members m JOIN wp_accounts a ON a.account_id=m.account_id
            JOIN wp_sessions s ON s.account_id=a.account_id
            WHERE m.world_id=? AND m.account_id=? AND s.token_sha=? AND s.expires_at>? AND a.enabled=1');
        $q->execute([$world,$this->id,$this->sha,time()]);$r=$q->fetchColumn();return $r===false?null:$r;
    }
    public function revoke():void
    {
        $q=$this->db->prepare('DELETE FROM wp_sessions WHERE token_sha=? AND account_id=?');$q->execute([$this->sha,$this->id]);
    }
}
