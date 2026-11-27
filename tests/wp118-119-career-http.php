<?php
declare(strict_types=1);
if(getenv('WP_TEST_ISOLATED')!=='YES'||!str_contains(getenv('WP_DB_DSN')?:'','dbname=wp_test;'))exit(2);
function ck(bool $b,string $m):void{if(!$b)throw new RuntimeException('WP118 HTTP '.$m);}
function req(string $method,string $path,mixed $body=null,?string $token=null,string $key='career-http-creation-001'):array{
    $h=['Content-Type: application/json','Idempotency-Key: '.$key];if($token!==null)$h[]='Authorization: Bearer '.$token;
    $opts=['method'=>$method,'header'=>implode("\r\n",$h),'ignore_errors'=>true,'timeout'=>20];
    if($body!==null)$opts['content']=json_encode($body,JSON_THROW_ON_ERROR);
    $raw=file_get_contents('http://127.0.0.1:18730/api/v1'.$path,false,stream_context_create(['http'=>$opts]));
    if($raw===false)throw new RuntimeException('HTTP transport failed');
    return ['status'=>(int)explode(' ',$http_response_header[0])[1],'body'=>json_decode($raw,true,64,JSON_THROW_ON_ERROR)];
}
$pdo=new PDO(getenv('WP_DB_DSN'),getenv('WP_DB_USER'),getenv('WP_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$exec=function($sql,$args)use($pdo){$q=$pdo->prepare($sql);$q->execute($args);};
$ids=[];$tokens=[];foreach(range(1,3) as $i){$id='acct-'.bin2hex(random_bytes(10));$tok=bin2hex(random_bytes(32));$ids[]=$id;$tokens[]=$tok;
$exec('INSERT INTO wp_accounts(account_id,username,password_hash) VALUES(?,?,?)',[$id,$id,'fixture']);$exec('INSERT INTO wp_sessions(token_sha,account_id,expires_at) VALUES(?,?,?)',[hash('sha256',$tok),$id,time()+3600]);}
[$a,$b,$c]=$tokens;
ck(req('POST','/career-worlds',['label'=>'Career'])['status']===401,'anonymous denied');
$n=req('POST','/career-worlds',['label'=>'Career'],$a);ck($n['status']===201,'create');
$w=$n['body']['world_id'];$p='/career-worlds/'.$w;
ck(req('POST','/career-worlds',['label'=>'Career'],$a)===$n,'idempotent creation');
ck(req('POST','/career-worlds',['label'=>'Changed'],$a)['status']===409,'creation conflict');
$read=req('GET',$p.'/career',null,$a);ck($read['status']===200&&$read['body']['career']!==null&&$read['body']['mandates']===[]&&!$read['body']['rankable'],'no automatic executive mandate');
ck(req('GET',$p.'/career',null,$b)['status']===404,'cross account denied');
ck(req('POST',$p.'/members',['account_id'=>$ids[1]],$a)['status']===200,'invite member');
ck(req('POST',$p.'/members',['account_id'=>$ids[2]],$b)['status']===409,'viewer cannot invite');
ck(req('GET',$p.'/career',null,$b)['body']['career']===null,'invitation not admission');
$ad=req('POST',$p.'/admission',(object)[],$b);ck($ad['status']===200&&$ad['body']['career']!==null&&$ad['body']['mandates']===[],'member career admitted');
ck(req('POST',$p.'/admission',(object)[],$b)===$ad,'admission replay');
ck(req('POST',$p.'/admission',['office'=>'president'],$b)['status']===422,'office injection denied');
ck(req('POST',$p.'/transition',['office'=>'president'],$a)['status']===404,'no public signing');
ck(req('POST','/worlds/'.$w.'/domestic/proposals',['policy_id'=>'education-reform'],$a)['status']===404,'no legacy owner bypass');
ck(req('POST','/worlds/'.$w.'/clock/advances',['target_day'=>'2027-05-15'],$a)['status']===404,'no legacy clock bypass');
ck(req('GET',$p.'/career',null,$c)['status']===404,'uninvited account denied');
foreach(['wp_career_active_offices','wp_career_memberships','wp_career_events','wp_career_ledgers','wp_world_mutation_fences','wp_world_clock','wp_alpha_members','wp_alpha_worlds'] as $t)$exec("DELETE FROM $t WHERE world_id=?",[$w]);
foreach($ids as $id){$exec('DELETE FROM wp_sessions WHERE account_id=?',[$id]);$exec('DELETE FROM wp_accounts WHERE account_id=?',[$id]);}
echo "PASS WP118 HTTP career sandbox, invitations, admission, replay, principal and legacy isolation\n";
