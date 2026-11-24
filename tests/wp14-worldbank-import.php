<?php
declare(strict_types=1);

require __DIR__.'/../games/world-politics/src/WorldBankOfflineImporter.php';
use Aeternum\WorldPolitics\{GeopoliticalActorRegistry,WorldBankOfflineImporter};

function wp14(bool $ok,string $message):void
{
    if (!$ok) throw new RuntimeException($message);
    echo "PASS WP14: ".$message."\n";
}
function wp14reject(callable $f,string $message):void
{
    try {$f();}
    catch(DomainException|InvalidArgumentException|JsonException $e) {
        wp14(true,$message);return;
    }
    throw new RuntimeException('Expected importer rejection: '.$message);
}
$document=json_decode(file_get_contents(
    __DIR__.'/../games/world-politics/data/2026-11-24-special-actors.json'
),true,32,JSON_THROW_ON_ERROR);
$document['entities'][]=[
    'id'=>'state_fr','label'=>'France','iso2'=>'FR','kind'=>'state',
    'un_status'=>'un_member','parent_id'=>null,'controller'=>'human_or_ai',
    'representation'=>'international_state_actor',
    'sources'=>['https://www.un.org/en/about-us/member-states'],
    'strategic_programmes'=>[]
];
$actors=new GeopoliticalActorRegistry($document);

$indicator='SP.POP.TOTL';
$row=static fn(string $iso,string $year,mixed $value,string $metric='SP.POP.TOTL'):array=>[
    'indicator'=>['id'=>$metric,'value'=>'Population, synthetic test record'],
    'country'=>['id'=>$iso,'value'=>'Synthetic country fixture'],
    'date'=>$year,'value'=>$value
];
$page=static fn(int $n,int $of,array $rows,int $total=4):string=>json_encode([
    ['page'=>$n,'pages'=>$of,'total'=>$total,'per_page'=>2],
    $rows
],JSON_THROW_ON_ERROR);
$pages=[
    $page(1,2,[$row('FR','2025',68000000.5),$row('IR','2025',null)]),
    $page(2,2,[$row('PS','2025',5500000),$row('EU','2025',430000000)])
];
$rights=[
    'license'=>'synthetic-approval-for-test-only',
    'license_url'=>'https://data.worldbank.org/summary-terms-of-use',
    'reviewed_at'=>'2026-11-24',
    'redistribution_approved'=>true
];
$result=WorldBankOfflineImporter::import($pages,$indicator,'2026-11-24',$actors,$rights);
$registry=$result['registry'];
wp14($registry->count()===3 && $result['manifest']['api_pages']===2
    && $result['manifest']['reported_rows']===4
    && $result['manifest']['skipped_rows']===1,
    'complete two-page archive imported without inventing unknown aggregate');
wp14($registry->latest('state_fr','population_total')['value']===68000001,
    'fractional observation is deterministically rounded to integer population');
wp14($registry->latest('state_fr','population_total')['quality']==='estimated'
    && $registry->latest('state_fr','population_total')['license']===$rights['license'],
    'loss of precision is marked and source-specific redistribution remains explicit');
wp14($registry->latest('state_ir','population_total')['kind']==='missing'
    && $registry->latest('state_ir','population_total')['value']===null,
    'official API missing value preserved as missing, not fabricated');
wp14($registry->latest('state_ps','population_total')['kind']==='observed',
    'non-member observer-state economic record is not ignored');
wp14($result['diagnostics'][0]['code']==='country_not_in_curated_scenario',
    'WDI aggregate not misclassified as UN member country');
wp14($result['manifest']['snapshot_sha']===$registry->hash(),
    'immutable source manifest includes deterministic data snapshot SHA');
wp14(WorldBankOfflineImporter::import(array_reverse($pages),
    $indicator,'2026-11-24',$actors,$rights)['registry']->hash()===$registry->hash(),
    'page ordering does not change deterministic data snapshot');

wp14reject(fn()=>WorldBankOfflineImporter::import(
    [$pages[0]],$indicator,'2026-11-24',$actors,$rights
),'partial pagination is rejected rather than falsely claiming a complete snapshot');
wp14reject(fn()=>WorldBankOfflineImporter::import(
    [$pages[0],$pages[0]],$indicator,'2026-11-24',$actors,$rights
),'duplicate WDI page rejected');
$badRights=$rights;$badRights['redistribution_approved']=false;
wp14reject(fn()=>WorldBankOfflineImporter::import(
    $pages,$indicator,'2026-11-24',$actors,$badRights
),'unapproved redistribution rejected before any import');
$badRights=$rights;$badRights['reviewed_at']='2026-11-25';
wp14reject(fn()=>WorldBankOfflineImporter::import(
    $pages,$indicator,'2026-11-24',$actors,$badRights
),'rights approval from the future cannot be used in past snapshot');
wp14reject(fn()=>WorldBankOfflineImporter::import(
    $pages,'UNKNOWN.CODE','2026-11-24',$actors,$rights
),'unknown API series cannot be assigned fabricated economic meaning');
$wrongSeries=[
    $page(1,1,[$row('FR','2025',80000000,'NY.GDP.MKTP.CD')],1)
];
wp14reject(fn()=>WorldBankOfflineImporter::import(
    $wrongSeries,$indicator,'2026-11-24',$actors,$rights
),'mixed indicator pages rejected');
$duplicateCountry=[
    $page(1,1,[$row('FR','2025',100),$row('FR','2025',200)],2)
];
wp14reject(fn()=>WorldBankOfflineImporter::import(
    $duplicateCountry,$indicator,'2026-11-24',$actors,$rights
),'conflicting country-year observations rejected');
$negative=[
    $page(1,1,[$row('FR','2025',-100)],1)
];
wp14reject(fn()=>WorldBankOfflineImporter::import(
    $negative,$indicator,'2026-11-24',$actors,$rights
),'physically invalid negative population rejected');
$future=[
    $page(1,1,[$row('FR','2027',68000000)],1)
];
$f=WorldBankOfflineImporter::import($future,$indicator,'2026-11-24',$actors,$rights);
wp14($f['registry']->count()===0
    && $f['diagnostics'][0]['code']==='outside_historical_snapshot',
    'future observations omitted with a provenance diagnostic');
$gdp=[
    $page(1,1,[$row('FR','2025',1234567890123.8,'NY.GDP.MKTP.CD')],1)
];
$g=WorldBankOfflineImporter::import($gdp,'NY.GDP.MKTP.CD','2026-11-24',$actors,$rights);
wp14($g['registry']->latest('state_fr','gdp_current_usd')['value']===1234567890124
    && $g['registry']->latest('state_fr','population_total')===null,
    'GDP stored as USD observation, not mixed with population or bilateral trade');
echo "WP14 safe offline economic reference importer tests passed.\n";
