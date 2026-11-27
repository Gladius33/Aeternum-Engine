<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/AlphaWorlds.php';
require_once __DIR__.'/PdoDomesticPolicyStore.php';
require_once __DIR__.'/ResearchExperiment.php';

/** Explicitly hypothetical authority, instantiated only by this isolated
 * in-memory research model. No live institutional or game write capability. */
final class ResearchScenarioAuthority implements DomesticPolicyAuthority
{
    public function __construct(private readonly string $world,private readonly string $day,private readonly ?string $policy=null,private readonly ?string $proof=null){}
    public function allows(string $world,string $ability):bool{return $world===$this->world&&in_array($ability,['domestic.propose','domestic.enact','domestic.clock'],true);}
    public function enactedInstrument(string $world,string $policy):?string{return $world===$this->world&&$policy===$this->policy?$this->proof:null;}
    public function clockDay(string $world):string{if($world!==$this->world)throw new \DomainException('Research scope');return $this->day;}
}
final class ResearchPersistentModel
{
    public const VERSION='wp-research-domestic-world-v1';
    public const START='2027-05-14';
    public const MAX_DAYS=180;
    public const MAX_EVENTS=64;
    public static function config():array{
        $bundle=GlobalReferenceBundle::published();
        $s=new AlphaScenario(AlphaScenario::INVESTITURE,$bundle,$bundle->sha());
        $c=$s->domestic();$c['catalog']+=array_intersect_key(SocialProgramme::policies($c['macro_baseline']),SocialProgramme::target2027());
        foreach(['finance_autonomy','daily_legal_enabled','trade_feedback_enabled','eu_trade_enabled','eu_trade_v2','eu_trade_v3','eu_trade_v4'] as $flag)$c[$flag]=false;
        return $c;
    }
    public static function pin(array $config):array{
        $pins=[];foreach(glob(__DIR__.'/*.php') as $f){$sha=hash_file('sha256',$f);if($sha===false)throw new \DomainException('Model source unavailable');$pins[basename($f)]=$sha;}
        ksort($pins,SORT_STRING);
        return ['version'=>self::VERSION,'config_sha'=>ResearchExperiment::hash($config),'engine_sha'=>ResearchExperiment::hash($pins),'php'=>PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION];
    }
    public static function build(string $world,array $config):DomesticPolicySimulation{return PdoDomesticPolicyStore::build('research-'.$world,$config);}
    public static function policies(array $config):array{return array_values(array_intersect(array_keys($config['catalog']),array_merge(['education-reform','administration-report'],array_keys(SocialProgramme::target2027()))));}
    public static function normalize(array $a):array{
        $keys=array_keys($a);sort($keys);
        if($keys!==['days','expected_revision','policy']||!is_int($a['days'])||$a['days']<1||$a['days']>7||!is_int($a['expected_revision'])||$a['expected_revision']<0||($a['policy']!==null&&(!is_string($a['policy'])||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$a['policy']))))
            throw new \DomainException('Invalid research transition');
        return ['expected_revision'=>$a['expected_revision'],'days'=>$a['days'],'policy'=>$a['policy']];
    }
    public static function features(array $s):array{
        $last=$s['days']===[]?[]:$s['days'][count($s['days'])-1];$m=$last['macro']??[];
        return ['day'=>$s['day'],'treasury_balance_minor'=>$s['balance'],'revenue_minor'=>$last['revenue']??null,'expense_minor'=>$last['expense']??null,
            'output_index_bps'=>$m['real_output_index_bps']??null,'unemployment_bps'=>$m['unemployment_bps']??null,'price_index_scaled'=>$m['price_index_scaled']??null,
            'wage_index_scaled'=>$m['wage_index_scaled']??null,'population'=>$last['population']['indicators']['population']??null,
            'active_policies'=>$last['active_policies']??[],'household_real_per_uc_index_bps'=>isset($last['population'],$s['population_model'],$s['macro_baseline'])?
            HouseholdAccount::day($s['macro_baseline'],$s['population_model'],$last,count($s['days']))['cases']['fixed_household_structure']['real_rdb_per_uc_proxy_index_bps']:null];
    }
    public static function step(DomesticPolicySimulation $sim,array $config,array $a):array{
        $a=self::normalize($a);$s=$sim->snapshot();
        if(count($s['days'])+$a['days']>self::MAX_DAYS)throw new \DomainException('Research world horizon');
        $before=self::features($s);$policy=$a['policy'];$effective=null;
        if($policy!==null){
            if(!in_array($policy,self::policies($config),true))throw new \DomainException('Unsupported research policy');
            $effective=isset(SocialProgramme::target2027()[$policy])?SocialProgramme::effectiveDay($policy,$s['day']):(new \DateTimeImmutable($s['day']))->modify('+'.($policy==='education-reform'?120:14).' days')->format('Y-m-d');
            $proof=ResearchExperiment::hash(['classification'=>'research_hypothesis_not_law','world'=>$s['world_id'],'day'=>$s['day'],'policy'=>$policy,'effective'=>$effective]);
            $p=new ResearchScenarioAuthority($s['world_id'],$s['day'],$policy,$proof);
            $sim->propose($p,$policy,$effective);$sim->authorize($p,$policy);
        }
        $daily=[];
        for($i=0;$i<$a['days'];$i++){
            $day=(new \DateTimeImmutable($sim->snapshot()['day']))->modify('+1 day')->format('Y-m-d');
            $sim->advance(new ResearchScenarioAuthority($s['world_id'],$day),$day);
            $daily[]=self::features($sim->snapshot());
        }
        return ['state'=>$before,'action'=>['policy'=>$policy,'hypothetical_effective_day'=>$effective,'days'=>$a['days']],
            'next_state'=>$daily[count($daily)-1],'observations'=>$daily,'reward'=>null,'classification'=>'conditional_research_not_legal_enactment'];
    }
}
