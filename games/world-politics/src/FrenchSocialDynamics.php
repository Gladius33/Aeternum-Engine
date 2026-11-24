<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

require_once __DIR__.'/FrenchConstitutionalCrisis.php';

/**
 * WP20 — heterogeneous, auditable social feedback to constitutional policies.
 *
 * Entirely scenario-based: coefficients are EXPLICIT design assumptions,
 * not empirical forecasts of actual French citizens, armed forces or unions.
 * Do not expose as a tool for optimizing repression or other coercion.
 *
 * Social protest, partial work withdrawal, parliamentary failure and a
 * constitutional change of government are deliberately distinct results.
 * A regime transition requires a later, separately lawful/politically
 * modeled process; it is NEVER triggered from unrest alone.
 */
final class FrenchSocialDynamics
{
    public const VERSION='wp20-fr-social-scenario-1';
    private const SECTOR_FIELDS=[
        'id','name','population_weight_bps','base_grievance',
        'collective_organization','economic_dependence',
        'mobility_constraint','workplace_fragility'
    ];
    private const CONTEXT_FIELDS=[
        'civil_liberties_trust','public_institutional_trust',
        'parliamentary_support','government_cohesion',
        'security_institutional_cohesion','military_constitutional_cohesion',
        'elite_cohesion','economic_resilience'
    ];

    private array $sectors=[];
    private string $world;
    private array $history=[];

    /** @param list<array<string,mixed>> $sectors */
    public function __construct(string $world,array $sectors)
    {
        self::id($world);
        if (!array_is_list($sectors) || count($sectors)<2 || count($sectors)>32) {
            throw new \InvalidArgumentException('Social sample must have 2..32 distinct sectors');
        }
        $seen=[];$sum=0;
        foreach ($sectors as $sector) {
            if (!is_array($sector)) throw new \InvalidArgumentException('Invalid social sector');
            self::fields($sector,self::SECTOR_FIELDS);
            self::id($sector['id']);
            if (isset($seen[$sector['id']])) throw new \InvalidArgumentException('Duplicate social sector');
            $seen[$sector['id']]=true;
            if (!is_string($sector['name'])
                || mb_strlen(trim($sector['name']))<1
                || mb_strlen($sector['name'])>120) {
                throw new \InvalidArgumentException('Invalid social sector name');
            }
            foreach (['base_grievance','collective_organization','economic_dependence',
                'mobility_constraint','workplace_fragility'] as $field) {
                self::score($sector[$field]);
            }
            self::score($sector['population_weight_bps'],10000);
            $sum+=$sector['population_weight_bps'];
            $this->sectors[$sector['id']]=$sector;
        }
        if ($sum!==10000) {
            throw new \InvalidArgumentException('Population cohort weights must add to 10000 basis points');
        }
        ksort($this->sectors,SORT_STRING);
        $this->world=$world;
    }

    /**
     * Use the legal/domain-verified 49.3 engagement, not a user-provided
     * "49.3 happened" flag. Impact and concessions per sector are
     * hypothetical scenario inputs, not fabricated survey data.
     *
     * @param array<string,int> $impacts grievance changes in [-1000,+1000]
     * @param array<string,int> $concessions negotiated relief in [0,1000]
     * @param array<string,int> $context independent scenario observations
     * @return array<string,mixed>
     */
    public function assess49_3(
        FrenchConstitutionalCrisis $constitution,string $billId,
        array $impacts,array $concessions,array $context
    ):array {
        self::id($billId);
        $state=$constitution->snapshot();
        if ($state['world_id']!==$this->world) {
            throw new \DomainException('Social response cannot read another world');
        }
        $hasEngagement=false;
        foreach ($state['events'] as $event) {
            if (($event['kind']??null)==='engage'
                && ($event['result']['bill_id']??null)===$billId) {
                $hasEngagement=true;
                break;
            }
        }
        if (!$hasEngagement) {
            throw new \DomainException('Only a real domain-recorded 49.3 engagement is admissible');
        }
        self::fields($context,self::CONTEXT_FIELDS);
        foreach ($context as $value) self::score($value);
        $expected=array_keys($this->sectors);
        $impactKeys=array_keys($impacts);$concessionKeys=array_keys($concessions);
        sort($impactKeys);sort($concessionKeys);
        if ($impactKeys!==$expected || $concessionKeys!==$expected) {
            throw new \InvalidArgumentException('Every curated social sector needs explicit scenario inputs');
        }
        foreach ($impacts as $effect) {
            if (!is_int($effect) || $effect<-1000 || $effect>1000) {
                throw new \InvalidArgumentException('Invalid sector exposure to legislation');
            }
        }
        foreach ($concessions as $relief) self::score($relief);

        ksort($impacts,SORT_STRING);
        ksort($concessions,SORT_STRING);
        ksort($context,SORT_STRING);
        $inputs=[
            'bill_id'=>$billId,
            'sectors_sha'=>$this->cohortHash(),
            'constitution_event_hash'=>$constitution->hash(),
            'impacts'=>$impacts,'concessions'=>$concessions,'context'=>$context
        ];
        $fingerprint=hash('sha256',self::json($inputs));
        if (isset($this->history[$billId])) {
            if (!hash_equals($this->history[$billId]['scenario_sha'],$fingerprint)) {
                throw new \DomainException('Existing social scenario cannot be silently recalibrated');
            }
            return $this->history[$billId];
        }

        $perSector=[];$protestSum=0;$withdrawalSum=0;
        $disruptionSum=0;$grievanceSum=0;$weightSum=0;
        foreach ($this->sectors as $key=>$sector) {
            $weight=$sector['population_weight_bps'];
            $grievance=self::clamp(
                $sector['base_grievance']+$impacts[$key]
                -$concessions[$key]
                -intdiv($context['public_institutional_trust'],5)
            );
            // Freer civic spaces may yield more *visible peaceful protest*;
            // restricting civic liberties may instead increase latent
            // mistrust and labor withdrawal. Repression is NOT a controllable
            // "win protest" action of this module.
            $publicProtest=self::clamp(
                intdiv(4*$grievance+2*$sector['collective_organization']
                    +$context['civil_liberties_trust']
                    -$sector['mobility_constraint'],7)
            );
            $workWithdrawal=self::clamp(
                intdiv(4*$grievance+2*$sector['collective_organization']
                    +2*(1000-$context['civil_liberties_trust'])
                    +$sector['workplace_fragility'],9)
            );
            $sectorDisruption=self::clamp(
                intdiv($workWithdrawal*$sector['economic_dependence'],1000)
            );
            $perSector[$key]=[
                'name'=>$sector['name'],
                'population_weight_bps'=>$weight,
                'grievance_index'=>$grievance,
                'peaceful_protest_propensity_index'=>$publicProtest,
                'work_withdrawal_propensity_index'=>$workWithdrawal,
                'productivity_disruption_index'=>$sectorDisruption,
                'estimated_production_loss_bps_low'=>intdiv($sectorDisruption,20),
                'estimated_production_loss_bps_high'=>intdiv($sectorDisruption,5),
                'observed'=>false
            ];
            $protestSum+=$publicProtest*$weight;
            $withdrawalSum+=$workWithdrawal*$weight;
            $disruptionSum+=$sectorDisruption*$weight;
            $grievanceSum+=$grievance*$weight;
            $weightSum+=$weight;
        }
        if ($weightSum!==10000) {
            throw new \UnexpectedValueException('Scenario population no longer balances');
        }
        $protest=intdiv($protestSum,10000);
        $withdrawal=intdiv($withdrawalSum,10000);
        $disruption=intdiv($disruptionSum,10000);
        $grievance=intdiv($grievanceSum,10000);

        // Constitutional government fragility and broader regime stress
        // have distinct contributors and DO NOT resolve a coup, revolt or
        // power transfer. No security-force tactical/operational model.
        $governmentPressure=self::clamp(intdiv(
            3*$grievance
            +2*(1000-$context['parliamentary_support'])
            +(1000-$context['government_cohesion'])
            +$protest,7
        ));
        $institutionalStrain=self::clamp(intdiv(
            2*(1000-$context['public_institutional_trust'])
            +2*(1000-$context['civil_liberties_trust'])
            +(1000-$context['elite_cohesion'])
            +(1000-$context['security_institutional_cohesion'])
            +(1000-$context['military_constitutional_cohesion'])
            +(1000-$context['economic_resilience'])
            +$grievance,9
        ));

        $record=[
            'bill_id'=>$billId,
            'world_id'=>$this->world,
            'classification'=>'scenario_assumptions_not_survey_or_forecast',
            'source_constitution_hash'=>$constitution->hash(),
            'scenario_sha'=>$fingerprint,
            'cohorts'=>$perSector,
            'aggregate'=>[
                'grievance_index'=>$grievance,
                'peaceful_protest_index'=>$protest,
                'work_withdrawal_index'=>$withdrawal,
                'production_disruption_index'=>$disruption,
                'government_political_pressure_index'=>$governmentPressure,
                'institutional_strain_index'=>$institutionalStrain,
                'production_loss_bps_low'=>intdiv($disruption,20),
                'production_loss_bps_high'=>intdiv($disruption,5),
                'protest_scale'=>self::band($protest),
                'labor_disruption_scale'=>self::band($withdrawal)
            ],
            'uncertainty_note'=>'Heuristic scenario coefficients are not empirically calibrated or predictions.',
            'government_removed'=>false,
            'regime_changed'=>false,
            'automatic_security_solution'=>false
        ];
        $this->history[$billId]=$record;
        ksort($this->history,SORT_STRING);
        return $record;
    }

    public function cohortHash():string
    {
        return hash('sha256',self::json([
            'version'=>self::VERSION,'world_id'=>$this->world,
            'sectors'=>$this->sectors
        ]));
    }

    public function snapshot():array
    {
        return [
            'world_id'=>$this->world,'version'=>self::VERSION,
            'sectors'=>$this->sectors,'history'=>$this->history,
            'cohort_sha'=>$this->cohortHash()
        ];
    }

    public function hash():string
    {
        return hash('sha256',self::json($this->snapshot()));
    }

    private static function band(int $index):string
    {
        return match(true) {
            $index<200=>'limited',
            $index<450=>'noticeable',
            $index<700=>'substantial',
            default=>'widespread'
        };
    }

    private static function clamp(int $value):int
    {
        return max(0,min(1000,$value));
    }

    private static function score(mixed $value,int $max=1000):void
    {
        if (!is_int($value) || $value<0 || $value>$max) {
            throw new \InvalidArgumentException('Invalid dimensionless scenario score');
        }
    }

    private static function fields(array $given,array $expected):void
    {
        $x=array_keys($given);
        sort($x,SORT_STRING);sort($expected,SORT_STRING);
        if ($x!==$expected) throw new \InvalidArgumentException('Unexpected social scenario fields');
    }

    private static function id(string $value):void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$value)) {
            throw new \InvalidArgumentException('Invalid social scenario identifier');
        }
    }

    private static function json(mixed $data):string
    {
        return json_encode(
            $data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
        );
    }
}
