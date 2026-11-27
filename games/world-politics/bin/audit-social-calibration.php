<?php
declare(strict_types=1);
require_once __DIR__.'/../src/SocialCalibration.php';
require_once __DIR__.'/../src/FrenchMacroBaseline.php';
require_once __DIR__.'/../src/FrenchDemography.php';
require_once __DIR__.'/../src/FrenchPublicServices.php';
use Aeternum\WorldPolitics\{SocialCalibration,FrenchMacroBaseline,FrenchDemography,FrenchPublicServices};
if(PHP_SAPI!=='cli')exit(2);
try{
    $b=FrenchMacroBaseline::load(true);$p=FrenchDemography::load('2027-05-14',$b['initial_unemployment_bps'],true);
    $s=FrenchPublicServices::load('2027-05-14',$p,$b);
    echo json_encode(['benchmark'=>SocialCalibration::benchmark(SocialCalibration::load()),'sensitivity'=>SocialCalibration::sensitivity($p,$s,$b),'primary_sensitivity'=>SocialCalibration::primarySensitivity($p,$s,$b),'target_2027_sensitivity'=>SocialCalibration::target2027Sensitivity($p,$s,$b),'observed_anchors'=>SocialCalibration::observedAnchors($s)],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
