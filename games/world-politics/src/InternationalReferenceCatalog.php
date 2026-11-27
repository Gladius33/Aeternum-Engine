<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/CountryCatalog.php';

/** WP115 immutable documentary reference ingestion. Not a world-state writer. */
final class InternationalReferenceCatalog
{
    public const VERSION='wp115-international-initial-v2';
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
        $this->loadParliamentaryReferences($root,$codes);
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

    /** Source election identifiers are independent of present-day memberships. */
    private function loadParliamentaryReferences(string $root,array $codes):void
    {
        $file=$root.'/wp115-ipu-party-seats-2026-11-27-v2.json';
        $data=$this->json($file);
        $this->editions['ipu-party-seats-v2']=hash_file('sha256',$file);
        self::need($data['schema']==='wp115-ipu-party-seat-reference-v2'&&$data['as_of']===self::AS_OF&&$data['runtime_admission']===false&&str_starts_with($data['source_index_url'],'https://data.ipu.org/elections/')&&preg_match('/^[a-f0-9]{64}$/D',$data['source_index_sha256'])===1,'IPU source edition');
        self::need(array_column($data['countries'],'iso2')===$codes,'IPU country coverage');
        $seen=[];$results=0;$rows=0;$covered=0;
        foreach($data['countries'] as $r){
            $code=$r['iso2'];
            self::need($r['runtime_admission']===false&&count($r['results'])<=3,'IPU country classification');
            $has=false;
            foreach($r['results'] as $v){
                $key=$v['election_code'];
                self::need(!isset($seen[$key]),'duplicate election ID');
                $seen[$key]=true;
                $this->checkElection($code,$v,true);
                if($v['parties']!==[]){++$results;$rows+=count($v['parties']);$has=true;}
            }
            $covered+=(int)$has;
            $this->countries[$code]['parliamentary_election_reference']=$r;
            $this->countries[$code]['national_election_supplements']=[];
        }
        self::need(count($seen)===126&&$results===114&&$rows===1077&&$covered===40,'IPU edition totals');
        $file=$root.'/wp115-national-election-supplement-v1.json';
        $national=$this->json($file);
        $this->editions['national-election-supplement-v1']=hash_file('sha256',$file);
        self::need($national['schema']==='wp115-national-party-seat-reference-v1'&&$national['as_of']===self::AS_OF&&$national['runtime_admission']===false,'national supplement');
        $nationalSeen=[];
        foreach($national['countries'] as $r){
            $code=$r['iso2'];
            self::need(isset($this->countries[$code])&&!isset($nationalSeen[$code]),'national country');
            $nationalSeen[$code]=true;
            $local=[];
            foreach($r['results'] as $v){
                self::need(!isset($local[$v['election_code']]),'national duplicate');
                $local[$v['election_code']]=true;
                $this->checkElection($code,$v,false);
            }
            $this->countries[$code]['national_election_supplements']=$r['results'];
        }
    }

    private function checkElection(string $code,array $r,bool $ipu):void
    {
        self::need(preg_match('/^'.preg_quote($code,'/').'-[A-Z0-9]+-E([0-9]{8})$/D',$r['election_code'],$m)===1,'election identifier');
        $date=substr($m[1],0,4).'-'.substr($m[1],4,2).'-'.substr($m[1],6,2);
        self::need($date<=self::AS_OF&&($r['source_election_date_identifier']??$date)===$date&&$r['certifies_current_legislature']===false,'historical election date');
        self::need(is_string($r['source_url'])&&str_starts_with($r['source_url'],$ipu?'https://data.ipu.org/':'https://www.sanews.gov.za/'),'election source');
        self::need(in_array($r['status'],['published_party_seat_reference','no_party_table_available','unavailable'],true)&&is_array($r['parties'])&&array_is_list($r['parties']),'election record');
        if($r['status']==='unavailable'){
            self::need($r['parties']===[],'unavailable election values');
            return;
        }
        if($ipu)self::need(preg_match('/^[a-f0-9]{64}$/D',$r['source_page_sha256'])===1,'IPU response digest');
        else self::need($r['source_publication_date']>=$date&&$r['source_publication_date']<=self::AS_OF,'national publication date');
        $labels=[];$sum=0;
        foreach($r['parties'] as $p){
            self::need(is_string($p['party_or_coalition'])&&trim($p['party_or_coalition'])!==''&&!isset($labels[$p['party_or_coalition']])&&is_int($p['seats'])&&$p['seats']>=0&&$p['seats']<=10000,'party seat record');
            $labels[$p['party_or_coalition']]=true;$sum+=$p['seats'];
        }
        self::need(($r['status']==='published_party_seat_reference')===($r['parties']!==[])&&$r['seats_reported_sum']===($r['parties']===[]?null:$sum),'seat table arithmetic');
    }

    /** Reports available evidence, not the existence of complete country laws. */
    public function coverage(string $iso2):array
    {
        $c=$this->country($iso2);
        $map=[
            'government_debt_and_creditors'=>['government_gross_debt','government_gross_debt_edp','government_debt_edp_pct_gdp','imf_gross_debt_pct_gdp','central_government_debt','external_debt_public_publicly_guaranteed','external_debt_service_public_publicly_guaranteed'],
            'government_revenue_expenditure_balance'=>['gdp_edp','government_balance_edp','government_balance_pct_gdp','government_expenditure_pct_gdp','government_revenue_pct_gdp','government_revenue','government_expenditure','imf_primary_expenditure_pct_gdp','imf_primary_balance_pct_gdp','tax_revenue_central_government','revenue_excluding_grants_central_government','expense_central_government','interest_payments_central_government_share_revenue'],
            'national_tax_and_social_security'=>['tax_wedge_single_average_worker','income_tax_share_labour_cost','employee_ssc_share_labour_cost','employer_ssc_payroll_tax_share_labour_cost','annual_labour_cost_ppp','standard_vat_or_gst_pct','tax_revenue_central_government'],
            'household_disposable_income_wages'=>['household_npish_consumption_per_capita_real','households_npish_consumption_share','gni_per_capita_atlas','gini_index','poverty_international_line','annual_labour_cost_ppp','gni_per_capita_ppp'],
            'population_structure_and_migration'=>['population_total','population_age_0_14_share','population_age_15_64_share','population_age_65_plus_share','fertility_rate','net_migration','urban_population_share'],
            'labour_market_sectoral'=>['unemployment_rate','labour_force_participation_15_64_modelled','agricultural_employment_share_modelled','industry_employment_share_modelled','service_employment_share_modelled','vulnerable_employment_modelled','employment_population_ratio_age_15_plus_modelled','youth_unemployment_modelled','labour_force_total','female_labour_force_participation_age_15_plus_modelled'],
            'defense_budget_and_service_law'=>['military_expenditure','military_expenditure_pct_gdp','military_expenditure_real_yoy','military_expenditure_share'],
            'bilateral_trade_and_energy'=>['exports_goods_services_share','imports_goods_services_share','energy_imports_net_share','energy_use_per_capita'],
            'foreign_reserves_currency_policy'=>['total_reserves_including_gold','official_exchange_rate_period_average','current_account_balance','current_account_balance_share_gdp','domestic_credit_private_sector'],
            'education_health_housing_services'=>['human_development_index','life_expectancy_at_birth','expected_schooling_years','mean_schooling_years','current_health_expenditure_share','domestic_government_health_expenditure_share','government_education_expenditure_share','physicians_density','hospital_beds_density','out_of_pocket_health_expenditure','electricity_access'],
            'investiture_2027_opening_balance'=>['real_gdp_growth','consumer_price_inflation_annual_average','government_gross_debt','government_revenue','government_expenditure','current_account_balance_share_gdp','unemployment_rate'],
            'corporate_and_industrial_structure'=>['gdp_current_usd','agriculture_value_added_share','manufacturing_value_added_share','industry_value_added_share','service_value_added_share','foreign_direct_investment_net_inflows','arable_land_share','total_natural_resource_rents','research_development_expenditure'],
        ];
        $published=array_filter(array_merge($c['parliamentary_election_reference']['results'],$c['national_election_supplements']),static fn(array $r):bool=>$r['parties']!==[]);
        $named=[
            'constitutional_primary_text_verified'=>['institutional_working_profile.candidate_official_portal'],
            'executive_election_and_succession'=>['institutional_working_profile'],
            'legislative_election_algorithm'=>['parliament_reference','electoral_system_reference'],
            'current_officeholders_and_government'=>['officeholder_reference'],
            'party_and_coalition_register'=>$published===[]?[]:['parliamentary_election_reference','national_election_supplements'],
            'election_results_historical'=>$published===[]?[]:['parliamentary_election_reference','national_election_supplements'],
            'national_tax_and_social_security'=>['vat_reference'],
            'foreign_reserves_currency_policy'=>['identity.currency_iso4217'],
            'international_treaty_obligations'=>['identity.eu_member','identity.nato_member_repo_snapshot','identity.brics_status_repo_snapshot'],
            'legal_emergency_and_security_powers'=>[],
        ];
        $out=[];
        foreach($c['unresolved_fields'] as $g){
            $f=$g['field'];$refs=$named[$f]??[];$matches=[];
            foreach($c['numeric'] as $r){
                if($r['value']!==null&&in_array($r['metric'],$map[$f]??[],true)&&($f!=='investiture_2027_opening_balance'||$r['year']===2027))
                    $matches[$r['metric']]=true;
            }
            $metrics=array_keys($matches);sort($metrics,SORT_STRING);
            $out[]=$g+['status'=>$refs===[]&&$metrics===[]?'not_documented_in_initial_pack':'reference_available_not_complete','documentary_fields'=>$refs,'numeric_metrics'=>$metrics,'authorizes_runtime'=>false,'fully_closed'=>false];
        }
        return ['iso2'=>$iso2,'snapshot_sha'=>$this->hash(),'fields'=>$out,'runtime_admission'=>false];
    }

    /** Historical facts and annual forecast inputs, never a fabricated May 2027 balance. */
    public function openingReference(string $iso2):array
    {
        $c=$this->country($iso2);
        $historical=[];$forecast=[];$missing=[];
        foreach($c['numeric'] as $r){
            $key=implode('|',[$r['edition'],$r['metric'],$r['unit'],$r['source_id']]);
            if($r['value']===null){$missing[]=$r;continue;}
            if(in_array($r['classification'],['projection','projection_unverified_excerpt'],true)){
                if($r['year']===2027)$forecast[]=$r;
                continue;
            }
            if($r['year']>2026)continue;
            if(!isset($historical[$key])||$r['year']>$historical[$key]['year'])$historical[$key]=$r;
        }
        ksort($historical,SORT_STRING);
        return ['schema'=>'wp115-opening-reference-v1','iso2'=>$iso2,'reference_as_of'=>self::AS_OF,
            'scenario_start_day'=>'2027-05-14','snapshot_sha'=>$this->hash(),
            'latest_non_null_historical_references'=>array_values($historical),
            'annual_2027_projections'=>$forecast,'explicit_missing_values'=>$missing,
            'actual_opening_state'=>null,'runtime_admission'=>false,
            'opening_state_status'=>'future_unknown_requires_separate_versioned_scenario_assumptions'];
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
