<?php
declare(strict_types=1);
// M13 document publication lint: bounded local links, required guides and
// intentionally unsigned release evidence must remain present.
$repo=realpath(__DIR__.'/..');
if ($repo===false) throw new RuntimeException('Repository unavailable');
$docs=[
    'README.md','docs/M12_SECURITY.md','docs/M13_RELEASE_ACCEPTANCE.md',
    'docs/M13_PLAYER_GUIDE.md','docs/M13_CAPACITY_LAB.md',
    'docs/M13_RUNBOOK.md'
];
$links=0;
foreach ($docs as $relative) {
    $path=$repo.'/'.$relative;
    $content=file_get_contents($path);
    if ($content===false || strlen($content)<100) throw new RuntimeException('Missing or empty doc: '.$relative);
    preg_match_all('/(?<!!)\[[^\]]+\]\(([^)\s]+)\)/u',$content,$matches);
    foreach ($matches[1] as $raw) {
        $target=rawurldecode(explode('#',$raw,2)[0]);
        if ($target==='' || preg_match('~^[a-z][a-z0-9+.-]*:~i',$target)) continue;
        if (str_starts_with($target,'/')) throw new RuntimeException('Absolute link in '.$relative);
        $resolved=realpath(dirname($path).'/'.$target);
        if ($resolved===false || !is_file($resolved)
            || !str_starts_with($resolved,$repo.DIRECTORY_SEPARATOR))
            throw new RuntimeException('Broken or escaping local Markdown link: '.$relative.' -> '.$target);
        $links++;
    }
    echo "PASS M13 documentation: $relative\n";
}
$manifest=json_decode(file_get_contents($repo.'/docs/release-evidence-v1.json'),true,16,JSON_THROW_ON_ERROR);
if (!array_key_exists('candidate_sha',$manifest) || $manifest['candidate_sha']!==null
    || !array_key_exists('operator',$manifest['signoff']??[]) || $manifest['signoff']['operator']!==null
    || count($manifest['gates']??[])!==9
    || in_array(true,$manifest['checks']??[],true))
    throw new RuntimeException('Committed template must remain explicitly non-authorizing');
echo "PASS M13 $links local links resolve; committed release template remains unsigned\n";
