<?php
declare(strict_types=1);
/** M13: fail-closed *metadata* contract; linked CI evidence requires independent verification. */
final class M13Gate {
    public const CHECKS=['security_review','recovery_replay','load_limits','migration_compatibility','two_full_games','restore_drill','authorized_staging'];
    public static function verify(array $doc,string $head): array {
        $errors=[];
        if (($doc['schema']??null)!=='aeternum-m13-v1') $errors[]='Invalid schema';
        if (($doc['release']??null)!=='1.0.0') $errors[]='Invalid release';
        $sha=$doc['candidate_sha']??null;
        if (!is_string($sha) || !preg_match('/^[0-9a-f]{40}$/D',$sha) || !hash_equals($sha,$head))
            $errors[]='Candidate SHA differs from Git HEAD';
        $gates=$doc['gates']??null;
        if (!is_array($gates)) $gates=[];
        $expected=[];
        for ($i=1;$i<=9;$i++) $expected[]=sprintf('G%02d',$i);
        $keys=array_keys($gates); sort($keys,SORT_STRING);
        if ($keys!==$expected) $errors[]='G01-G09 set is incomplete or contains extra keys';
        foreach ($expected as $id) {
            $g=$gates[$id]??null;
            if (!is_array($g) || ($g['result']??null)!=='passed') {
                $errors[]="$id not signed off";
                continue;
            }
            $proofs=$g['evidence']??null;
            if (!is_array($proofs) || !array_is_list($proofs) || !$proofs) {
                $errors[]="$id has no evidence";
                continue;
            }
            foreach ($proofs as $proof) {
                $url=is_array($proof)?($proof['url']??null):null;
                $proofSha=is_array($proof)?($proof['sha']??null):null;
                $reason=is_array($proof)?($proof['reason']??null):null;
                if (!is_string($url) || !preg_match('~^https://github\.com/Gladius33/Aeternum-Engine/(actions/runs/[0-9]+|commit/[a-f0-9]{40}|blob/[a-f0-9]{40}/[A-Za-z0-9._/-]+)$~D',$url)
                    || !is_string($proofSha) || !is_string($sha) || !hash_equals($sha,$proofSha)
                    || !is_string($reason) || trim($reason)==='') {
                    $errors[]="$id contains invalid or unpinned proof metadata";
                    break;
                }
            }
        }
        $checks=$doc['checks']??null;
        if (!is_array($checks)) $checks=[];
        $keys=array_keys($checks); sort($keys,SORT_STRING);
        $required=self::CHECKS; sort($required,SORT_STRING);
        if ($keys!==$required) $errors[]='Missing or unexpected cross-cutting release checks';
        foreach (self::CHECKS as $check)
            if (($checks[$check]??null)!==true) $errors[]="Check still pending: $check";
        $signoff=$doc['signoff']??null;
        $actor=is_array($signoff)?($signoff['operator']??null):null;
        $signedSha=is_array($signoff)?($signoff['sha']??null):null;
        $at=is_array($signoff)?($signoff['at']??null):null;
        if (!is_string($actor) || trim($actor)==='' || strlen($actor)>128
            || !is_string($sha) || !is_string($signedSha) || !hash_equals($sha,$signedSha)
            || !is_string($at) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$at))
            $errors[]='Missing SHA-pinned operator approval';
        return ['metadata_complete'=>!$errors,'head'=>$head,'blockers'=>$errors];
    }
}
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME'])===__FILE__) {
    $enforce=in_array('--enforce',$argv,true);
    $inputs=array_values(array_filter(array_slice($argv,1),static fn(string $s):bool=>$s!=='--enforce'));
    if (count($inputs)>1) { fwrite(STDERR,"Usage: php tools/m13-release-gate.php [manifest] [--enforce]\n"); exit(1); }
    try {
        $src=$inputs[0]??__DIR__.'/../docs/release-evidence-v1.json';
        $bytes=file_get_contents($src);
        if ($bytes===false || strlen($bytes)>65536) throw new RuntimeException('Evidence unreadable or oversized');
        $data=json_decode($bytes,true,16,JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('Evidence not a JSON object');
        $p=proc_open(['git','rev-parse','--verify','HEAD'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
        if (!is_resource($p)) throw new RuntimeException('Git unavailable');
        $head=trim(stream_get_contents($pipes[1]));
        stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($p)!==0 || !preg_match('/^[a-f0-9]{40}$/D',$head))
            throw new RuntimeException('Unable to verify checkout HEAD');
        $out=M13Gate::verify($data,$head);
        $out['warning']='Offline metadata only: verify external evidence and operator authorization independently';
        echo json_encode($out,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
        exit($out['metadata_complete']?0:($enforce?2:0));
    } catch (Throwable $error) {
        fwrite(STDERR,"Invalid M13 release metadata: ".get_class($error)."\n");
        exit(1);
    }
}
