<?php
declare(strict_types=1);
use Aeternum\Core\{GamePackLoader,EconomyCommandHandler,RuleRegistry};
$medievalBytes = file_get_contents(__DIR__.'/../games/reference-medieval/manifest.json');
$industrialBytes = file_get_contents(__DIR__.'/../games/reference-industrial/manifest.json');
$medieval = GamePackLoader::load($medievalBytes);
$industrial = GamePackLoader::load($industrialBytes);
check($medieval->id==='reference-medieval' && $industrial->id==='reference-industrial','two distinct data-only game packs load');
check($medieval->digest===hash('sha256',$medievalBytes) && $medieval->rules()->key!==$industrial->rules()->key,'rule key derives from exact artifact bytes and identity');
check($medieval->rules()->key===GamePackLoader::load($medievalBytes)->rules()->key,'pack identity stable across load');
check($medieval->rules()->key!==GamePackLoader::load($medievalBytes."\n")->rules()->key,'modified artifact bytes change rule identity');
// A third non-themed game is an entirely declarative pack: its resource
// production and building plans need no new engine branches.
$guild=GamePackLoader::load(file_get_contents(__DIR__.'/../games/reference-guild/manifest.json'));
check($guild->id==='reference-guild' && $guild->playerHomestead
    && count($guild->buildings)===2 && $guild->settlement===null && $guild->industry===null,
    'M14 third game is data-only and uses generic construction onboarding');
check(!$medieval->playerHomestead && !$industrial->playerHomestead,
    'M14 historical manifests keep the original opt-in onboarding behavior');
$badGuild=json_decode(file_get_contents(__DIR__.'/../games/reference-guild/manifest.json'),true,32,JSON_THROW_ON_ERROR);
$badGuild['player_homestead']='yes';
rejects(fn()=>GamePackLoader::load(json_encode($badGuild,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M14 homestead policy rejects string truthiness');
$badGuild['player_homestead']=true;
$badGuild['buildings']=[];
rejects(fn()=>GamePackLoader::load(json_encode($badGuild,JSON_THROW_ON_ERROR)),
    InvalidArgumentException::class,'M14 homestead without building definitions is denied');

$registry = new RuleRegistry();
$mr=$medieval->rules(); $ir=$industrial->rules(); $registry->register($mr); $registry->register($ir);
check($registry->get($mr->key)->commands instanceof EconomyCommandHandler,'pack uses shared economy engine, not theme code');
rejects(fn()=>GamePackLoader::load('{'),JsonException::class,'malformed JSON rejected');
rejects(fn()=>GamePackLoader::load(str_repeat(' ',GamePackLoader::MAX_BYTES+1)),InvalidArgumentException::class,'oversize manifest rejected');
$base=json_decode($medievalBytes,true,32,JSON_THROW_ON_ERROR);
$bad=$base; $bad['engine_api']='2';
rejects(fn()=>GamePackLoader::load(json_encode($bad,JSON_THROW_ON_ERROR)),InvalidArgumentException::class,'incompatible API rejected');
$bad=$base; $bad['script']='not allowed';
rejects(fn()=>GamePackLoader::load(json_encode($bad,JSON_THROW_ON_ERROR)),InvalidArgumentException::class,'unknown manifest field rejected');
$bad=$base; $bad['resources'][0]['amount']=1.5;
rejects(fn()=>GamePackLoader::load(json_encode($bad,JSON_THROW_ON_ERROR)),InvalidArgumentException::class,'non-integer rate rejected');
$bad=$base; $bad['resources'][]=$bad['resources'][0];
rejects(fn()=>GamePackLoader::load(json_encode($bad,JSON_THROW_ON_ERROR)),InvalidArgumentException::class,'duplicate resource definition rejected');
$bad=$base; $bad['upgrades'][0]['resource']='nonexistent';
rejects(fn()=>GamePackLoader::load(json_encode($bad,JSON_THROW_ON_ERROR)),InvalidArgumentException::class,'unknown upgrade resource rejected');
$bad=$base; $bad['resources'][0]['initial_stock']=1001;
rejects(fn()=>GamePackLoader::load(json_encode($bad,JSON_THROW_ON_ERROR)),InvalidArgumentException::class,'initial stock above capacity rejected');
$bad=$base; $bad['upgrades'][0]['duration_seconds']='120';
rejects(fn()=>GamePackLoader::load(json_encode($bad,JSON_THROW_ON_ERROR)),InvalidArgumentException::class,'numeric string rejected');
