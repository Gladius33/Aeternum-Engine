<?php
declare(strict_types=1);
require_once __DIR__.'/../src/HistoricalProgrammeCoverage.php';
if(PHP_SAPI!=='cli')exit(2);
try{echo json_encode(\Aeternum\WorldPolitics\HistoricalProgrammeCoverage::report(),JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";}
catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
