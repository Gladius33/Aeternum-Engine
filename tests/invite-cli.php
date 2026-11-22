<?php
declare(strict_types=1);
// Exercise the actual operator CLI against the same disposable schema.
$cliEnv=[
    'AETERNUM_DSN'=>getenv('AETERNUM_TEST_DSN'),
    'AETERNUM_DB_USER'=>getenv('AETERNUM_TEST_USER')?:'',
    'AETERNUM_DB_PASSWORD'=>getenv('AETERNUM_TEST_PASSWORD')?:'',
    'AETERNUM_INDUSTRIAL_WORLD'=>$inviteWorld,
    'AETERNUM_MEDIEVAL_WORLD'=>'',
    'PATH'=>getenv('PATH')?:'/usr/bin'
];
$invokeInvite=static function(string $world,string $count) use($cliEnv):array {
    $proc=proc_open([PHP_BINARY,__DIR__.'/../bin/invite.php',$world,$count],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$cliEnv);
    if(!is_resource($proc))throw new RuntimeException('Cannot run operator invitation CLI');
    fclose($pipes[0]);
    $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return [proc_close($proc),$stdout,$stderr];
};
[$ok,$stdout]=$invokeInvite($inviteWorld,'2');
check($ok===0,'real invitation CLI issues tokens for configured world');
$cliCodes=array_filter(explode("\n",trim($stdout)));
check(count($cliCodes)===2 && count(array_unique($cliCodes))===2,'CLI prints exactly two unique codes');
foreach($cliCodes as $code) {
    check(strlen($code)===64,'operator CLI emits 256-bit invite');
    $s=db()->prepare('SELECT COUNT(*) FROM engine_signup_invitations WHERE token_hash=? AND world_id=?');
    $s->execute([hash('sha256',$code),$inviteWorld]);
    check((int)$s->fetchColumn()===1,'CLI-issued invite only persisted as digest');
}
check($invokeInvite($inviteWorld,'0')[0]!==0,'CLI rejects zero batch');
check($invokeInvite('unconfigured-world','1')[0]!==0,'CLI rejects unapproved world');
