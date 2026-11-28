<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/UniverseLobby.php';
require_once __DIR__.'/NationalEconomicScenario.php';

/** Read model only: no synthetic GDP growth, no new office authority.
 * Historical observations and effective trade scenarios remain distinct. */
final class InternationalUniverseView{
    public const VERSION='wp130-shared-international-view-v1';
    public static function build(int $no,string $world,string $day,string $edition,string $account,array $own,array $slots,GlobalReferenceBundle $bundle,array $institutions,array $dip,array $econ):array{
        foreach([$dip['state'],$econ['state']] as $domain)
            if($domain['world_id']!==$world||$domain['day']!==$day)throw new \DomainException('International publication scope mismatch');
        foreach($institutions as $cc=>$state)
            if($state['world_id']!==$world||$state['country_code']!==$cc||$state['day']!==$day)throw new \DomainException('International institution day mismatch');
        $actual=array_column($slots,'country_code');$expected=UniverseLobby::roster($edition);sort($actual);sort($expected);
        if($actual!==$expected)throw new \DomainException('International roster mismatch');
        $rows=[];$ownActor='state_'.strtolower($own['country_code']);
        foreach($slots as $slot){
            $cc=$slot['country_code'];$id='state_'.strtolower($cc);$actor=$bundle->actors->get($id);
            if(!$bundle->actors->isHumanPlayable($id))throw new \DomainException('Unreviewed slot');
            $i=$institutions[$cc]??null;
            $gdp=$bundle->references->latest($id,'gdp_current_usd');$pop=$bundle->references->latest($id,'population_total');
            $budget=$bundle->references->latest($id,'government_budget_usd');
            $perCapita=null;
            if(($gdp['kind']??null)==='observed'&&($pop['kind']??null)==='observed'&&$gdp['year']===$pop['year']&&$pop['value']>0)
                $perCapita=['value'=>intdiv($gdp['value'],$pop['value']),'unit'=>'USD/person','year'=>$gdp['year'],'classification'=>'derived_historical_observation'];
            $national=NationalEconomicScenario::profile($cc,$day);
            $scenario=$national===null?null:NationalEconomicScenario::project($national,$id,$day,$gdp,$econ['state']);
            $rows[]=['country'=>$cc,'national_reference'=>$cc===$own['country_code']?$national:null,
                'next_ordinary_chamber_renewal_month'=>$national['next_ordinary_chamber_renewal_month']??null,
                'consumption_scenario'=>$scenario,
                'fiscal_scenario'=>$national===null?null:NationalEconomicScenario::fiscal($national,$scenario,$gdp),'actor_id'=>$id,'name'=>$actor['label'],'controller'=>$slot['closed_on']!==null?'succession_pending':($slot['account_id']===null?'ai':'human'),
                'is_you'=>$slot['account_id']===$account,'national_institution_clock'=>$i!==null,'domestic_policy'=>$cc==='FR',
                'institution'=>$i===null?null:['legislature'=>$i['legislature'],'government_status'=>$i['accountability']['status']??null,'incumbent_reference'=>$i['accountability']['incumbent']??null],
                'observations'=>['gdp'=>$gdp,'population'=>$pop,'budget'=>$budget,'gdp_per_capita'=>$perCapita],
                'foreign_domestic_macro_model'=>false];
        }
        $targets=[];foreach($bundle->actors->all() as $a)if($a['id']!==$ownActor)$targets[]=['id'=>$a['id'],'label'=>$a['label'],'kind'=>$a['kind'],'controller'=>$a['controller']];
        $offers=[];foreach($dip['state']['proposals'] as $p)if($p['from']===$ownActor||$p['to']===$ownActor)$offers[]=$p;
        $incoming=array_values(array_filter($offers,fn($p)=>$p['to']===$ownActor&&$p['status']==='pending_response'));
        usort($offers,fn($a,$b)=>strcmp($b['created_on'],$a['created_on'])?:strcmp($a['id'],$b['id']));
        $effects=[];foreach($econ['state']['projections'] as $id=>$effect){
            $policy=$econ['state']['policies'][$id]??null;
            if($policy===null||$policy['status']!=='effective')throw new \DomainException('Orphan trade impact');
            if($policy['from']===$ownActor||$policy['to']===$ownActor)$effects[]=['from'=>$policy['from'],'to'=>$policy['to'],...$effect];
        }
        // Do not sum overlapping sanctions against a single bilateral baseline.
        // Missing bilateral observations remain null, not zero.
        return ['version'=>self::VERSION,'universe_no'=>$no,'world_id'=>$world,'day'=>$day,'roster_version'=>$edition,
            'country'=>$own['country_code'],'actor_id'=>$ownActor,'generation'=>(int)$own['generation'],'diplomacy_programme'=>$own['diplomacy_programme'],
            'countries'=>$rows,'actors'=>$targets,'diplomacy_etag'=>$dip['etag'],'incoming'=>$incoming,'recent_offers'=>array_slice($offers,0,30),
            'trade_effects'=>array_slice($effects,-50),'trade_effect_count'=>count($effects),'reference_as_of'=>$bundle->references->asOf,'reference_sha'=>$bundle->references->hash(),
            'classification'=>'shared_executive_diplomacy_alpha','international_career_pool'=>false,'official_ranking_eligible'=>false];
    }
}
