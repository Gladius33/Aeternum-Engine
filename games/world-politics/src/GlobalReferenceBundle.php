<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CountryCatalog.php';
require_once __DIR__.'/WorldBankOfflineImporter.php';

/** WP31: fixed reviewed geopolitical universe; WDI archives are separately licensed. */
final class GlobalReferenceBundle
{
    public const DATE='2026-11-25';
    public readonly CountryCatalog $countries;
    public readonly GeopoliticalActorRegistry $actors;
    public readonly EconomicReferenceRegistry $references;
    public readonly array $manifest;
    private readonly array $records;
    public function __construct(?array $pack=null)
    {
        $catalog=json_decode(file_get_contents(__DIR__.'/../data/2026-11-25-un-members-v1.json'),true,32,JSON_THROW_ON_ERROR);
        $rows=[];
        foreach ($catalog['countries'] as $r) {
            $rows[]=$r+['as_of'=>$catalog['as_of'],'source'=>$r['un_member']?$catalog['membership_source']:$catalog['observer_source']];
        }
        $this->countries=new CountryCatalog($rows);
        $this->countries->assertGlobalCoverage();
        if (count($rows)!==195 || array_column(array_filter($rows,fn($r)=>!$r['un_member']),'iso2')!==['PS','VA'])
            throw new \DomainException('Expected exactly two UN observer states');
        $special=json_decode(file_get_contents(__DIR__.'/../data/2026-11-24-special-actors.json'),true,32,JSON_THROW_ON_ERROR);
        $entities=[];foreach ($special['entities'] as $r) $entities[$r['id']]=$r;
        foreach ($this->countries->all() as $r) {
            $code=$r['iso2'];$id='state_'.strtolower($code);
            if (isset($entities[$id])) continue;
            $entities[$id]=['id'=>$id,'label'=>$r['name'],'iso2'=>$code,'kind'=>'state',
                'un_status'=>$r['un_member']?'un_member':'non_member_observer_state','parent_id'=>null,
                'controller'=>$r['control_policy'],'representation'=>'international_state_actor',
                'sources'=>[$r['source'],$catalog['code_source']],'strategic_programmes'=>[]];
        }
        $this->actors=new GeopoliticalActorRegistry(['version'=>$catalog['version'],'observation_date'=>self::DATE,
            'disclaimer'=>'UN membership metadata; eligibility is a game rule, not actual political control.',
            'entities'=>array_values($entities)]);
        $asOf=$pack['as_of']??self::DATE;
        if ($asOf>self::DATE) throw new \DomainException('Observation postdates scenario start');
        $this->references=new EconomicReferenceRegistry($asOf,$pack['records']??[],$this->actors);
        $this->records=$this->references->all();
        $series=$pack['series']??[];
        if (!is_array($series) || !array_is_list($series) || count($series)>2) throw new \DomainException('Invalid WDI provenance manifest');
        $mapped=['SP.POP.TOTL'=>'population_total','NY.GDP.MKTP.CD'=>'gdp_current_usd'];
        $seen=[];$counts=[];
        foreach ($series as $m) {
            if (!is_array($m)||!isset($mapped[$m['series']??''])||isset($seen[$m['series']])
                || ($m['as_of']??null)!==$asOf || ($m['redistribution_approved']??false)!==true
                || ($m['provider']??null)!=='World Bank API v2' || !is_string($m['license']??null)
                || !is_array($m['page_sha256']??null)||$m['page_sha256']===[])
                throw new \DomainException('Invalid imported series evidence');
            foreach($m['page_sha256'] as $sha) if (!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)) throw new \DomainException('Invalid archive digest');
            $seen[$m['series']]=$m;$counts[$mapped[$m['series']]]=0;
        }
        foreach ($this->records as $r) {
            if (!isset($counts[$r['metric']]) || !in_array($r['kind'],['observed','missing'],true))
                throw new \DomainException('Unattributed global economic record');
            $counts[$r['metric']]++;
        }
        foreach ($seen as $id=>$m) {
            $subset=array_values(array_filter($this->records,fn($r)=>$r['metric']===$mapped[$id]));
            $reg=new EconomicReferenceRegistry($asOf,$subset,$this->actors);
            if ($counts[$mapped[$id]]!==$m['imported_rows'] || !hash_equals($m['snapshot_sha'],$reg->hash()))
                throw new \DomainException('WDI series snapshot mismatch');
        }
        ksort($seen,SORT_STRING);
        $this->manifest=['as_of'=>$asOf,'catalog_version'=>$catalog['version'],'actors_sha'=>$this->actors->hash(),
            'reference_sha'=>$this->references->hash(),'un_members'=>193,'observer_states'=>2,
            'governing_authorities'=>count($this->actors->all())-195,'series'=>array_values($seen),
            'observations'=>count(array_filter($this->records,fn($r)=>$r['kind']==='observed')),
            'explicit_missing'=>count(array_filter($this->records,fn($r)=>$r['kind']==='missing'))];
    }
    public function payload():array {return ['as_of'=>$this->manifest['as_of'],'records'=>$this->records,'series'=>$this->manifest['series']];}
    public function sha():string {return hash('sha256',self::json([$this->manifest,$this->payload()]));}
    public static function json(array $a):string{return json_encode($a,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    /** Trusted local archive ingestion. No arbitrary network URLs or player uploads. */
    public static function import(array $archives,string $asOf):self
    {
        $base=new self();$records=[];$manifests=[];
        if ($archives===[] || count($archives)>2) throw new \InvalidArgumentException('Select one or two supported WDI series');
        foreach ($archives as $series=>$archive) {
            $r=WorldBankOfflineImporter::import($archive['pages'],$series,$asOf,$base->actors,$archive['rights']);
            $records=[...$records,...$r['registry']->all()];
            $m=$r['manifest'];$m['page_sha256']=array_map(fn($p)=>hash('sha256',$p),$archive['pages']);
            sort($m['page_sha256'],SORT_STRING);$manifests[]=$m;
        }
        return new self(['as_of'=>$asOf,'records'=>$records,'series'=>$manifests]);
    }
    public function coverage():array
    {
        $counts=['population_total'=>['observed'=>0,'missing'=>0,'absent'=>0],'gdp_current_usd'=>['observed'=>0,'missing'=>0,'absent'=>0]];
        $latest=[];foreach ($this->records as $r) {
            $key=$r['actor_id'].'/'.$r['metric'];
            if (!isset($latest[$key])||$latest[$key]['year']<$r['year']) $latest[$key]=$r;
        }
        foreach ($this->countries->all() as $c) foreach (array_keys($counts) as $metric) {
            $r=$latest['state_'.strtolower($c['iso2']).'/'.$metric]??null;
            $counts[$metric][$r===null?'absent':$r['kind']]++;
        }
        return $counts;
    }
}

final class PdoGlobalReferenceBundles
{
    public function __construct(private readonly \PDO $db) {}
    public function install(GlobalReferenceBundle $bundle):string
    {
        $sha=$bundle->sha();$json=GlobalReferenceBundle::json($bundle->payload());
        $q=$this->db->prepare('INSERT INTO wp_reference_bundles(bundle_sha,payload_json) VALUES(?,?) ON DUPLICATE KEY UPDATE bundle_sha=bundle_sha');
        $q->execute([$sha,$json]);$this->load($sha);
        return $sha;
    }
    public function load(string $sha):GlobalReferenceBundle
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$sha)) throw new \InvalidArgumentException('Invalid reference bundle id');
        $q=$this->db->prepare('SELECT payload_json FROM wp_reference_bundles WHERE bundle_sha=?');
        $q->execute([$sha]);$json=$q->fetchColumn();
        if ($json===false) throw new \DomainException('Unknown reference bundle');
        $bundle=new GlobalReferenceBundle(json_decode($json,true,64,JSON_THROW_ON_ERROR));
        if (!hash_equals($sha,$bundle->sha())) throw new \UnexpectedValueException('Reference bundle changed');
        return $bundle;
    }
}
