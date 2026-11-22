<?php
declare(strict_types=1);
use Aeternum\Core\{ConfiguredWorlds,PdoInvitations,SystemClock};
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
try {
    require __DIR__.'/../vendor/autoload.php';
    if ($argc!==2) throw new InvalidArgumentException('Usage: php bin/invite-revoke.php APPROVED_WORLD (code via stdin)');
    $packs=ConfiguredWorlds::load();
    if (!isset($packs[$argv[1]])) throw new InvalidArgumentException('World not approved');
    $code=trim(stream_get_contents(STDIN));
    if (!preg_match('/^[a-f0-9]{64}$/D',$code)) throw new InvalidArgumentException('Code must be 64 hex characters');
    $dsn=getenv('AETERNUM_DSN');
    if (!$dsn) throw new RuntimeException('Missing DB settings');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER')?:'',getenv('AETERNUM_DB_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    if (!(new PdoInvitations($pdo,new SystemClock()))->revoke($code,$argv[1])) throw new DomainException('Invitation not revocable');
    echo "Unused invitation revoked.\n";
} catch (Throwable) {
    fwrite(STDERR,"Invitation revocation failed; check world, code and unused status.\n");
    exit(1);
}
