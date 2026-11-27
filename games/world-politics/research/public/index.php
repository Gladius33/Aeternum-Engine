<?php
declare(strict_types=1);
require_once __DIR__.'/../connection.php';
ini_set('display_errors','0');
header('Content-Type: application/json');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
try{
    if(getenv('WP_RESEARCH_ENABLED')!=='YES')$r=['status'=>503,'body'=>['code'=>'research_disabled']];
    elseif(!(PHP_SAPI==='cli-server'&&getenv('WP_RESEARCH_LOOPBACK')==='YES'&&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)&&in_array($_SERVER['SERVER_NAME']??'',['127.0.0.1','::1'],true))&&!in_array($_SERVER['HTTPS']??'',['on','1'],true))$r=['status'=>403,'body'=>['code'=>'https_required']];
    elseif(($_SERVER['REQUEST_URI']??'')==='/research/v1/stripe/webhook'){
        require_once __DIR__.'/../../src/ResearchStripe.php';
        if(getenv('WP_STRIPE_ENABLED')!=='YES')$r=['status'=>503,'body'=>['code'=>'stripe_disabled']];
        elseif(($_SERVER['REQUEST_METHOD']??'')!=='POST')$r=['status'=>405,'body'=>['code'=>'method_not_allowed']];
        else{
            \Aeternum\WorldPolitics\StripeConfiguration::ready();
            $raw=file_get_contents('php://input',false,null,0,262145);
            if($raw===false||strlen($raw)>262144)$r=['status'=>413,'body'=>['code'=>'request_bound']];
            else{
                try{$event=\Aeternum\WorldPolitics\StripeWebhook::verify($raw,$_SERVER['HTTP_STRIPE_SIGNATURE']??'',getenv('WP_STRIPE_WEBHOOK_SECRET'),time());}
                catch(\DomainException|\JsonException $e){$event=null;}
                if($event===null)$r=['status'=>400,'body'=>['code'=>'invalid_stripe_event']];
                else{
                    $client=new \Aeternum\WorldPolitics\StripeTestClient();
                    $r=['status'=>200,'body'=>(new \Aeternum\WorldPolitics\ResearchStripe(\Aeternum\WorldPolitics\researchDatabase(),$client->request(...)))->handle($event)];
                }
            }
        }
    }
    else{
        $length=$_SERVER['CONTENT_LENGTH']??'0';
        if(!ctype_digit((string)$length)||(int)$length>16384)$r=['status'=>413,'body'=>['code'=>'request_bound']];
        else{
            $raw=file_get_contents('php://input',false,null,0,16385);
            $h=['authorization'=>$_SERVER['HTTP_AUTHORIZATION']??'','content-type'=>$_SERVER['CONTENT_TYPE']??''];
            if(isset($_SERVER['HTTP_IDEMPOTENCY_KEY']))$h['idempotency-key']=$_SERVER['HTTP_IDEMPOTENCY_KEY'];
            $g=new \Aeternum\WorldPolitics\ResearchGateway(new \Aeternum\WorldPolitics\ResearchQueue(\Aeternum\WorldPolitics\researchDatabase()));
            $r=$g->handle($_SERVER['REQUEST_METHOD']??'',$_SERVER['REQUEST_URI']??'',$raw===false?'':$raw,$h);
        }
    }
}catch(Throwable $e){$r=['status'=>503,'body'=>['code'=>'research_unavailable']];}
http_response_code($r['status']);echo json_encode($r['body'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
