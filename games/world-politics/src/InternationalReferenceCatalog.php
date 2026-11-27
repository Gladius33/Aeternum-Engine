<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CountryCatalog.php';

/** WP115 immutable documentary reference ingestion. Not a world-state writer. */
final class InternationalReferenceCatalog
{
    public const VERSION='wp115-international-initial-v1';
    public const AS_OF='2026-11-27';
    private array $countries=[];
    private array $editions=[];
    private string $sha;

    public function __construct(?string $root=null,?string $expectedSha=null)
    {
        $root??=__DIR__.'/../data';
        $codes=array_values(array_diff(array_merge(CountryCatalog::EU_2026,['CH'],CountryCatalog::STRATEGIC_STARTER),['FR']));
        sort($codes,SORT_STRING);
        self::need(count($codes)===42,'country roster');
        $staging=$root.'/wp115-collection-v0.4';
        $manifest=$this->manifest($staging);
        self::need($manifest['country_iso2']===$codes,'archive country roster');
        $sources=$this->listed($staging,$manifest,'documentation/sources.json');
        $institution=$this->json($root.'/wp115-institutional-reference-v1.json');
        $this->editions['institutional-reference-v1']=hash_file('sha256',$root.'/wp115-institutional-reference-v1.json');
        self::need($institution['schema']==='wp115-institutional-reference-v1'&&$institution['retrieved_on']===self::AS_OF&&$institution['officeholder_observation_date']<self::AS_OF,'institution date');
        $institutionByCode=[];
        foreach($institution['countries'] as $r){
            $code=$r['iso2'];
            self::need(in_array($code,$codes,true)&&!isset($institutionByCode[$code])&&$r['runtime_admission']===false,'institution record');
            $o=$r['officeholders'];$e=$r['electoral_reference'];
            self::need(is_string($o['foreign_minister_name'])&&$o['foreign_minister_name']!==''&&is_int($e['source_year'])&&$e['source_year']<=2026&&in_array($e['system'],['List PR','AV','FPTP','MMP','Parallel','STV','No direct elections'],true),'institution detail');
            self::need(in_array($o['government_mode'],['same_person_as_head_of_state','collective_executive_not_single_prime_minister','separate_office'],true),'government mode');
            self::need(($o['head_of_state_name']!==null||($code==='JP'&&$o['head_of_state_label']==='Emperor of Japan'))&&($o['head_of_government_name']!==null||$code==='CH'),'officeholder identity');
            if($o['government_mode']==='same_person_as_head_of_state')self::need($o['head_of_state_name']===$o['head_of_government_name'],'same officeholder');
            $institutionByCode[$code]=$r;
        }
        ksort($institutionByCode,SORT_STRING);
        self::need(array_keys($institutionByCode)===$codes,'institution coverage');
        foreach($codes as $code){
            $c=$this->listed($staging,$manifest,'countries/'.$code.'.json');
            self::need($c['identity']['iso2']===$code&&$c['institutional_working_profile']['executable']===false&&$c['renewal_reference']['certifies_result']===false,'country classification');
            $this->countries[$code]=[
                'identity'=>$c['identity'],
                'institutional_working_profile'=>$c['institutional_working_profile'],
                'parliament_reference'=>$c['parliament_reference'],
                'renewal_reference'=>$c['renewal_reference'],
                'officeholder_reference'=>['as_of'=>$institution['officeholder_observation_date'],'source'=>$institution['sources']['officeholders'],'data'=>$institutionByCode[$code]['officeholders']],
                'electoral_system_reference'=>['source'=>$institution['sources']['electoral'],'data'=>$institutionByCode[$code]['electoral_reference']],
                'numeric'=>[],
                'unresolved_fields'=>[],
                'runtime_admission'=>false,
            ];
        }
        foreach(['numeric_all','supplementary_numeric','fiscal_monitor_2026_04'] as $table){
            $rows=$this->listed($staging,$manifest,'data/'.$table.'.json');
            foreach($rows as $r){
                $source=$sources[$r['source_id']]??throw new \DomainException('Unknown numeric source');
                $this->add($r['iso2'],[
                    'edition'=>'collection-v0.4','metric'=>$r['metric'],'year'=>$r['year'],'period'=>$r['period']??(string)$r['year'],
                    'value'=>$r['value'],'unit'=>$r['unit'],'classification'=>$r['kind'],
                    'source_id'=>$r['source_id'],'source_url'=>$source['url'],
                    'source_vintage'=>$r['vintage']??$source['vintage']??null,
                    'quality'=>$r['quality']??null,'note'=>$r['note']??null
                ]);
            }
        }
        foreach($this->listed($staging,$manifest,'data/gaps.json') as $g){
            self::need(isset($this->countries[$g['iso2']])&&$g['value']===null,'gap classification');
            $this->countries[$g['iso2']]['unresolved_fields'][]=[
                'field'=>$g['field'],'baseline_status'=>$g['status'],'required_evidence'=>$g['required_evidence_fr'],
            ];
        }
        foreach(['election_dates_reference','vat_reference'] as $table){
            $seen=[];
            foreach($this->listed($staging,$manifest,'data/'.$table.'.json') as $r){
                $code=$r['iso2'];
                self::need(isset($this->countries[$code])&&!isset($seen[$code]),'supplement country');
                $seen[$code]=true;
                $this->countries[$code][$table]=$r;
            }
            self::need(count($seen)===42,'supplement coverage');
        }
        foreach([
            'wp115-wdi-2026-11-27-v1'=>['wp115-wdi-evidence-v1',24,2632],
            'wp115-wdi-economic-social-2026-11-27-v2'=>['wp115-wdi-economic-social-v2',28,2112]
        ] as $edition=>$expected){
            $dir=$root.'/'.$edition;
            $m=$this->manifest($dir);
            $ev=$this->listed($dir,$m,'evidence.json');
            self::need($ev['schema']===$expected[0]&&$ev['country_iso2']===$codes&&$ev['years']===[2023,2024,2025]&&$ev['runtime_admission']===false&&$ev['transport_or_validation_errors']===[]&&count($ev['series'])===$expected[1],'WDI edition');
            $nonNull=0;
            foreach($ev['series'] as $indicator=>$series){
                self::need($series['indicator']===$indicator&&$series['source_url']==='https://data.worldbank.org/indicator/'.$indicator&&is_string($series['source_vintage'])&&$series['source_vintage']!=='','WDI provenance');
                $seen=[];
                foreach($series['rows'] as $r){
                    $k=$r['iso2'].':'.$r['year'];
                    self::need($r['indicator']===$indicator&&in_array($r['year'],[2023,2024,2025],true)&&!isset($seen[$k]),'WDI observation key');
                    $seen[$k]=true;
                    self::need($r['kind']===($r['value']===null?'missing':'published_historical_or_estimated'),'WDI observation class');
                    $nonNull+=(int)($r['value']!==null);
                    $this->add($r['iso2'],[
                        'edition'=>$edition,'metric'=>$r['metric'],'year'=>$r['year'],'period'=>(string)$r['year'],
                        'value'=>$r['value'],'unit'=>$r['unit'],'classification'=>$r['kind'],
                        'source_id'=>$indicator,'source_url'=>$series['source_url'],'source_vintage'=>$series['source_vintage'],
                        'quality'=>null,'note'=>null
                    ]);
                }
                self::need(count($seen)===126,'WDI series coverage');
            }
            self::need($nonNull===$expected[2],'WDI non-null count');
        }
        foreach($this->countries as &$c){
            ksort($c['numeric'],SORT_STRING);
            $c['numeric']=array_values($c['numeric']);
            self::need(count($c['unresolved_fields'])===22,'national gap count');
        }
        unset($c);
        ksort($this->editions,SORT_STRING);
        $this->sha=hash('sha256',json_encode(['version'=>self::VERSION,'editions'=>$this->editions],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
        if($expectedSha!==null)self::need(hash_equals($expectedSha,$this->sha),'pinned catalogue digest mismatch');
        self::need($this->summary()['numeric_non_null']===6413&&$this->summary()['numeric_slots']===8228,'catalogue counts');
    }

    private function add(string $code,array $r):void
    {
        self::need(isset($this->countries[$code])&&is_int($r['year'])&&$r['year']>=2023&&$r['year']<=2028&&is_string($r['metric'])&&$r['metric']!==''&&is_string($r['unit'])&&$r['unit']!==''&&is_string($r['source_url'])&&str_starts_with($r['source_url'],'https://'),'numeric record');
        $v=$r['value'];$k=$r['classification'];
        self::need(($v===null)===($k==='missing')&&($v===null||is_int($v)||(is_float($v)&&is_finite($v))),'numeric value or null');
        self::need(in_array($k,['observed','historical_estimate','projection','missing','published_reference','published_modelled_case','secondary_reference','published_estimate','historical_unverified_excerpt','projection_unverified_excerpt','published_historical_or_estimated'],true),'numeric classification');
        if($r['year']>2026)self::need(in_array($k,['projection','projection_unverified_excerpt','missing'],true),'future observation');
        $key=implode('|',[$r['edition'],$r['metric'],$r['year'],$r['period'],$r['source_id']]);
        self::need(!isset($this->countries[$code]['numeric'][$key]),'duplicate numeric record');
        $this->countries[$code]['numeric'][$key]=$r;
    }
    private function manifest(string $dir):array
    {
        $path=$dir.'/manifest.json';$m=$this->json($path);
        self::need(is_array($m['files'])&&count($m['files'])>0,'manifest');
        $this->editions[basename($dir)]=hash_file('sha256',$path);
        return $m;
    }
    private function listed(string $dir,array $manifest,string $name):array
    {
        $expected=$manifest['files'][$name]??null;
        self::need(is_string($expected)&&preg_match('/^[a-f0-9]{64}$/D',$expected)===1,'manifest path '.$name);
        $path=$dir.'/'.$name;
        $raw=$this->bytes($path);
        self::need(hash_equals($expected,hash('sha256',$raw)),'source checksum '.$name);
        return json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    }
    private function bytes(string $path):string
    {
        self::need(is_file($path)&&filesize($path)<=5000000,'reference unavailable or oversized');
        $s=file_get_contents($path);
        self::need(is_string($s),'reference read');
        return $s;
    }
    private function json(string $path):array{return json_decode($this->bytes($path),true,64,JSON_THROW_ON_ERROR);}
    private static function need(bool $ok,string $message):void{if(!$ok)throw new \DomainException('WP115: '.$message);}
    public function hash():string{return $this->sha;}
    public function country(string $iso2):array
    {
        if(!isset($this->countries[$iso2]))throw new \DomainException('Unknown WP115 country');
        return $this->countries[$iso2];
    }
    public function series(string $iso2,string $metric):array
    {
        return array_values(array_filter($this->country($iso2)['numeric'],static fn(array $r):bool=>$r['metric']===$metric));
    }
    public function summary():array
    {
        $slots=0;$nonnull=0;$gaps=0;
        foreach($this->countries as $c){
            $slots+=count($c['numeric']);$gaps+=count($c['unresolved_fields']);
            foreach($c['numeric'] as $r)$nonnull+=(int)($r['value']!==null);
        }
        return ['version'=>self::VERSION,'as_of'=>self::AS_OF,'snapshot_sha'=>$this->sha,'countries'=>array_keys($this->countries),'numeric_slots'=>$slots,'numeric_non_null'=>$nonnull,'unresolved_baseline_fields'=>$gaps,'runtime_admission'=>false,'editions'=>$this->editions];
    }
}
