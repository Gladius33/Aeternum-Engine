<?php
declare(strict_types=1);
use Aeternum\Core\{AuthHttpApi,ActivationMailer,FrozenClock,PdoSessions,PdoEmailActivation};
$emailApiClock=new FrozenClock(30000);
$emailPack=\Aeternum\Core\GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json'));
$emailWorld='email-http-world';
$fakeMailer=new class extends ActivationMailer {
    public array $deliveries=[];
    public function deliver(string $email,string $login,string $code):void {
        $this->deliveries[]=[$email,$login,$code];
    }
};
$emailApi=new AuthHttpApi(db(),$emailApiClock,120,[$emailWorld=>$emailPack],false,30,8,true,$fakeMailer);
$emailJson=static fn(array $data):string=>json_encode($data,JSON_THROW_ON_ERROR);
$emailReg=['login'=>'http-mail-player','password'=>'http activation strong password','world'=>$emailWorld,'email'=>'http-verified@example.org'];
check($emailApi->handle('POST','/v1/auth/register','',$emailJson([
    ...$emailReg,'email'=>"attacker@example.org\r\nBcc:someone@example.org"
]),'203.0.113.120')[0]===400,'invalid mailbox or header injection rejected before account creation');
check($emailApi->handle('POST','/v1/auth/register','',$emailJson([
    ...$emailReg,'email'=>str_repeat('a',260).'@example.org'
]),'203.0.113.121')[0]===400,'oversized mailbox rejected with HTTP 400');
[$st,$out]=$emailApi->handle('POST','/v1/auth/register','',$emailJson($emailReg),'203.0.113.101');
check($st===202 && $out===['data'=>['activationRequired'=>true]],'mailbox signup never returns a bearer');
check(count($fakeMailer->deliveries)===1,'mail injected transport receives one code');
$code=$fakeMailer->deliveries[0][2];
check(strlen($code)===64,'delivery code is high entropy');
check($emailApi->handle('POST','/v1/auth/login','',$emailJson([
    'login'=>$emailReg['login'],'password'=>$emailReg['password']
]),'203.0.113.102')[0]===401,'HTTP login rejects unactivated account');
check($emailApi->handle('POST','/v1/auth/register','',$emailJson($emailReg),'203.0.113.103')[0]===202,
    'duplicate signup returns nonrevealing accepted response');
check(count($fakeMailer->deliveries)===1,'duplicate signup sends no new activation');
$emailApiClock->advanceTo(30061);
$resend=$emailApi->handle('POST','/v1/auth/activation/resend','',$emailJson([
    'login'=>$emailReg['login'],'password'=>$emailReg['password'],'email'=>$emailReg['email']
]),'203.0.113.110');
check($resend[0]===202 && count($fakeMailer->deliveries)===2,'HTTP resend delivers a replacement mailbox code');
$replacementCode=$fakeMailer->deliveries[1][2];
check($replacementCode!==$code,'email resend rotates code');
check($emailApi->handle('POST','/v1/auth/activate','',$emailJson([
    'login'=>$emailReg['login'],'password'=>$emailReg['password'],'code'=>$code
]),'203.0.113.111')[0]===401,'superseded mailbox code refused');
$code=$replacementCode;
$proof=['login'=>$emailReg['login'],'password'=>$emailReg['password'],'code'=>$code];
check($emailApi->handle('POST','/v1/auth/activate','',$emailJson([...$proof,'code'=>str_repeat('a',64)]),'203.0.113.104')[0]===401,
    'HTTP activation rejects forged code');
check($emailApi->handle('POST','/v1/auth/activate','',$emailJson([...$proof,'password'=>'another wrong password']),'203.0.113.105')[0]===401,
    'HTTP activation rejects wrong password');
[$st,$out]=$emailApi->handle('POST','/v1/auth/activate','',$emailJson($proof),'203.0.113.106');
check($st===200 && ($out['data']['world']??null)===$emailWorld,'HTTP activation completes verified world claim');
check((new PdoSessions(db(),$emailApiClock))->resolve($out['data']['token'])===$out['data']['account'],
    'email-verified actor owns an actual session');
check(strlen($out['data']['recoveryCode'])===64,'activated player receives offline recovery secret');
check($emailApi->handle('POST','/v1/auth/activate','',$emailJson($proof),'203.0.113.107')[0]===401,
    'completed HTTP activation cannot replay code');
$repeat=$emailApi->handle('POST','/v1/auth/login','',$emailJson([
    'login'=>$emailReg['login'],'password'=>$emailReg['password']
]),'203.0.113.108');
check($repeat[0]===200,'activated player can later sign back in');
$oldMode=new AuthHttpApi(db(),$emailApiClock);
check($oldMode->handle('POST','/v1/auth/activate','',$emailJson($proof),'203.0.113.109')[0]===404,
    'activation endpoints disabled unless explicitly configured');
