<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,ScoreProjector,ScoreRules};

$m15Path=__DIR__.'/../games/reference-guild/versions/0.2.0/manifest.json';
$m15Bytes=file_get_contents($m15Path);
$m15Pack=GamePackLoader::load($m15Bytes);
$m15Old=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-guild/manifest.json'));
check($m15Pack->progression instanceof ScoreRules
    && $m15Pack->version==='0.2.0'
    && $m15Old->progression===null,
    'M15 opt-in scores use a new pinned pack version, never silently upgrading historical worlds');
check($m15Pack->digest===hash('sha256',$m15Bytes)
    && $m15Pack->rules()->key!==$m15Old->rules()->key,
    'M15 scoring manifest is pinned by immutable artifact identity');
$m15Projector=new ScoreProjector($m15Pack->progression);
$m15State=[
    'build:max:player:market_hall'=>1,
    'build:max:player:watchtower'=>1,
    'econ:player:silver:level'=>1,
    'market:once:status'=>'filled',
    'market:once:seller'=>'player',
    'market:once:buyer'=>'other',
    'market:ignored:status'=>'cancelled',
    'market:ignored:seller'=>'player',
    'market:ignored:buyer'=>'other',
];
check($m15Projector->forAccount($m15State,'player')===[
    'development'=>26,'military'=>30,'global'=>56
], 'M15 completed buildings, completed trades and upgrades create deterministic integer score');
check($m15Projector->forAccount($m15State,'other')===[
    'development'=>6,'military'=>0,'global'=>6
], 'M15 same completed trade scores each participating actor once');
check($m15Projector->forAccount($m15State,'stranger')===[
    'development'=>0,'military'=>0,'global'=>0
], 'M15 irrelevant actors cannot inherit scores from other world accounts');
check($m15Projector->forAccount($m15State,'player')===$m15Projector->forAccount($m15State,'player'),
    'M15 score rebuilding is pure and idempotent; no counters modified by reads');
rejects(fn()=> $m15Projector->forAccount(array_replace($m15State,
    ['build:max:player:market_hall'=>'1']),'player'),UnexpectedValueException::class,
    'M15 rejects strings masquerading as integer progression levels');
rejects(fn()=> $m15Projector->forAccount(array_replace($m15State,
    ['econ:player:silver:level'=>101]),'player'),UnexpectedValueException::class,
    'M15 rejects out-of-bounds upgrade levels');
rejects(fn()=> $m15Projector->forAccount(array_fill_keys(range(0,100000),0),'player'),
    DomainException::class,'M15 scoring bounded by the canonical state size');
$m15Manifest=json_decode($m15Bytes,true,32,JSON_THROW_ON_ERROR);
$m15Bad=$m15Manifest;$m15Bad['progression']['building_points']['unsupported']=1;
rejects(fn()=>GamePackLoader::load(json_encode($m15Bad,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M15 rejects unknown building score weights');
$m15Bad=$m15Manifest;$m15Bad['progression']['trade_points']='6';
rejects(fn()=>GamePackLoader::load(json_encode($m15Bad,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M15 rejects numeric strings in score configuration');
$m15Bad=$m15Manifest;$m15Bad['progression']['trade_points']=-5;
rejects(fn()=>GamePackLoader::load(json_encode($m15Bad,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M15 rejects negative scoring rewards');
$m15Bad=$m15Manifest;$m15Bad['progression']['building_points']=[];
rejects(fn()=>GamePackLoader::load(json_encode($m15Bad,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M15 score weights must be JSON objects, not arrays');
$m15Bad=$m15Manifest;$m15Bad['progression']['execute']='code';
rejects(fn()=>GamePackLoader::load(json_encode($m15Bad,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M15 manifests cannot inject scoring formula code');
$m15Bad=$m15Manifest;unset($m15Bad['progression']);
check(GamePackLoader::load(json_encode($m15Bad,JSON_THROW_ON_ERROR))->progression===null,
    'M15 without progression retains the no-scoring default');
