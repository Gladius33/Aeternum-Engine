<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\CampaignEventQueue;
require_once __DIR__.'/../src/CampaignEventQueue.php';
if(PHP_SAPI!=='cli'||getenv('WP_CAMPAIGN_AUTHOR')!=='YES'||$argc!==2){
    fwrite(STDERR,"Usage: WP_CAMPAIGN_AUTHOR=YES php bin/publish-campaign-event.php /path/to/fictional-event.json\n");exit(2);
}
$path=realpath($argv[1]);
if($path===false||!is_file($path)||filesize($path)>8192)throw new RuntimeException('Invalid event file');
$e=json_decode(file_get_contents($path),true,16,JSON_THROW_ON_ERROR);
$dsn=getenv('WP_DB_DSN');if(!is_string($dsn)||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('Dedicated database required');
$db=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
(new CampaignEventQueue($db))->publish($e);
echo "Published fictional campaign event.\n";
