<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Immutable documentary data. Secondary sources are usable as gameplay
 * references at their actual historical date; never relabelled as 2027. */
final class NationalReferenceFallback
{
    public const VERSION='wp130-latest-available-reference-v1';
    private const FILES=[
        'wp130-national-economy-reference-v1.json'=>'fc951675740ba80b5b6e0b5df8a29ce969f4698f',
        'wp130-historical-resource-rents-staging-v1.json'=>'36a879c3d35fc782aa2d07c7cbef8c9a61016a29',
        'wp130-historical-social-services-staging-v1.json'=>'18332733480d6280c38d3389d1a24d5c75910a00',
        'wp130-multisource-gap-supplement-v1.json'=>'35d061c2bea4f0650295b0ef30d1015ac29e2312',
        'wp130-historical-economic-gap-supplement-v1.json'=>'9ac663bafdf84205b6bb800cfa36e9002f604d68',
        'wp130-fiscal-debt-gap-supplement-v1.json'=>'2e9fbc40ffc7696136b31b0967fe1f3a3659c171',
        'wp130-final-coverage-supplement-v1.json'=>'e71744cc6196a4e420919fa137a67a827df96b65',
        'wp130-archival-national-completion-v1.json'=>'ca85aa11102971a0173ea60991b8447a68c5498a',
        'wp130-archival-debt-extension-v1.json'=>'341eb636ca3e9596e28230bb794f96ff90dd99be'];
    private static function load(string $file):array{
        static $cache=[];
        if(isset($cache[$file]))return $cache[$file];
        $raw=file_get_contents(__DIR__.'/../data/'.$file);
        if(!is_string($raw)||!hash_equals(self::FILES[$file],sha1('blob '.strlen($raw)."\0".$raw)))
            throw new \DomainException('Unpinned reference supplement');
        return $cache[$file]=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    }
    private static function entry(string $id,string $unit,int $year,int|float|null $value,string $publisher,string $url,string $retrieved,string $quality,?string $mirror=null):array{
        if($year<1900||$year>2026||($value!==null&&!is_finite((float)$value)))throw new \DomainException('Invalid reference value');
        return ['value'=>$value,'year'=>$year,'data_year'=>$value===null?null:$year,'indicator'=>$id,'unit'=>$unit,
            'kind'=>$value===null?'missing':'published_historical_or_estimated','source_name'=>'World Bank — World Development Indicators',
            'source_url'=>$url,'publisher'=>$publisher,'retrieved_on'=>$retrieved,'quality'=>$value===null?'missing':$quality,'secondary_source_url'=>$mirror];
    }
    public static function country(string $cc):array{
        $base=self::load('wp130-national-economy-reference-v1.json');
        $rent=self::load('wp130-historical-resource-rents-staging-v1.json');
        $social=self::load('wp130-historical-social-services-staging-v1.json');
        if(!isset($base['countries'][$cc])&&!array_key_exists($cc,$rent['countries']))throw new \DomainException('Unknown national reference');
        $out=[];
        foreach($base['countries'][$cc]['observations']??[] as $id=>[$year,$value]){
            $m=$base['series'][$id];$out[$m['metric']]=self::entry($id,$m['unit'],$year,$value,$m['publisher'],$m['source_url'],$base['as_of'],'frozen_wdi_reference');
        }
        $id=$rent['indicator'];
        if(array_key_exists($cc,$rent['countries'])){
            $m=$base['series'][$id];$key=$m['metric'];
            if(!isset($out[$key])||$out[$key]['value']===null||$out[$key]['year']<$rent['year'])
                $out[$key]=self::entry($id,$rent['unit'],$rent['year'],$rent['countries'][$cc],$m['publisher'],$rent['primary_metadata_url'],$rent['retrieved_on'],'secondary_transcription_not_primary_reconciled',$rent['transcription_source_url']);
        }
        foreach($social['series'] as $id=>$s){
            $m=$base['series'][$id];$key=$m['metric'];
            foreach($s['rows'] as $r)if($r['iso2']===$cc&&(!isset($out[$key])||$out[$key]['value']===null||$out[$key]['year']<$r['year'])){
                $out[$key]=self::entry($id,$s['unit'],$r['year'],$r['value'],$m['publisher'],$s['primary_metadata_url'],$social['retrieved_on'],'secondary_transcription_not_primary_reconciled',$s['secondary_sources'][0]);
            }
        }
        foreach(['wp130-multisource-gap-supplement-v1.json','wp130-historical-economic-gap-supplement-v1.json','wp130-fiscal-debt-gap-supplement-v1.json','wp130-final-coverage-supplement-v1.json','wp130-archival-national-completion-v1.json','wp130-archival-debt-extension-v1.json'] as $supplement){
        $extra=self::load($supplement);
        foreach($extra['rows'] as $r){
            if($r['country']!==$cc)continue;
            $m=$base['series'][$r['indicator']];$key=$m['metric'];
            if($r['unit']!==$m['unit'])throw new \DomainException('Supplement unit mismatch');
            if(!isset($out[$key])||$out[$key]['value']===null||$out[$key]['year']<$r['year']){
                $entry=self::entry($r['indicator'],$r['unit'],$r['year'],$r['value'],$r['publisher'],$r['source_url'],$extra['retrieved_on'],$r['quality'],$r['secondary_source_url']);
                $entry['source_name']=$r['source_name'];$entry['source_indicator']=$r['source_indicator'];
                if(isset($r['data_period']))$entry['data_period']=$r['data_period'];
                if(isset($r['kind']))$entry['kind']=$r['kind'];
                $out[$key]=$entry;
            }
        }
        }
        $final=self::load('wp130-fiscal-debt-gap-supplement-v1.json');$alternatives=[];
        foreach($final['alternatives'] as $r)if($r['country']===$cc)$alternatives[]=$r;
        $closing=self::load('wp130-final-coverage-supplement-v1.json');
        foreach($closing['alternatives'] as $r)if($r['country']===$cc)$alternatives[]=$r;
        $archive=self::load('wp130-archival-national-completion-v1.json');
        foreach($archive['alternatives'] as $r)if($r['country']===$cc)$alternatives[]=$r;
        $extension=self::load('wp130-archival-debt-extension-v1.json');
        foreach($extension['alternatives'] as $r)if($r['country']===$cc)$alternatives[]=$r;
        foreach($extension['unresolved'] as $r)if($r['country']===$cc&&isset($out[$r['metric']])&&$out[$r['metric']]['value']===null)$out[$r['metric']]['availability']=$r;
        return ['version'=>self::VERSION,'country'=>$cc,'observations'=>$out,'alternative_references'=>$alternatives,'reference_blobs'=>self::FILES,
            'coverage'=>['available'=>count(array_filter($out,fn($r)=>$r['value']!==null)),'slots'=>count($out)],
            'classification'=>'documentary_reference_not_2027_observation','rankable'=>false];
    }
}
