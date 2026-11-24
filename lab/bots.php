<?php
declare(strict_types=1);
use Aeternum\Core\{ConfiguredWorlds,EconomyHttpApi,PdoIdentity,PdoSessions,PlayerProvisioner,SystemClock,WorldBuildingHttpApi};
if(PHP_SAPI!=='cli'||getenv('AETERNUM_LAB_ONLY')!=='1'||getenv('AETERNUM_DSN')!=='mysql:host=db;port=3306;dbname=aeternum_lab;charset=utf8mb4')exit(2);
require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/bot-policy.php';
umask(0077);
$packs=['medieval'=>['wood','grain'],'industrial'=>['steel','energy','goods'],'guild'=>['silver','grain','spice']];
$secrets='/var/lib/aeternum-bot-secrets/accounts.json';
$roster='/var/lib/aeternum-bot-public/roster.json';
function atomicJson(string $path,array $data,int $mode):void{
    $tmp=$path.'.tmp';
    if(file_put_contents($tmp,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Cannot write bot state');
    chmod($tmp,$mode);
    if(!rename($tmp,$path))throw new RuntimeException('Cannot publish bot state');
}
function readData(object $api,string $path,string $bearer):array{
    [$code,$data]=$api->handle('GET',$path,$bearer);
    if($code!==200||!is_array($data['data']??null))throw new RuntimeException('Bot read refused');
    return $data['data'];
}
try{
    $mode=$argv[1]??'';
    if(!in_array($mode,['init','run'],true))throw new RuntimeException('Unknown bot mode');
    $pdo=new PDO(getenv('AETERNUM_DSN'),getenv('AETERNUM_DB_USER'),getenv('AETERNUM_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
    $worlds=ConfiguredWorlds::load();$clock=new SystemClock();
    if($mode==='init'){
        if(!is_file($secrets)){
            $credentials=[];
            foreach($packs as $pack=>$resources)for($i=1;$i<=10;$i++)
                $credentials[sprintf('%s-bot-%02d',$pack,$i)]=bin2hex(random_bytes(32));
            atomicJson($secrets,$credentials,0600);
        }
        $credentials=json_decode(file_get_contents($secrets),true,8,JSON_THROW_ON_ERROR);
        $ids=new PdoIdentity($pdo);$provisioner=new PlayerProvisioner($pdo,$clock);
        $status=['version'=>1,'updatedAt'=>time(),'worlds'=>[]];
        foreach($packs as $pack=>$resources)for($i=1;$i<=10;$i++){
            $login=sprintf('%s-bot-%02d',$pack,$i);
            $password=$credentials[$login]??throw new RuntimeException('Missing bot credentials');
            $actor=$ids->authenticate($login,$password)??$ids->register($login,$password);
            $provisioner->provision('lab-'.$pack,$actor,$worlds['lab-'.$pack]);
            $status['worlds']['lab-'.$pack][]=['name'=>sprintf('Bot %02d',$i),'profile'=>LabBotPolicy::PROFILES[($i-1)%4],
                'status'=>'ready','built'=>0,'lastAction'=>null];
        }
        atomicJson($roster,$status,0644);
        echo "Thirty laboratory bots provisioned.\n";exit;
    }
    $approved=trim(file_get_contents(__DIR__.'/bot-policy.sha256'));
    if(!hash_equals($approved,hash_file('sha256',__DIR__.'/bot-policy.php')))throw new RuntimeException('Policy digest mismatch');
    $credentials=json_decode(file_get_contents($secrets),true,8,JSON_THROW_ON_ERROR);
    $sessions=new PdoSessions($pdo,$clock);
    $rules=[];foreach($worlds as $world=>$pack)$rules[$world]=$pack->rules();
    $economy=new EconomyHttpApi($pdo,$clock,$rules);$buildingApi=new WorldBuildingHttpApi($pdo,$clock,$worlds);
    $status=json_decode(file_get_contents($roster),true,12,JSON_THROW_ON_ERROR);
    $lastTick=-1;
    while(true){
        $tick=intdiv(time(),30);
        if($tick===$lastTick){sleep(1);continue;}$lastTick=$tick;
        foreach($packs as $pack=>$resources)for($i=1;$i<=10;$i++){
            $world='lab-'.$pack;$login=sprintf('%s-bot-%02d',$pack,$i);
            $row=$status['worlds'][$world][$i-1];$token=null;
            try{
                $token=$sessions->login($login,$credentials[$login],120);
                if($token===null)throw new RuntimeException('Bot login denied');
                $actor=$sessions->resolve($token);if($actor===null)throw new RuntimeException('Bot session denied');
                $bearer='Bearer '.$token;$root='/v1/worlds/'.$world;
                $choices=readData($buildingApi,$root.'/construction/options',$bearer);
                $progress=readData($buildingApi,$root.'/construction/progression',$bearer);
                $stocks=[];
                foreach($resources as $resource)$stocks[$resource]=readData($economy,$root.'/economy/overview/'.$actor.'/'.$resource,$bearer);
                $campaign=$pack==='guild'?[]:readData($buildingApi,$root.($pack==='medieval'?'/settlement/status/':'/industrial/status/').$actor,$bearer);
                $offers=$pack==='guild'?readData($economy,$root.'/economy/market',$bearer)['offers']:[];
                $row['built']=count(array_filter($progress['buildings'],static fn($b)=>$b['level']>0));
                $row['status']='idle';
                $plan=LabBotPolicy::choose($pack,$actor,$i,$tick,$choices,$progress,$stocks,$campaign,$offers);
                if($plan!==null){
                    // Stable journal ID across restarts and changing plans; no second action.
                    $id='lab-bot-'.hash('sha256',$world."\0".$actor."\0".$tick);
                    if($plan['type']==='market.create')$plan['payload']['offer']=substr($id,0,64);
                    [$code,]=$economy->handle('POST',$root.'/economy/commands',$bearer,json_encode(['id'=>$id]+$plan,JSON_THROW_ON_ERROR));
                    $row['status']=$code===200?'active':'blocked';
                    if($code===200)$row['lastAction']=$plan['type'];
                }
            }catch(Throwable){$row['status']='blocked';}
            finally{if($token!==null)$sessions->revoke($token);}
            $status['worlds'][$world][$i-1]=$row;
        }
        $sessions->purgeExpired(500);$status['updatedAt']=time();
        atomicJson($roster,$status,0644);
        echo json_encode(['tick'=>$tick,'worlds'=>3,'bots'=>30],JSON_THROW_ON_ERROR).PHP_EOL;
        sleep(max(1,30-time()%30));
    }
}catch(Throwable){fwrite(STDERR,"Laboratory bot operation failed; check private volumes, registry and database.\n");exit(1);}
