<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ResearchStripe.php';
require_once __DIR__.'/OperatorAccess.php';

/** Test-mode Game Premium settlement. Separate DB, orders and receipts;
 * no Research tenant IDs or direct gameplay authority. */
final class GameStripe
{
    public function __construct(private readonly \PDO $db,private readonly \Closure $api){DeploymentMode::assertDatabase($db);}
    private function q(string $sql,array $p=[]):\PDOStatement{$q=$this->db->prepare($sql);$q->execute($p);return $q;}
    private function tx(callable $f):mixed{$this->db->beginTransaction();try{$r=$f();$this->db->commit();return $r;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}}
    private function get(string $type,string $id,array $args=[]):array{return ($this->api)('GET','/v1/'.$type.'/'.$id,$args,null);}
    private function order(string $id,bool $lock=false):array{
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new \DomainException('Invalid order');
        $r=$this->q('SELECT * FROM wp_stripe_game_orders WHERE order_id=?'.($lock?' FOR UPDATE':''),[$id])->fetch(\PDO::FETCH_ASSOC);
        if($r===false)throw new \DomainException('Unknown game order');return $r;
    }
    public function checkout(string $account,string $key):array{
        if(!preg_match('/^acct-[a-f0-9]{32}$/D',$account)||!preg_match('/^[A-Za-z0-9_-]{12,100}$/D',$key)||OperatorAccess::game($account))throw new \DomainException('Invalid checkout account');
        $pid=ResearchStripe::id(getenv('WP_STRIPE_PRICE_GAME_PREMIUM_MONTHLY')?:'','price_');
        $p=$this->get('prices',$pid);ResearchStripe::testObject($p,$pid);$amount=$p['unit_amount']??null;
        if(($p['active']??null)!==true||($p['currency']??null)!=='eur'||($p['type']??null)!=='recurring'||($p['recurring']['interval']??null)!=='month'||($p['recurring']['interval_count']??null)!==1||($p['recurring']['usage_type']??null)!=='licensed'||!is_int($amount)||$amount<1||$amount>100000000)
            throw new \DomainException('Unsupported game price');
        $key=hash('sha256',$key);
        $o=$this->tx(function()use($account,$key,$pid,$amount){
            if((int)$this->q('SELECT enabled FROM wp_accounts WHERE account_id=? FOR UPDATE',[$account])->fetchColumn()!==1)throw new \DomainException('Inactive game account');
            $old=$this->q('SELECT * FROM wp_stripe_game_orders WHERE account_id=? AND request_sha=?',[$account,$key])->fetch(\PDO::FETCH_ASSOC);
            if($old!==false){if($old['ended_at']!==null)throw new \DomainException('Closed order');return $old;}
            if($this->q('SELECT 1 FROM wp_stripe_game_orders WHERE account_id=? AND (ended_at IS NULL OR held=1) LIMIT 1',[$account])->fetchColumn()!==false)throw new \DomainException('Existing subscription or unresolved hold');
            $id=bin2hex(random_bytes(16));$this->q('INSERT INTO wp_stripe_game_orders(order_id,account_id,request_sha,price_id,amount_minor,created_at) VALUES(?,?,?,?,?,?)',[$id,$account,$key,$pid,$amount,time()]);
            return $this->order($id);
        });
        if($o['checkout_id']!==null)return ['order_id'=>$o['order_id'],'checkout_id'=>$o['checkout_id'],'checkout_url'=>$o['checkout_url'],'test_mode'=>true];
        if(time()-(int)$o['created_at']>3600)throw new \DomainException('Checkout reconciliation required');
        $id=$o['order_id'];$r=($this->api)('POST','/v1/checkout/sessions',['mode'=>'subscription','line_items'=>[['price'=>$o['price_id'],'quantity'=>1]],
            'payment_method_types'=>['card'],'success_url'=>StripeConfiguration::url('WP_STRIPE_SUCCESS_URL'),'cancel_url'=>StripeConfiguration::url('WP_STRIPE_CANCEL_URL'),
            'metadata'=>['wp_game_order'=>$id],'client_reference_id'=>$id,'subscription_data'=>['metadata'=>['wp_game_order'=>$id]]],'wp-game-'.$id);
        $sid=ResearchStripe::id($r['id']??null,'cs_test_');ResearchStripe::testObject($r,$sid);$url=$r['url']??null;
        if(!is_string($url)||parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_HOST)!=='checkout.stripe.com'||strlen($url)>2048)throw new \DomainException('Unexpected Checkout URL');
        return $this->tx(function()use($id,$sid,$url){$o=$this->order($id,true);
            if($o['checkout_id']!==null&&$o['checkout_id']!==$sid)throw new \DomainException('Checkout conflict');
            $this->q('UPDATE wp_stripe_game_orders SET checkout_id=?,checkout_url=? WHERE order_id=?',[$sid,$url,$id]);
            return ['order_id'=>$id,'checkout_id'=>$sid,'checkout_url'=>$url,'test_mode'=>true];
        });
    }
    public function portal(string $account):array{
        if(OperatorAccess::game($account))throw new \DomainException('Operator is not billable');
        $r=$this->q('SELECT o.customer_id FROM wp_stripe_game_orders o JOIN wp_accounts a USING(account_id) WHERE o.account_id=? AND o.ended_at IS NULL AND a.enabled=1',[$account])->fetchColumn();
        return StripeConfiguration::portal($this->api,ResearchStripe::id($r,'cus_'));
    }
    /** A release allows future verified payments; it never reinstates a
     * revoked Premium entitlement or issues a data token on its own. */
    public function resolveHold(string $pi,string $ref):array{
        ResearchStripe::id($pi,'pi_');
        return $this->tx(function()use($pi,$ref){
            $a=$this->q('SELECT o.account_id FROM wp_stripe_game_receipts r JOIN wp_stripe_game_orders o USING(order_id) WHERE r.payment_id=?',[$pi])->fetchColumn();
            if(!is_string($a))throw new \DomainException('Unknown receipt');
            $this->q('SELECT account_id FROM wp_accounts WHERE account_id=? FOR UPDATE',[$a]);
            $r=StripeHold::resolve($this->db,'game',$pi,$ref,['restore_premium'=>false],static fn()=>null);
            if($r['status']==='resolved'&&$this->q('SELECT 1 FROM wp_stripe_game_holds h JOIN wp_stripe_game_receipts r USING(payment_id) JOIN wp_stripe_game_orders o USING(order_id) WHERE o.account_id=? AND h.resolved_at IS NULL LIMIT 1 FOR UPDATE',[$a])->fetchColumn()===false)
                $this->q('UPDATE wp_stripe_game_orders SET held=0 WHERE account_id=?',[$a]);
            return $r;
        });
    }
    private function revoke(string $account):void{
        $this->q('UPDATE wp_player_api_entitlements SET paid_until=0 WHERE account_id=?',[$account]);
        $this->q('DELETE FROM wp_player_api_keys WHERE account_id=?',[$account]);
    }
    public function handle(array $e):array{
        $type=$e['type'];$obj=$e['data']['object'];
        if(in_array($type,['charge.refunded','charge.dispute.created','charge.dispute.funds_withdrawn'],true)){
            $cid=ResearchStripe::id($type==='charge.refunded'?($obj['id']??null):($obj['charge']??null),'ch_');
            $c=$this->get('charges',$cid);ResearchStripe::testObject($c,$cid);
            if(($c['disputed']??false)!==true&&(!is_int($c['amount_refunded']??null)||$c['amount_refunded']<1))return ['status'=>'no_confirmed_reversal'];
            $pi=ResearchStripe::id($c['payment_intent']??null,'pi_');
            return $this->tx(function()use($pi,$type,$c){
                StripeHold::fence($this->db,$pi,true);
                $a=$this->q('SELECT o.account_id FROM wp_stripe_game_receipts r JOIN wp_stripe_game_orders o USING(order_id) WHERE r.payment_id=?',[$pi])->fetchColumn();
                if(!is_string($a))return ['status'=>'unallocated_reversal_recorded'];
                $this->q('SELECT account_id FROM wp_accounts WHERE account_id=? FOR UPDATE',[$a]);
                if(!StripeHold::observe($this->db,'game',$pi,$type,(int)($c['amount_refunded']??0),($c['disputed']??false)===true))return ['status'=>'already_reconciled'];
                $this->q('UPDATE wp_stripe_game_orders SET held=1 WHERE account_id=?',[$a]);
                $this->revoke($a);return ['status'=>'frozen_for_reconciliation'];
            });
        }
        if(in_array($type,['customer.subscription.deleted','customer.subscription.updated'],true)){
            $sid=ResearchStripe::id($obj['id']??null,'sub_');$s=$this->get('subscriptions',$sid);ResearchStripe::testObject($s,$sid);
            if(!isset($s['metadata']['wp_game_order']))return ['status'=>'unrelated'];
            if(!in_array($s['status']??null,['canceled','unpaid','incomplete_expired'],true))return ['status'=>'unchanged'];
            $o=$this->order($s['metadata']['wp_game_order']);$initial=false;
            if($o['subscription_id']===null){
                if($o['checkout_id']===null)throw new \RuntimeException('Checkout binding pending');
                $session=$this->get('checkout/sessions',$o['checkout_id']);ResearchStripe::testObject($session,$o['checkout_id']);
                if(($session['subscription']??null)!==$sid||($session['customer']??null)!==($s['customer']??null)||($session['metadata']['wp_game_order']??null)!==$o['order_id']||($session['mode']??null)!=='subscription')
                    throw new \DomainException('Initial game subscription binding');
                $initial=true;
            }
            return $this->tx(function()use($o,$s,$sid,$initial){$this->q('SELECT account_id FROM wp_accounts WHERE account_id=? FOR UPDATE',[$o['account_id']]);$r=$this->order($o['order_id'],true);
                if($r['subscription_id']===null&&$initial){
                    if(!in_array($s['status'],['canceled','incomplete_expired'],true))return ['status'=>'await_initial_payment'];
                    if($r['ended_at']!==null)return ['status'=>'already_ended'];
                    $this->q('UPDATE wp_stripe_game_orders SET ended_at=? WHERE order_id=?',[time(),$r['order_id']]);
                    return ['status'=>'unpaid_order_ended'];
                }
                if($r['subscription_id']!==$sid||$r['customer_id']!==($s['customer']??null))return ['status'=>'unallocated_subscription'];
                if($r['ended_at']!==null)return ['status'=>'already_ended'];
                if(in_array($s['status'],['canceled','incomplete_expired'],true))$this->q('UPDATE wp_stripe_game_orders SET ended_at=? WHERE order_id=?',[time(),$r['order_id']]);
                $this->revoke($r['account_id']);return ['status'=>'subscription_ended'];});
        }
        if($type==='checkout.session.expired'){
            $sid=ResearchStripe::id($obj['id']??null,'cs_test_');$session=$this->get('checkout/sessions',$sid);ResearchStripe::testObject($session,$sid);
            if(($session['status']??null)!=='expired'||!isset($session['metadata']['wp_game_order']))return ['status'=>'unchanged'];
            $o=$this->order($session['metadata']['wp_game_order']);
            return $this->tx(function()use($o,$sid){
                $this->q('SELECT account_id FROM wp_accounts WHERE account_id=? FOR UPDATE',[$o['account_id']]);
                $r=$this->order($o['order_id'],true);
                if($r['checkout_id']===null)throw new \RuntimeException('Checkout binding pending');
                if($r['checkout_id']!==$sid||$r['subscription_id']!==null||$r['held'])return ['status'=>'unchanged'];
                $this->q('UPDATE wp_stripe_game_orders SET ended_at=COALESCE(ended_at,?) WHERE order_id=?',[time(),$r['order_id']]);
                return ['status'=>'checkout_expired'];
            });
        }
        if($type!=='invoice.paid')return ['status'=>'ignored'];
        $iid=ResearchStripe::id($obj['id']??null,'in_');$i=$this->get('invoices',$iid);ResearchStripe::testObject($i,$iid);
        $sid=ResearchStripe::id($i['subscription']??null,'sub_');$sub=$this->get('subscriptions',$sid);ResearchStripe::testObject($sub,$sid);
        if(!isset($sub['metadata']['wp_game_order']))return ['status'=>'unrelated'];
        $o=$this->order($sub['metadata']['wp_game_order']);$customer=ResearchStripe::id($i['customer']??null,'cus_');
        if($o['checkout_id']===null||($sub['customer']??null)!==$customer||($sub['status']??null)!=='active'||($i['status']??null)!=='paid'||($i['paid_out_of_band']??null)!==false||($i['currency']??null)!=='eur'||($i['collection_method']??null)!=='charge_automatically'
            ||!in_array($i['billing_reason']??null,['subscription_create','subscription_cycle'],true)||($i['amount_paid']??null)!==(int)$o['amount_minor']||($i['lines']['has_more']??null)!==false||count($i['lines']['data']??[])!==1)
            throw new \DomainException('Unsupported game invoice');
        $session=$this->get('checkout/sessions',$o['checkout_id']);ResearchStripe::testObject($session,$o['checkout_id']);
        if(($session['subscription']??null)!==$sid||($session['customer']??null)!==$customer||($session['metadata']['wp_game_order']??null)!==$o['order_id']||($session['mode']??null)!=='subscription')throw new \DomainException('Game checkout binding');
        $line=$i['lines']['data'][0];ResearchStripe::line($line,$o);$end=$line['period']['end']??null;$start=$line['period']['start']??null;
        if(!is_int($end)||!is_int($start)||$end<=$start||$end-$start>32*86400||$end>time()+32*86400)throw new \DomainException('Invalid monthly service period');
        $pi=ResearchStripe::id($i['payment_intent']??null,'pi_');ResearchStripe::confirmPayment($this->get(...),$pi,$customer,(int)$o['amount_minor']);
        return $this->tx(function()use($o,$customer,$sid,$pi,$end){
            $reversed=StripeHold::fence($this->db,$pi,false);
            if((int)$this->q('SELECT enabled FROM wp_accounts WHERE account_id=? FOR UPDATE',[$o['account_id']])->fetchColumn()!==1)throw new \DomainException('Inactive game account');
            $r=$this->order($o['order_id'],true);
            if((int)$r['held']===1||$r['ended_at']!==null||($r['customer_id']!==null&&$r['customer_id']!==$customer)||($r['subscription_id']!==null&&$r['subscription_id']!==$sid))throw new \DomainException('Game settlement binding');
            $old=$this->q('SELECT order_id FROM wp_stripe_game_receipts WHERE payment_id=?',[$pi])->fetchColumn();
            if($old!==false){if($old!==$r['order_id'])throw new \DomainException('Payment allocated');return ['status'=>'duplicate'];}
            if($reversed)return ['status'=>'payment_reversed_before_settlement'];
            if($end<=time()||$end<=(int)$r['period_end'])return ['status'=>'stale_period'];
            $this->q('INSERT INTO wp_player_api_entitlements(account_id,paid_until) VALUES(?,?) ON DUPLICATE KEY UPDATE paid_until=GREATEST(paid_until,VALUES(paid_until))',[$r['account_id'],$end]);
            $this->q('INSERT INTO wp_stripe_game_receipts(payment_id,order_id,created_at) VALUES(?,?,?)',[$pi,$r['order_id'],time()]);
            $this->q('UPDATE wp_stripe_game_orders SET customer_id=?,subscription_id=?,period_end=? WHERE order_id=?',[$customer,$sid,$end,$r['order_id']]);
            return ['status'=>'settled'];
        });
    }
}
