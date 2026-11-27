<?php
declare(strict_types=1);
require_once __DIR__.'/../games/world-politics/research/connection.php';
use Aeternum\WorldPolitics\ResearchQueue;
if(getenv('WP_RESEARCH_TEST')!=='YES'||!str_contains(getenv('WP_RESEARCH_DB_DSN')?:'','dbname=wp_research_test;'))exit(2);
function ckr(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);}
$db=\Aeternum\WorldPolitics\researchDatabase();$q=new ResearchQueue($db);
$a=$q->createTenant();$b=$q->createTenant();
function rr(string $method,string $path,?string $token=null,?array $body=null,string $key='real-research-key-01'):array{
    $h=['Content-Type: application/json','Idempotency-Key: '.$key];if($token!==null)$h[]='Authorization: Bearer '.$token;
    $o=['method'=>$method,'header'=>implode("\r\n",$h),'ignore_errors'=>true,'timeout'=>5];
    if($body!==null)$o['content']=json_encode($body,JSON_THROW_ON_ERROR);
    $raw=file_get_contents('http://127.0.0.1:18731'.$path,false,stream_context_create(['http'=>$o]));
    if($raw===false)throw new RuntimeException('Research HTTP unavailable');
    return ['status'=>(int)explode(' ',$http_response_header[0])[1],'body'=>json_decode($raw,true,64,JSON_THROW_ON_ERROR)];
}
$p='/research/v1/experiments';$body=['type'=>'countries','parameters'=>[]];
ckr(rr('GET','/research/v1/models')['status']===401,'anonymous HTTP denied');
ckr(rr('GET','/research/v1/models',$a['token'])['status']===200,'authenticated model catalogue');
$x=rr('POST',$p,$a['token'],$body);ckr($x['status']===202,'real queued job');
$id=$x['body']['job_id'];
ckr(rr('POST',$p,$a['token'],$body)===$x,'HTTP idempotency');
ckr(rr('POST',$p,$a['token'],['type'=>'household-backtest','parameters'=>[]])['status']===409,'HTTP idempotency conflict');
ckr(rr('GET',$p.'/'.$id,$b['token'])['status']===404,'cross tenant read denied');
ckr($q->work()['status']==='complete','separate DB worker');
$done=rr('GET',$p.'/'.$id,$a['token']);
ckr($done['status']===200&&count($done['body']['result']['result']['rows'])===195,'real country result');
ckr(rr('GET',$p.'/'.$id.'/csv',$a['token'])['status']===200,'real CSV');
ckr(rr('GET','/api/v1/worlds',$a['token'])['status']===404,'no player route');
$rot=$q->rotate($a['tenant_id']);ckr(rr('GET',$p.'/'.$id,$a['token'])['status']===401,'HTTP revoked key');
ckr(rr('GET',$p.'/'.$id,$rot['token'])===$done,'rotation preserves result');
$db->prepare('UPDATE wp_research_jobs SET result_json=? WHERE job_id=?')->execute(['{}',$id]);
ckr(rr('GET',$p.'/'.$id,$rot['token'])['status']===409,'tampered result rejected');
echo "PASS WP123 isolated dedicated MariaDB and actual HTTP research transport\n";
