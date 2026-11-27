<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Documentary registry only: no election admission, authority or policy compilation. */
final class PoliticalMovementReference
{
    public const VERSION='wp117-movement-reference-v1';
    public const AS_OF='2026-11-27';
    private const INPUTS=[
        '2026-11-25-un-members-v1.json'=>'c648d718cb4b87d6652e1ecad0bd5afcc31b7dc7',
        'wp115-ipu-party-seats-2026-11-27-v2.json'=>'fc5a828481e626e8cf08504f869dd3636f534a40',
        'wp115-national-election-supplement-v1.json'=>'35465e8601bf5fa5c3c6d892e41b360d30cf0dcf',
        'wp115-institutional-reference-v1.json'=>'3fab407ba4ac1bf8097baf03b93082ceb3336ab5',
        'wp114-direct-source-inventory.json'=>'ba31c17196fd09efbf19661cefd5f000fa6fc372',
    ];
    private array $countries=[],$organizations=[],$people=[],$sources=[],$doctrines=[],$affiliations=[],$relationships=[],$documents=[],$elections=[],$officeholders=[],$campaigns=[],$pins=[];
    private string $sha;
    public function __construct(?string $root=null,?string $expectedSha=null)
    {
        $root??=__DIR__.'/../data';
        $input=[];
        foreach(self::INPUTS as $name=>$blob)$input[$name]=$this->load($root,$name,$blob);
        $u=$input['2026-11-25-un-members-v1.json'];
        foreach($u['countries'] as $r){
            $code=$r['iso2'];
            self::need(is_string($code)&&preg_match('/^[A-Z]{2}$/D',$code)===1&&!isset($this->countries[$code]),'country');
            $this->countries[$code]=['identity'=>$r,'organization_ids'=>[],'election_observation_ids'=>[],'office_observation_ids'=>[],'campaign_reference_ids'=>[],'exhaustive'=>false,'runtime_admission'=>false];
        }
        self::need(count($this->countries)===195,'country roster');
        foreach(['wp115-ipu-party-seats-2026-11-27-v2.json','wp115-national-election-supplement-v1.json'] as $name){
            $d=$input[$name];
            self::need($d['as_of']===self::AS_OF&&$d['runtime_admission']===false,'election edition');
            foreach($d['countries'] as $r){
                $c=$r['iso2'];self::need(isset($this->countries[$c]),'election country');
                foreach($r['results'] as $e){
                    self::need($e['certifies_current_legislature']===false,'historical only');
                    foreach($e['parties'] as $p){
                        $label=$p['party_or_coalition'];
                        $id='electoral-'.hash('sha256',self::json([$c,$e['election_code'],$label]));
                        self::need(!isset($this->elections[$id])&&is_string($label)&&trim($label)!==''&&is_int($p['seats'])&&$p['seats']>=0,'election row');
                        $this->elections[$id]=['id'=>$id,'country'=>$c,'kind'=>'historical_electoral_label','label'=>$label,
                            'election_code'=>$e['election_code'],'observed_on'=>$e['source_election_date_identifier'],'seats'=>$p['seats'],
                            'source_url'=>$e['source_url'],'source_page_sha256'=>$e['source_page_sha256']??null,'source_edition'=>$name,
                            'organization_id'=>null,'current_membership_verified'=>false,'runtime_admission'=>false];
                        $this->countries[$c]['election_observation_ids'][]=$id;
                    }
                }
            }
        }
        self::need(count($this->elections)===1095,'frozen electoral row count');
        $d=$input['wp115-institutional-reference-v1.json'];
        foreach($d['countries'] as $r){
            $c=$r['iso2'];self::need(isset($this->countries[$c]),'office country');
            foreach(['head_of_state','head_of_government','foreign_minister'] as $role){
                $name=$r['officeholders'][$role.'_name'];if($name===null)continue;
                $id='office-observation-'.hash('sha256',self::json([$c,$role,$d['officeholder_observation_date'],$name]));
                self::need(!isset($this->officeholders[$id]),'office observation');
                $this->officeholders[$id]=['id'=>$id,'country'=>$c,'name'=>$name,'role'=>$role,'observed_on'=>$d['officeholder_observation_date'],
                    'source'=>$d['sources']['officeholders'],'person_id'=>null,'organization_id'=>null,'current_office_verified'=>false,'runtime_admission'=>false];
                $this->countries[$c]['office_observation_ids'][]=$id;
            }
        }
        $d=$input['wp114-direct-source-inventory.json'];
        foreach($d['people'] as $key=>$p){
            $id='campaign-source-fr-'.$key;
            $this->campaigns[$id]=['id'=>$id,'country'=>'FR','name'=>$p['name'],'source_url'=>$p['url'],
                'observed_on'=>$p['observed_on'],'election_year'=>$d['election_year'],'publication_state'=>$p['publication_state'],
                'person_id'=>null,'organization_id'=>null,'final_ballot_verified'=>false,'runtime_admission'=>false];
            $this->countries['FR']['campaign_reference_ids'][]=$id;
        }
        $s=$this->load($root,'wp117-movement-seed-v1.json',null);
        self::need($s['schema']==='wp117-movement-seed-v1'&&$s['as_of']===self::AS_OF&&$s['runtime_admission']===false&&$s['exhaustive']===false,'seed edition');
        foreach($s['sources'] as $id=>$r){
            self::id($id);self::need(is_string($r['url'])&&str_starts_with($r['url'],'https://')&&$r['reviewed_on']===self::AS_OF,'source');
            self::need(in_array($r['kind'],['institutional','secondary_directory','self_description','independent_press','academic_discovery'],true),'source kind');
            if($r['source_date']!==null)self::day($r['source_date']);
            self::need($r['source_bytes_sha256']===null||preg_match('/^[a-f0-9]{64}$/D',$r['source_bytes_sha256'])===1,'source hash');
            $this->sources[$id]=$r;
        }
        foreach($s['doctrines'] as $r){
            $id=$r['id'];self::id($id);self::need(!isset($this->doctrines[$id])&&$r['normative_rank']===null&&$r['kind']==='open_descriptive_vocabulary','doctrine');$this->doctrines[$id]=$r;
        }
        foreach($s['organizations'] as $r){
            $id=$r['id'];self::id($id);self::need(!isset($this->organizations[$id])&&isset($this->countries[$r['country']])&&$r['runtime_admission']===false&&$r['status']==='documented_identity_only','organization');
            self::need(in_array($r['kind'],['party','historical_party','movement','association','electoral_coalition','organization_body','advocacy_structure','union','employer_organization','ngo','media','other'],true),'organization kind');
            self::need(is_string($r['name'])&&trim($r['name'])!=='','organization name');$this->sourceList($r['source_ids']);
            foreach($r['doctrines'] as $a)self::need(isset($this->doctrines[$a['id']],$this->sources[$a['source']])&&in_array($a['source'],$r['source_ids'],true)&&$a['classification']==='attributed_descriptor','doctrine attribution');
            $this->organizations[$id]=$r;$this->countries[$r['country']]['organization_ids'][]=$id;
        }
        foreach($s['people'] as $r){
            $id=$r['id'];self::id($id);self::need(!isset($this->people[$id])&&isset($this->countries[$r['country']])&&is_string($r['name'])&&trim($r['name'])!=='','person');
            $this->sourceList($r['source_ids']);$this->people[$id]=$r+['runtime_admission'=>false];
        }
        foreach($s['affiliations'] as $r){
            $id=$r['id'];self::id($id);self::need(!isset($this->affiliations[$id])&&isset($this->people[$r['person']],$this->organizations[$r['organization']],$this->sources[$r['source']]),'affiliation links');
            self::day($r['observed_on']);foreach(['start','end'] as $k)if($r[$k]!==null)self::day($r[$k]);
            self::need(($r['start']===null||$r['start']<=$r['observed_on'])&&($r['end']===null||$r['observed_on']<=$r['end']),'affiliation dates');
            self::need(in_array($r['classification'],['institutional_observation','independent_observation','self_reported_observation'],true),'affiliation evidence');
            $this->affiliations[$id]=$r+['current_role_verified'=>false,'runtime_admission'=>false];
        }
        foreach($s['relationships'] as $r){
            $id=$r['id'];self::id($id);self::need(!isset($this->relationships[$id])&&$r['from']!==$r['to']&&isset($this->organizations[$r['from']],$this->organizations[$r['to']],$this->sources[$r['source']])&&$r['legal_continuity_verified']===false,'relationship');
            self::day($r['observed_on']);$this->relationships[$id]=$r;
        }
        foreach($s['documents'] as $r){
            $id=$r['id'];self::id($id);self::need(!isset($this->documents[$id])&&isset($this->organizations[$r['organization']],$this->sources[$r['source']])&&$r['runtime_admission']===false,'document');
            self::need(in_array($r['kind'],['programme','manifesto','statutes','doctrinal_text','institutional_project'],true)&&$r['content_status']==='section_index_only'&&$r['restricted_documentary']===true,'document scope');
            if($r['publication_date']!==null)self::day($r['publication_date']);
            self::need(array_is_list($r['section_ids'])&&count($r['section_ids'])===count(array_unique($r['section_ids'])),'section IDs');
            foreach($r['section_ids'] as $section)self::need(is_string($section)&&preg_match('/^[A-Z][0-9]{1,3}$/D',$section)===1,'section ID');
            $this->documents[$id]=$r;
        }
        ksort($this->pins,SORT_STRING);
        $this->sha=hash('sha256',self::json(['version'=>self::VERSION,'pins'=>$this->pins]));
        if($expectedSha!==null)self::need(hash_equals($expectedSha,$this->sha),'edition digest');
    }
    private function load(string $root,string $name,?string $blob):array
    {
        $path=$root.'/'.$name;
        self::need(is_file($path)&&filesize($path)<=8000000,'missing or oversized input');
        $bytes=file_get_contents($path);self::need(is_string($bytes),'input read');
        if($blob!==null)self::need(hash_equals($blob,sha1('blob '.strlen($bytes)."\0".$bytes)),'pinned input blob');
        $this->pins[$name]=hash('sha256',$bytes);
        $v=json_decode($bytes,true,64,JSON_THROW_ON_ERROR);self::need(is_array($v),'input object');return $v;
    }
    private static function json(array $a):string{return json_encode($a,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private static function need(bool $ok,string $why):void{if(!$ok)throw new \DomainException('WP117: '.$why);}
    private static function id(string $id):void{self::need(preg_match('/^[a-z][a-z0-9_-]{0,95}$/D',$id)===1,'identifier');}
    private static function day(string $s):void{$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$s,new \DateTimeZone('UTC'));self::need($d!==false&&$d->format('Y-m-d')===$s&&$s<=self::AS_OF,'documentary date');}
    private function sourceList(array $ids):void{self::need(array_is_list($ids)&&count($ids)>0&&count($ids)===count(array_unique($ids)),'source list');foreach($ids as $id)self::need(isset($this->sources[$id]),'unknown source');}
    public function pin():array{return ['version'=>self::VERSION,'sha256'=>$this->sha,'inputs'=>$this->pins,'runtime_admission'=>false];}
    public function summary():array
    {
        $covered=0;$labels=0;$offices=0;
        foreach($this->countries as $r){$covered+=(int)($r['organization_ids']!==[]||$r['election_observation_ids']!==[]);$labels+=(int)($r['election_observation_ids']!==[]);$offices+=(int)($r['office_observation_ids']!==[]);}
        return ['countries'=>count($this->countries),'countries_with_movement_evidence'=>$covered,'countries_with_electoral_labels'=>$labels,'countries_with_office_observations'=>$offices,'organizations'=>count($this->organizations),'electoral_observations'=>count($this->elections),'office_observations'=>count($this->officeholders),'campaign_sources'=>count($this->campaigns),'people'=>count($this->people),'affiliations'=>count($this->affiliations),'relationships'=>count($this->relationships),'doctrines'=>count($this->doctrines),'documents'=>count($this->documents),'exhaustive'=>false,'runtime_admission'=>false];
    }
    public function country(string $code):array
    {
        $r=$this->countries[$code]??throw new \DomainException('Unknown reference country');
        return $r+['coverage'=>$r['organization_ids']!==[]||$r['election_observation_ids']!==[]?'partial_documentary_evidence':'no_movement_evidence',
            'current_roster_verified'=>false,'programme_completeness_verified'=>false];
    }
    /** Bounded read-only pagination; IDs sorted bytewise, no fuzzy identity merge. */
    public function page(string $kind,?string $country=null,?string $after=null,int $limit=100):array
    {
        self::need($limit>=1&&$limit<=100&&($country===null||isset($this->countries[$country])),'page scope');
        $map=match($kind){'organizations'=>$this->organizations,'people'=>$this->people,'electoral_observations'=>$this->elections,'office_observations'=>$this->officeholders,'campaign_sources'=>$this->campaigns,'doctrines'=>$this->doctrines,'affiliations'=>$this->affiliations,'relationships'=>$this->relationships,'documents'=>$this->documents,'sources'=>$this->sources,default=>throw new \DomainException('Unknown reference kind')};
        if($country!==null)self::need(in_array($kind,['organizations','people','electoral_observations','office_observations','campaign_sources'],true),'unsupported country filter');
        if($country!==null)$map=array_filter($map,fn($r)=>$r['country']===$country);
        ksort($map,SORT_STRING);
        if($after!==null)self::need(isset($map[$after]),'unknown cursor');
        $out=[];$next=null;
        foreach($map as $id=>$r){if($after!==null&&strcmp($id,$after)<=0)continue;if(count($out)===$limit){$next=$out[count($out)-1]['id'];break;}$out[]=['id'=>$id]+$r;}
        return ['edition_sha256'=>$this->sha,'kind'=>$kind,'country'=>$country,'rows'=>$out,'next'=>$next,'runtime_admission'=>false];
    }
}
