<?php
declare(strict_types=1);
if (getenv('WP_TEST_ISOLATED')!=='YES' || !str_contains(getenv('WP_DB_DSN')?:'','wp_test')) exit(2);
function ck30(bool $ok,string $label):void {if (!$ok) throw new RuntimeException('WP30 FAIL: '.$label);echo "PASS WP30: $label\n";}
function req30(string $method,string $path,?array $body=null,?string $token=null,array $extra=[]):array {
    $h=['Content-Type: application/json'];if ($token!==null) $h[]='Authorization: Bearer '.$token;
    foreach($extra as $k=>$v)$h[]=$k.': '.$v;
    $opts=['method'=>$method,'header'=>implode("\r\n",$h),'ignore_errors'=>true,'timeout'=>20];
    if ($body!==null)$opts['content']=json_encode($body,JSON_THROW_ON_ERROR);
    $raw=file_get_contents('http://127.0.0.1:18730/api/v1'.$path,false,stream_context_create(['http'=>$opts]));
    if ($raw===false) throw new RuntimeException('HTTP transport failure');
    $status=(int)explode(' ',$http_response_header[0])[1];$etag=null;
    foreach($http_response_header as $line)if(str_starts_with(strtolower($line),'etag:'))$etag=trim(substr($line,5));
    return ['status'=>$status,'body'=>json_decode($raw,true,32,JSON_THROW_ON_ERROR),'etag'=>$etag];
}
$invite=getenv('WP_ALPHA_INVITE');
ck30(req30('GET','/health')['status']===200,'live HTTP endpoint');
ck30(req30('GET','/worlds')['status']===401,'no anonymous worlds');
ck30(req30('POST','/accounts',['username'=>'alice','password'=>'strong-password-123','invite'=>'wrong'])['status']===403,'private enrollment gate');
$a=req30('POST','/accounts',['username'=>'alice','password'=>'strong-password-123','invite'=>$invite]);
$b=req30('POST','/accounts',['username'=>'bob','password'=>'strong-password-456','invite'=>$invite]);
ck30($a['status']===201 && $b['status']===201,'two real accounts');
ck30(req30('POST','/sessions',['username'=>'alice','password'=>'wrong-password-123'])['status']===401,'bad password rejected');
$ta=req30('POST','/sessions',['username'=>'alice','password'=>'strong-password-123'])['body']['access_token'];
$tb=req30('POST','/sessions',['username'=>'bob','password'=>'strong-password-456'])['body']['access_token'];
$pdo=new PDO(getenv('WP_DB_DSN'),getenv('WP_DB_USER'),getenv('WP_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
ck30($pdo->query("SELECT password_hash FROM wp_accounts WHERE username='alice'")->fetchColumn()!== 'strong-password-123'
    && str_starts_with($pdo->query("SELECT password_hash FROM wp_accounts WHERE username='alice'")->fetchColumn(),'$argon2id$'),
    'Argon2id persisted');
ck30(in_array(hash('sha256',$ta),$pdo->query('SELECT token_sha FROM wp_sessions')->fetchAll(PDO::FETCH_COLUMN),true),'only token digest persisted');
$h=['Idempotency-Key'=>'create-world-0001'];$input=['label'=>'Private simulation','scenario'=>'fr-sandbox-v1'];
$wa=req30('POST','/worlds',$input,$ta,$h);
ck30($wa['status']===201,'atomic world bootstrap');
$w=$wa['body']['world_id'];$root='/worlds/'.$w;
ck30(req30('POST','/worlds',$input,$ta,$h)['body']===$wa['body'],'durable create replay');
ck30(req30('POST','/worlds',['label'=>'different','scenario'=>'fr-sandbox-v1'],$ta,$h)['status']===409,'create key collision');
ck30(req30('GET',$root.'/clock',null,$tb)['status']===404,'cross-account read concealed');
$state=req30('GET',$root.'/countries/FR/politics',null,$ta);
ck30($state['status']===200 && $state['body']['people']===[],'durable country roster');
$created=req30('POST',$root.'/countries/FR/politics/people',['name'=>'Camille Test','mode'=>'quick'],$ta,
    ['If-Match'=>$state['etag'],'Idempotency-Key'=>'create-person-0001']);
ck30($created['status']===201,'authenticated fictional profile');
$head=req30('POST',$root.'/countries/FR/politics/head-of-state',['person_id'=>$created['body']['person_id']],$ta,
    ['If-Match'=>$created['etag'],'Idempotency-Key'=>'init-president-001']);
ck30($head['status']===200,'alternative presidency scenario');
ck30(req30('GET',$root.'/countries/FR/politics',null,$ta)['body']['head_of_state']===$created['body']['person_id'],'reconnected state');
ck30(req30('GET',$root.'/countries/FR/institutions',null,$ta)['status']===200,'national institution read');
ck30(req30('GET',$root.'/diplomacy',null,$ta)['status']===200,'diplomacy read');
ck30(req30('GET',$root.'/organizations',null,$ta)['status']===200,'organization read');
ck30(req30('GET',$root.'/economy',null,$ta)['status']===200,'international economy read');
$dom=req30('GET',$root.'/domestic',null,$ta);ck30($dom['status']===200,'domestic read');
$p=req30('POST',$root.'/domestic/proposals',['policy_id'=>'education-reform','effective_day'=>'2026-11-25'],$ta,
    ['If-Match'=>$dom['etag'],'Idempotency-Key'=>'proposal-000001']);
ck30($p['status']===202,'scenario policy proposed');
ck30(req30('POST',$root.'/domestic/enactments',['policy_id'=>'education-reform'],$ta)['status']===404,'no legal approval escalation');
ck30(req30('POST',$root.'/diplomacy/clock',['target_day'=>'2026-11-25'],$ta)['status']===404,'no direct subsystem clock');
ck30(req30('POST',$root.'/members',['account_id'=>$b['body']['account_id'],'access'=>'viewer'],$ta)['status']===200,'owner grants viewer');
ck30(req30('GET',$root.'/clock',null,$tb)['status']===200,'viewer can read shared world');
ck30(req30('POST',$root.'/clock/advances',['target_day'=>'2026-11-25'],$tb)['status']===403,'viewer cannot advance world');
$c=req30('GET',$root.'/clock',null,$ta);
$t=req30('POST',$root.'/clock/advances',['target_day'=>'2026-11-25'],$ta,['If-Match'=>$c['etag'],'Idempotency-Key'=>'day-advance-0001']);
ck30($t['status']===200 && $t['body']['coherent']===true,'server-authorized coordinated tick');
$after=req30('GET',$root.'/domestic',null,$ta);
ck30($after['body']['day']==='2026-11-25' && $after['body']['days'][0]['active_policies']===[],'unapproved reform remains inert');
ck30(req30('POST',$root.'/members',['account_id'=>$b['body']['account_id'],'access'=>'none'],$ta)['status']===200
    && req30('GET',$root.'/clock',null,$tb)['status']===404,'live membership revocation');
ck30(req30('DELETE','/session',null,$ta)['status']===200 && req30('GET','/worlds',null,$ta)['status']===401,'logout revokes token');
$ta2=req30('POST','/sessions',['username'=>'alice','password'=>'strong-password-123'])['body']['access_token'];
ck30(req30('GET',$root.'/clock',null,$ta2)['body']['committed_day']==='2026-11-25','login resumes saved world');
$scenarios=req30('GET','/scenarios',null,$ta2);
ck30($scenarios['status']===200 && count($scenarios['body']['scenarios'])===3,'WP31 scenario selection');
$g=req30('POST','/worlds',['label'=>'Global test','scenario'=>'fr-global-v1'],$ta2,['Idempotency-Key'=>'global-world-0001']);
ck30($g['status']===201,'WP31 global world provisioned');
$gr='/worlds/'.$g['body']['world_id'];
$refs=req30('GET',$gr.'/references',null,$ta2);
ck30($refs['status']===200 && $refs['body']['manifest']['un_members']===193 && count($refs['body']['countries'])===195,'WP31 worldwide reference API');
$actors=req30('GET',$gr.'/diplomacy/actors',null,$ta2);
ck30($actors['status']===200 && count($actors['body']['actors'])===196,'WP31 global diplomatic actors');
$legacy=req30('GET',$root.'/diplomacy/actors',null,$ta2);
ck30($legacy['status']===200 && count($legacy['body']['actors'])===5,'WP31 legacy world unchanged');
$gc=req30('GET',$gr.'/clock',null,$ta2);
ck30($gc['status']===200 && $gc['body']['committed_day']==='2026-11-25','WP31 pinned scenario date');
$next=req30('POST',$gr.'/clock/advances',['target_day'=>'2026-11-26'],$ta2,['If-Match'=>$gc['etag'],'Idempotency-Key'=>'global-tick-0001']);
ck30($next['status']===200 && $next['body']['coherent']===true,'WP31 global clock regression');
ck30(req30('GET',$gr.'/references',null,$tb)['status']===404,'WP31 no cross-world reference access');
$comp=req30('POST','/worlds',['label'=>'Comparative test','scenario'=>'fr-comparative-v1'],$ta2,['Idempotency-Key'=>'comparative-world-01']);
ck30($comp['status']===201,'WP32 comparative world created');
$cr='/worlds/'.$comp['body']['world_id'];
foreach(['DE','ES','GB','IT','JP','US'] as $cc) {
    $state=req30('GET',$cr.'/countries/'.$cc.'/institutions',null,$ta2);
    ck30($state['status']===200 && $state['body']['accountability']['status']==='in_office','WP32 state '.$cc);
}
$cc=req30('GET',$cr.'/clock',null,$ta2);
ck30(count($cc['body']['countries'])===7,'WP32 pinned seven national clocks');
$ct=req30('POST',$cr.'/clock/advances',['target_day'=>'2026-11-26'],$ta2,['If-Match'=>$cc['etag'],'Idempotency-Key'=>'comparative-tick-01']);
ck30($ct['status']===200 && $ct['body']['coherent']===true,'WP32 seven synchronized institutions');
$jp=req30('GET',$cr.'/countries/JP/institutions',null,$ta2);
ck30($jp['body']['accountability']['day']==='2026-11-26','WP32 nested accountability clock');
ck30(req30('POST',$cr.'/countries/DE/institutions/decisions',['action'=>'accountability_vote','payload'=>['evidence_id'=>'spoof']],$ta2)['status']===404,'WP32 HTTP cannot invent votes');
$pdo->prepare('UPDATE wp_sessions SET expires_at=0 WHERE token_sha=?')->execute([hash('sha256',$ta2)]);
ck30(req30('GET','/worlds',null,$ta2)['status']===401,'expired token denied');
echo "WP30 HTTP private alpha acceptance passed.\n";
