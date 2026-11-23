<?php
declare(strict_types=1);
// Runs after M12 populated the fixture. Real subprocesses, independent MySQL
// connections, simultaneous start barrier; all queries are authenticated reads.
(static function():void {
    if (!getenv('AETERNUM_TEST_DSN')) throw new RuntimeException('M13 requires disposable MariaDB');
    $directory=sys_get_temp_dir().'/aeternum-m13-'.bin2hex(random_bytes(8));
    if (!mkdir($directory,0700)) throw new RuntimeException('Cannot create M13 barrier directory');
    $gate=$directory.'/start';
    $children=[];
    $start=hrtime(true);
    try {
        for ($i=0;$i<4;$i++) {
            $proc=proc_open([PHP_BINARY,__DIR__.'/m13-capacity-worker.php',$gate],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if (!is_resource($proc)) throw new RuntimeException('Unable to spawn M13 worker');
            fclose($pipes[0]);
            $children[]=[$proc,$pipes];
        }
        if (file_put_contents($gate,"go\n")===false) throw new RuntimeException('Cannot release M13 workers');
        $market=[];$balance=[];$errors=0;
        foreach ($children as [$proc,$pipes]) {
            $out=stream_get_contents($pipes[1]);
            $err=stream_get_contents($pipes[2]);
            fclose($pipes[1]);fclose($pipes[2]);
            $code=proc_close($proc);
            if ($code!==0) throw new RuntimeException('M13 worker exited '.$code.': '.trim($err));
            $result=json_decode($out,true,8,JSON_THROW_ON_ERROR);
            if (!is_array($result) || count($result['market_ms']??[])!==50
                || count($result['balance_ms']??[])!==50 || ($result['errors']??1)!==0)
                throw new RuntimeException('Incomplete M13 response or inconsistent counts');
            foreach ($result['market_ms'] as $ms) {
                if (!is_int($ms) && !is_float($ms)) throw new RuntimeException('Invalid market duration');
                $market[]=(float)$ms;
            }
            foreach ($result['balance_ms'] as $ms) {
                if (!is_int($ms) && !is_float($ms)) throw new RuntimeException('Invalid balance duration');
                $balance[]=(float)$ms;
            }
            $errors+=(int)$result['errors'];
        }
        sort($market,SORT_NUMERIC);sort($balance,SORT_NUMERIC);
        $p95=static fn(array $a):float=>$a[(int)ceil(count($a)*.95)-1];
        $wall=(hrtime(true)-$start)/1e6;
        $marketP95=$p95($market);
        $balanceP95=$p95($balance);
        $report=[
            'suite'=>'m13-mariadb-parallel-read-probe','workers'=>4,'reads_per_worker'=>100,
            'market_reads'=>count($market),'balance_reads'=>count($balance),
            'fixture_offers'=>1001,'fixture_balances'=>1002,
            'market_p95_ms'=>round($marketP95,3),'balance_p95_ms'=>round($balanceP95,3),
            'wall_ms'=>round($wall,3),'errors'=>$errors,
            'limits'=>['per_read_p95_ms'=>3000,'wall_ms'=>90000],
            'qualification'=>'disposable-runner-only-not-a-production-SLA'
        ];
        echo "M13_CAPACITY_JSON ".json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
        check(count($market)===200 && count($balance)===200 && $errors===0,
            'M13 four real workers completed 400 authorized MariaDB reads without data errors');
        check($marketP95<3000 && $balanceP95<3000 && $wall<90000,
            'M13 bounded parallel-read regression remains within generous laboratory guards');
    } finally {
        if (is_file($gate)) unlink($gate);
        if (is_dir($directory)) rmdir($directory);
        // Reap any worker still running after an exception rather than leave
        // background processes modifying later fixture assumptions.
        foreach ($children as [$proc,$pipes]) {
            // proc_close() invalidates the resource on successful workers.
            // Only unreaped children need signalling and closing here.
            if (!is_resource($proc)) continue;
            $status=proc_get_status($proc);
            if ($status['running']) proc_terminate($proc,9);
            foreach ([1,2] as $fd) if (is_resource($pipes[$fd])) fclose($pipes[$fd]);
            proc_close($proc);
        }
    }
})();
