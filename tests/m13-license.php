<?php
declare(strict_types=1);
// M13 legal/attribution regression: no inferred permission to change ACCL-1.0.
// Git blob SHA is pinned to the owner-approved license in the M12 main merge.
$root=dirname(__DIR__);
$license=file_get_contents($root.'/LICENSE.md');
$notice=file_get_contents($root.'/NOTICE');
$html=file_get_contents($root.'/public/play.html');
$readme=file_get_contents($root.'/README.md');
if ($license===false || $notice===false || $html===false || $readme===false)
    throw new RuntimeException('Missing legal or attribution file');
$blobHash=sha1('blob '.strlen($license)."\0".$license);
$approved='131bbd75621360f0875b2ad5bad063b370531bf4';
if (!hash_equals($approved,$blobHash))
    throw new RuntimeException('ACCL-1.0 license changed without acceptance review');
$credit='Powered by Aeternum Engine — Created by Sébastien TOUILLEUX.';
foreach (['NOTICE'=>$notice,'play.html'=>$html,'README.md'=>$readme] as $name=>$bytes) {
    if (!str_contains($bytes,'ACCL-1.0') || !str_contains($bytes,'Sébastien TOUILLEUX'))
        throw new RuntimeException('Missing license or creator attribution in '.$name);
}
if (!str_contains($notice,$credit) || !str_contains($html,$credit))
    throw new RuntimeException('Required full attribution absent from NOTICE or player UI');
if (!str_contains($html,'https://github.com/Gladius33/Aeternum-Engine/blob/main/LICENSE.md'))
    throw new RuntimeException('Player interface has no canonical license link');
echo "PASS M13 owner-approved license bytes unchanged (Git blob $blobHash)\n";
echo "PASS M13 attribution and canonical license link present in NOTICE and client\n";
