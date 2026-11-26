<?php
declare(strict_types=1);
namespace Aeternum\WorldPolitics;
require_once __DIR__.'/ElectoralCycle.php';

/** Source-backed ordinary parliamentary horizons for the six existing foreign
 * institutional pilots. The ballot and party models remain fictional. Never
 * represent selected scenario dates as official future announcements. */
final class ComparativeElectionCalendar
{
    public const VERSION='wp54r-comparative-election-calendar-2026-11-v1';
    private static function usElectionDay(int $year):string
    {
        $day=new \DateTimeImmutable($year.'-11-01');
        while($day->format('N')!=='1')$day=$day->modify('+1 day');
        return $day->modify('+1 day')->format('Y-m-d');
    }
    public static function report():array
    {
        $esEnd=new \DateTimeImmutable('2027-07-23');
        $esPoll=$esEnd->modify('-24 days')->modify('+54 days')->format('Y-m-d');
        $us=self::usElectionDay(2028);
        return ['version'=>self::VERSION,'as_of'=>'2026-11-26','classification'=>'national_legal_references_with_pinned_scenario_dates',
            'countries'=>[
                'DE'=>['chamber'=>'Bundestag','term_years'=>4,'last_poll'=>'2025-02-23','term_start'=>'2025-03-25','ordinary_earliest'=>'2029-01-26','ordinary_latest'=>'2029-03-25',
                    'scenario_poll'=>'2029-03-18','date_status'=>'scenario_inside_official_window','early_election_max_days'=>60,
                    'executive_rule'=>'chancellor_depends_on_parliamentary_election_and_confidence_not_presidential_term_cap',
                    'sources'=>['https://www.bundeswahlleiterin.de/bundestagswahlen/2025.html','https://www.bundeswahlleiterin.de/bundestagswahlen/2029.html']],
                'ES'=>['chamber'=>'Congress of Deputies','term_years'=>4,'last_poll'=>'2023-07-23','ordinary_expiry'=>$esEnd->format('Y-m-d'),
                    'scenario_poll'=>$esPoll,'date_status'=>'derived_ordinary_calendar_no_early_dissolution',
                    'executive_rule'=>'prime_minister_depends_on_congressional_investiture_and_confidence',
                    'sources'=>['https://www.boe.es/buscar/act.php?id=BOE-A-1978-31229','https://boe.es/buscar/act.php?id=BOE-A-1985-11672','https://www.congreso.es/es/busqueda-de-publicaciones?_publicaciones_id_texto=BOCG-14-D-632.CODI.&_publicaciones_legislatura=XIV&_publicaciones_mode=mostrarTextoIntegro&p_p_id=publicaciones&p_p_lifecycle=0&p_p_mode=view&p_p_state=normal']],
                'GB'=>['chamber'=>'House of Commons','term_years'=>5,'last_poll'=>'2024-07-04','term_start'=>'2024-07-09','ordinary_latest'=>'2029-08-15',
                    'scenario_poll'=>'2029-07-19','date_status'=>'scenario_before_published_latest_day',
                    'executive_rule'=>'prime_minister_depends_on_commons_confidence',
                    'sources'=>['https://commonslibrary.parliament.uk/research-briefings/sn04512/','https://www.parliament.uk/about/how/elections-and-voting/general/']],
                'IT'=>['chamber'=>'Chamber of Deputies and Senate','term_years'=>5,'last_poll'=>'2022-09-25','term_start'=>'2022-10-13',
                    'ordinary_expiry'=>'2027-10-13','new_election_max_days_after_expiry'=>70,'scenario_poll'=>'2027-10-24','date_status'=>'scenario_inside_constitutional_window',
                    'executive_rule'=>'prime_minister_depends_on_both_chambers_confidence',
                    'sources'=>['https://temi.camera.it/leg19/temi/elezioni-e-referendum','https://www.senato.it/istituzione/la-costituzione/parte-ii/titolo-i/sezione-i/articolo-61','https://www.senato.it/sites/default/files/media-documents/Costituzione_novembre_2021.pdf']],
                'JP'=>['chamber'=>'House of Representatives','term_years'=>4,'last_poll'=>'2026-02-08','scenario_poll'=>'2030-01-27','date_status'=>'scenario_before_ordinary_four_year_expiry',
                    'early_election_max_days'=>40,'upper_house_term_years'=>6,'upper_house_partial_renewal_years'=>3,
                    'executive_rule'=>'prime_minister_designated_by_diet',
                    'sources'=>['https://www.shugiin.go.jp/internet/itdb_kenpou.nsf/html/kenpou/en/constitution.htm','https://data.ipu.org/parliament/JP/JP-LC01/election/JP-LC01-E20260208/']],
                'US'=>['chamber'=>'House of Representatives','term_years'=>2,'scenario_poll'=>$us,'date_status'=>'statutory_calendar_calculation',
                    'presidential'=>['term_years'=>4,'maximum_elections_total'=>2,'more_than_two_years_of_anothers_term_max_elections'=>1,
                        'next_ordinary_poll'=>$us,'next_term_start'=>'2029-01-20','personal_limit_is_not_consecutive'=>true],
                    'executive_rule'=>'separate_presidential_election_and_congressional_terms',
                    'sources'=>['https://uscode.house.gov/us/usc/t2/s7/','https://uscode.house.gov/us/usc/t3/s21/','https://uscode.house.gov/us/usc/t3/s101/','https://www.archives.gov/founding-docs/amendments-11-27']]
            ],'fictional_ballot_methods_unchanged'=>true,'complete_national_electoral_law_implemented'=>false,
            'recurring_foreign_elections_and_early_dissolution_integration_complete'=>false];
    }
    public static function config(string $country,string $start):array
    {
        $r=self::report()['countries'][$country]??throw new \DomainException('Unsupported comparative calendar');
        if($start>=$r['scenario_poll'])throw new \DomainException('Scenario is later than pinned election');
        $c=ElectoralScenario::config($country,$start);$c['poll_day']=$r['scenario_poll'];return $c;
    }
}
