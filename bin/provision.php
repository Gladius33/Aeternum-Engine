<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,PdoIdentity,PlayerProvisioner,SystemClock};
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
try {
    require __DIR__.'/../vendor/autoload.php';
    if ($argc!==4 || !in_array($argv[1],['reference-medieval','reference-space'],true) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',$argv[2])) throw new InvalidArgumentException('Usage: php bin/provision.php PACK WORLD LOGIN (password on stdin)');
    $password=fgets(STDIN,1026);
    if ($password===false) throw new InvalidArgumentException('Password required on stdin');
    $password=rtrim($password,"\r\n");
    $dsn=getenv('AETERNUM_DSN');
    if (!$dsn) throw new RuntimeException('Missing database configuration');
    $pdo=new PDO($dsn,getenv('AETERNUM_DB_USER') ?: '',getenv('AETERNUM_DB_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $identity=new PdoIdentity($pdo);
    $pack=GamePackLoader::load(file_get_contents(__DIR__.'/../games/'.$argv[1].'/manifest.json'));
    $actor=$identity->authenticate($argv[3],$password);
    if ($actor===null) $actor=$identity->register($argv[3],$password);
    (new PlayerProvisioner($pdo,new SystemClock()))->provision($argv[2],$actor,$pack);
    echo json_encode(['world'=>$argv[2],'account'=>$actor,'entity'=>$actor],JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR,"Provisioning failed. Verify credentials, schema and world rules; retry identical arguments after partial failure.\n");
    exit(1);
}
