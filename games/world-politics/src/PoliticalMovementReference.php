<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;

/** Documentary registry only: no election admission, authority or policy compilation. */
final class PoliticalMovementReference
{
    public const VERSION='wp117-movement-reference-v6';
    public const AS_OF='2026-11-27';
    private const INPUTS=[
        '2026-11-25-un-members-v1.json'=>'c648d718cb4b87d6652e1ecad0bd5afcc31b7dc7',
        'wp115-ipu-party-seats-2026-11-27-v2.json'=>'fc5a828481e626e8cf08504f869dd3636f534a40',
        'wp115-national-election-supplement-v1.json'=>'35465e8601bf5fa5c3c6d892e41b360d30cf0dcf',
        'wp115-institutional-reference-v1.json'=>'3fab407ba4ac1bf8097baf03b93082ceb3336ab5',
        'wp114-direct-source-inventory.json'=>'ba31c17196fd09efbf19661cefd5f000fa6fc372',
        'wp117-partyfacts-2023-v1/core-parties.json'=>'f75d79ede7a4840b5be654b5a29fecc8cce39fe9',
        'wp117-partyfacts-2023-v1/external-crosswalks.json'=>'09cb41db2b6c6b138bdd0c5a72b8965e1e6be67f',
        'wp117-cnccfp-2024-v1/parties.json'=>'a817286e2e8cd5da4ce5e5fb733d0fca5d5754ad',
    ];
    private array $countries=[],$organizations=[],$people=[],$sources=[],$doctrines=[],$affiliations=[],$relationships=[],$documents=[],$elections=[],$officeholders=[],$campaigns=[],$pins=[];
    private array $partyfacts=[],$cnccfp=[],$crosswalks=[],$labelCorrespondences=[],$coverageAudit=[],$academicCrosswalks=[],$academicCountryCounts=[],$academicDatasetCounts=[],$academicStatusCounts=[];
    private const ISO3_TO_ISO2='AFG:AF ALB:AL DZA:DZ AND:AD AGO:AO ATG:AG ARG:AR ARM:AM AUS:AU AUT:AT AZE:AZ BHS:BS BHR:BH BGD:BD BRB:BB BLR:BY BEL:BE BLZ:BZ BEN:BJ BTN:BT BOL:BO BIH:BA BWA:BW BRA:BR BRN:BN BGR:BG BFA:BF BDI:BI CPV:CV KHM:KH CMR:CM CAN:CA CAF:CF TCD:TD CHL:CL CHN:CN COL:CO COM:KM COG:CG COD:CD CRI:CR CIV:CI HRV:HR CUB:CU CYP:CY CZE:CZ DNK:DK DJI:DJ DMA:DM DOM:DO ECU:EC EGY:EG SLV:SV GNQ:GQ ERI:ER EST:EE SWZ:SZ ETH:ET FJI:FJ FIN:FI FRA:FR GAB:GA GMB:GM GEO:GE DEU:DE GHA:GH GRC:GR GRD:GD GTM:GT GIN:GN GNB:GW GUY:GY HTI:HT HND:HN HUN:HU ISL:IS IND:IN IDN:ID IRN:IR IRQ:IQ IRL:IE ISR:IL ITA:IT JAM:JM JPN:JP JOR:JO KAZ:KZ KEN:KE KIR:KI PRK:KP KOR:KR KWT:KW KGZ:KG LAO:LA LVA:LV LBN:LB LSO:LS LBR:LR LBY:LY LIE:LI LTU:LT LUX:LU MDG:MG MWI:MW MYS:MY MDV:MV MLI:ML MLT:MT MHL:MH MRT:MR MUS:MU MEX:MX FSM:FM MDA:MD MCO:MC MNG:MN MNE:ME MAR:MA MOZ:MZ MMR:MM NAM:NA NRU:NR NPL:NP NLD:NL NZL:NZ NIC:NI NER:NE NGA:NG MKD:MK NOR:NO OMN:OM PAK:PK PLW:PW PAN:PA PNG:PG PRY:PY PER:PE PHL:PH POL:PL PRT:PT QAT:QA ROU:RO RUS:RU RWA:RW KNA:KN LCA:LC VCT:VC WSM:WS SMR:SM STP:ST SAU:SA SEN:SN SRB:RS SYC:SC SLE:SL SGP:SG SVK:SK SVN:SI SLB:SB SOM:SO ZAF:ZA SSD:SS ESP:ES LKA:LK SDN:SD SUR:SR SWE:SE CHE:CH SYR:SY TJK:TJ TZA:TZ THA:TH TLS:TL TGO:TG TON:TO TTO:TT TUN:TN TUR:TR TKM:TM TUV:TV UGA:UG UKR:UA ARE:AE GBR:GB USA:US URY:UY UZB:UZ VUT:VU VEN:VE VNM:VN YEM:YE ZMB:ZM ZWE:ZW PSE:PS VAT:VA';
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
            $this->countries[$code]=['identity'=>$r,'organization_ids'=>[],'election_observation_ids'=>[],'office_observation_ids'=>[],'campaign_reference_ids'=>[],'partyfacts_ids'=>[],'cnccfp_ids'=>[],'exhaustive'=>false,'runtime_admission'=>false];
        }
        self::need(count($this->countries)===195,'country roster');
        $this->loadPartyFacts($input['wp117-partyfacts-2023-v1/core-parties.json']);
        $this->loadAcademicCrosswalks($input['wp117-partyfacts-2023-v1/external-crosswalks.json']);
        unset($input['wp117-partyfacts-2023-v1/external-crosswalks.json']);
        $this->loadCnccfp($input['wp117-cnccfp-2024-v1/parties.json']);
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
        $s=$this->load($root,'wp117-movement-seed-v1.json','cd3cb5d13e1c339e3a0bc182ba581627e30c86a5');
        self::need($s['schema']==='wp117-movement-seed-v1'&&$s['as_of']===self::AS_OF&&$s['runtime_admission']===false&&$s['exhaustive']===false,'seed edition');
        $supp=$this->load($root,'wp117-national-register-supplement-v1.json','014b798a82b93176e9ab83ad4f19be7da55a2fb0');
        self::need($supp['schema']==='wp117-national-register-supplement-v1'&&$supp['as_of']===self::AS_OF&&$supp['runtime_admission']===false&&$supp['exhaustive']===false,'supplement edition');
        self::need(array_intersect_key($s['sources'],$supp['sources'])===[],'duplicate supplement source');
        $s['sources']+=$supp['sources'];
        foreach(['organizations','people','affiliations','documents'] as $key)$s[$key]=array_merge($s[$key],$supp[$key]);
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
            if(isset($r['registration_observation'])){
                $reg=$r['registration_observation'];self::day($reg['registered_on']);self::day($reg['observed_on']);
                self::need($reg['registered_on']<=$reg['observed_on']&&$reg['observed_on']===self::AS_OF&&in_array($reg['source'],$r['source_ids'],true)&&$this->sources[$reg['source']]['kind']==='institutional'&&$reg['classification']==='listed_as_registered_at_review','registration observation');
            }
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
            self::need(in_array($r['kind'],['programme','manifesto','statutes','doctrinal_text','institutional_project'],true)&&in_array($r['content_status'],['section_index_only','catalogue_reference_only'],true)&&$r['restricted_documentary']===true,'document scope');
            if($r['publication_date']!==null)self::day($r['publication_date']);
            self::need(array_is_list($r['section_ids'])&&count($r['section_ids'])===count(array_unique($r['section_ids'])),'section IDs');
            self::need($r['content_status']!=='catalogue_reference_only'||($r['kind']==='statutes'&&$r['section_ids']===[]&&$this->sources[$r['source']]['kind']==='institutional'),'catalogue scope');
            foreach($r['section_ids'] as $section)self::need(is_string($section)&&preg_match('/^[A-Z][0-9]{1,3}$/D',$section)===1,'section ID');
            $this->documents[$id]=$r;
        }
        foreach($supp['crosswalks'] as $r){
            $id=$r['id'];self::id($id);
            $ref='cnccfp-'.hash('sha256',$r['cnccfp_source_id']);
            self::need(!isset($this->crosswalks[$id])&&isset($this->organizations[$r['organization_id']],$this->cnccfp[$ref])&&$r['country']==='FR'&&$this->organizations[$r['organization_id']]['country']===$r['country']&&in_array($r['source_label'],$this->cnccfp[$ref]['names'],true)&&$r['classification']==='reviewed_name_correspondence_only'&&$r['legal_identity_verified']===false&&$r['runtime_admission']===false,'crosswalk evidence');
            self::day($r['observed_on']);$this->crosswalks[$id]=$r+['source_reference_id'=>$ref];
        }
        $this->buildDiscoveryIndexes();
        ksort($this->pins,SORT_STRING);
        $this->sha=hash('sha256',self::json(['version'=>self::VERSION,'pins'=>$this->pins]));
        if($expectedSha!==null)self::need(hash_equals($expectedSha,$this->sha),'edition digest');
    }
    /** Exact source IDs only. No automatic merger with IPU labels or curated organizations. */
    private function loadPartyFacts(array $d):void
    {
        self::need($d['schema']==='wp117-partyfacts-2023-v1'&&$d['doi']==='doi:10.7910/DVN/TJINLQ'
            &&$d['dataset_version']==='1.1'&&$d['license']==='CC0-1.0'&&$d['publication_date']==='2023-12-15'
            &&$d['runtime_admission']===false&&$d['source_url']==='https://dataverse.harvard.edu/api/access/datafile/7675615?format=original'
            &&$d['source_bytes_sha256']==='0f8c594999d784de8c1c2ddc61d18fd6bbe3db6260e5581e31e1942b9dc3c06c'
            &&preg_match('/^[a-f0-9]{64}$/D',$d['metadata_sha256'])===1,'Party Facts source pin');
        $map=[];
        foreach(explode(' ',self::ISO3_TO_ISO2) as $pair){[$a,$b]=explode(':',$pair);self::need(!isset($map[$a])&&isset($this->countries[$b]),'country crosswalk');$map[$a]=$b;}
        self::need(count($map)===195&&count(array_unique($map))===195,'complete ISO crosswalk');
        $counts=[];
        foreach($d['rows'] as $r){
            $source=$r['source_id'];$code=$r['source_country_code'];
            self::need(is_string($source)&&preg_match('/^[0-9]+$/D',$source)===1&&is_string($code)&&preg_match('/^[a-zA-Z0-9]{2,8}$/D',$code)===1,'Party Facts source identity');
            $id='partyfacts-'.$source;
            self::need(!isset($this->partyfacts[$id]),'duplicate Party Facts ID');
            $c=$map[$code]??(isset($this->countries[$code])?$code:null);
            $name=null;
            foreach(['name','name_english','name_other','name_short'] as $k){
                self::need($r[$k]===null||(is_string($r[$k])&&trim($r[$k])!==''),'Party Facts name');
                if($name===null&&$r[$k]!==null)$name=$r[$k];
            }
            self::need($name!==null,'Party Facts name missing');
            foreach(['year_first_source','year_last_source'] as $k)self::need($r[$k]===null||is_string($r[$k]),'source year');
            $this->partyfacts[$id]=['id'=>$id,'country'=>$c,'label'=>$name,'kind'=>'academic_party_reference',
                'source_url'=>$d['source_url'],'source_edition'=>'partyfacts-2023-v1.1','source_bytes_sha256'=>$d['source_bytes_sha256'],
                'current_membership_verified'=>false,'organization_id'=>null,'runtime_admission'=>false]+$r;
            $counts[$code]=($counts[$code]??0)+1;
            if($c!==null)$this->countries[$c]['partyfacts_ids'][]=$id;
        }
        ksort($counts,SORT_STRING);
        self::need(count($this->partyfacts)===7199&&count($counts)===223&&$counts===$d['country_counts'],'Party Facts count');
    }
    /** French accounting-source identifiers, not a legal status or affiliation inference. */
    private function loadCnccfp(array $d):void
    {
        self::need($d['schema']==='wp117-cnccfp-2024-v1'&&$d['exercise_year']===2024
            &&$d['source_resource_url']==='https://www.data.gouv.fr/api/1/datasets/r/8c53e69e-607a-49d9-98d7-7dabad5ec4a9'
            &&$d['source_bytes_sha256']==='6ce50ac58fe95308b21995463a38a2cc83852980fde19cdd110002e8582c49f2'
            &&$d['runtime_admission']===false&&$d['id_column']==='Code_CNCCFP'&&$d['name_column']==='Nom_du_parti'
            &&$d['source_rows']===575&&$d['license']==='Licence Ouverte / Open Licence','CNCCFP source pin');
        foreach($d['rows'] as $r){
            $source=$r['source_id'];
            self::need(is_string($source)&&preg_match('/^[A-Za-z0-9._\/-]{1,32}$/D',$source)===1,'CNCCFP source identity');
            $id='cnccfp-'.hash('sha256',$source);
            self::need(!isset($this->cnccfp[$id])&&is_array($r['names'])&&count($r['names'])>0,'CNCCFP unique identifier');
            foreach($r['names'] as $name)self::need(is_string($name)&&trim($name)!=='','CNCCFP name');
            $this->cnccfp[$id]=['id'=>$id,'country'=>'FR','label'=>$r['names'][0],'kind'=>'official_accounting_reference',
                'source_url'=>$d['source_resource_url'],'exercise_year'=>2024,'source_bytes_sha256'=>$d['source_bytes_sha256'],
                'organization_id'=>null,'current_legal_status_verified'=>false,'runtime_admission'=>false]+$r;
            $this->countries['FR']['cnccfp_ids'][]=$id;
        }
        self::need(count($this->cnccfp)===575,'CNCCFP row count');
    }
    /** Same-country, exact bytewise labels after ASCII case/whitespace normalization.
     * A discovery hint, not entity resolution. No abbreviations or fuzzy matching.
     */
    private function loadAcademicCrosswalks(array $d):void
    {
        self::need($d['schema']==='wp117-partyfacts-crosswalk-2023-v1'&&$d['doi']==='doi:10.7910/DVN/TJINLQ'
            &&$d['dataset_version']==='1.1'&&$d['runtime_admission']===false&&$d['license']==='CC0-1.0'
            &&$d['source_url']==='https://dataverse.harvard.edu/api/access/datafile/7675616?format=original'
            &&$d['source_bytes_sha256']==='b7c65b6ca6f7e8314cbb4d79c6187a6eb02633deafb5b7d98a9d35d10d0b11ee'
            &&$d['metadata_sha256']==='541353fd1dea64c91be9bdc1d66cbad40f0ec45adb57b3a793010e6bd430cb23'
            &&$d['fields']===['external_id','dataset_key','dataset_party_id','country','partyfacts_id','year_first','year_last']
            &&$d['source_rows']===44888&&$d['linked_rows']===44798,'academic crosswalk edition');
        $iso=[];foreach(explode(' ',self::ISO3_TO_ISO2) as $pair){[$a,$b]=explode(':',$pair);$iso[$a]=$b;}
        $linked=0;
        foreach($d['rows'] as $r){
            self::need(array_is_list($r)&&count($r)===7,'external source columns');
            [$external,$dataset,$party,$code,$core,$first,$last]=$r;
            self::need(is_string($external)&&ctype_digit($external)&&is_string($dataset)&&$dataset!==''&&is_string($party)&&$party!==''&&is_string($code)&&$code!==''&&($core===null||(is_string($core)&&preg_match('/^-?[0-9]+$/D',$core)===1)),'external source identity');
            foreach([$first,$last] as $year)self::need($year===null||is_string($year),'external source year');
            $id='partyfacts-external-'.$external;
            self::need(!isset($this->academicCrosswalks[$id]),'duplicate external source identity');
            $country=$iso[$code]??(isset($this->countries[$code])?$code:null);
            $candidate=$core===null?null:($this->partyfacts['partyfacts-'.$core]??null);
            $coreRef=$candidate===null?null:$candidate['id'];
            $status=$core===null?'source_unlinked':($candidate===null?'source_identifier_unresolved':($country===null||$candidate['country']===null?'country_unmapped':($country!==$candidate['country']?'country_mismatch':'source_link_same_country')));
            $linked+=(int)($core!==null);
            $this->academicDatasetCounts[$dataset]=($this->academicDatasetCounts[$dataset]??0)+1;
            $this->academicStatusCounts[$status]=($this->academicStatusCounts[$status]??0)+1;
            if($country!==null)$this->academicCountryCounts[$country]=($this->academicCountryCounts[$country]??0)+1;
            $this->academicCrosswalks[$id]=['id'=>$id,'country'=>$country,'source_country_code'=>$code,'external_id'=>$external,'dataset_key'=>$dataset,'dataset_party_id'=>$party,
                'source_partyfacts_id'=>$core,'core_reference_id'=>$coreRef,'year_first_source'=>$first,'year_last_source'=>$last,
                'classification'=>$status,'source_url'=>$d['source_url'],'legal_identity_verified'=>false,'runtime_admission'=>false];
        }
        ksort($this->academicDatasetCounts,SORT_STRING);ksort($this->academicStatusCounts,SORT_STRING);
        self::need(count($this->academicCrosswalks)===$d['source_rows']&&$linked===$d['linked_rows']&&$this->academicDatasetCounts===$d['dataset_counts'],'academic crosswalk counts');
    }
    private function buildDiscoveryIndexes():void
    {
        $groups=[];
        $add=static function(array &$groups,?string $country,string $kind,string $id,string $label):void{
            if($country===null)return;
            $key=strtolower(trim(preg_replace('/\\s+/u',' ',$label)??$label));
            if(strlen($key)<3)return;
            $groups[$country][$key][$kind][$id]=true;
        };
        foreach($this->organizations as $id=>$r)$add($groups,$r['country'],'organizations',$id,$r['name']);
        foreach($this->elections as $id=>$r)$add($groups,$r['country'],'electoral_observations',$id,$r['label']);
        foreach($this->partyfacts as $id=>$r)foreach(['name','name_english','name_other'] as $field)if($r[$field]!==null)$add($groups,$r['country'],'partyfacts_references',$id,$r[$field]);
        foreach($this->cnccfp as $id=>$r)foreach($r['names'] as $name)$add($groups,$r['country'],'cnccfp_references',$id,$name);
        foreach($groups as $country=>$labels)foreach($labels as $label=>$kinds){
            if(count($kinds)<2)continue;
            ksort($kinds,SORT_STRING);$refs=[];
            foreach($kinds as $kind=>$ids){$keys=array_keys($ids);sort($keys,SORT_STRING);$refs[$kind]=$keys;}
            $id='label-'.hash('sha256',self::json([$country,(string)$label]));
            $this->labelCorrespondences[$id]=['id'=>$id,'country'=>$country,'normalized_label'=>(string)$label,'reference_ids'=>$refs,'classification'=>'unverified_exact_label_candidate','legal_identity_verified'=>false,'runtime_admission'=>false];
        }
        foreach($this->countries as $country=>$r){
            $id='coverage-'.strtolower($country);
            $counts=[];
            foreach(['organization_ids','election_observation_ids','office_observation_ids','campaign_reference_ids','partyfacts_ids','cnccfp_ids'] as $field)$counts[$field]=count($r[$field]);
            $counts['academic_crosswalk_observations']=$this->academicCountryCounts[$country]??0;
            $counts['registered_party_observations']=0;
            $counts['affiliation_observations']=0;
            $counts['document_references']=0;
            $counts['reviewed_name_correspondences']=0;
            $counts['unverified_label_correspondences']=0;
            foreach($r['organization_ids'] as $oid)$counts['registered_party_observations']+=(int)isset($this->organizations[$oid]['registration_observation']);
            foreach($this->affiliations as $a)$counts['affiliation_observations']+=(int)($this->organizations[$a['organization']]['country']===$country);
            foreach($this->documents as $d)$counts['document_references']+=(int)($this->organizations[$d['organization']]['country']===$country);
            foreach($this->crosswalks as $c)$counts['reviewed_name_correspondences']+=(int)($c['country']===$country);
            foreach($this->labelCorrespondences as $c)$counts['unverified_label_correspondences']+=(int)($c['country']===$country);
            $this->coverageAudit[$id]=['id'=>$id,'country'=>$country,'counts'=>$counts,'current_roster_verified'=>false,'programme_completeness_verified'=>false,'exhaustive'=>false,'runtime_admission'=>false];
        }
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
        $covered=0;$labels=0;$offices=0;$academic=0;$unmapped=0;
        foreach($this->partyfacts as $row)$unmapped+=(int)($row['country']===null);
        foreach($this->countries as $r){$covered+=(int)($r['organization_ids']!==[]||$r['election_observation_ids']!==[]||$r['partyfacts_ids']!==[]);$academic+=(int)($r['partyfacts_ids']!==[]);$labels+=(int)($r['election_observation_ids']!==[]);$offices+=(int)($r['office_observation_ids']!==[]);}
        return ['countries'=>count($this->countries),'countries_with_movement_evidence'=>$covered,'countries_with_electoral_labels'=>$labels,'countries_with_office_observations'=>$offices,'countries_with_partyfacts'=>$academic,'partyfacts_references'=>count($this->partyfacts),'partyfacts_unmapped_country_references'=>$unmapped,'cnccfp_references'=>count($this->cnccfp),'coverage_audit_countries'=>count($this->coverageAudit),'unverified_label_correspondences'=>count($this->labelCorrespondences),'academic_crosswalk_references'=>count($this->academicCrosswalks),'academic_crosswalk_datasets'=>count($this->academicDatasetCounts),'academic_crosswalk_status_counts'=>$this->academicStatusCounts,'crosswalks'=>count($this->crosswalks),'organizations'=>count($this->organizations),'electoral_observations'=>count($this->elections),'office_observations'=>count($this->officeholders),'campaign_sources'=>count($this->campaigns),'people'=>count($this->people),'affiliations'=>count($this->affiliations),'relationships'=>count($this->relationships),'doctrines'=>count($this->doctrines),'documents'=>count($this->documents),'exhaustive'=>false,'runtime_admission'=>false];
    }
    public function country(string $code):array
    {
        $r=$this->countries[$code]??throw new \DomainException('Unknown reference country');
        return $r+['coverage'=>$r['organization_ids']!==[]||$r['election_observation_ids']!==[]||$r['partyfacts_ids']!==[]?'partial_documentary_evidence':'no_movement_evidence',
            'current_roster_verified'=>false,'programme_completeness_verified'=>false];
    }
    /** Bounded read-only pagination; IDs sorted bytewise, no fuzzy identity merge. */
    public function page(string $kind,?string $country=null,?string $after=null,int $limit=100):array
    {
        self::need($limit>=1&&$limit<=100&&($country===null||isset($this->countries[$country])),'page scope');
        $map=match($kind){'organizations'=>$this->organizations,'people'=>$this->people,'electoral_observations'=>$this->elections,'office_observations'=>$this->officeholders,'campaign_sources'=>$this->campaigns,'partyfacts_references'=>$this->partyfacts,'cnccfp_references'=>$this->cnccfp,'crosswalks'=>$this->crosswalks,'academic_crosswalks'=>$this->academicCrosswalks,'label_correspondences'=>$this->labelCorrespondences,'coverage_audit'=>$this->coverageAudit,'doctrines'=>$this->doctrines,'affiliations'=>$this->affiliations,'relationships'=>$this->relationships,'documents'=>$this->documents,'sources'=>$this->sources,default=>throw new \DomainException('Unknown reference kind')};
        if($country!==null)self::need(in_array($kind,['organizations','people','electoral_observations','office_observations','campaign_sources','partyfacts_references','cnccfp_references','crosswalks','academic_crosswalks','label_correspondences','coverage_audit'],true),'unsupported country filter');
        if($country!==null)$map=array_filter($map,fn($r)=>$r['country']===$country);
        ksort($map,SORT_STRING);
        if($after!==null)self::need(isset($map[$after]),'unknown cursor');
        $out=[];$next=null;
        foreach($map as $id=>$r){if($after!==null&&strcmp($id,$after)<=0)continue;if(count($out)===$limit){$next=$out[count($out)-1]['id'];break;}$out[]=['id'=>$id]+$r;}
        return ['edition_sha256'=>$this->sha,'kind'=>$kind,'country'=>$country,'rows'=>$out,'next'=>$next,'runtime_admission'=>false];
    }
}
