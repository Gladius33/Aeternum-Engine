<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/src/AlphaGateway.php';
use Aeternum\WorldPolitics\{AlphaGateway,AlphaAccounts,UniverseLobby};
if(getenv('WP_TEST_ISOLATED')!=='YES'||!str_contains(getenv('WP_DB_DSN')?:'','dbname=wp_test_multinode;'))exit(2);
function ck102(bool $v,string $msg):void{if(!$v)throw new RuntimeException('WP102 '.$msg);echo "PASS WP102: $msg\n";}
$connect=fn()=>new PDO(getenv('WP_DB_DSN'),getenv('WP_DB_USER'),getenv('WP_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db=$connect();$accounts=new AlphaAccounts($db,getenv('WP_ALPHA_INVITE'));$gw=new AlphaGateway($connect,getenv('WP_ALPHA_INVITE'));
$tokens=[];foreach(range(1,8) as $n){$user='universe-'.$n;$password='universe-test-password-'.$n;
    $accounts->register(['username'=>$user,'password'=>$password,'invite'=>getenv('WP_ALPHA_INVITE')]);$tokens[$n]=$accounts->login(['username'=>$user,'password'=>$password])['access_token'];}
function call102(string $method,string $path,?array $body,int $n,array $headers=[]):array{
    global $gw,$tokens;
    return $gw->handle($method,'/api/v1'.$path,$body===null?'':json_encode($body,JSON_THROW_ON_ERROR),['content-type'=>'application/json','authorization'=>'Bearer '.$tokens[$n],...$headers],'wp102-test-peer');
}
function join102(int $n,string $country,?int $no=null,string $key=''):array{
    return call102('POST','/universes/join',['country'=>$country,...($no===null?[]:['universe_no'=>$no])],$n,['idempotency-key'=>$key?:'wp102-join-test-'.$n]);
}
$first=join102(1,'FR');ck102($first['status']===201,'first player can join alone');
$no=$first['body']['universe_no'];$world=$first['body']['world_id'];$root='/worlds/'.$world;
ck102(join102(1,'FR')===$first,'join receipt replay');
ck102(join102(2,'FR',$no)['status']===409,'country exclusive');
ck102(join102(1,'DE',$no,'wp102-double-claim')['status']===409,'one slot per account and universe');
$directory=call102('GET','/universes',null,1)['body']['universes'];
ck102(count($directory)===1&&$directory[0]['vacant']===6,'six automatic slots');
$before=call102('GET',$root.'/countries/FR/institutions',null,1);
ck102($before['status']===200&&call102('GET',$root.'/clock',null,2)['status']===404,'read membership isolation');
ck102(call102('POST',$root.'/clock/advances',['target_day'=>'2027-05-15'],1)['status']===403,'no human world clock grant');
foreach(['DE','ES','GB','IT','JP','US'] as $j=>$country){$last=join102($j+2,$country,$no,'wp102-fill-country-'.$country);ck102($last['status']===201,'claim '.$country);}
ck102($last['body']['new_universe']!==null&&$last['body']['new_universe']['world_id']!==$world,'last slot creates fresh universe immediately');
$directory=call102('GET','/universes',null,1)['body']['universes'];
ck102(count($directory)===2&&$directory[0]['vacant']===0&&$directory[1]['vacant']===7,'fresh spare contains only automatic slots');
ck102(call102('POST',$root.'/domestic/proposals',['policy_id'=>'education-reform','effective_day'=>'2027-05-20'],2)['status']===403,'foreign player cannot command France');
$dip=call102('GET',$root.'/diplomacy',null,2);
$payload=['from'=>'state_de','to'=>'state_fr','action'=>'dialogue'];
$headers=['if-match'=>$dip['headers']['ETag'],'idempotency-key'=>'wp102-diplomacy-de'];
ck102(call102('POST',$root.'/diplomacy/proposals',$payload,2,$headers)['status']===202,'foreign player commands own diplomacy');
$dip=call102('GET',$root.'/diplomacy',null,2);
$payload['from']='state_fr';$payload['to']='state_de';
ck102(call102('POST',$root.'/diplomacy/proposals',$payload,2,['if-match'=>$dip['headers']['ETag'],'idempotency-key'=>'wp102-spoof-fr'])['status']===403,'foreign player cannot spoof another state');
ck102(call102('DELETE','/universes/'.$no.'/membership',null,1)['status']===200,'release France to AI');
ck102(call102('GET',$root.'/clock',null,1)['status']===404,'former member access revoked');
$rejoin=join102(8,'FR',$no);
ck102($rejoin['status']===201&&$rejoin['body']['world_id']===$world&&$rejoin['body']['generation']===2,'late claimant inherits same world');
ck102(call102('GET',$root.'/countries/FR/institutions',null,8)===$before,'institutional state unchanged at handover');
ck102(count(call102('GET','/universes/'.$no.'/rankings',null,8)['body']['rows'])===8,'universe standings preserve past player');
ck102(join102(8,'FR',$directory[1]['universe_no'],'wp102-other-universe')['status']===201,'account may join separate universe');
$global=call102('GET','/universe-rankings/global',null,8)['body'];
ck102(count($global['rows'])===8&&!$global['composite_score_available']&&$global['rows'][0]['score']===null,'global standings one row per player; no fabricated scores');
