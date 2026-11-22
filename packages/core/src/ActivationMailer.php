<?php
declare(strict_types=1);
namespace Aeternum\Core;

/**
 * Delivery boundary. Production needs an operator-configured PHP mail transport;
 * CI uses an opt-in loopback-only sink, never a real email destination.
 */
class ActivationMailer
{
    public function deliver(string $email,string $login,string $code): void
    {
        $email=PdoEmailActivation::normalizeEmail($email);
        if(!preg_match('/^[a-z0-9][a-z0-9_-]{2,63}$/D',$login)
            || !preg_match('/^[a-f0-9]{64}$/D',$code))
            throw new \InvalidArgumentException('Invalid activation delivery');
        $sink=getenv('AETERNUM_TEST_ACTIVATION_OUTBOX');
        if($sink!==false && $sink!=='') {
            if(getenv('AETERNUM_BROWSER_TEST')!=='1' || PHP_SAPI!=='cli-server'
                || ($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'
                || !is_dir($sink) || is_link($sink))
                throw new \RuntimeException('Unsafe activation test sink');
            $path=rtrim($sink,'/').'/'.hash('sha256',$login).'.json';
            if(file_put_contents($path,json_encode(['login'=>$login,'code'=>$code],JSON_THROW_ON_ERROR),LOCK_EX)===false)
                throw new \RuntimeException('Test delivery failure');
            chmod($path,0600);
            return;
        }
        $from=getenv('AETERNUM_ACTIVATION_MAIL_FROM');
        $url=getenv('AETERNUM_ACTIVATION_URL');
        if(!is_string($from)||!is_string($url)
            || filter_var($from,FILTER_VALIDATE_EMAIL)===false
            || str_contains($from,"\r") || str_contains($from,"\n")
            || !preg_match('~^https://[a-zA-Z0-9.-]+(?::[0-9]{2,5})?/[a-zA-Z0-9/_-]+\.html$~D',$url))
            throw new \RuntimeException('Activation mail transport not configured');
        $body="Aeternum account activation\n\nLogin: ".$login."\nActivation code: ".$code.
              "\n\nOpen ".$url." and enter this code. It expires after 24 hours.\n".
              "If you did not request this account, ignore this message.\n";
        if(!mail($email,'Aeternum account activation',$body,"From: ".$from."\r\nContent-Type: text/plain; charset=UTF-8"))
            throw new \RuntimeException('Activation mail transport rejected the message');
    }
}
