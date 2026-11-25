<?php
declare(strict_types=1);

namespace Aeternum\WorldPolitics;

/**
 * Early, explicitly partial country-specific rulebooks. An unimplemented
 * power MUST fail closed; a shared role name is never enough to confer it.
 * Sources describe law; abstract gameplay delays are NOT legal forecasts.
 */
final class InstitutionalRulebook
{
    /** @return array<string,mixed> */
    public static function forCountry(string $country): array
    {
        return match ($country) {
            'FR' => [
                'country'=>'FR','version'=>'fr-1958-wp05-0.2',
                'system'=>'semi_presidential_republic',
                'head_of_state'=>'president',
                'head_of_government'=>'prime_minister',
                'legislature'=>'national_assembly',
                'policy_source'=>'https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000571356/',
                'source_articles'=>['8','12','39'],
                'bill_proposers'=>['prime_minister','member_of_parliament'],
                'dissolution'=>[
                    'role'=>'president','mode'=>'consultations_required',
                    'election_min_days'=>20,'election_max_days'=>40,
                    'no_repeat_after_elections_years'=>1,
                ],
            ],
            'DE' => [
                'country'=>'DE','version'=>'de-gg-wp05-0.2',
                'system'=>'federal_parliamentary_republic',
                'head_of_state'=>'federal_president',
                'head_of_government'=>'federal_chancellor',
                'legislature'=>'bundestag',
                'policy_source'=>'https://www.gesetze-im-internet.de/englisch_gg/',
                'source_articles'=>['63','64','68','76'],
                'bill_proposers'=>['federal_government','bundestag_member','bundesrat'],
                'dissolution'=>['mode'=>'conditional_unimplemented'],
            ],
            'US' => [
                'country'=>'US','version'=>'us-constitution-wp05-0.2',
                'system'=>'federal_presidential_republic',
                'head_of_state'=>'president',
                'head_of_government'=>'president',
                'legislature'=>'congress',
                'policy_source'=>'https://www.senate.gov/civics/constitution_item/constitution.htm',
                'source_articles'=>['I','II'],
                'bill_proposers'=>['member_of_congress'],
                'dissolution'=>['mode'=>'unavailable'],
            ],
            'GB'=>[
                'country'=>'GB','version'=>'gb-wp32-v1','system'=>'parliamentary_constitutional_monarchy',
                'head_of_state'=>'monarch','head_of_government'=>'prime_minister','legislature'=>'house_of_commons',
                'policy_source'=>'https://commonslibrary.parliament.uk/research-briefings/cbp-10915/','source_articles'=>['confidence convention'],
                'bill_proposers'=>['member_of_parliament'],'dissolution'=>['mode'=>'conditional_unimplemented'],
            ],
            'ES'=>[
                'country'=>'ES','version'=>'es-wp32-v1','system'=>'parliamentary_constitutional_monarchy',
                'head_of_state'=>'monarch','head_of_government'=>'prime_minister','legislature'=>'congress_of_deputies',
                'policy_source'=>'https://www.boe.es/buscar/act.php?id=BOE-A-1978-31229','source_articles'=>['87','113','114'],
                'bill_proposers'=>['government'],'dissolution'=>['mode'=>'conditional_unimplemented'],
            ],
            'IT'=>[
                'country'=>'IT','version'=>'it-wp32-v1','system'=>'parliamentary_republic',
                'head_of_state'=>'president','head_of_government'=>'prime_minister','legislature'=>'parliament',
                'policy_source'=>'https://www.senato.it/istituzione/il-senato-nel-sistema-bicamerale/il-parlamento','source_articles'=>['70','71','94'],
                'bill_proposers'=>['government','member_of_parliament'],'dissolution'=>['mode'=>'conditional_unimplemented'],
            ],
            'JP'=>[
                'country'=>'JP','version'=>'jp-wp32-v1','system'=>'parliamentary_constitutional_monarchy',
                'head_of_state'=>'emperor','head_of_government'=>'prime_minister','legislature'=>'national_diet',
                'policy_source'=>'https://www.sangiin.go.jp/eng/law/tcoj/','source_articles'=>['41','56','69'],
                'bill_proposers'=>['diet_member'],'dissolution'=>['mode'=>'conditional_unimplemented'],
            ],
            default => throw new \DomainException('No reviewed constitutional module for this country'),
        };
    }

    public static function hasRules(string $country):bool
    {
        return in_array($country,['FR','DE','US','GB','ES','IT','JP'],true);
    }
}
