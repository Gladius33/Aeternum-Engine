<?php
declare(strict_types=1);
use Aeternum\WorldPolitics\{GlobalReferenceBundle,PdoGlobalReferenceBundles};
require_once __DIR__.'/../src/GlobalReferenceBundle.php';
if (PHP_SAPI!=='cli'||getenv('WP_REFERENCE_IMPORT')!=='YES'||$argc!==2) {
    fwrite(STDERR,"Usage: WP_REFERENCE_IMPORT=YES php bin/import-global-references.php /path/to/reviewed-manifest.json\n");exit(2);
}
$manifestPath=realpath($argv[1]);
if ($manifestPath===false) throw new RuntimeException('Missing manifest');
$root=dirname($manifestPath);
$manifest=json_decode(file_get_contents($manifestPath),true,32,JSON_THROW_ON_ERROR);
$archives=[];
foreach ($manifest['series'] as $id=>$entry) {
    $pages=[];
    foreach ($entry['pages'] as $p) {
        if (!is_string($p['file'])||basename($p['file'])!==$p['file']) throw new RuntimeException('Only local adjacent archive filenames permitted');
        $path=realpath($root.'/'.$p['file']);
        if ($path===false||dirname($path)!==$root||filesize($path)>12000000) throw new RuntimeException('Invalid local archive');
        $raw=file_get_contents($path);
        if (!hash_equals($p['sha256'],hash('sha256',$raw))) throw new RuntimeException('Archive digest mismatch');
        $pages[]=$raw;
    }
    $archives[$id]=['pages'=>$pages,'rights'=>$entry['rights']];
}
$bundle=GlobalReferenceBundle::import($archives,$manifest['as_of']);
$dsn=getenv('WP_DB_DSN');
if (!is_string($dsn)||!str_starts_with($dsn,'mysql:')) throw new RuntimeException('MariaDB DSN required');
$pdo=new PDO($dsn,getenv('WP_DB_USER')?:'',getenv('WP_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$sha=(new PdoGlobalReferenceBundles($pdo))->install($bundle);
echo GlobalReferenceBundle::json(['bundle_sha'=>$sha,'manifest'=>$bundle->manifest,'coverage'=>$bundle->coverage()])."\n";
