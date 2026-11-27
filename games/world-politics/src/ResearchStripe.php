<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ResearchBilling.php';
require_once __DIR__.'/DeploymentMode.php';

final class StripeConfiguration
{
    public const VERSION='2024-10-28.acacia';
    public static function ready():void{
        DeploymentMode::stripeTest();
        if(getenv('WP_STRIPE_ENABLED')!=='YES'||getenv('WP_STRIPE_MODE')!=='test'||getenv('WP_STRIPE_API_VERSION')!==self::VERSION
            ||getenv('WP_STRIPE_CURRENCY')!=='eur'||!preg_match('/^(sk|rk)_test_[A-Za-z0-9_]{8,}$/D',getenv('WP_STRIPE_SECRET_KEY')?:'')
            ||!preg_match('/^whsec_[A-Za-z0-9_]{16,}$/D',getenv('WP_STRIPE_WEBHOOK_SECRET')?:''))
            throw new \DomainException('Stripe test configuration required');
    }
    public static function offer(string $offer):array{
        $map=['standard-monthly'=>['standard','subscription','WP_STRIPE_PRICE_RESEARCH_STANDARD_MONTHLY','WP_STRIPE_STANDARD_INCLUDED_CREDITS'],
            'pro-monthly'=>['pro','subscription','WP_STRIPE_PRICE_RESEARCH_PRO_MONTHLY','WP_STRIPE_PRO_INCLUDED_CREDITS'],
            'standard-credits'=>['standard','payment','WP_STRIPE_PRICE_RESEARCH_STANDARD_CREDITS','WP_STRIPE_STANDARD_PACK_CREDITS'],
            'pro-credits'=>['pro','payment','WP_STRIPE_PRICE_RESEARCH_PRO_CREDITS','WP_STRIPE_PRO_PACK_CREDITS']];
        if(!isset($map[$offer]))throw new \DomainException('Unknown offer');
        [$plan,$mode,$priceVar,$unitsVar]=$map[$offer];$price=getenv($priceVar)?:'';$units=getenv($unitsVar)?:'';
        if(!preg_match('/^price_[A-Za-z0-9]{5,100}$/D',$price)||!ctype_digit($units)||(int)$units<1||(int)$units>1000000000)
            throw new \DomainException('Offer not configured');
        return ['plan'=>$plan,'mode'=>$mode,'price'=>$price,'units'=>(int)$units];
    }
    public static function url(string $name):string{
        $u=getenv($name)?:'';$p=parse_url($u);
        if(!filter_var($u,FILTER_VALIDATE_URL)||!is_array($p)||($p['scheme']??'')!=='https'||!isset($p['host'])||isset($p['user'],$p['pass'])||isset($p['user'])||isset($p['pass'])||isset($p['fragment']))
            throw new \DomainException('Invalid configured redirect');
        return $u;
    }
}
final class StripeTestClient
{
    public function request(string $method,string $path,array $args=[],?string $key=null):array{
        StripeConfiguration::ready();
        if(!in_array($method,['GET','POST'],true)||!preg_match('~^/v1/(?:prices/[A-Za-z0-9_]+|checkout/sessions(?:/[A-Za-z0-9_]+(?:/line_items)?)?|invoices/[A-Za-z0-9_]+|subscriptions/[A-Za-z0-9_]+|payment_intents/[A-Za-z0-9_]+|charges/[A-Za-z0-9_]+|billing_portal/sessions)$~D',$path))
            throw new \DomainException('Unsupported Stripe request');
        $url='https://api.stripe.com'.$path;
        if($method==='GET'&&$args!==[])$url.='?'.http_build_query($args,'','&',PHP_QUERY_RFC3986);
        $h=['Stripe-Version: '.StripeConfiguration::VERSION,'Accept: application/json'];
        if($key!==null)$h[]='Idempotency-Key: '.$key;
        $ch=curl_init($url);$out='';
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$h,CURLOPT_USERPWD=>getenv('WP_STRIPE_SECRET_KEY').':',
            CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
            CURLOPT_WRITEFUNCTION=>static function($ch,string $s)use(&$out):int{if(strlen($out)+strlen($s)>1048576)return 0;$out.=$s;return strlen($s);}]);
        if($method==='POST')curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($args,'','&',PHP_QUERY_RFC3986));
        $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if($ok===false||$status<200||$status>=300)throw new \RuntimeException('Stripe API unavailable or rejected');
        $r=json_decode($out,true,64,JSON_THROW_ON_ERROR);if(!is_array($r))throw new \RuntimeException('Stripe response invalid');return $r;
    }
}
final class StripeWebhook
{
    public static function verify(string $raw,string $signature,string $secret,int $now):array{
        if(strlen($raw)>262144||strlen($signature)>4096||strlen($secret)<22)throw new \DomainException('Webhook bounds');
        $t=null;$s=[];
        foreach(explode(',',$signature) as $p){$kv=explode('=',trim($p),2);if(count($kv)!==2)throw new \DomainException('Malformed signature');
            if($kv[0]==='t'){if($t!==null||!preg_match('/^[0-9]{1,12}$/D',$kv[1]))throw new \DomainException('Timestamp');$t=$kv[1];}
            elseif($kv[0]==='v1'&&preg_match('/^[a-f0-9]{64}$/D',$kv[1]))$s[]=$kv[1];
        }
        if($t===null||abs($now-(int)$t)>300)throw new \DomainException('Stale signature');
        $expected=hash_hmac('sha256',$t.'.'.$raw,$secret);$ok=false;foreach($s as $sig)if(hash_equals($expected,$sig))$ok=true;
        if(!$ok)throw new \DomainException('Invalid signature');
        $e=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if(!is_array($e)||($e['object']??null)!=='event'||($e['livemode']??null)!==false||($e['api_version']??null)!==StripeConfiguration::VERSION
            ||!is_string($e['id']??null)||!preg_match('/^evt_[A-Za-z0-9_]{5,100}$/D',$e['id'])||!is_string($e['type']??null)||!is_array($e['data']['object']??null))
            throw new \DomainException('Unsupported event edition or mode');
        return $e;
    }
}
/** Test-mode settlement only. The API transport is injected for deterministic
 * offline tests. No user-supplied price, credit quantity or payment state. */
final class ResearchStripe
{
    public function __construct(private readonly \PDO $db,private readonly \Closure $api){DeploymentMode::assertDatabase($db);}
    private function q(string $sql,array $p=[]):\PDOStatement{$q=$this->db->prepare($sql);$q->execute($p);return $q;}
    private function tx(callable $f):mixed{$this->db->beginTransaction();try{$r=$f();$this->db->commit();return $r;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
    private static function id(mixed $v,string $prefix):string{
        if(!is_string($v)||!preg_match('/^'.preg_quote($prefix,'/').'[A-Za-z0-9_]{5,100}$/D',$v))throw new \DomainException('Invalid Stripe object ID');return $v;
    }
    private function get(string $type,string $id,array $args=[]):array{return ($this->api)('GET','/v1/'.$type.'/'.$id,$args,null);}
    private static function testObject(array $o,string $id):void{if(($o['id']??null)!==$id||($o['livemode']??null)!==false)throw new \DomainException('Stripe object identity');}
    private function order(string $id,bool $lock=false):array{
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new \DomainException('Invalid order');
        $r=$this->q('SELECT * FROM wp_stripe_research_orders WHERE order_id=?'.($lock?' FOR UPDATE':''),[$id])->fetch(\PDO::FETCH_ASSOC);
        if($r===false)throw new \DomainException('Unknown order');return $r;
    }
    public function checkout(string $tenant,string $offer,string $requestKey):array{
        if(!preg_match('/^[a-f0-9]{32}$/D',$tenant)||!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$requestKey))throw new \DomainException('Invalid checkout');
        $p=StripeConfiguration::offer($offer);$price=$this->get('prices',$p['price']);self::testObject($price,$p['price']);
        $amount=$price['unit_amount']??null;
        if(($price['active']??null)!==true||($price['currency']??null)!=='eur'||!is_int($amount)||$amount<1||$amount>100000000
            ||($p['mode']==='subscription'?(($price['type']??null)!=='recurring'||($price['recurring']['interval']??null)!=='month'||($price['recurring']['interval_count']??null)!==1||($price['recurring']['usage_type']??null)!=='licensed'):(($price['type']??null)!=='one_time')))
            throw new \DomainException('Unsupported Stripe price');
        $key=hash('sha256',$requestKey);
        $order=$this->tx(function()use($tenant,$offer,$p,$amount,$key){
            $a=$this->q('SELECT plan,enabled,billing_mode,admin_exempt FROM wp_research_tenants WHERE tenant_id=? FOR UPDATE',[$tenant])->fetch(\PDO::FETCH_ASSOC);
            if($a===false||(int)$a['enabled']!==1||(int)$a['admin_exempt']===1||($p['mode']==='payment'&&$a['plan']!==$p['plan']))throw new \DomainException('Ineligible tenant');
            $old=$this->q('SELECT * FROM wp_stripe_research_orders WHERE tenant_id=? AND request_sha=?',[$tenant,$key])->fetch(\PDO::FETCH_ASSOC);
            if($old!==false){if($old['offer']!==$offer)throw new \DomainException('Checkout conflict');return $old;}
            if($p['mode']==='subscription'&&$this->q("SELECT 1 FROM wp_stripe_research_orders WHERE tenant_id=? AND mode='subscription' LIMIT 1",[$tenant])->fetchColumn()!==false)
                throw new \DomainException('Subscription already provisioned; operator review required');
            $id=bin2hex(random_bytes(16));
            $this->q('INSERT INTO wp_stripe_research_orders(order_id,tenant_id,request_sha,offer,plan,mode,price_id,units,amount_minor,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
                [$id,$tenant,$key,$offer,$p['plan'],$p['mode'],$p['price'],$p['units'],$amount,time()]);
            return $this->order($id);
        });
        if($order['checkout_id']!==null)return ['order_id'=>$order['order_id'],'checkout_id'=>$order['checkout_id'],'checkout_url'=>$order['checkout_url'],'test_mode'=>true];
        if(time()-(int)$order['created_at']>3600)throw new \DomainException('Checkout creation requires operator reconciliation');
        $id=$order['order_id'];$args=['mode'=>$order['mode'],'line_items'=>[['price'=>$order['price_id'],'quantity'=>1]],'payment_method_types'=>['card'],
            'success_url'=>StripeConfiguration::url('WP_STRIPE_SUCCESS_URL'),'cancel_url'=>StripeConfiguration::url('WP_STRIPE_CANCEL_URL'),
            'metadata'=>['wp_order'=>$id],'client_reference_id'=>$id];
        if($order['mode']==='subscription')$args['subscription_data']=['metadata'=>['wp_order'=>$id]];
        else{$args['payment_intent_data']=['metadata'=>['wp_order'=>$id]];$args['customer_creation']='always';}
        $r=($this->api)('POST','/v1/checkout/sessions',$args,'wp-research-'.$id);
        $sid=self::id($r['id']??null,'cs_test_');self::testObject($r,$sid);$url=$r['url']??null;
        if(!is_string($url)||parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_HOST)!=='checkout.stripe.com'||strlen($url)>2048)throw new \DomainException('Unexpected Checkout URL');
        return $this->tx(function()use($id,$sid,$url){
            $o=$this->order($id,true);if($o['checkout_id']!==null&&$o['checkout_id']!==$sid)throw new \DomainException('Checkout binding conflict');
            $this->q('UPDATE wp_stripe_research_orders SET checkout_id=?,checkout_url=? WHERE order_id=?',[$sid,$url,$id]);
            return ['order_id'=>$id,'checkout_id'=>$sid,'checkout_url'=>$url,'test_mode'=>true];
        });
    }
    public function portal(string $tenant):array{
        if(!preg_match('/^[a-f0-9]{32}$/D',$tenant))throw new \DomainException('Invalid tenant');
        $r=$this->q("SELECT o.customer_id FROM wp_stripe_research_orders o JOIN wp_research_tenants t USING(tenant_id)
            WHERE o.tenant_id=? AND o.mode='subscription' AND o.customer_id IS NOT NULL AND t.admin_exempt=0 AND t.enabled=1",[$tenant])->fetchColumn();
        $customer=self::id($r,'cus_');
        $p=($this->api)('POST','/v1/billing_portal/sessions',['customer'=>$customer,'return_url'=>StripeConfiguration::url('WP_STRIPE_PORTAL_RETURN_URL')],null);
        $url=$p['url']??null;
        if(!is_string($url)||parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_HOST)!=='billing.stripe.com'||strlen($url)>2048)throw new \DomainException('Unexpected portal URL');
        return ['portal_url'=>$url,'test_mode'=>true];
    }
    /** Freeze rather than silently destroy credits on a confirmed reversal.
     * Operator reconciliation required, including partial refunds and disputes. */
    private function freezePayment(string $pi,string $reason):array{
        return $this->tx(function()use($pi,$reason){
            $r=$this->q('SELECT o.tenant_id FROM wp_stripe_research_receipts r JOIN wp_stripe_research_orders o USING(order_id) WHERE r.payment_id=?',[$pi])->fetchColumn();
            if(!is_string($r))return ['status'=>'unallocated_payment'];
            $this->q('SELECT tenant_id FROM wp_research_tenants WHERE tenant_id=? FOR UPDATE',[$r]);
            $this->q('INSERT IGNORE INTO wp_stripe_research_holds(payment_id,reason,created_at) VALUES(?,?,?)',[$pi,$reason,time()]);
            $this->q('UPDATE wp_research_tenants SET enabled=0 WHERE tenant_id=? AND admin_exempt=0',[$r]);
            return ['status'=>'frozen_for_reconciliation'];
        });
    }
    private static function line(array $line,array $o):void{
        if(($line['price']['id']??null)!==$o['price_id']||($line['quantity']??null)!==1||($line['currency']??null)!=='eur'
            ||($line['amount']??$line['amount_subtotal']??null)!==(int)$o['amount_minor']||($line['proration']??false)!==false)
            throw new \DomainException('Price or quantity mismatch');
    }
    private function confirmedIntent(string $id,string $customer,int $amount):void{
        $p=$this->get('payment_intents',$id);self::testObject($p,$id);
        if(($p['status']??null)!=='succeeded'||($p['currency']??null)!=='eur'||($p['customer']??null)!==$customer||($p['amount_received']??null)!==$amount)
            throw new \DomainException('Unconfirmed payment intent');
        $cid=self::id($p['latest_charge']??null,'ch_');$c=$this->get('charges',$cid);self::testObject($c,$cid);
        if(($c['paid']??null)!==true||($c['refunded']??null)!==false||($c['disputed']??null)!==false||($c['amount_refunded']??null)!==0||($c['payment_intent']??null)!==$id)
            throw new \DomainException('Payment reversed or disputed');
    }
    public function handle(array $e):array{
        $type=$e['type'];$obj=$e['data']['object'];
        if(in_array($type,['charge.refunded','charge.dispute.created','charge.dispute.funds_withdrawn'],true)){
            $cid=self::id($type==='charge.refunded'?($obj['id']??null):($obj['charge']??null),'ch_');
            $c=$this->get('charges',$cid);self::testObject($c,$cid);
            if(($c['disputed']??false)!==true&&(!is_int($c['amount_refunded']??null)||$c['amount_refunded']<1))return ['status'=>'no_confirmed_reversal'];
            return $this->freezePayment(self::id($c['payment_intent']??null,'pi_'),$type);
        }
        if(in_array($type,['customer.subscription.deleted','customer.subscription.updated'],true)){
            $sid=self::id($obj['id']??null,'sub_');$sub=$this->get('subscriptions',$sid);self::testObject($sub,$sid);
            $status=$sub['status']??null;
            if(!in_array($status,['canceled','unpaid','incomplete_expired'],true))return ['status'=>'unchanged'];
            $o=$this->order($sub['metadata']['wp_order']??'');
            return $this->tx(function()use($o,$sub,$sid){
                $a=$this->q('SELECT tenant_id FROM wp_research_tenants WHERE tenant_id=? FOR UPDATE',[$o['tenant_id']])->fetchColumn();
                $r=$this->order($o['order_id'],true);
                if($a===false||$r['subscription_id']!==$sid||$r['customer_id']!==($sub['customer']??null))return ['status'=>'unallocated_subscription'];
                $this->q('UPDATE wp_research_tenants SET paid_until=LEAST(paid_until,?),included_units=0 WHERE tenant_id=? AND admin_exempt=0',[time(),$a]);
                return ['status'=>'subscription_ended'];
            });
        }
        // Do not grant money for a redirect, subscription checkout completion,
        // unpaid asynchronous events or unsupported event types.
        if(in_array($type,['checkout.session.completed','checkout.session.async_payment_succeeded'],true)){
            $sid=self::id($obj['id']??null,'cs_test_');$s=$this->get('checkout/sessions',$sid);self::testObject($s,$sid);
            if(($s['mode']??null)==='subscription')return ['status'=>'await_invoice'];
            if(($s['mode']??null)!=='payment'||($s['status']??null)!=='complete'||($s['payment_status']??null)!=='paid')return ['status'=>'await_payment'];
            $o=$this->order($s['metadata']['wp_order']??'');$customer=self::id($s['customer']??null,'cus_');
            if($o['mode']!=='payment'||$o['checkout_id']!==$sid||($s['client_reference_id']??null)!==$o['order_id']||($s['currency']??null)!=='eur'
                ||($s['amount_subtotal']??null)!==(int)$o['amount_minor']||($s['amount_total']??null)!==(int)$o['amount_minor'])
                throw new \DomainException('Checkout mismatch');
            $lines=$this->get('checkout/sessions',$sid.'/line_items',['limit'=>2]);
            if(($lines['has_more']??null)!==false||count($lines['data']??[])!==1)throw new \DomainException('Checkout line count');
            self::line($lines['data'][0],$o);
            $pi=self::id($s['payment_intent']??null,'pi_');$this->confirmedIntent($pi,$customer,(int)$o['amount_minor']);
            return $this->settle($o,$customer,$pi,null,null);
        }
        if($type==='invoice.paid'){
            $iid=self::id($obj['id']??null,'in_');$i=$this->get('invoices',$iid);self::testObject($i,$iid);
            $subid=self::id($i['subscription']??null,'sub_');$sub=$this->get('subscriptions',$subid);self::testObject($sub,$subid);
            $o=$this->order($sub['metadata']['wp_order']??'');$customer=self::id($i['customer']??null,'cus_');
            if($o['mode']!=='subscription'||$o['checkout_id']===null||($sub['customer']??null)!==$customer||($sub['status']??null)!=='active'
                ||($i['status']??null)!=='paid'||($i['paid_out_of_band']??null)!==false||($i['currency']??null)!=='eur'||($i['collection_method']??null)!=='charge_automatically'
                ||!in_array($i['billing_reason']??null,['subscription_create','subscription_cycle'],true)
                ||($i['amount_paid']??null)!==(int)$o['amount_minor']||($i['lines']['has_more']??null)!==false||count($i['lines']['data']??[])!==1)
                throw new \DomainException('Unsupported invoice');
            $s=$this->get('checkout/sessions',$o['checkout_id']);self::testObject($s,$o['checkout_id']);
            if(($s['subscription']??null)!==$subid||($s['customer']??null)!==$customer||($s['metadata']['wp_order']??null)!==$o['order_id']||($s['mode']??null)!=='subscription')
                throw new \DomainException('Subscription checkout binding');
            $line=$i['lines']['data'][0];self::line($line,$o);$end=$line['period']['end']??null;$start=$line['period']['start']??null;
            if(!is_int($end)||!is_int($start)||$end<=$start||$end-$start>32*86400||$end>time()+32*86400)
                throw new \DomainException('Invalid monthly service period');
            $pi=self::id($i['payment_intent']??null,'pi_');$this->confirmedIntent($pi,$customer,(int)$o['amount_minor']);
            return $this->settle($o,$customer,$pi,$subid,$end);
        }
        return ['status'=>'ignored'];
    }
    private function settle(array $o,string $customer,string $payment,?string $sub,?int $end):array{
        return $this->tx(function()use($o,$customer,$payment,$sub,$end){
            $tenant=$o['tenant_id'];
            $a=$this->q('SELECT * FROM wp_research_tenants WHERE tenant_id=? FOR UPDATE',[$tenant])->fetch(\PDO::FETCH_ASSOC);
            $r=$this->order($o['order_id'],true);
            if($a===false||(int)$a['enabled']!==1||($r['customer_id']!==null&&$r['customer_id']!==$customer)||($r['subscription_id']!==null&&$r['subscription_id']!==$sub))
                throw new \DomainException('Settlement account binding');
            $old=$this->q('SELECT order_id FROM wp_stripe_research_receipts WHERE payment_id=?',[$payment])->fetchColumn();
            if($old!==false){if($old!==$r['order_id'])throw new \DomainException('Payment already allocated');return ['status'=>'duplicate'];}
            if($sub===null){
                if($r['settled_at']!==null||$a['plan']!==$r['plan'])throw new \DomainException('Credit pack tier changed or already settled');
                ResearchBilling::fund($this->db,$tenant,'stripe-'.$payment,'topup',(int)$r['units'],null,null,null,false);
            }else{
                if($end===null||$end<=time()||$end<=(int)$r['period_end']||$end<=(int)$a['paid_until'])return ['status'=>'stale_period'];
                ResearchBilling::fund($this->db,$tenant,'stripe-'.$payment,'renewal',(int)$r['units'],$end,$r['plan'],'monthly',false);
            }
            $this->q('INSERT INTO wp_stripe_research_receipts(payment_id,order_id,created_at) VALUES(?,?,?)',[$payment,$r['order_id'],time()]);
            $this->q('UPDATE wp_stripe_research_orders SET customer_id=?,subscription_id=?,period_end=?,settled_at=? WHERE order_id=?',[$customer,$sub,$end??0,time(),$r['order_id']]);
            return ['status'=>'settled'];
        });
    }
}
