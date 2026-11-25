<?php
declare(strict_types=1);
require_once __DIR__.'/m13-actions-audit.php';
require_once __DIR__.'/m13-release-gate.php';

/** Assemble a non-authorizing dossier. Never writes or signs a release manifest. */
final class F07Dossier
{
    public const ARTIFACTS=[
        'PHP core CI'=>['f02-capacity-evidence'],
        'M12 security and supply-chain'=>['f03-resilience-evidence','f03-dependency-evidence'],
        'M11 isolated operations acceptance'=>['f06-distribution-evidence','m11-lab-evidence'],
        'G08 private local playtest'=>['g08-local-game-screenshots'],
    ];
    public static function require(bool $ok,string $reason):void
    {if(!$ok)throw new RuntimeException($reason);}
    public static function sha(string $sha):void
    {self::require(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'Invalid commit identity');}
    private static function number(mixed $value,float $min,float $max):bool
    {return (is_int($value)||is_float($value))&&is_finite((float)$value)&&$value>=$min&&$value<=$max;}
    public static function reports(string $main,array $load,array $recovery,array $distribution,array $browser,array $audit):array
    {
        self::sha($main);
        foreach ([[$load['runner']['source_sha']??null,$load['runner']['checkout_sha']??null,$load['status']??null],
            [$recovery['head']??null,$recovery['checkout']??null,$recovery['status']??null],
            [$distribution['sourceHead']??null,$distribution['checkout']??null,$distribution['status']??null],
            [$browser['sourceHead']??null,$browser['checkout']??null,$browser['status']??null]] as [$source,$checkout,$status])
            self::require($source===$main&&$checkout===$main&&$status==='passed','Missing successful exact-main report');
        self::require(($load['dataset']['historical_replay']??false)===true,'Historical replay evidence missing');
        $profiles=$load['profiles']??[];
        self::require(array_column($profiles,'clients')===[1,4,16,32],'Missing load profiles');
        foreach($profiles as $p)self::require(($p['operations']??0)===11*$p['clients']&&($p['errors']??null)===0
            &&self::number($p['p95_ms']??null,0,12000)&&self::number($p['p99_ms']??null,0,20000)
            &&self::number($p['wall_seconds']??null,0,180)
            &&self::number($p['worker']['max_observed_due_lag_seconds']??null,0,10)
            &&($p['pending_events']??null)===0,'Load regression or incomplete evidence');
        $soak=$load['soak']??[];
        self::require(($soak['reads']??null)===768&&($soak['errors']??null)===0
            &&self::number($soak['p95_ms']??null,0,12000)&&self::number($soak['p99_ms']??null,0,20000)
            &&($load['overload']['other_actor_available']??false)===true
            &&($load['overload']['same_actor_recovered_next_window']??false)===true
            &&($load['overload']['observed_429']??0)>0
            &&($load['verification']['ledger_conserved']??false)===true
            &&($load['verification']['pending_events']??null)===0,'Load recovery or conservation missing');
        $faults=$recovery['faults']??[];sort($faults);
        self::require($faults===['abandoned_http_response','clock_regression','membership_revoked_while_waiting',
            'sigkill_due_worker','sql_connection_during_notification','sql_connection_during_trade']
            &&($recovery['restart_workers']??null)===4&&($recovery['ledger_conserved']??false)===true
            &&($recovery['projection_verified']??false)===true&&($recovery['pending_events']??null)===0
            &&($recovery['notification_count']??null)===1
            &&($recovery['verification']['journal_entries']??0)>0
            &&$recovery['verification']['journal_entries']===($recovery['verification']['outbox_entries']??null),'Recovery evidence incomplete');
        foreach(['freshInstall','strictPreflight','runtimeDdlDenied','restoredPlayer','restoredModule',
            'testPaymentReplay','testRevocation','privateMetrics'] as $key)
            self::require(($distribution[$key]??false)===true,'Distribution evidence missing: '.$key);
        self::require(($distribution['tables']??0)>30&&($distribution['fingerprintedRows']??0)>0
            &&($distribution['verification']['verified']??false)===true
            &&count($distribution['verification']['worlds']??[])===1
            &&($distribution['verification']['worlds'][0]['pendingEvents']??null)===0,'Restore verification incomplete');
        $games=$browser['games']??[];
        self::require(array_column($games,'pack')===['medieval','industrial','guild'],'Missing distinct browser games');
        foreach($games as $g){
            $c=$g['campaign']??[];$e=$g['experience']??[];
            self::require(($c['pack']??null)===$g['pack']&&($e['pack']??null)===$g['pack']
                &&($c['victory']??false)===true&&($c['reconnected']??false)===true
                &&($c['research']??null)===2&&($c['expeditions']??null)===2,'Incomplete campaign');
            foreach(['independentActors','tradeCommitted','sameIdAfterLostReply','allianceAccepted',
                'messageEscaped','blockAndUnblock','operatorModeration','reconnected','keyboard'] as $flag)
                self::require(($e[$flag]??false)===true,'Incomplete multiplayer evidence: '.$flag);
            $views=$e['measurements']??[];
            self::require(array_column($views,'device')===['desktop','mobile'],'Missing measured viewports');
            foreach($views as $v)self::require(($v['overflow']??null)===false&&($v['badBadges']??null)===0
                &&self::number($v['nodes']??null,1,9999)&&self::number($v['frameP95Ms']??null,0,249.999999)
                &&self::number($v['navMs']??null,0,29999)&&self::number($v['minNavHeight']??null,44,10000)
                &&self::number($v['minNavContrast']??null,4.5,21),'Browser measurement incomplete or failed');
        }
        $v=$audit['metadata']['vulnerabilities']??[];
        self::require(($v['high']??null)===0&&($v['critical']??null)===0,'Locked npm audit missing or high/critical findings');
        return ['mixed_operations'=>array_sum(array_column($profiles,'operations')),'soak_reads'=>$soak['reads'],
            'profiles'=>$profiles,'recovery_faults'=>$faults,'restored_tables'=>$distribution['tables'],
            'restored_rows'=>$distribution['fingerprintedRows'],'games'=>array_column($games,'pack'),
            'npm_vulnerabilities'=>$v];
    }
    public static function draft(string $main,array $ci):array
    {
        self::sha($main);
        $doc=['schema'=>'aeternum-m13-v1','release'=>'1.0.0','candidate_sha'=>$main,'gates'=>[],
            'checks'=>array_fill_keys(M13Gate::CHECKS,false),'signoff'=>['operator'=>null,'sha'=>null,'at'=>null]];
        $map=[
            'G01'=>['PHP core CI','M12 security and supply-chain'],
            'G02'=>['PHP core CI','M14 third-pack browser acceptance','M11 isolated operations acceptance'],
            'G03'=>['PHP core CI','M15 progression browser acceptance'],
            'G04'=>['G08 private local playtest','M14 third-pack browser acceptance'],
            'G05'=>['Browser E2E','G08 private local playtest','M12 security and supply-chain'],
            'G06'=>['G08 private local playtest','M15 progression browser acceptance','M16 isolated bot acceptance'],
            'G07'=>['M10 isolated acceptance','M11 isolated operations acceptance','M13 release readiness contract'],
            'G08'=>['M11 isolated operations acceptance'],
            'G09'=>M13ActionsAudit::REQUIRED];
        foreach($map as $gate=>$names){
            $evidence=[];
            foreach($names as $name){
                $run=$ci['workflows'][$name]??[];
                if(($ci['sha']??null)===$main&&($run['passed']??false)===true)
                    $evidence[]=['url'=>$run['url'],'sha'=>$main,'reason'=>$name.' — automated evidence for independent review'];
            }
            $doc['gates'][$gate]=['result'=>'pending','evidence'=>$evidence];
        }
        return $doc;
    }
}
function f07Exec(array $args,string $cwd):string {
    $p=proc_open($args,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/dev/null','a']],$pipes,$cwd);
    F07Dossier::require(is_resource($p),'Unable to start required tool');
    $out=stream_get_contents($pipes[1]);fclose($pipes[1]);
    F07Dossier::require(proc_close($p)===0,'Required command failed: '.$args[0]);
    return trim($out);
}
function f07Read(string $path):array {
    F07Dossier::require(is_file($path)&&!is_link($path)&&filesize($path)>0&&filesize($path)<=2_000_000,'Missing or oversized report: '.basename($path));
    $data=json_decode(file_get_contents($path),true,48,JSON_THROW_ON_ERROR);
    F07Dossier::require(is_array($data),'Invalid report');
    return $data;
}
if(isset($_SERVER['SCRIPT_FILENAME'])&&realpath($_SERVER['SCRIPT_FILENAME'])===__FILE__){
    try{
        F07Dossier::require($argc===4,'Usage: php tools/f07-dossier.php PR_HEAD MAIN_MERGE ABSOLUTE_NEW_PRIVATE_DIRECTORY');
        [$script,$candidate,$main,$dest]=$argv;F07Dossier::sha($candidate);F07Dossier::sha($main);
        $root=dirname(__DIR__);
        F07Dossier::require($candidate!==$main&&str_starts_with($dest,'/')&&!file_exists($dest)
            &&!is_link($dest),'Use a new private output directory');
        $parent=realpath(dirname($dest));
        F07Dossier::require($parent!==false&&!str_starts_with($parent.'/',$root.'/')
            &&(fileperms($parent)&0077)===0,'Private parent directory required outside checkout');
        F07Dossier::require(f07Exec(['git','rev-parse','HEAD'],$root)===$main,'Checkout must be exact merged candidate');
        $parents=explode(' ',f07Exec(['git','rev-list','--parents','-n','1',$main],$root));
        F07Dossier::require(count($parents)===3&&$parents[2]===$candidate,'Expected two-parent merge of exact PR head');
        f07Exec(['git','diff','--quiet',$candidate,$main,'--'],$root);
        f07Exec(['git','diff','--quiet','HEAD','--'],$root);
        $pr=M13ActionsAudit::evaluate(array_values(array_filter(M13ActionsAudit::fetchGitHub($candidate),
            static fn($r)=>($r['event']??null)==='pull_request')),$candidate);
        $push=M13ActionsAudit::evaluate(array_values(array_filter(M13ActionsAudit::fetchGitHub($main),
            static fn($r)=>($r['event']??null)==='push'&&($r['head_branch']??null)==='main')),$main);
        F07Dossier::require($pr['all_required_ci_green']&&$push['all_required_ci_green'],'Both exact-head PR and exact-main push must have ten successful workflows');
        umask(0077);F07Dossier::require(mkdir($dest,0700),'Cannot create dossier directory');
        foreach(F07Dossier::ARTIFACTS as $workflow=>$artifacts){
            $run=$push['workflows'][$workflow]['id'];
            foreach($artifacts as $artifact){
                $target=$dest.'/'.$artifact;
                f07Exec(['gh','run','download',(string)$run,'--repo','Gladius33/Aeternum-Engine','--name',$artifact,'--dir',$target],$root);
            }
        }
        $package=$dest.'/f06-distribution-evidence';
        $build=f07Read($package.'/f06-build-a.json');
        $name='aeternum-'.substr($main,0,12).'.tar.gz';
        F07Dossier::require(($build['sourceSha']??null)===$main&&($build['artifact']??null)===$name
            &&is_file($package.'/'.$name)&&!is_link($package.'/'.$name)
            &&hash_file('sha256',$package.'/'.$name)===($build['sha256']??null),'Exact-main package checksum mismatch');
        $sidecar=file_get_contents($package.'/'.$name.'.sha256');
        F07Dossier::require(is_string($sidecar)&&trim($sidecar)===$build['sha256'].'  '.$name,'Archive sidecar mismatch');
        $summary=F07Dossier::reports($main,f07Read($dest.'/f02-capacity-evidence/f02-capacity.json'),
            f07Read($dest.'/f03-resilience-evidence/f03-resilience.json'),f07Read($package.'/f06-distribution.json'),
            f07Read($dest.'/g08-local-game-screenshots/f07-browser-evidence.json'),
            f07Read($dest.'/f03-dependency-evidence/f03-npm-audit.json'));
        $sbom=f07Read($dest.'/f03-dependency-evidence/f03-client-sbom.json');
        F07Dossier::require(($sbom['bomFormat']??null)==='CycloneDX'&&is_array($sbom['components']??null),'Missing CycloneDX inventory');
        $draft=F07Dossier::draft($main,$push);
        F07Dossier::require(!M13Gate::verify($draft,$main)['metadata_complete'],'Draft must never authorize release');
        $dossier=['status'=>'automated_evidence_collected','release_authorized'=>false,'candidate'=>$candidate,'main'=>$main,
            'pr_ci'=>$pr,'main_ci'=>$push,'package'=>$build,'measurements'=>$summary,
            'remaining_review'=>['independent security review','target load budget and authorized staging',
                'physical devices and accessibility','operator signoff and publication decision']];
        foreach(['dossier.json'=>$dossier,'unsigned-review.json'=>$draft] as $file=>$data)
            F07Dossier::require(file_put_contents($dest.'/'.$file,json_encode($data,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL)!==false,'Cannot write evidence');
        echo json_encode(['collected'=>true,'release_authorized'=>false,'directory'=>$dest],JSON_THROW_ON_ERROR).PHP_EOL;
    }catch(Throwable $e){fwrite(STDERR,'F07 dossier incomplete: '.$e->getMessage().PHP_EOL);exit(2);}
}
