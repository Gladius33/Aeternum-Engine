<?php
declare(strict_types=1);
if(getenv('WP_TEST_ISOLATED')!=='YES'||!str_contains(getenv('WP_TEST_DSN')?:'','dbname=wp_test;'))exit(2);
require_once __DIR__.'/../games/world-politics/src/ResearchGateway.php';
use Aeternum\WorldPolitics\{ResearchQueue as Q,ResearchGateway as G};
function ckResearch(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);}
$db=new PDO(getenv('WP_TEST_DSN'),getenv('WP_TEST_USER'),getenv('WP_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->exec(file_get_contents(__DIR__.'/../games/world-politics/research/database/001_research.sql'));
putenv('WP_RESEARCH_EVIDENCE_KEY=ci-only-research-evidence-not-for-deployment');
$q=new Q($db);$g=new G($q);
$a=$q->createTenant();$b=$q->createTenant();
$head=fn($t,$key='research-test-key-01')=>['authorization'=>'Bearer '.$t,'content-type'=>'application/json','idempotency-key'=>$key];
$body=json_encode(['type'=>'household-backtest','parameters'=>[]],JSON_THROW_ON_ERROR);
$path='/research/v1/experiments';
ckResearch($g->handle('POST',$path,$body,[])['status']===401,'anonymous denied');
$x=$g->handle('POST',$path,$body,$head($a['token']));ckResearch($x['status']===202,'queued');
$id=$x['body']['job_id'];
ckResearch($g->handle('POST',$path,$body,$head($a['token']))===$x,'same request receipt');
ckResearch($g->handle('GET',$path.'/'.$id,'',$head($b['token']))['status']===404,'tenant isolation');
ckResearch($g->handle('DELETE',$path.'/'.$id,'',$head($b['token']))['status']===404,'cross tenant cancellation denied');
ckResearch($q->work()['status']==='complete','worker execution');
$done=$g->handle('GET',$path.'/'.$id,'',$head($a['token']));
ckResearch($done['body']['status']==='complete'&&$done['body']['result']['research_only'],'completed report');
ckResearch($g->handle('GET',$path.'/'.$id.'/csv','',$head($a['token']))['status']===200,'CSV');
$y=$g->handle('POST',$path,$body,$head($a['token'],'research-test-key-02'));
ckResearch($g->handle('DELETE',$path.'/'.$y['body']['job_id'],'',$head($a['token']))['status']===200,'cancel queued');
ckResearch($q->work()===null,'cancelled work not run');
$rot=$q->rotate($a['tenant_id']);
ckResearch($g->handle('GET',$path.'/'.$id,'',$head($a['token']))['status']===401,'old key revoked');
ckResearch($g->handle('GET',$path.'/'.$id,'',$head($rot['token']))['status']===200,'rotated key works');
ckResearch($g->handle('GET','/api/v1/worlds','',$head($rot['token']))['status']===404,'no gameplay route');
$z=$g->handle('POST',$path,$body,$head($rot['token'],'research-test-key-03'));
$db->prepare('UPDATE wp_research_jobs SET manifest_json=? WHERE job_id=?')->execute(['{}',$z['body']['job_id']]);
ckResearch($q->work()['status']==='failed','changed evidence fails closed');
$pay=$q->createTenant('standard',30,false,'payg');
ckResearch($g->handle('GET','/research/v1/models','',$head($pay['token']))['status']===402,'empty prepaid wallet denied');
$q->fund($pay['tenant_id'],'payment-fixture-001','topup',50);
$q->fund($pay['tenant_id'],'payment-fixture-001','topup',50);
try{$q->fund($a['tenant_id'],'payment-fixture-001','topup',50);throw new RuntimeException('Cross-tenant payment replay accepted');}catch(DomainException $expected){}
ckResearch($q->usage($pay['token'])['prepaid_remaining']===50,'idempotent funding');
$x=$g->handle('POST',$path,$body,$head($pay['token']));
ckResearch($x['status']===202&&$q->usage($pay['token'])['prepaid_remaining']===39,'admission plus compute debit');
ckResearch($g->handle('POST',$path,$body,$head($pay['token']))===$x&&$q->usage($pay['token'])['prepaid_remaining']===38,'retry only transport debit');
$q->fund($pay['tenant_id'],'renewal-fixture-001','renewal',10,time()+86400*30,'standard','monthly',true);
ckResearch($q->usage($pay['token'])['included_remaining']===10&&$q->usage($pay['token'])['prepaid_remaining']===38,'renewal preserves wallet');
require_once __DIR__.'/../games/world-politics/src/GameDataset.php';
putenv('WP_DATASET_BRIDGE_KEY=ci-dataset-bridge-key-not-for-production');
$game=\Aeternum\WorldPolitics\GameDataset::project('world-example','2027-05-15',1,['state'=>['world_id'=>'world-example','day'=>'2027-05-15','days'=>[['day'=>'2027-05-15','currency'=>'EUR','revenue'=>100,'expense'=>200,'closing_balance'=>300,'macro'=>['unemployment_bps'=>800]]]]]);
$pack=['version'=>\Aeternum\WorldPolitics\GameDataset::VERSION,'rows'=>[$game]];
ckResearch($q->importGameDataset($pack)['inserted']===1&&$q->importGameDataset($pack)['inserted']===0,'game dataset import idempotent');
require_once __DIR__.'/../games/world-politics/src/GameDatasetSync.php';
$sync=\Aeternum\WorldPolitics\GameDatasetSync::class;
$fetch=fn(int $after)=>$after===0?($pack+['next_after'=>8,'has_more'=>true]):['version'=>$pack['version'],'rows'=>[],'next_after'=>$after,'has_more'=>false];
$first=$sync::run($db,$q,'ci-source',1,$fetch);
ckResearch($first['status']==='backlog_remaining'&&$first['through_seq']===8&&$first['inserted']===0,'durable cursor after idempotent batch');
$second=$sync::run($db,$q,'ci-source',10,$fetch);
ckResearch($second['status']==='complete'&&$second['from_seq']===8&&$second['through_seq']===8,'resume after cursor');
try{$sync::run($db,$q,'ci-source',10,fn($after)=>['version'=>$pack['version'],'rows'=>[$pack['rows'][0]],'next_after'=>$after,'has_more'=>true]);throw new RuntimeException('Invalid cursor accepted');}catch(DomainException $expected){}
ckResearch((int)$db->query("SELECT last_seq FROM wp_research_dataset_sync WHERE stream_id='ci-source'")->fetchColumn()===8,'invalid page did not advance cursor');

$pro=$q->createTenant('pro',30);
ckResearch($g->handle('GET','/research/v1/datasets/game-worlds','',$head($pro['token']))['body']['rows'][0]['row']===$game['row'],'Pro game data feed');
ckResearch($g->handle('GET','/research/v1/datasets/game-worlds','',$head($pay['token']))['status']===403,'standard game feed denied');
ckResearch($q->withdrawGameDataset($game['row']['world'])['deleted_rows']===1,'withdrawn imported aggregate');
ckResearch($g->handle('GET','/research/v1/datasets/game-worlds','',$head($pro['token']))['body']['rows']===[],'withdrawn data no longer served');
try{$q->importGameDataset($pack);throw new RuntimeException('Withdrawn world reimported');}catch(DomainException $expected){}
$withdrawn=$sync::run($db,$q,'ci-withdrawn',10,fn(int $after)=>$after===0?($pack+['next_after'=>1,'has_more'=>false]):['version'=>$pack['version'],'rows'=>[],'next_after'=>$after,'has_more'=>false]);
ckResearch($withdrawn['withdrawn_skipped']===1&&$withdrawn['through_seq']===1,'withdrawn batch does not block daily stream');
$meter=$q->usage($pro['token']);$remaining=$meter['included_remaining'];
$q->configureCapacity('global',86400,0,0);
ckResearch($g->handle('GET','/research/v1/models','',$head($pro['token']))['status']===429,'global emergency stop despite credit');
ckResearch($q->usage($pro['token'])['included_remaining']===$remaining,'capacity denial does not debit');
$q->configureCapacity('global',86400,150000,1000000);
$q->configureCapacity('pro',3600,0,0);
ckResearch($g->handle('GET','/research/v1/models','',$head($pro['token']))['status']===429,'per-tier operator hard limit');
$q->configureCapacity('pro',3600,3000,30000);
ckResearch($g->handle('GET','/research/v1/models','',$head($pro['token']))['status']===200,'live operator limit restored');
echo "PASS WP123 isolated tenant queue, worker, CSV, cancellation, rotation and evidence pin\n";

require_once __DIR__.'/../games/world-politics/src/ResearchStripe.php';
use Aeternum\WorldPolitics\{ResearchStripe as Stripe,StripeWebhook as Hook,StripeConfiguration as SC};
foreach(['WP_STRIPE_PRICE_RESEARCH_STANDARD_CREDITS=price_testcredits','WP_STRIPE_STANDARD_PACK_CREDITS=250','WP_STRIPE_PRICE_RESEARCH_STANDARD_MONTHLY=price_testmonthly','WP_STRIPE_STANDARD_INCLUDED_CREDITS=1000','WP_STRIPE_SUCCESS_URL=https://example.invalid/success','WP_STRIPE_CANCEL_URL=https://example.invalid/cancel'] as $v)putenv($v);
$st=$q->createTenant('standard',0,false,'payg');$objects=[];$calls=0;$checkoutArgs=[];
$objects['/v1/prices/price_testcredits']=['id'=>'price_testcredits','livemode'=>false,'active'=>true,'currency'=>'eur','unit_amount'=>500,'type'=>'one_time'];
$objects['/v1/prices/price_testmonthly']=['id'=>'price_testmonthly','livemode'=>false,'active'=>true,'currency'=>'eur','unit_amount'=>2000,'type'=>'recurring','recurring'=>['interval'=>'month','interval_count'=>1,'usage_type'=>'licensed']];
$api=function($method,$path,$args,$key)use(&$objects,&$calls,&$checkoutArgs){
    if($method==='POST'){
        $calls++;$checkoutArgs=$args;$sid='cs_test_fixture'.$calls;
        return ['id'=>$sid,'livemode'=>false,'url'=>'https://checkout.stripe.com/c/pay/'.$sid];
    }
    if(!isset($objects[$path]))throw new RuntimeException('Unexpected Stripe fixture request '.$path);
    return $objects[$path];
};
$stripe=new Stripe($db,$api);
$co=$stripe->checkout($st['tenant_id'],'standard-credits','stripe-test-checkout-01');
ckResearch($stripe->checkout($st['tenant_id'],'standard-credits','stripe-test-checkout-01')===$co&&$calls===1,'checkout idempotent');
$sid=$co['checkout_id'];$customer='cus_fixture001';$pi='pi_fixture001';
$objects['/v1/checkout/sessions/'.$sid]=['id'=>$sid,'livemode'=>false,'mode'=>'payment','status'=>'complete','payment_status'=>'paid','customer'=>$customer,
    'metadata'=>['wp_order'=>$co['order_id']],'client_reference_id'=>$co['order_id'],'currency'=>'eur','amount_subtotal'=>500,'amount_total'=>500,'payment_intent'=>$pi];
$objects['/v1/checkout/sessions/'.$sid.'/line_items']=['has_more'=>false,'data'=>[['price'=>['id'=>'price_testcredits'],'quantity'=>1,'currency'=>'eur','amount_subtotal'=>500]]];
$objects['/v1/payment_intents/'.$pi]=['id'=>$pi,'livemode'=>false,'status'=>'succeeded','currency'=>'eur','customer'=>$customer,'amount_received'=>500,'latest_charge'=>'ch_fixture001'];
$objects['/v1/charges/ch_fixture001']=['id'=>'ch_fixture001','livemode'=>false,'paid'=>true,'refunded'=>false,'disputed'=>false,'amount_refunded'=>0,'payment_intent'=>$pi];
$e=['id'=>'evt_fixture001','object'=>'event','livemode'=>false,'api_version'=>SC::VERSION,'type'=>'checkout.session.completed','data'=>['object'=>['id'=>$sid]]];
$raw=json_encode($e,JSON_THROW_ON_ERROR);$now=time();$wh='whsec_fixture_secret_0123456789';
$sig='t='.$now.',v1='.hash_hmac('sha256',$now.'.'.$raw,$wh);
$verified=Hook::verify($raw,$sig,$wh,$now);
try{Hook::verify($raw.' ',$sig,$wh,$now);throw new RuntimeException('Tampered event accepted');}catch(DomainException $expected){}
try{Hook::verify($raw,$sig,$wh,$now+301);throw new RuntimeException('Stale event accepted');}catch(DomainException $expected){}
$objects['/v1/checkout/sessions/'.$sid]['payment_status']='unpaid';
ckResearch($stripe->handle($verified)['status']==='await_payment','unpaid checkout grants nothing');
$objects['/v1/checkout/sessions/'.$sid]['payment_status']='paid';
$objects['/v1/checkout/sessions/'.$sid.'/line_items']['data'][0]['quantity']=2;
try{$stripe->handle($verified);throw new RuntimeException('Wrong quantity accepted');}catch(DomainException $expected){}
$objects['/v1/checkout/sessions/'.$sid.'/line_items']['data'][0]['quantity']=1;
ckResearch($stripe->handle($verified)['status']==='settled'&&$stripe->handle($verified)['status']==='duplicate','confirmed pack once');
ckResearch((int)$db->query("SELECT prepaid_units FROM wp_research_tenants WHERE tenant_id='".$st['tenant_id']."'")->fetchColumn()===250,'exact pack credit');
$co2=$stripe->checkout($st['tenant_id'],'standard-monthly','stripe-test-checkout-02');
$sid2=$co2['checkout_id'];$sub='sub_fixture001';$pi2='pi_fixture002';$end=time()+30*86400;
$objects['/v1/checkout/sessions/'.$sid2]=['id'=>$sid2,'livemode'=>false,'mode'=>'subscription','customer'=>$customer,'subscription'=>$sub,'metadata'=>['wp_order'=>$co2['order_id']]];
$objects['/v1/subscriptions/'.$sub]=['id'=>$sub,'livemode'=>false,'status'=>'active','customer'=>$customer,'metadata'=>['wp_order'=>$co2['order_id']]];
$objects['/v1/invoices/in_fixture001']=['id'=>'in_fixture001','livemode'=>false,'subscription'=>$sub,'customer'=>$customer,'status'=>'paid','paid_out_of_band'=>false,'currency'=>'eur','collection_method'=>'charge_automatically','billing_reason'=>'subscription_create','amount_paid'=>2000,'payment_intent'=>$pi2,
    'lines'=>['has_more'=>false,'data'=>[['price'=>['id'=>'price_testmonthly'],'quantity'=>1,'currency'=>'eur','amount'=>2000,'proration'=>false,'period'=>['start'=>$end-30*86400,'end'=>$end]]]]];
$objects['/v1/payment_intents/'.$pi2]=['id'=>$pi2,'livemode'=>false,'status'=>'succeeded','currency'=>'eur','customer'=>$customer,'amount_received'=>2000,'latest_charge'=>'ch_fixture002'];
$objects['/v1/charges/ch_fixture002']=['id'=>'ch_fixture002','livemode'=>false,'paid'=>true,'refunded'=>false,'disputed'=>false,'amount_refunded'=>0,'payment_intent'=>$pi2];
$invoice=['type'=>'invoice.paid','data'=>['object'=>['id'=>'in_fixture001']]];
ckResearch($stripe->handle($invoice)['status']==='settled'&&$stripe->handle($invoice)['status']==='duplicate','monthly settlement once');
$balance=$q->usage($st['token']);
ckResearch($balance['included_remaining']===1000&&$balance['prepaid_remaining']===250&&$balance['paid_until']===$end,'subscription period and wallet retained');
echo "PASS Stripe test-mode signed events, server price validation, checkout/settlement idempotency\n";
