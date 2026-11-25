<?php
declare(strict_types=1);

/**
 * M13 independent GitHub Actions evidence audit.
 * A successful workflow list is only CI evidence, never release approval.
 * Run this AFTER workflows have completed; do not use it as an in-flight CI
 * success check because it rightly rejects unfinished/missing workflows.
 */
final class M13ActionsAudit
{
    public const REQUIRED=[
        'PHP core CI','Browser E2E','M10 isolated acceptance',
        'M11 isolated operations acceptance','M12 security and supply-chain',
        'M13 release readiness contract', 'G08 private local playtest',
        'M14 third-pack browser acceptance', 'M15 progression browser acceptance',
        'M16 isolated bot acceptance'
    ];

    public static function evaluate(array $runs,string $sha): array
    {
        if (!preg_match('/^[a-f0-9]{40}$/D',$sha)) throw new InvalidArgumentException('Invalid SHA');
        $latest=[];
        foreach ($runs as $run) {
            if (!is_array($run) || ($run['head_sha']??null)!==$sha) continue;
            $event=$run['event']??null;
            if (!in_array($event,['pull_request','push'],true)) continue;
            $name=$run['name']??null;
            $id=$run['id']??null;
            if (!is_string($name) || !in_array($name,self::REQUIRED,true)
                || !is_int($id) || $id<1) continue;
            // Newer run IDs supersede stale, cancelled attempts for the same
            // workflow on this exact commit. Do not count a previous SHA.
            if (!isset($latest[$name]) || $id>$latest[$name]['id']) $latest[$name]=$run;
        }
        $checks=[];$missing=[];$failed=[];
        foreach (self::REQUIRED as $name) {
            if (!isset($latest[$name])) { $missing[]=$name; continue; }
            $run=$latest[$name];
            $done=($run['status']??null)==='completed' && ($run['conclusion']??null)==='success';
            $checks[$name]=[
                'id'=>$run['id'],'status'=>$run['status']??'unknown',
                'conclusion'=>$run['conclusion']??null,'passed'=>$done,
                'url'=>'https://github.com/Gladius33/Aeternum-Engine/actions/runs/'.$run['id']
            ];
            if (!$done) $failed[]=$name;
        }
        return [
            'sha'=>$sha,'all_required_ci_green'=>$missing===[] && $failed===[],
            'missing'=>$missing,'not_successful'=>$failed,'workflows'=>$checks,
            'disclaimer'=>'CI success alone cannot certify security, staging, capacity or V1.0 release.'
        ];
    }

    public static function fetchGitHub(string $sha): array
    {
        if (!preg_match('/^[a-f0-9]{40}$/D',$sha)) throw new InvalidArgumentException('Invalid SHA');
        $uri='https://api.github.com/repos/Gladius33/Aeternum-Engine/actions/runs?head_sha='.$sha.'&per_page=100';
        $headers=["User-Agent: Aeternum-M13-Release-Audit/1.0",
            "Accept: application/vnd.github+json","X-GitHub-Api-Version: 2022-11-28"];
        $token=getenv('GITHUB_TOKEN');
        if (is_string($token) && $token!=='') {
            if (preg_match('/[\r\n]/',$token)) throw new RuntimeException('Malformed GitHub token');
            $headers[]='Authorization: Bearer '.$token;
        }
        $context=stream_context_create(['http'=>[
            'method'=>'GET','header'=>implode("\r\n",$headers)."\r\n",
            'timeout'=>20,'follow_location'=>0,'ignore_errors'=>false
        ]]);
        $response=@file_get_contents($uri,false,$context);
        if ($response===false || strlen($response)>2_000_000)
            throw new RuntimeException('GitHub Actions API unavailable or oversized');
        $json=json_decode($response,true,64,JSON_THROW_ON_ERROR);
        if (!is_array($json) || !isset($json['workflow_runs']) || !is_array($json['workflow_runs']))
            throw new RuntimeException('Unexpected GitHub Actions response');
        if (($json['total_count']??0)>100)
            throw new RuntimeException('More than 100 workflow runs; explicit pagination required');
        return $json['workflow_runs'];
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME'])===__FILE__) {
    try {
        if (count($argv)!==2) throw new InvalidArgumentException('Usage: php tools/m13-actions-audit.php SHA');
        $result=M13ActionsAudit::evaluate(M13ActionsAudit::fetchGitHub($argv[1]),$argv[1]);
        echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
        exit($result['all_required_ci_green']?0:2);
    } catch (Throwable $e) {
        fwrite(STDERR,"M13 GitHub audit unavailable: ".get_class($e)."\n");
        exit(1);
    }
}
