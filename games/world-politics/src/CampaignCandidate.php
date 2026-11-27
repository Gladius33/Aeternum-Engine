<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/HistoricalCandidateReference.php';
require_once __DIR__.'/CampaignLifecycle.php';
require_once __DIR__.'/PresidentialProgramme.php';
require_once __DIR__.'/ProgrammeSourceInventory.php';

/** Choose the player-controlled identity at creation, not an electoral claim.
 * The immutable simulation character ID and all score rules are unchanged.
 * Programme text is a roleplay record; only actual authorized policy commands
 * can affect the economy, institutions or simulated electorate. */
final class CampaignCandidate
{
    public const VERSION='wp75-campaign-candidate-v1';
    /** Source discovery snapshot for the target election. No source is a
     * certified candidacy, completed extraction or executable programme. */
    public static function programme2027Reference():array
    {
        $r=['election_year'=>2027,'scenario_start'=>'2027-05-14','observed_on'=>'2026-11-27',
            'classification'=>'provisional_source_discovery_not_final_ballot',
            'final_candidate_list_verified'=>false,'complete_programmes_verified'=>false,
            'historical_2022_is_2027_programme'=>false,'historical_2022_automatic_import'=>false,
            'sources'=>[
                ['id'=>'fr2027-les-ecologistes','publisher'=>'Les Écologistes','kind'=>'party_programme',
                    'url'=>'https://lesecologistes.fr/pages/6ImK65GKUnvibm33WGkjkj/projet-2027'],
                ['id'=>'fr2027-debout-la-france','publisher'=>'Debout la France','kind'=>'partial_project_announcement',
                    'url'=>'https://www.debout-la-france.fr/evenement/conference-de-presse-sur-le-projet-economique-le-12-11-2026/'],
                ['id'=>'fr2027-retailleau','publisher'=>'Bruno Retailleau','kind'=>'campaign_project',
                    'url'=>'https://www.avecretailleau.fr/projet/faq'],
                ['id'=>'fr2027-place-publique','publisher'=>'Place publique','kind'=>'partial_project_announcement',
                    'url'=>'https://place-publique.eu/'],
                ['id'=>'fr2027-data-gouv-swipe','publisher'=>'Swipe Your President','kind'=>'independent_discovery_index',
                    'url'=>'https://www.data.gouv.fr/datasets/programmes-presidentielle-2027-qui-a-publie-son-programme-quand-et-les-mesures-par-theme-swipe-your-president'],
                ['id'=>'fr2027-data-gouv-ratio','publisher'=>'Ratio 2027','kind'=>'independent_discovery_index',
                    'url'=>'https://www.data.gouv.fr/datasets/programmes-de-lelection-presidentielle-francaise-2027'],
                ['id'=>'fr2027-lfi','publisher'=>'La France insoumise','kind'=>'campaign_project',
                    'url'=>'https://melenchon2027.fr/programme/livre'],
                ['id'=>'fr2027-philippe','publisher'=>'Édouard Philippe','kind'=>'campaign_project',
                    'url'=>'https://www.edouardphilippe.fr/programme'],
            ],'extraction_complete'=>false,'executable_measure_count'=>count(SocialProgramme::target2027()),
            'reviewed_measures'=>[
                ['id'=>'pf2027-jean-luc-melenchon-smic','publisher'=>'La France insoumise',
                    'source'=>'https://melenchon2027.fr/communique/plan-pauvrete/',
                    'target'=>['monthly_net_eur'=>1700],
                    'scope'=>'nominal_net_wage_floor_cohort_proxy'],
                ['id'=>'pf2027-jean-luc-melenchon-pension','publisher'=>'La France insoumise',
                    'source'=>'https://melenchon2027.fr/programme/livre/mettre-en-securite-sociale/chapitre1',
                    'target'=>['age'=>60,'contribution_years'=>40],
                    'scope'=>'representative_pension_career_and_age_cohort_proxy'],
                ['id'=>'pf2027-ecologistes-renovation','publisher'=>'Les Écologistes',
                    'source'=>'https://lesecologistes.fr/manifesto/1VEj8MDN3PHPwmprxozbqV/2-pouvoir-se-loger-decemment',
                    'target'=>['annual_units'=>500000],
                    'scope'=>'incremental_annual_renovation_funding_proxy_not_energy_saving_certification'],
                ['id'=>'pf2027-ecologistes-smic','publisher'=>'Les Écologistes',
                    'source'=>'https://lesecologistes.fr/posts/4b3Vl4nRDOZk4nuMvI4eKa/nos-propositions-pour-un-travail-emancipateur-ecologique-et-democratique',
                    'target'=>['monthly_gross_eur'=>2000],'scope'=>'gross_wage_floor_with_fictional_payroll'],
                ['id'=>'pf2027-retailleau-contributions','publisher'=>'Bruno Retailleau',
                    'source'=>'https://www.avecretailleau.fr/projet/mesure/rendre-30-milliards-d-euros-aux-salaries-et-aux-entreprises',
                    'target'=>['annual_employee_relief_eur'=>15000000000,'annual_employer_relief_eur'=>15000000000],'scope'=>'gross_revenue_loss_no_automatic_savings'],
                ['id'=>'pf2027-dlf-production-tax','publisher'=>'Debout la France','source'=>'https://www.debout-la-france.fr/wp-content/uploads/2026/11/Programme-et-plan-de-financement-economique.pdf',
                    'target'=>['annual_reduction_eur'=>5000000000],'scope'=>'annual_budget_flow_only'],
                ['id'=>'pf2027-dlf-industrial-grants','publisher'=>'Debout la France','source'=>'https://www.debout-la-france.fr/wp-content/uploads/2026/11/Programme-et-plan-de-financement-economique.pdf',
                    'target'=>['annual_eur'=>4000000000],'scope'=>'annual_grant_budget_only_no_guaranteed_jobs'],
                ['id'=>'pf2027-lfi-public-housing','publisher'=>'La France insoumise','source'=>'https://melenchon2027.fr/programme/plans-investissement/btp-logement/',
                    'target'=>['annual_units'=>200000,'years'=>5],'scope'=>'incremental_public_housing_proxy'],
                ['id'=>'pf2027-lfi-renovation','publisher'=>'La France insoumise','source'=>'https://melenchon2027.fr/programme/livre/pour-une-bifurcation-ecologique-et-sociale/chapitre3/',
                    'target'=>['annual_units'=>700000],'scope'=>'incremental_insulation_funding_proxy'],
                ['id'=>'pf2027-lfi-public-index','publisher'=>'La France insoumise','source'=>'https://melenchon2027.fr/plans-thematiques/pauvrete/',
                    'target'=>['increase_bps'=>1000],'scope'=>'indexed_pay_component_not_total_salary'],
                ['id'=>'pf2027-lfi-eldercare-capacity','publisher'=>'La France insoumise','source'=>'https://melenchon2027.fr/programme/livre/mettre-en-securite-sociale/chapitre2/',
                    'target'=>['annual_places'=>10000,'years'=>5],'scope'=>'incremental_operating_cost_not_construction_certification'],
                ['id'=>'pf2027-retailleau-overtime','publisher'=>'Bruno Retailleau','source'=>'https://www.avecretailleau.fr/projet/mesure/rapprocher-le-brut-du-net-avec-le-seuil-zero-cotisation',
                    'target'=>['annual_threshold_hours'=>1623,'social_contributions_above_threshold_bps'=>0,'csg_crds_retained'=>true],'scope'=>'residual_payroll_relief_without_claimed_financing'],
                ['id'=>'pf2027-philippe-teacher-pay','publisher'=>'Édouard Philippe','source'=>'https://www.edouardphilippe.fr/programme/ecole',
                    'target'=>['increase_bps'=>2000,'phase_years'=>5,'scope'=>'teachers'],'scope'=>'linear_five_year_payroll_proxy'],
            ],
            'identified_unmapped'=>[],
            'direct_source_inventory'=>ProgrammeSourceInventory::report(),
            'selection_enabled'=>false,'reviewed_profile_selection_enabled'=>false,'requires_primary_source_review'=>true];
        $ids=[];
        foreach($r['reviewed_measures'] as $m){
            $id=$m['id'];$d=SocialProgramme::target2027()[$id]??null;
            if(isset($ids[$id])||$d===null||$d['target']!==$m['target']||!str_starts_with($m['source'],'https://'))
                throw new \DomainException('Invalid dated programme source mapping');
            $ids[$id]=true;
        }
        if(array_diff_key(SocialProgramme::target2027(),$ids)!==[])throw new \DomainException('Missing dated programme provenance');
        $r['reference_sha']=hash('sha256',json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        return $r;
    }
    /** Dated published projects, distinct from the final official ballot.
     * Party-only programmes are not attributed to an individual without
     * reviewed evidence. Selection never activates any policy. */
    public static function campaign2027Choices():array
    {
        $reference=self::programme2027Reference();
        $rows=[
            ['id'=>'campaign2027-jean-luc-melenchon','name'=>'Jean-Luc Mélenchon','publisher'=>'La France insoumise','source'=>'https://melenchon2027.fr/programme/decouvrir'],
            ['id'=>'campaign2027-bruno-retailleau','name'=>'Bruno Retailleau','publisher'=>'Bruno Retailleau','source'=>'https://www.avecretailleau.fr/projet/faq'],
            ['id'=>'campaign2027-nicolas-dupont-aignan','name'=>'Nicolas Dupont-Aignan','publisher'=>'Debout la France','source'=>'https://www.debout-la-france.fr/wp-content/uploads/2026/11/Programme-et-plan-de-financement-economique.pdf'],
            ['id'=>'campaign2027-edouard-philippe','name'=>'Édouard Philippe','publisher'=>'Édouard Philippe','source'=>'https://www.edouardphilippe.fr/programme'],
        ];
        // Preserve previously issued reference IDs and their exact roster
        // fingerprints. New programme editions have separate IDs.
        $oldIds=[
            'campaign2027-jean-luc-melenchon'=>['pf2027-jean-luc-melenchon-smic','pf2027-jean-luc-melenchon-pension'],
            'campaign2027-bruno-retailleau'=>['pf2027-retailleau-contributions'],
            'campaign2027-nicolas-dupont-aignan'=>['pf2027-dlf-production-tax','pf2027-dlf-industrial-grants'],
            'campaign2027-edouard-philippe'=>['pf2027-philippe-teacher-pay'],
        ];
        foreach([$rows[0],$rows[1]] as $row){$row['id'].='-v2';$rows[]=$row;}
        foreach($rows as &$row){
            $row['as_of']=$reference['observed_on'];
            $row['measure_ids']=$oldIds[$row['id']]??array_column(array_values(array_filter($reference['reviewed_measures'],fn($m)=>$m['publisher']===$row['publisher'])),'id');
            $row['final_ballot_verified']=false;
            $row+=self::editionAvailability($row);
        }unset($row);
        return $rows;
    }
    /** Release requires a positively reviewed, versioned programme edition.
     * Mere existence of a partial corpus or a campaign website never suffices. */
    private static function editionAvailability(array $edition):array
    {
        $approval=ProgrammeSourceInventory::report()['approved_editions'][$edition['id']]??null;
        $ok=$approval!==null&&$approval['programme_complete']===true&&$approval['implementation_complete']===true
            &&$approval['measure_ids']===$edition['measure_ids']&&$edition['measure_ids']!==[];
        if($ok){
            try{PresidentialProgramme::compileProfile($edition['measure_ids']);}
            catch(\InvalidArgumentException $e){$ok=false;}
        }
        return ['playable'=>$ok,'programme_complete'=>$ok,'availability_code'=>$ok?'available':'programme_incomplete',
            'availability_label_fr'=>$ok?'Programme disponible':'Programme encore non disponible'];
    }
    public static function candidateDirectory2027():array
    {
        $editions=self::campaign2027Choices();$rows=[];
        foreach(ProgrammeSourceInventory::report()['people'] as $id=>$person){
            $matches=array_values(array_filter($editions,fn($e)=>$e['id']==='campaign2027-'.$id||str_starts_with($e['id'],'campaign2027-'.$id.'-v')));
            $released=array_values(array_filter($matches,fn($e)=>$e['playable']));
            $best=$released!==[]?$released[array_key_last($released)]:($matches[array_key_last($matches)]??null);
            $playable=$best['playable']??false;
            $rows[]=['reference_id'=>$best['id']??'campaign2027-'.$id,'name'=>$person['name'],'source'=>$person['url'],
                'publication_state'=>$person['publication_state'],'as_of'=>$person['observed_on'],'playable'=>$playable,
                'programme_complete'=>$playable,'availability_code'=>$playable?'available':'programme_incomplete',
                'availability_label_fr'=>$playable?'Programme disponible':'Programme encore non disponible',
                'reviewed_measure_count'=>count($best['measure_ids']??[])];
        }
        return $rows;
    }
    /** Known discovery-only candidates lack a roster edition, so reject
     * them before normalization with the same stable API error. */
    public static function discoveryOnly(mixed $input):bool
    {
        if(!is_array($input)||($input['type']??null)!=='campaign2027'||!is_string($input['reference_id']??null))return false;
        $keys=array_keys($input);sort($keys);
        if($keys!==['reference_id','type'])return false;
        $id=$input['reference_id'];
        return !isset(array_column(self::campaign2027Choices(),null,'id')[$id])
            &&isset(array_column(self::candidateDirectory2027(),null,'reference_id')[$id]);
    }
    public static function mayStart(mixed $input):bool
    {
        if(!is_array($input))return false;
        if(($input['type']??null)==='fictional')return true;
        if(($input['type']??null)!=='campaign2027')return false;
        return (array_column(self::campaign2027Choices(),null,'id')[$input['reference_id']??'']['playable']??false)===true;
    }
    private static function normalize2027(string $id):array
    {
        $choice=array_column(self::campaign2027Choices(),null,'id')[$id]??throw new \DomainException('Unknown 2027 campaign reference');
        $measures=array_column(self::programme2027Reference()['reviewed_measures'],null,'id');
        $programme=[];
        foreach($choice['measure_ids'] as $mid){
            $m=$measures[$mid];$d=SocialProgramme::target2027()[$mid]??throw new \DomainException('Missing 2027 execution mapping');
            if($d['target']!==$m['target'])throw new \DomainException('Target source mismatch');
            $programme[]=['id'=>$mid,'domain'=>$d['domain'],'proposal'=>json_encode($m['target'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'sources'=>[$m['source']]];
        }
        return ['id'=>CampaignLifecycle::PLAYER,'kind'=>'real','name'=>$choice['name'],'country'=>'FR',
            'biography'=>'Projet public pour 2027, observé avant le scrutin. Personnage de simulation, sans certification de candidature définitive ni de résultat électoral.',
            'as_of'=>$choice['as_of'],'sources'=>[$choice['source']],'programme'=>$programme,'avatar'=>PoliticalProfileTemplate::AVATAR];
    }
    public static function options():array
    {
        $ref=HistoricalCandidateReference::report(HistoricalCandidateReference::pin());
        return ['version'=>self::VERSION,'scenario'=>'fr-investiture-2027-v1','selection_at_creation'=>true,
            'fictional_template'=>PoliticalProfileTemplate::definition(),
            'historical_choices'=>array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name'],'as_of'=>$p['as_of'],'sources'=>$p['sources'],'playable'=>false,'availability_code'=>'historical_archive_only','availability_label_fr'=>'Archive historique — non jouable en 2027'],$ref['roster_compatible_profiles']),
            'historical_reference_sha'=>$ref['reference_sha'],'historical_programme_scope'=>$ref['programme_partial_scope'],'historical_measure_count'=>$ref['curated_measure_count'],'historical_selection_is_alternate_history'=>true,
            'target_election_year'=>2027,'historical_reference_election_year'=>2022,'historical_choices_are_2027_candidates'=>false,
            'programme_2027_reference'=>self::programme2027Reference(),'campaign_2027_choices'=>self::campaign2027Choices(),'candidate_directory_2027'=>self::candidateDirectory2027(),'fictional_candidate_playable'=>true,'incomplete_candidate_selection_blocked'=>true,
            'future_eligibility_certified'=>false,'programme_mechanics_enabled'=>false,
            'managed_universe_explicit_programme_catalog'=>PresidentialProgramme::measureCatalog(),
            'historical_social_measure_catalog'=>SocialProgramme::definitions(),'historical_measures_automatically_imported'=>false,'changes_score_rules'=>false];
    }
    /** Managed opening identity only; historical programme provenance is
     * extended without changing the immutable earlier reference pack. */
    public static function normalizeManaged(mixed $input):array
    {
        $person=self::normalize($input);
        if($input['type']==='historical'){
            require_once __DIR__.'/HistoricalProgrammeCoverage.php';
            $id=substr($input['reference_id'],5);$r=HistoricalProgrammeCoverage::report()['candidates'][$id]??throw new \DomainException('Missing candidate coverage');
            foreach([...$r['additional_executable'],...$r['additional_unmapped']] as $m){
                $person['programme'][]=['id'=>$m['id'],'domain'=>$m['domain'],'proposal'=>$m['summary'],'sources'=>[$m['source']]];
            }
        }
        $roster=new PoliticalRoster('candidate-validation','FR',['prime_minister']);
        $roster->addPerson($person);$roster->setHeadOfState(CampaignLifecycle::PLAYER);
        return $person;
    }
    public static function normalize(mixed $input):array
    {
        if(!is_array($input)||array_is_list($input)||!is_string($input['type']??null))throw new \InvalidArgumentException('Invalid candidate selection');
        if($input['type']==='historical'){
            $keys=array_keys($input);sort($keys);
            if($keys!==['reference_id','type']||!is_string($input['reference_id']))throw new \InvalidArgumentException('Invalid historical candidate fields');
            $ref=HistoricalCandidateReference::report(HistoricalCandidateReference::pin());
            $person=null;
            foreach($ref['roster_compatible_profiles'] as $p)if($p['id']===$input['reference_id']){$person=$p;break;}
            if($person===null)throw new \DomainException('Unknown historical identity');
            $person['id']=CampaignLifecycle::PLAYER;
            $person['biography'].=' Identité utilisée dans une histoire alternative fictive ; ne constitue pas une candidature attestée en 2027.';
        }elseif($input['type']==='campaign2027'){
            $keys=array_keys($input);sort($keys);
            if($keys!==['reference_id','type']||!is_string($input['reference_id']))throw new \InvalidArgumentException('Invalid 2027 campaign fields');
            $person=self::normalize2027($input['reference_id']);
        }elseif($input['type']==='fictional'){
            foreach(array_keys($input) as $k)if(!in_array($k,['type','name','mode','biography','programme','profile'],true))throw new \InvalidArgumentException('Unknown fictional candidate field');
            unset($input['type']);$input['id']=CampaignLifecycle::PLAYER;
            $person=PoliticalProfileTemplate::normalizeFictional($input,'FR');
        }else throw new \InvalidArgumentException('Unknown candidate type');
        $roster=new PoliticalRoster('candidate-validation','FR',['prime_minister']);
        $roster->addPerson($person);$roster->setHeadOfState(CampaignLifecycle::PLAYER);
        return $person;
    }
}
